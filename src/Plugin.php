<?php
/**
 * Montaggio del plugin.
 *
 * Questo file dice chi si aggancia a cosa, e nient'altro: nessuna logica di
 * dominio. Serve a poter rispondere in dieci secondi alla domanda "cosa fa
 * questo plugin quando WordPress si avvia".
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic;

use Storegentic\Admin\Pagina;
use Storegentic\Admin\Riquadro;
use Storegentic\Analitica\Registratore;
use Storegentic\Api\Contratto;
use Storegentic\Catalogo\Pianificatore;
use Storegentic\Frontend\Interfaccia;
use Storegentic\Frontend\Pagina as PaginaRicerca;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	public static function avvia(): void {
		load_plugin_textdomain( 'storegentic', false, dirname( plugin_basename( FILE_PRINCIPALE ) ) . '/languages' );

		Pianificatore::avvia();
		Registratore::avvia();

		/*
		 * La pagina dei risultati si monta anche in amministrazione: le regole
		 * di riscrittura vanno registrate in ogni contesto, altrimenti quando
		 * WordPress le riscrive — dalla pagina dei permalink, o dopo un
		 * aggiornamento — la nostra regola non e' presente e sparisce.
		 */
		PaginaRicerca::avvia();

		if ( is_admin() ) {
			Pagina::avvia();
			Riquadro::avvia();
		} else {
			Interfaccia::avvia();
		}

		self::rest();
		self::invalida_contratto_al_cambio();
	}

	/**
	 * Le chiamate al servizio passano dal server, non dal browser.
	 *
	 * La chiave del negozio non deve mai finire in una pagina pubblica: se
	 * il JavaScript chiamasse Storegentic direttamente, la chiave sarebbe
	 * leggibile da chiunque apra il sorgente. Il browser parla con
	 * WordPress, WordPress parla con Storegentic.
	 */
	private static function rest(): void {
		add_action(
			'rest_api_init',
			static function (): void {
				require_once PERCORSO . '/src/Frontend/Ponte.php';
				Frontend\Ponte::registra();
			}
		);
	}

	/**
	 * Un contratto ottenuto con un'altra chiave non descrive questo negozio.
	 */
	private static function invalida_contratto_al_cambio(): void {
		add_action(
			'update_option_' . Impostazioni::CHIAVE,
			static function ( $vecchie, $nuove ): void {
				$prima = is_array( $vecchie ) ? ( ( $vecchie['chiave'] ?? '' ) . '|' . ( $vecchie['base'] ?? '' ) ) : '';
				$dopo  = is_array( $nuove ) ? ( ( $nuove['chiave'] ?? '' ) . '|' . ( $nuove['base'] ?? '' ) ) : '';

				if ( $prima !== $dopo ) {
					Contratto::dimentica();
				}
			},
			10,
			2
		);
	}
}
