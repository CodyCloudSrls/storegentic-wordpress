<?php
/**
 * Eventi di analisi.
 *
 * Gli eventi non partono uno per uno: si accodano e si spediscono a gruppi.
 * Una ricerca genera due o tre eventi, e una chiamata di rete per ciascuno
 * rallenterebbe la ricerca stessa per raccogliere un dato che non serve
 * subito a nessuno.
 *
 * Ogni evento porta un identificativo proprio, cosi' se una spedizione parte
 * due volte il server riconosce i doppioni e li scarta: la catena e'
 * idempotente, e un rinvio non falsa i numeri.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Analitica;

use Storegentic\Api\Client;
use Storegentic\Api\Contratto;
use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registratore {

	private const CODA      = \Storegentic\PREFISSO_OPZIONI . 'coda_eventi';
	private const AGGANCIO  = 'storegentic_svuota_coda';

	/** Oltre questi eventi in coda si spedisce subito, senza aspettare. */
	private const SOGLIA = 20;

	/** Tetto di guardia: se il servizio e' irraggiungibile la coda non cresce all'infinito. */
	private const TETTO = 500;

	/**
	 * Come si chiamano, al servizio, le cose che succedono qui.
	 *
	 * PERCHE' UNA TRADUZIONE E NON I NOMI GIUSTI OVUNQUE. Il resto del plugin
	 * parla di quello che fa — "l'utente ha aperto il widget", "ha toccato un
	 * risultato" — e questo file solo sa come il servizio chiama quelle cose.
	 * E' lo stesso principio degli indirizzi: il vocabolario lo detta il
	 * server, e il plugin lo traduce in un punto unico invece di spargerlo.
	 *
	 * PERCHE' SERVIVA. Il servizio accetta sei tipi soli — query_sent,
	 * results_returned, result_clicked, add_to_cart, checkout_started,
	 * purchase_completed — e rifiuta tutti gli altri con "Unsupported
	 * eventType". Il plugin ne mandava di inventati: le analisi venivano
	 * scartate in silenzio, e nel pannello di Storegentic non compariva nulla.
	 *
	 * `mode` dice da dove viene la domanda, e anche quello e' un elenco chiuso:
	 * agent_chat, agent_search, image_search.
	 *
	 * @var array<string,array{0:string,1:string|null}>
	 */
	private const VOCABOLARIO = array(
		'search_query'        => array( 'query_sent', 'agent_search' ),
		'search_results'      => array( 'results_returned', 'agent_search' ),
		'search_result_click' => array( 'result_clicked', 'agent_search' ),
		'image_search'        => array( 'query_sent', 'image_search' ),
		'image_results'       => array( 'results_returned', 'image_search' ),
		'agent_chat'          => array( 'query_sent', 'agent_chat' ),
		'agent_results'       => array( 'results_returned', 'agent_chat' ),
		'add_to_cart'         => array( 'add_to_cart', null ),
	);

	public static function avvia(): void {
		add_action( self::AGGANCIO, array( self::class, 'spedisci' ) );

		// Ultima occasione utile: si spedisce a fine richiesta, non durante.
		add_action( 'shutdown', array( self::class, 'forse_spedisci' ) );
	}

	/**
	 * @param array<string,mixed> $dettagli
	 */
	public static function accoda( string $tipo, array $dettagli = array() ): void {
		if ( ! Impostazioni::leggi( 'analitica' ) ) {
			return;
		}

		$coda = self::coda();

		if ( count( $coda ) >= self::TETTO ) {
			return;
		}

		$tradotto = self::VOCABOLARIO[ $tipo ] ?? null;

		/*
		 * Un evento che il servizio non conosce non si accoda nemmeno: la coda
		 * sta in un'opzione del database, e riempirla di cose che verranno
		 * rifiutate e' lavoro sprecato due volte.
		 */
		if ( null === $tradotto ) {
			return;
		}

		$coda[] = array_filter(
			array(
				'eventType'  => $tradotto[0],
				// Identificativo unico: rende innocuo un rinvio.
				'eventId'    => 'evt_' . wp_generate_password( 20, false, false ),
				'occurredAt' => gmdate( 'Y-m-d\TH:i:s.v\Z' ),
				'sessionId'  => $dettagli['sessionId'] ?? null,
				'channel'    => 'woocommerce-plugin',
				'mode'       => $tradotto[1],
				'data'       => $dettagli['data'] ?? array(),
			),
			static fn( $v ) => null !== $v && '' !== $v
		);

		update_option( self::CODA, $coda, false );

		if ( count( $coda ) >= self::SOGLIA ) {
			self::programma();
		}
	}

	public static function forse_spedisci(): void {
		if ( empty( self::coda() ) ) {
			return;
		}

		self::programma();
	}

	private static function programma(): void {
		if ( wp_next_scheduled( self::AGGANCIO ) ) {
			return;
		}

		wp_schedule_single_event( time() + 30, self::AGGANCIO );
	}

	/**
	 * Spedisce la coda.
	 *
	 * La coda si svuota PRIMA della chiamata: se la spedizione fallisce gli
	 * eventi tornano in coda. Il contrario — svuotare dopo — perderebbe gli
	 * eventi arrivati durante la chiamata.
	 */
	public static function spedisci(): void {
		$coda = self::coda();

		if ( empty( $coda ) || ! Impostazioni::configurato() ) {
			return;
		}

		$contratto = Contratto::ottieni();

		/*
		 * Servizio irraggiungibile: la coda resta dov'e'. Prima si cancellava
		 * ogni volta che l'indirizzo risultava assente, e l'indirizzo risulta
		 * assente anche quando il contratto non arriva: bastava un'ora di
		 * disservizio per buttare via tutti gli eventi raccolti.
		 */
		if ( is_wp_error( $contratto ) ) {
			return;
		}

		$percorso = Contratto::endpoint( 'analyticsEvents' );

		if ( '' === $percorso ) {
			// Contratto valido che NON dichiara la raccolta: si smette davvero.
			delete_option( self::CODA );
			return;
		}

		update_option( self::CODA, array(), false );

		$client   = new Client( null, null, 15 );
		$risposta = $client->post( $percorso, array( 'events' => array_values( $coda ) ) );

		if ( is_wp_error( $risposta ) ) {
			// Rimessi in testa, davanti a quelli arrivati nel frattempo.
			$attuale = self::coda();
			update_option( self::CODA, array_slice( array_merge( $coda, $attuale ), 0, self::TETTO ), false );
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function coda(): array {
		$coda = get_option( self::CODA, array() );

		return is_array( $coda ) ? $coda : array();
	}
}
