<?php
/**
 * Plugin Name:       Storegentic per WooCommerce
 * Plugin URI:        https://storegentic.it
 * Description:       Collega un negozio WooCommerce a Storegentic: ricerca semantica, agente conversazionale e analisi. Funziona su qualsiasi negozio, senza toccare il tema.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            CodyCloud Srls
 * Author URI:        https://www.codycloud.it
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       storegentic
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * Requires Plugins:  woocommerce
 *
 * -----------------------------------------------------------------------------
 * IMPIANTO
 *
 * Il plugin non sa quali indirizzi chiamare. Conosce due sole cose: la base
 * del servizio e la chiave del negozio. Tutto il resto — quali funzioni sono
 * attive, quali indirizzi usare, quanto si puo' chiedere — arriva
 * dall'handshake, che e' il contratto che il server dichiara a ogni
 * installazione.
 *
 * E' una scelta, non una scorciatoia: un plugin che scrive i propri indirizzi
 * nel codice va aggiornato ogni volta che il servizio cambia, e su un parco
 * di installazioni sparse quell'aggiornamento non arriva mai a tutti. Qui il
 * server puo' spostare un endpoint, spegnere una funzione o cambiare un
 * limite, e le installazioni si adeguano al ciclo di handshake successivo.
 *
 * Vedi Storegentic\Api\Contratto.
 * -----------------------------------------------------------------------------
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSIONE       = '0.1.0';
const FILE_PRINCIPALE = __FILE__;
const PERCORSO       = __DIR__;
const PREFISSO_OPZIONI = 'storegentic_';

/**
 * Caricatore delle classi.
 *
 * Non si usa Composer: il plugin deve poter essere installato caricando uno
 * zip, senza passaggi di build. Le classi seguono lo schema
 * `Storegentic\Sotto\Nome` => `src/Sotto/Nome.php`.
 */
spl_autoload_register(
	static function ( string $classe ): void {
		if ( ! str_starts_with( $classe, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relativo = substr( $classe, strlen( __NAMESPACE__ ) + 1 );
		$percorso = PERCORSO . '/src/' . str_replace( '\\', '/', $relativo ) . '.php';

		if ( is_readable( $percorso ) ) {
			require_once $percorso;
		}
	}
);

/**
 * WooCommerce e' un requisito, non un'opzione: senza, il plugin non ha un
 * catalogo da sincronizzare. Si controlla su `plugins_loaded` perche' prima
 * di quel momento l'elenco dei plugin attivi non e' ancora completo.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Storegentic richiede WooCommerce attivo. Il plugin resta inattivo finché WooCommerce non viene attivato.', 'storegentic' )
					);
				}
			);
			return;
		}

		Plugin::avvia();
	},
	20
);

/**
 * Dichiara la compatibilita' con l'archiviazione ordini ad alte prestazioni.
 * Senza questa riga WooCommerce mostra il plugin come "non compatibile" anche
 * quando non tocca gli ordini.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FILE_PRINCIPALE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FILE_PRINCIPALE, true );
		}
	}
);

register_activation_hook(
	FILE_PRINCIPALE,
	static function (): void {
		require_once PERCORSO . '/src/Installazione.php';
		Installazione::attiva();
	}
);

register_deactivation_hook(
	FILE_PRINCIPALE,
	static function (): void {
		require_once PERCORSO . '/src/Installazione.php';
		Installazione::disattiva();
	}
);
