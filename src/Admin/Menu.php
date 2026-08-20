<?php
/**
 * Il menu, e quali pagine contiene.
 *
 * PERCHE' UN MENU PROPRIO E NON UNA VOCE SOTTO WOOCOMMERCE. Perche' il plugin
 * funziona anche senza WooCommerce: su un sito senza negozio indicizza le
 * pagine e gli articoli e fa da base di conoscenza. Appeso sotto WooCommerce
 * il pannello semplicemente non esisteva — nessun menu a cui attaccarsi,
 * nessun modo di arrivarci, nemmeno digitando l'indirizzo, perche' il permesso
 * richiesto era `manage_woocommerce` e senza WooCommerce non ce l'ha nessuno.
 *
 * PERCHE' DIVISO PER AMBITO. Prima era una pagina sola con sette sezioni, e
 * per cambiare il colore di un pulsante si scorreva davanti alla chiave del
 * servizio, alla sincronizzazione e alle analisi. Ogni pagina qui risponde a
 * una domanda sola:
 *
 *   Panoramica    va tutto bene?
 *   Collegamento  a chi siamo collegati?
 *   Aspetto       che faccia ha?
 *   Ricerca       come cerca?
 *   Contenuti     cosa sa?
 *   Statistiche   cosa cercano i clienti?
 *
 * OGNI PAGINA SALVA SOLO SE STESSA. Il modulo di ogni pagina dichiara a quale
 * gruppo appartiene, e il salvataggio guarda solo i gruppi dichiarati: una
 * casella che sta su un'altra pagina non viene letta, e quindi non viene
 * spenta per il fatto di non essere stata spedita. E' la stessa regola di
 * prima, quando i gruppi erano sezioni della stessa pagina; ora che sono
 * pagine diverse conta ancora di piu'.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {

	/** Lo slug della pagina principale, che e' anche quello del menu. */
	public const RADICE = 'storegentic';

	/**
	 * Le pagine, nell'ordine in cui compaiono.
	 *
	 * `gruppo` e' il nome con cui il salvataggio riconosce i campi di quella
	 * pagina; una pagina senza gruppo non ha un modulo da salvare.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function pagine(): array {
		return array(
			self::RADICE                => array(
				'titolo' => __( 'Panoramica', 'storegentic' ),
				'vista'  => 'panoramica',
				'gruppo' => '',
			),
			'storegentic-collegamento'  => array(
				'titolo' => __( 'Collegamento', 'storegentic' ),
				'vista'  => 'collegamento',
				'gruppo' => 'collegamento',
			),
			'storegentic-aspetto'       => array(
				'titolo' => __( 'Aspetto', 'storegentic' ),
				'vista'  => 'aspetto',
				'gruppo' => 'aspetto',
			),
			'storegentic-ricerca'       => array(
				'titolo' => __( 'Ricerca', 'storegentic' ),
				'vista'  => 'ricerca',
				'gruppo' => 'ricerca',
			),
			'storegentic-contenuti'     => array(
				'titolo' => Negozio::c_e() ? __( 'Catalogo', 'storegentic' ) : __( 'Contenuti', 'storegentic' ),
				'vista'  => 'contenuti',
				'gruppo' => 'contenuti',
			),
			'storegentic-statistiche'   => array(
				'titolo' => __( 'Statistiche', 'storegentic' ),
				'vista'  => 'statistiche',
				'gruppo' => '',
			),
		);
	}

	public static function registra(): void {
		$permesso = Negozio::permesso();

		add_menu_page(
			__( 'Storegentic', 'storegentic' ),
			__( 'Storegentic', 'storegentic' ),
			$permesso,
			self::RADICE,
			array( Pagina::class, 'rendi' ),
			self::icona(),
			/*
			 * Sotto WooCommerce e sopra Aspetto: e' il posto delle cose che
			 * riguardano cosa il sito vende o racconta, e non lo strumento con
			 * cui e' costruito.
			 */
			58
		);

		foreach ( self::pagine() as $slug => $pagina ) {
			add_submenu_page(
				self::RADICE,
				sprintf(
					/* translators: %s: il nome della pagina. */
					__( 'Storegentic — %s', 'storegentic' ),
					$pagina['titolo']
				),
				$pagina['titolo'],
				$permesso,
				$slug,
				array( Pagina::class, 'rendi' )
			);
		}
	}

	/** La pagina che si sta guardando, con i suoi dati. */
	public static function corrente(): string {
		$slug = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- sola lettura.

		return isset( self::pagine()[ $slug ] ) ? $slug : self::RADICE;
	}

	/** L'indirizzo di una pagina del menu. */
	public static function url( string $slug = self::RADICE, array $extra = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $slug ), $extra ), admin_url( 'admin.php' ) );
	}

	/**
	 * L'icona del menu.
	 *
	 * Una lente stilizzata, disegnata qui e non presa dal set di WordPress: le
	 * dashicons disponibili o parlano di ricerca generica o di commercio, e
	 * questo plugin fa tutte e due le cose a seconda del sito. Il colore e'
	 * `currentColor` cosi' segue il tema dell'amministrazione, chiaro o scuro
	 * che sia, invece di restare grigio quando la voce e' selezionata.
	 */
	private static function icona(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="8.6" cy="8.6" r="5.4"/><path d="M12.7 12.7L17 17"/><path d="M6.4 8.6h4.4M8.6 6.4v4.4"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
