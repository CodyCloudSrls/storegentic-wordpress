<?php
/**
 * Da prodotto WooCommerce a prodotto Storegentic.
 *
 * Il contratto chiede pochi campi obbligatori — `sku` e `name` — e un
 * `metadata` libero. La tentazione e' riversarci dentro tutto: qui invece si
 * manda cio' che serve a cercare e a mostrare una scheda, e nient'altro.
 * Un catalogo di 200 prodotti con 80 campi ciascuno costa banda a ogni
 * sincronizzazione e non migliora di un punto la ricerca.
 *
 * LO SKU E' LA CHIAVE. Il server identifica i prodotti dallo SKU, e la
 * riconciliazione cancella cio' che non ha visto. Un prodotto senza SKU non
 * puo' essere sincronizzato in modo stabile: gliene viene costruito uno
 * derivato dall'identificativo, che non cambia nel tempo.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Catalogo;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mappatore {

	/**
	 * @return array<string,mixed>
	 */
	public static function prodotto( WC_Product $p ): array {
		$voce = array(
			'sku'         => self::sku( $p ),
			'name'        => $p->get_name(),
			'description' => self::descrizione( $p ),
			'productUrl'  => (string) get_permalink( $p->get_id() ),
			'metadata'    => self::metadati( $p ),
		);

		/**
		 * Permette a un negozio di aggiungere campi propri senza toccare il plugin.
		 *
		 * @param array<string,mixed> $voce
		 * @param WC_Product          $p
		 */
		return (array) apply_filters( 'storegentic_prodotto', $voce, $p );
	}

	/**
	 * Lo SKU con cui il prodotto e' conosciuto dal servizio.
	 *
	 * Se il negozio non usa gli SKU se ne costruisce uno dall'identificativo
	 * del prodotto: e' stabile nel tempo, che e' l'unica cosa che conta per
	 * la riconciliazione. Il prefisso evita di collidere con uno SKU vero
	 * inserito in seguito.
	 */
	public static function sku( WC_Product $p ): string {
		$sku = trim( (string) $p->get_sku() );

		return '' !== $sku ? $sku : 'wc-' . $p->get_id();
	}

	/**
	 * Il testo su cui si cerca.
	 *
	 * Prima la descrizione breve, che nei cataloghi curati e' quella scritta
	 * per essere letta; la lunga come ripiego. Gli shortcode vengono
	 * eseguiti e poi rimossi i tag: al servizio serve testo, non markup.
	 */
	private static function descrizione( WC_Product $p ): string {
		$testo = trim( (string) $p->get_short_description() );

		if ( '' === $testo ) {
			$testo = trim( (string) $p->get_description() );
		}

		$testo = wp_strip_all_tags( strip_shortcodes( $testo ), true );
		$testo = html_entity_decode( $testo, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$testo = trim( (string) preg_replace( '/\s+/u', ' ', $testo ) );

		// Oltre questa soglia si descrive un prodotto diverso da quello cercato.
		return mb_substr( $testo, 0, 2000 );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function metadati( WC_Product $p ): array {
		$prezzo = $p->get_price();

		$dati = array(
			'price'        => '' !== $prezzo && null !== $prezzo ? (float) $prezzo : null,
			'regularPrice' => '' !== $p->get_regular_price() ? (float) $p->get_regular_price() : null,
			'currency'     => get_woocommerce_currency(),
			'availability' => $p->is_in_stock() ? 'in_stock' : 'out_of_stock',
			'stock'        => $p->managing_stock() ? (int) $p->get_stock_quantity() : null,
			'imageUrl'     => self::immagine( $p ),
			'brand'        => self::marchio( $p ),
			'categories'   => self::categorie( $p ),
			'categoryPath' => self::percorso_categoria( $p ),
			'type'         => $p->get_type(),
			'onSale'       => $p->is_on_sale(),
			'featured'     => $p->is_featured(),
			'permalink'    => (string) get_permalink( $p->get_id() ),
			'productId'    => $p->get_id(),
			'updatedAt'    => $p->get_date_modified() ? $p->get_date_modified()->date( DATE_ATOM ) : null,
		);

		$attributi = self::attributi( $p );
		if ( ! empty( $attributi ) ) {
			$dati['attributes'] = $attributi;
		}

		// I campi vuoti non vanno spediti: non aggiungono nulla e pesano.
		return array_filter(
			$dati,
			static fn( $v ) => null !== $v && '' !== $v && array() !== $v
		);
	}

	private static function immagine( WC_Product $p ): ?string {
		$id = (int) $p->get_image_id();

		if ( $id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $id, 'woocommerce_single' );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	/**
	 * Il marchio.
	 *
	 * WooCommerce 9.4 ha introdotto `product_brand`; prima ognuno usava la
	 * propria tassonomia. Si prova quella ufficiale e poi le due piu'
	 * diffuse, invece di pretendere che il negozio usi la nostra.
	 */
	private static function marchio( WC_Product $p ): ?string {
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tassonomia ) {
			if ( ! taxonomy_exists( $tassonomia ) ) {
				continue;
			}
			$termini = wp_get_post_terms( $p->get_id(), $tassonomia, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $termini ) && ! empty( $termini ) ) {
				return (string) $termini[0];
			}
		}

		return null;
	}

	/**
	 * @return array<int,string>
	 */
	private static function categorie( WC_Product $p ): array {
		$termini = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );

		return is_wp_error( $termini ) ? array() : array_values( array_map( 'strval', $termini ) );
	}

	/**
	 * Il percorso gerarchico della categoria piu' profonda.
	 *
	 * Il contratto usa `categoryPath` come chiave delle categorie, in forma
	 * "genitore/figlio/nipote". Si sceglie la piu' profonda perche' e' la
	 * piu' specifica: "collane/pietre-dure" dice piu' di "collane".
	 */
	private static function percorso_categoria( WC_Product $p ): ?string {
		$termini = wp_get_post_terms( $p->get_id(), 'product_cat' );

		if ( is_wp_error( $termini ) || empty( $termini ) ) {
			return null;
		}

		$migliore = null;
		$profondo = -1;

		foreach ( $termini as $t ) {
			$catena = self::catena( $t );
			$p_att  = count( $catena );
			if ( $p_att > $profondo ) {
				$profondo = $p_att;
				$migliore = implode( '/', $catena );
			}
		}

		return $migliore;
	}

	/**
	 * @return array<int,string> Gli slug dal capostipite al termine.
	 */
	private static function catena( \WP_Term $termine ): array {
		$catena = array( $termine->slug );
		$corrente = $termine;
		$guardia  = 0;

		// La guardia evita un ciclo infinito se la tassonomia ha un anello.
		while ( $corrente->parent > 0 && $guardia < 10 ) {
			$genitore = get_term( $corrente->parent, $termine->taxonomy );
			if ( ! $genitore instanceof \WP_Term ) {
				break;
			}
			array_unshift( $catena, $genitore->slug );
			$corrente = $genitore;
			$guardia++;
		}

		return $catena;
	}

	/**
	 * @return array<string,array<int,string>>
	 */
	private static function attributi( WC_Product $p ): array {
		$fuori = array();

		foreach ( $p->get_attributes() as $attributo ) {
			if ( ! $attributo instanceof \WC_Product_Attribute || ! $attributo->get_visible() ) {
				continue;
			}

			$etichetta = wc_attribute_label( $attributo->get_name(), $p );
			$valori    = $attributo->is_taxonomy()
				? wp_get_post_terms( $p->get_id(), $attributo->get_name(), array( 'fields' => 'names' ) )
				: $attributo->get_options();

			if ( is_wp_error( $valori ) || empty( $valori ) ) {
				continue;
			}

			$fuori[ $etichetta ] = array_values( array_map( 'strval', $valori ) );
		}

		return $fuori;
	}

	/**
	 * Le categorie come blocco a se', per l'indice di categoria del servizio.
	 *
	 * @param array<int,\WP_Term> $termini
	 * @return array<int,array<string,mixed>>
	 */
	public static function categorie_payload( array $termini ): array {
		$fuori = array();

		foreach ( $termini as $t ) {
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}

			$descrizione = trim( wp_strip_all_tags( (string) $t->description, true ) );

			$fuori[] = array_filter(
				array(
					'categoryPath'     => implode( '/', self::catena( $t ) ),
					'name'             => $t->name,
					'shortDescription' => mb_substr( $descrizione, 0, 300 ),
					'description'      => mb_substr( $descrizione, 0, 2000 ),
					'locale'           => substr( get_locale(), 0, 2 ),
					'productCount'     => (int) $t->count,
				),
				static fn( $v ) => null !== $v && '' !== $v
			);
		}

		return $fuori;
	}
}
