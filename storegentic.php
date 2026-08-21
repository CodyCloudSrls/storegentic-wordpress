<?php
/**
 * Plugin Name:       Storegentic
 * Plugin URI:        https://storegentic.it
 * Description:       Collega un sito WordPress a Storegentic: ricerca semantica, ricerca con una foto, assistente e analisi. Con WooCommerce indicizza i prodotti; senza, indicizza i contenuti del sito e fa da base di conoscenza. Funziona con qualsiasi tema, senza toccarlo.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            CodyCloud Srls
 * Author URI:        https://www.codycloud.it
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain:       storegentic
 * Domain Path:       /languages
 * WC requires at least: 8.0
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

const VERSIONE       = '0.3.0';
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
 * WOOCOMMERCE NON E' PIU' UN REQUISITO.
 *
 * Prima lo era, e senza WooCommerce il plugin si fermava qui con un avviso.
 * Era una restrizione senza motivo: quello che il plugin sa fare — indicizzare
 * dei contenuti, cercarli a parole o con una foto, rispondere a domande su di
 * essi — non ha bisogno di un carrello. Su un sito senza negozio diventa la
 * base di conoscenza del sito: indicizza pagine e articoli e risponde su
 * quelli.
 *
 * Il plugin si adatta da solo; la domanda "c'e' un negozio?" si fa in un posto
 * solo, in Storegentic\Negozio.
 *
 * Si aspetta comunque `plugins_loaded`, perche' prima di quel momento non si
 * puo' sapere se WooCommerce c'e'.
 */
add_action( 'plugins_loaded', array( Plugin::class, 'avvia' ), 20 );

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
