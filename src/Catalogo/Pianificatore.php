<?php
/**
 * Quando gira la sincronizzazione.
 *
 * Un passo per esecuzione del cron, non tutta la sincronizzazione: cosi'
 * ogni esecuzione sta dentro il tempo massimo di PHP anche su hosting
 * condivisi, e un catalogo grande viene spedito in piu' esecuzioni invece
 * che in una che va in timeout a meta'.
 *
 * Finche' restano pagine da spedire, il passo successivo viene programmato
 * subito: il cron di WordPress e' innescato dalle visite, quindi su un sito
 * con traffico i passi si susseguono in fretta, e su un sito fermo la
 * sincronizzazione riprende alla prima visita utile.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Catalogo;

use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pianificatore {

	public const AGGANCIO_PERIODICO = 'storegentic_sincro_periodica';
	public const AGGANCIO_PASSO     = 'storegentic_sincro_passo';

	public static function avvia(): void {
		add_action( self::AGGANCIO_PERIODICO, array( self::class, 'periodica' ) );
		add_action( self::AGGANCIO_PASSO, array( self::class, 'passo' ) );

		// Il cambio di frequenza deve riprogrammare, non aspettare il prossimo giro.
		add_action( 'update_option_' . Impostazioni::CHIAVE, array( self::class, 'riprogramma' ), 10, 2 );
	}

	/** Avvia una sincronizzazione completa, se non ce n'e' gia' una. */
	public static function periodica(): void {
		if ( ! Impostazioni::leggi( 'sincro_automatica' ) ) {
			return;
		}

		if ( Sincronizzazione::in_corso() ) {
			// La precedente non e' finita: si lascia proseguire.
			self::programma_passo();
			return;
		}

		$esito = Sincronizzazione::avvia();

		if ( ! is_wp_error( $esito ) ) {
			self::programma_passo();
		}
	}

	/** Esegue un passo e, se serve, programma il successivo. */
	public static function passo(): void {
		if ( ! Sincronizzazione::in_corso() ) {
			return;
		}

		$esito = Sincronizzazione::passo();

		if ( is_wp_error( $esito ) ) {
			/*
			 * Il passo e' fallito. Non si riprogramma: lo stato resta
			 * `fallita` e la riconciliazione non parte. Riprovare in
			 * automatico su un errore di configurazione o di quota
			 * significherebbe solo ripetere lo stesso errore ogni minuto.
			 */
			return;
		}

		if ( Sincronizzazione::in_corso() ) {
			self::programma_passo();
		}
	}

	public static function programma_passo(): void {
		if ( wp_next_scheduled( self::AGGANCIO_PASSO ) ) {
			return;
		}

		// Un minuto di distanza: dà respiro al server e al negozio.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::AGGANCIO_PASSO );
	}

	/**
	 * @param mixed $vecchie
	 * @param mixed $nuove
	 */
	public static function riprogramma( $vecchie, $nuove ): void {
		$prima = is_array( $vecchie ) ? ( $vecchie['frequenza'] ?? '' ) : '';
		$dopo  = is_array( $nuove ) ? ( $nuove['frequenza'] ?? '' ) : '';

		if ( $prima === $dopo ) {
			return;
		}

		self::spegni_periodica();
		self::accendi_periodica();
	}

	public static function accendi_periodica(): void {
		if ( wp_next_scheduled( self::AGGANCIO_PERIODICO ) ) {
			return;
		}

		$frequenza = (string) Impostazioni::leggi( 'frequenza' );
		wp_schedule_event( time() + HOUR_IN_SECONDS, $frequenza, self::AGGANCIO_PERIODICO );
	}

	public static function spegni_periodica(): void {
		wp_clear_scheduled_hook( self::AGGANCIO_PERIODICO );
		wp_clear_scheduled_hook( self::AGGANCIO_PASSO );
	}
}
