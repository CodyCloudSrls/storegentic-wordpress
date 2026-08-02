<?php
/**
 * I suggerimenti mentre si scrive.
 *
 * PERCHE' NON LI CHIEDE AL SERVIZIO. Misurato su questo negozio, una ricerca
 * semantica costa otto secondi tondi, sempre, e il numero non dipende da
 * quanti risultati si chiedono: e' un costo fisso del servizio. Una ricerca
 * lanciata a ogni tasto premuto avrebbe prodotto una fila di richieste da otto
 * secondi l'una, un cerchietto che gira per tutta la digitazione, e la quota
 * del negozio consumata da chi ancora non ha finito di scrivere.
 *
 * Quindi il lavoro si divide. Mentre si scrive rispondono i nomi dei prodotti
 * e delle categorie, che stanno gia' nel database del negozio e si trovano in
 * pochi millisecondi: e' quello che serve a chi sa gia' cosa vuole. La ricerca
 * semantica — quella che capisce "un regalo per una laurea" — parte quando si
 * preme Invio, cioe' quando chi cerca ha finito di formulare la domanda e
 * accetta di aspettare.
 *
 * Non e' un ripiego: e' la divisione giusta anche a parita' di velocita'.
 * Un elenco di nomi che compare sotto il dito e una risposta ragionata sono
 * due cose diverse, e chi cerca sa quale delle due sta chiedendo.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Suggerimenti {

	/** Quanti se ne mostrano in tutto. */
	private const QUANTI = 7;

	/** Per quanto si conserva la risposta a un dato principio di parola. */
	private const DURATA = 600;

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function per( string $inizio ): array {
		$inizio = trim( $inizio );

		if ( mb_strlen( $inizio ) < 2 ) {
			return array();
		}

		$impronta = 'sg_sugg_' . md5( mb_strtolower( $inizio ) );
		$pronti   = get_transient( $impronta );

		if ( is_array( $pronti ) ) {
			return $pronti;
		}

		$voci = array_merge( self::categorie( $inizio ), self::prodotti( $inizio ) );
		$voci = array_slice( $voci, 0, self::QUANTI );

		set_transient( $impronta, $voci, self::DURATA );

		return $voci;
	}

	/**
	 * Le categorie che cominciano con quelle lettere.
	 *
	 * Vanno per prime: portare a una categoria e' piu' utile che portare a un
	 * prodotto solo, perche' chi scrive "colla" quasi sempre vuole vedere le
	 * collane, non una collana in particolare.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function categorie( string $inizio ): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$termini = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name__like' => $inizio,
				'hide_empty' => true,
				'number'     => 3,
			)
		);

		if ( is_wp_error( $termini ) ) {
			return array();
		}

		$voci = array();

		foreach ( $termini as $t ) {
			$url = get_term_link( $t );

			if ( is_wp_error( $url ) ) {
				continue;
			}

			$voci[] = array(
				'tipo'      => 'categoria',
				'etichetta' => (string) $t->name,
				'nota'      => sprintf(
					/* translators: %d: quanti prodotti ci sono nella categoria. */
					_n( '%d gioiello', '%d gioielli', (int) $t->count, 'storegentic' ),
					(int) $t->count
				),
				'url'       => (string) $url,
			);
		}

		return $voci;
	}

	/**
	 * I prodotti il cui nome contiene quelle lettere.
	 *
	 * Si interroga direttamente la tabella dei contenuti invece di usare la
	 * ricerca di WordPress: quella cerca anche dentro le descrizioni, e su un
	 * catalogo grande diventa lenta proprio dove serve che sia immediata. Qui
	 * si guarda solo il titolo, con un tetto stretto.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function prodotti( string $inizio ): array {
		global $wpdb;

		/*
		 * Si cerca il principio di una parola, non una sottostringa qualunque:
		 * "oro" non deve pescare "lavoro". Il primo modello prende l'inizio del
		 * titolo, il secondo l'inizio di ogni parola successiva.
		 */
		$come  = $wpdb->esc_like( $inizio );
		$primo = $come . '%';
		$dopo  = '% ' . $come . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$righe = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title
				   FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				    AND post_status = 'publish'
				    AND ( post_title LIKE %s OR post_title LIKE %s )
			   ORDER BY CHAR_LENGTH(post_title) ASC
				  LIMIT 6",
				$primo,
				$dopo
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( empty( $righe ) ) {
			return array();
		}

		$voci = array();

		foreach ( $righe as $riga ) {
			$prodotto = wc_get_product( (int) $riga->ID );

			if ( ! $prodotto || ! $prodotto->is_visible() ) {
				continue;
			}

			$voci[] = array(
				'tipo'      => 'prodotto',
				'etichetta' => (string) $riga->post_title,
				'nota'      => html_entity_decode( wp_strip_all_tags( (string) $prodotto->get_price_html() ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'url'       => (string) $prodotto->get_permalink(),
			);
		}

		return $voci;
	}
}
