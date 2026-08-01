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

		$coda[] = array_filter(
			array(
				'eventType'  => $tipo,
				// Identificativo unico: rende innocuo un rinvio.
				'eventId'    => 'evt_' . wp_generate_password( 20, false, false ),
				'occurredAt' => gmdate( 'Y-m-d\TH:i:s.v\Z' ),
				'sessionId'  => $dettagli['sessionId'] ?? null,
				'channel'    => 'woocommerce-plugin',
				'mode'       => $dettagli['mode'] ?? null,
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
