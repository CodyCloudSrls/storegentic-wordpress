<?php
/**
 * C'e' un negozio, o solo un sito?
 *
 * PERCHE' UNA CLASSE PER UNA DOMANDA SOLA. Il plugin fa due mestieri vicini ma
 * non uguali. Su un sito con WooCommerce indicizza i prodotti, e i risultati
 * sono schede con prezzo e disponibilita'. Su un sito senza, indicizza le
 * pagine e gli articoli, e diventa una base di conoscenza: l'assistente
 * risponde su quello che il sito racconta.
 *
 * La domanda "c'e' WooCommerce?" si faceva in undici file diversi, ognuno con
 * la sua forma — `class_exists( 'WooCommerce' )`, `function_exists(
 * 'wc_get_product' )`, `defined( 'WC_VERSION' )` — e ognuna vera in un momento
 * diverso del caricamento. Qui c'e' una risposta sola, e chi la usa non deve
 * sapere come si ottiene.
 *
 * COSA SIGNIFICA "C'E'". Non basta che il plugin sia installato: serve che sia
 * caricato al punto da poter chiedere un prodotto. Si controlla la funzione e
 * non la classe, perche' e' la funzione che poi si usa davvero.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Negozio {

	/** C'e' WooCommerce, e risponde? */
	public static function c_e(): bool {
		return function_exists( 'wc_get_product' ) && function_exists( 'wc_get_product_id_by_sku' );
	}

	/**
	 * Il permesso che serve per toccare le impostazioni.
	 *
	 * Su un negozio e' quello di WooCommerce, cosi' chi gestisce il negozio ci
	 * arriva senza essere amministratore del sito. Senza WooCommerce quel
	 * permesso non esiste — nessun ruolo lo ha — e pretenderlo chiuderebbe fuori
	 * anche l'amministratore.
	 */
	public static function permesso(): string {
		$permesso = self::c_e() ? 'manage_woocommerce' : 'manage_options';

		/**
		 * Permette di affidare le impostazioni a un altro ruolo.
		 *
		 * @param string $permesso Il permesso che il plugin userebbe.
		 */
		return (string) apply_filters( 'storegentic_permesso', $permesso );
	}

	/**
	 * I tipi di contenuto su cui il plugin cerca in casa propria.
	 *
	 * Li usano i suggerimenti mentre si scrive e il ripiego quando il servizio
	 * non risponde: tutti e due interrogano il database del sito, e devono
	 * guardare le stesse cose che sono state mandate all'indice. Guardare
	 * altrove vorrebbe dire suggerire una pagina che il servizio non conosce, o
	 * non trovare quella che conosce.
	 *
	 * @return array<int,string>
	 */
	public static function tipi_indicizzati(): array {
		if ( self::c_e() ) {
			return array( 'product' );
		}

		$tipi = array_values( array_filter( (array) Impostazioni::leggi( 'tipi' ) ) );

		return empty( $tipi ) ? array( 'page' ) : $tipi;
	}

	/**
	 * Lo SKU con cui il servizio conosce un contenuto del sito.
	 *
	 * Su un negozio lo costruisce Catalogo\Mappatore dal prodotto; qui serve la
	 * strada corta, che parte da un identificativo e basta.
	 */
	public static function sku_di( int $id ): string {
		if ( self::c_e() ) {
			$prodotto = wc_get_product( $id );

			return $prodotto ? \Storegentic\Frontend\Risolutore::sku( $prodotto ) : '';
		}

		$post = get_post( $id );

		return $post instanceof \WP_Post ? \Storegentic\Catalogo\Contenuti::sku( $post ) : '';
	}
}
