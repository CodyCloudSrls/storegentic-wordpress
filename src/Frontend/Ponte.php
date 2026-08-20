<?php
/**
 * Ponte fra il browser e Storegentic.
 *
 * MOTIVO. La chiave del negozio autorizza a leggere e scrivere il catalogo.
 * Se il JavaScript chiamasse Storegentic direttamente, la chiave sarebbe nel
 * sorgente di ogni pagina, leggibile da chiunque. Qui il browser parla solo
 * con WordPress; la chiave resta sul server.
 *
 * Il ponte espone il minimo indispensabile: cercare a parole, cercare con una
 * foto, chiedere all'assistente, segnalare un evento. Non espone la
 * sincronizzazione, che e' un'operazione di amministrazione.
 *
 * Qui non c'e' logica di ricerca: sta in Ricerca, che serve anche la pagina
 * dei risultati. Questo file si occupa di chi puo' chiamare, quanto spesso, e
 * con quali parametri.
 *
 * Ogni rotta e' pubblica per necessita' — chi cerca non e' registrato — e
 * quindi ha un limite di frequenza per indirizzo IP: senza, il ponte
 * diventa un modo gratuito per consumare la quota del negozio.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Analitica\Misure;
use Storegentic\Analitica\Registratore;
use Storegentic\Impostazioni;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ponte {

	private const SPAZIO = 'storegentic/v1';

	/**
	 * Quanto costa ogni rotta, e quanto se ne puo' spendere al minuto.
	 *
	 * Non tutte le richieste pesano uguale. Una ricerca a parole e' una
	 * chiamata breve; una foto va caricata e vettorizzata; una risposta
	 * dell'assistente tiene occupato un processo per decine di secondi e
	 * consuma la quota molto piu' in fretta. Un limite unico o e' troppo largo
	 * per l'assistente o e' troppo stretto per chi scrive nella barra.
	 */
	private const COSTO = array(
		'ricerca'    => 1,
		'immagine'   => 4,
		'assistente' => 6,
		'evento'     => 1,
		/*
		 * I suggerimenti non costano quota: leggono il database del negozio e
		 * non chiamano il servizio. Farli pesare sul contatore vorrebbe dire
		 * che chi scrive una frase lunga esaurisce il proprio credito prima di
		 * aver premuto Invio una volta sola.
		 */
		'suggerimenti' => 0,
	);

	/** Spesa ammessa per finestra, per indirizzo. */
	private const TETTO = 40;

	/** Ampiezza della finestra, in secondi. */
	private const FINESTRA = 60;

	public static function registra(): void {
		register_rest_route(
			self::SPAZIO,
			'/ricerca',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'ricerca' ),
				'permission_callback' => static fn( $r ) => self::ammessa( $r, 'ricerca' ),
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== trim( $v ),
					),
					'forma' => array(
						'type'              => 'string',
						'default'           => 'griglia',
						'sanitize_callback' => static fn( $v ) => 'riga' === $v ? 'riga' : 'griglia',
					),
					'topK'  => array(
						'type'              => 'integer',
						'default'           => Ricerca::RAPIDO,
						'sanitize_callback' => static fn( $v ) => max( 1, min( 50, (int) $v ) ),
					),
				),
			)
		);

		register_rest_route(
			self::SPAZIO,
			'/immagine',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'immagine' ),
				'permission_callback' => static fn( $r ) => self::ammessa( $r, 'immagine' ),
				'args'                => array(
					'foto'  => array(
						'type'     => 'string',
						'required' => true,
						/*
						 * Niente sanitize_text_field: e' base64, e quella
						 * funzione toglierebbe caratteri validi corrompendo
						 * l'immagine. La validita' la controlla Ricerca, che
						 * decodifica e verifica che sia davvero un'immagine.
						 */
						'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== trim( $v ),
					),
					'mime'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn( $v ) => preg_match( '#^image/[a-z0-9.+-]+$#i', (string) $v ) ? strtolower( (string) $v ) : '',
					),
					'query' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'forma' => array(
						'type'              => 'string',
						'default'           => 'griglia',
						'sanitize_callback' => static fn( $v ) => 'riga' === $v ? 'riga' : 'griglia',
					),
					'topK'  => array(
						'type'              => 'integer',
						'default'           => Ricerca::AMPIO,
						'sanitize_callback' => static fn( $v ) => max( 1, min( 50, (int) $v ) ),
					),
				),
			)
		);

		register_rest_route(
			self::SPAZIO,
			'/assistente',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( Assistente::class, 'rispondi' ),
				'permission_callback' => static fn( $r ) => self::ammessa( $r, 'assistente' ),
				'args'                => array(
					'messaggio' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $v ) => mb_substr( sanitize_textarea_field( (string) $v ), 0, 500 ),
						'validate_callback' => static fn( $v ) => is_string( $v ) && '' !== trim( $v ),
					),
					'storia'    => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		/*
		 * I suggerimenti non toccano il servizio: leggono il database del
		 * negozio. E' una GET perche' e' una lettura, e cosi' il browser puo'
		 * riusare la risposta quando si cancella una lettera e la si riscrive.
		 */
		register_rest_route(
			self::SPAZIO,
			'/suggerimenti',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'suggerimenti' ),
				'permission_callback' => static fn( $r ) => self::ammessa( $r, 'suggerimenti' ),
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 60 ),
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
				'permission_callback' => static fn( $r ) => self::ammessa( $r, 'evento' ),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function ammessa( WP_REST_Request $richiesta, string $rotta = 'ricerca' ) {
		if ( ! Impostazioni::leggi( 'attivo' ) ) {
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

		$spesa = (int) get_transient( $impronta );

		if ( $spesa >= self::TETTO ) {
			return new WP_Error(
				'storegentic_troppe_richieste',
				__( 'Troppe richieste. Riprova fra qualche istante.', 'storegentic' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $impronta, $spesa + ( self::COSTO[ $rotta ] ?? 1 ), self::FINESTRA * 2 );

		return true;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function ricerca( WP_REST_Request $richiesta ) {
		return self::rispondi(
			Ricerca::testo(
				(string) $richiesta->get_param( 'query' ),
				(int) $richiesta->get_param( 'topK' )
			),
			$richiesta
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function immagine( WP_REST_Request $richiesta ) {
		return self::rispondi(
			Ricerca::foto(
				(string) $richiesta->get_param( 'foto' ),
				(string) $richiesta->get_param( 'mime' ),
				(string) $richiesta->get_param( 'query' ),
				(int) $richiesta->get_param( 'topK' )
			),
			$richiesta
		);
	}

	/**
	 * L'esito con il markup gia' pronto.
	 *
	 * Il browser riceve i dati per filtrare e ordinare, e l'HTML per
	 * disegnare. Costruire il markup lato client avrebbe voluto dire tenerne
	 * due versioni: vedi Frontend\Scheda.
	 *
	 * @param array<string,mixed>|WP_Error $esito
	 * @return WP_REST_Response|WP_Error
	 */
	private static function rispondi( $esito, WP_REST_Request $richiesta ) {
		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		$forma = 'riga' === (string) $richiesta->get_param( 'forma' ) ? 'riga' : 'griglia';

		$esito['risultati'] = Scheda::con_html( (array) $esito['risultati'], $forma );

		return new WP_REST_Response( $esito, 200 );
	}

	public static function suggerimenti( WP_REST_Request $richiesta ): WP_REST_Response {
		$risposta = new WP_REST_Response(
			array( 'voci' => self::con_schede( Suggerimenti::per( (string) $richiesta->get_param( 'q' ) ) ) ),
			200
		);

		// Un elenco di nomi di prodotti non cambia da un minuto all'altro.
		$risposta->header( 'Cache-Control', 'public, max-age=300' );

		return $risposta;
	}

	/**
	 * Ai suggerimenti di prodotto si attacca la scheda vera.
	 *
	 * Una riga di solo testo dice il nome e il prezzo; la scheda dice anche
	 * com'e' fatto il gioiello. In un negozio la fotografia non e' decorazione:
	 * e' meta' dell'informazione, ed e' quella che fa decidere.
	 *
	 * Si usa lo stesso componente delle altre righe — Frontend\Scheda — invece
	 * di disegnarne una terza: cosi' una riga di risultato ha lo stesso aspetto
	 * ovunque compaia, e resta una sola definizione da mantenere.
	 *
	 * Le categorie restano righe di testo: non hanno una fotografia, e
	 * inventargliela sarebbe peggio che non averla.
	 *
	 * @param array<int,array<string,string>> $voci
	 * @return array<int,array<string,mixed>>
	 */
	private static function con_schede( array $voci ): array {
		$grezzi = array();

		foreach ( $voci as $i => $v ) {
			if ( 'prodotto' === ( $v['tipo'] ?? '' ) && ! empty( $v['sku'] ) ) {
				$grezzi[ $i ] = array( 'sku' => (string) $v['sku'] );
			}
		}

		if ( empty( $grezzi ) ) {
			return $voci;
		}

		/*
		 * Risolutore::schede() salta cio' che non trova e non conserva la
		 * posizione, quindi si riallinea sullo SKU invece che sull'indice.
		 */
		$per_sku = array();

		foreach ( Scheda::con_html( Risolutore::schede( array_values( $grezzi ) ), 'riga' ) as $s ) {
			$per_sku[ (string) $s['sku'] ] = $s['html'];
		}

		foreach ( $voci as $i => $v ) {
			$sku = (string) ( $v['sku'] ?? '' );

			if ( '' !== $sku && isset( $per_sku[ $sku ] ) ) {
				$voci[ $i ]['html'] = $per_sku[ $sku ];
			}
		}

		return $voci;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function evento( WP_REST_Request $richiesta ) {
		$tipo = sanitize_key( (string) $richiesta->get_param( 'eventType' ) );

		if ( '' === $tipo ) {
			return new WP_Error( 'storegentic_evento_senza_tipo', __( 'Evento senza tipo.', 'storegentic' ), array( 'status' => 400 ) );
		}

		$dati = self::dati_evento( $richiesta->get_param( 'data' ) );

		Registratore::accoda(
			$tipo,
			array(
				'sessionId' => mb_substr( sanitize_text_field( (string) $richiesta->get_param( 'sessionId' ) ), 0, 64 ),
				'mode'      => mb_substr( sanitize_key( (string) $richiesta->get_param( 'mode' ) ), 0, 32 ),
				'data'      => $dati,
			)
		);

		/*
		 * Lo stesso fatto va anche nel conto tenuto in casa. Non e' un doppione:
		 * il Registratore SPEDISCE a Storegentic e poi dimentica, e il servizio
		 * non offre alcun indirizzo per rileggere quegli eventi. Senza questa
		 * riga il pannello del negozio non saprebbe mai quali prodotti si aprono.
		 */
		if ( 'search_result_click' === $tipo && ! empty( $dati['sku'] ) ) {
			Misure::segna_apertura( (string) $dati['sku'] );
		}

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
