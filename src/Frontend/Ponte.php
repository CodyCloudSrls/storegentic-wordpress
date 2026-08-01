<?php
/**
 * Ponte fra il browser e Storegentic.
 *
 * MOTIVO. La chiave del negozio autorizza a leggere e scrivere il catalogo.
 * Se il JavaScript chiamasse Storegentic direttamente, la chiave sarebbe nel
 * sorgente di ogni pagina, leggibile da chiunque. Qui il browser parla solo
 * con WordPress; la chiave resta sul server.
 *
 * Il ponte espone il minimo indispensabile: cercare, chiedere all'agente,
 * segnalare un evento. Non espone la sincronizzazione, che e' un'operazione
 * di amministrazione.
 *
 * Ogni rotta e' pubblica per necessita' — chi cerca non e' registrato — e
 * quindi ha un limite di frequenza per indirizzo IP: senza, il ponte
 * diventa un modo gratuito per consumare la quota del negozio.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Api\Client;
use Storegentic\Api\Contratto;
use Storegentic\Analitica\Registratore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ponte {

	private const SPAZIO = 'storegentic/v1';

	/** Richieste ammesse per finestra, per indirizzo. */
	private const TETTO = 30;

	/** Ampiezza della finestra, in secondi. */
	private const FINESTRA = 60;

	public static function registra(): void {
		register_rest_route(
			self::SPAZIO,
			'/ricerca',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'ricerca' ),
				'permission_callback' => array( self::class, 'ammessa' ),
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== trim( $v ),
					),
					'topK'  => array(
						'type'              => 'integer',
						'default'           => 12,
						'sanitize_callback' => static fn( $v ) => max( 1, min( 40, (int) $v ) ),
					),
				),
			)
		);

		register_rest_route(
			self::SPAZIO,
			'/evento',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'evento' ),
				'permission_callback' => array( self::class, 'ammessa' ),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function ammessa( WP_REST_Request $richiesta ) {
		if ( ! \Storegentic\Impostazioni::leggi( 'attivo' ) ) {
			return new WP_Error( 'storegentic_spento', __( 'Storegentic non è attivo su questo negozio.', 'storegentic' ), array( 'status' => 403 ) );
		}

		/*
		 * Finestra fissa, agganciata all'orologio e non all'ultima richiesta.
		 * Con `set_transient` rinnovato a ogni colpo la scadenza si sposta in
		 * avanti di continuo: sotto traffico costante il contatore non scade
		 * mai, e un visitatore che ha fatto trenta ricerche resta bloccato
		 * finche' non smette del tutto. Qui la finestra e' un blocco di
		 * secondi: allo scadere il contatore riparte da solo.
		 */
		$finestra = (int) floor( time() / self::FINESTRA );
		$impronta = 'sg_freq_' . md5( self::indirizzo() . '|' . $finestra );

		$conteggio = (int) get_transient( $impronta );

		if ( $conteggio >= self::TETTO ) {
			return new WP_Error(
				'storegentic_troppe_richieste',
				__( 'Troppe richieste. Riprova fra qualche istante.', 'storegentic' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $impronta, $conteggio + 1, self::FINESTRA * 2 );

		return true;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function ricerca( WP_REST_Request $richiesta ) {
		$percorso = Contratto::endpoint( 'search' );

		if ( '' === $percorso ) {
			return new WP_Error( 'storegentic_ricerca_assente', __( 'La ricerca non è disponibile per questo negozio.', 'storegentic' ), array( 'status' => 503 ) );
		}

		/*
		 * Un solo tentativo, e un timeout corto. Il client riprova sui 5xx
		 * con attese crescenti, e va benissimo per la sincronizzazione, che
		 * gira nel cron. Qui no: questa rotta e' pubblica e c'e' una persona
		 * che aspetta. Con i ritentativi una singola ricerca poteva tenere
		 * occupato un processo di PHP per quasi un minuto, e poche richieste
		 * contemporanee bastavano a saturare i processi disponibili.
		 */
		$client = new Client( null, null, 8, 0 );

		$risposta = $client->post(
			$percorso,
			array(
				'query' => (string) $richiesta->get_param( 'query' ),
				'topK'  => (int) $richiesta->get_param( 'topK' ),
			)
		);

		if ( is_wp_error( $risposta ) ) {
			$stato = (int) ( $risposta->get_error_data()['stato'] ?? 502 );
			$risposta->add_data( array( 'status' => $stato >= 400 ? $stato : 502 ) );
			return $risposta;
		}

		/*
		 * Al browser va solo cio' che serve a disegnare una scheda. Il resto
		 * della risposta (punteggi di rerank, diagnostica del rollout) e'
		 * informazione interna del servizio: non si ripubblica.
		 */
		return new WP_REST_Response(
			array(
				'risultati' => array_map( array( self::class, 'scheda' ), (array) ( $risposta['results'] ?? array() ) ),
				'categorie' => array_map(
					static fn( $c ) => array(
						'percorso' => (string) ( $c['categoryPath'] ?? '' ),
						'conteggio' => (int) ( $c['count'] ?? 0 ),
					),
					(array) ( $risposta['categories'] ?? $risposta['topCategories'] ?? array() )
				),
				'tempoMs'   => (int) ( $risposta['tookMs'] ?? 0 ),
			),
			200
		);
	}

	/**
	 * @param array<string,mixed> $c
	 * @return array<string,mixed>
	 */
	private static function scheda( array $c ): array {
		return array(
			'sku'       => (string) ( $c['sku'] ?? '' ),
			'nome'      => (string) ( $c['name'] ?? '' ),
			'url'       => esc_url_raw( (string) ( $c['url'] ?? '' ) ),
			'immagine'  => esc_url_raw( (string) ( $c['imageUrl'] ?? '' ) ),
			'prezzo'    => isset( $c['priceFormatted'] ) ? (string) $c['priceFormatted'] : null,
			'marchio'   => isset( $c['brand'] ) ? (string) $c['brand'] : null,
			'categoria' => isset( $c['category'] ) ? (string) $c['category'] : null,
			'sommario'  => isset( $c['shortDescription'] ) ? wp_strip_all_tags( (string) $c['shortDescription'] ) : null,
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function evento( WP_REST_Request $richiesta ) {
		$tipo = sanitize_key( (string) $richiesta->get_param( 'eventType' ) );

		if ( '' === $tipo ) {
			return new WP_Error( 'storegentic_evento_senza_tipo', __( 'Evento senza tipo.', 'storegentic' ), array( 'status' => 400 ) );
		}

		Registratore::accoda(
			$tipo,
			array(
				'sessionId' => mb_substr( sanitize_text_field( (string) $richiesta->get_param( 'sessionId' ) ), 0, 64 ),
				'mode'      => mb_substr( sanitize_key( (string) $richiesta->get_param( 'mode' ) ), 0, 32 ),
				'data'      => self::dati_evento( $richiesta->get_param( 'data' ) ),
			)
		);

		return new WP_REST_Response( array( 'accodato' => true ), 202 );
	}

	/**
	 * @param mixed $grezzi
	 * @return array<string,mixed>
	 */
	private static function dati_evento( $grezzi ): array {
		if ( ! is_array( $grezzi ) ) {
			return array();
		}

		$puliti = array();

		/*
		 * Solo scalari, non piu' di venti chiavi e non piu' di 500 caratteri
		 * per valore. Il numero di chiavi da solo non basta: sanitize_text_field
		 * non impone alcuna lunghezza, quindi venti valori da un megabyte
		 * ciascuno finivano nella coda, e la coda sta in un'opzione del
		 * database caricata a ogni richiesta.
		 */
		foreach ( array_slice( $grezzi, 0, 20, true ) as $chiave => $valore ) {
			if ( ! is_scalar( $valore ) ) {
				continue;
			}

			$puliti[ sanitize_key( (string) $chiave ) ] = is_string( $valore )
				? mb_substr( sanitize_text_field( $valore ), 0, 500 )
				: $valore;
		}

		return $puliti;
	}

	/**
	 * L'indirizzo di chi chiama, per il limite di frequenza.
	 *
	 * Si usa REMOTE_ADDR, che e' l'unico valore che il client non puo'
	 * scegliere. Le intestazioni inoltrate — X-Forwarded-For e simili — sono
	 * scritte da chi chiama: leggendole per prime, chiunque poteva mandare
	 * un valore diverso a ogni richiesta e non incontrare mai il limite,
	 * consumando la quota del negozio a costo zero.
	 *
	 * Dietro un proxy vero REMOTE_ADDR e' l'indirizzo del proxy, e il limite
	 * diventa collettivo: piu' severo del necessario, ma dalla parte giusta.
	 * Chi ha un proxy fidato lo dichiara con il filtro qui sotto, che riceve
	 * anche il valore inoltrato in modo da poterlo usare consapevolmente.
	 */
	private static function indirizzo(): string {
		$remoto = isset( $_SERVER['REMOTE_ADDR'] )
			? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: '';

		$indirizzo = filter_var( $remoto, FILTER_VALIDATE_IP ) ? $remoto : '0.0.0.0';

		/**
		 * Permette a chi sta dietro un proxy fidato di indicare l'indirizzo vero.
		 *
		 * Chi usa questo filtro si assume la responsabilita' di verificare che
		 * la richiesta arrivi davvero dal proprio proxy.
		 *
		 * @param string $indirizzo Indirizzo che il plugin userebbe.
		 */
		$scelto = (string) apply_filters( 'storegentic_indirizzo_client', $indirizzo );

		return filter_var( $scelto, FILTER_VALIDATE_IP ) ? $scelto : $indirizzo;
	}
}
