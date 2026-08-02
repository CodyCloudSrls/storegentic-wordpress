<?php
/**
 * Da risultato del servizio a scheda da mostrare.
 *
 * LA DIVISIONE DEL LAVORO. Storegentic decide QUALI prodotti rispondono a una
 * domanda e in che ordine: e' cio' che sa fare e che il negozio da solo non
 * saprebbe fare. Il negozio decide COSA sono quei prodotti: prezzo, foto,
 * disponibilita', nome. Quel dato ce l'ha gia', aggiornato al secondo.
 *
 * Prima leggevo tutto dalla risposta del servizio. Funziona finche' l'indice
 * e' fresco, ma l'indice si aggiorna una volta al giorno: fra due
 * sincronizzazioni un prezzo puo' cambiare, un pezzo unico puo' finire, una
 * foto puo' essere sostituita. Il visitatore vedeva il catalogo di ieri e lo
 * scopriva solo aprendo la scheda.
 *
 * Qui il collegamento e' lo SKU, che e' anche la chiave con cui il servizio
 * identifica i prodotti. Quando lo SKU corrisponde a un prodotto del negozio
 * si legge dal negozio; quando non corrisponde — prodotto cancellato, o
 * catalogo non ancora riallineato — si usa cio' che manda il servizio, senza
 * far sparire il risultato.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Catalogo\Mappatore;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Risolutore {

	/**
	 * Corrispondenze SKU -> identificativo gia' trovate in questa richiesta.
	 *
	 * `wc_get_product_id_by_sku()` interroga il database ogni volta. Una
	 * risposta dell'assistente cita lo stesso prodotto in piu' punti, e senza
	 * questa memoria la stessa riga verrebbe cercata cinque volte.
	 *
	 * @var array<string,int>
	 */
	private static array $trovati = array();

	/**
	 * @param array<int,array<string,mixed>> $risultati Come arrivano dal servizio.
	 * @return array<int,array<string,mixed>>
	 */
	public static function schede( array $risultati ): array {
		$schede = array();
		$visti  = array();

		foreach ( $risultati as $grezzo ) {
			if ( ! is_array( $grezzo ) ) {
				continue;
			}

			$sku = trim( (string) ( $grezzo['sku'] ?? '' ) );

			/*
			 * Lo stesso prodotto puo' tornare piu' volte: una per variante che
			 * ha superato la soglia. A schermo sarebbero due schede identiche.
			 */
			if ( '' !== $sku && isset( $visti[ $sku ] ) ) {
				continue;
			}

			$visti[ $sku ] = true;

			$prodotto = self::prodotto( $sku );
			$scheda   = $prodotto instanceof WC_Product
				? self::dal_negozio( $prodotto )
				: self::dal_servizio( $grezzo );

			if ( null === $scheda ) {
				continue;
			}

			$scheda['sku']       = $sku;
			$scheda['punteggio'] = isset( $grezzo['score'] ) ? round( (float) $grezzo['score'], 4 ) : null;

			$schede[] = $scheda;
		}

		return $schede;
	}

	/**
	 * Il prodotto del negozio che corrisponde a questo SKU, se esiste.
	 *
	 * Si accetta solo cio' che un visitatore puo' davvero vedere: un prodotto
	 * in bozza o nel cestino resta nell'indice finche' non si sincronizza, ma
	 * mostrarlo porterebbe a una pagina 404.
	 */
	private static function prodotto( string $sku ): ?WC_Product {
		if ( '' === $sku || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return null;
		}

		if ( ! isset( self::$trovati[ $sku ] ) ) {
			self::$trovati[ $sku ] = (int) wc_get_product_id_by_sku( $sku );

			/*
			 * Il catalogo puo' non usare gli SKU: in quel caso il mappatore ne
			 * costruisce uno dall'identificativo ("wc-1234"). Qui si fa il
			 * percorso inverso, altrimenti su quei negozi nessun risultato
			 * troverebbe mai il proprio prodotto.
			 */
			if ( 0 === self::$trovati[ $sku ] && preg_match( '/^wc-(\d+)$/', $sku, $pezzi ) ) {
				self::$trovati[ $sku ] = (int) $pezzi[1];
			}
		}

		if ( 0 === self::$trovati[ $sku ] ) {
			return null;
		}

		$prodotto = wc_get_product( self::$trovati[ $sku ] );

		if ( ! $prodotto instanceof WC_Product || 'publish' !== $prodotto->get_status() ) {
			return null;
		}

		return $prodotto;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function dal_negozio( WC_Product $p ): array {
		$in_saldo = $p->is_on_sale();

		return array(
			'id'          => $p->get_id(),
			'nome'        => $p->get_name(),
			'url'         => (string) $p->get_permalink(),
			'immagine'    => self::immagine( $p ),
			'alt'         => self::alt( $p ),
			'prezzo'      => self::prezzo( $p ),
			'prezzoPieno' => $in_saldo ? self::formatta( (float) $p->get_regular_price() ) : null,
			'valore'      => '' !== (string) $p->get_price() ? (float) $p->get_price() : null,
			'marchio'     => self::marchio( $p ),
			'categoria'   => self::categoria( $p ),
			'disponibile' => $p->is_in_stock(),
			'unico'       => self::pezzo_unico( $p ),
			'sommario'    => self::sommario( $p->get_short_description() ),
			/*
			 * Solo cio' che si compra con un clic solo. Un prodotto variabile
			 * ha bisogno che si scelga la misura, e un pulsante che finge di
			 * poterlo aggiungere manderebbe l'utente in errore: per quelli si
			 * apre la scheda.
			 */
			'acquistabile' => $p->is_purchasable() && $p->is_in_stock() && ! $p->is_type( 'variable' ),
			'daScheda'     => true,
		);
	}

	/**
	 * Il ripiego: il prodotto non e' piu' nel negozio, ma nell'indice si'.
	 *
	 * @param array<string,mixed> $c
	 * @return array<string,mixed>|null
	 */
	private static function dal_servizio( array $c ): ?array {
		$url = esc_url_raw( (string) self::campo( $c, 'url' ) );

		// Senza indirizzo la scheda non porta da nessuna parte: si scarta.
		if ( '' === $url ) {
			return null;
		}

		$prezzo = self::campo( $c, 'price' );

		return array(
			'id'           => 0,
			'nome'         => (string) ( $c['name'] ?? '' ),
			'url'          => $url,
			'immagine'     => esc_url_raw( (string) self::campo( $c, 'imageUrl' ) ) ?: null,
			'alt'          => (string) ( $c['name'] ?? '' ),
			'prezzo'       => is_numeric( $prezzo ) ? self::formatta( (float) $prezzo ) : null,
			'prezzoPieno'  => null,
			'valore'       => is_numeric( $prezzo ) ? (float) $prezzo : null,
			'marchio'      => self::campo( $c, 'brand' ) ?: null,
			'categoria'    => self::etichetta_categoria( (string) ( self::campo( $c, 'category' ) ?: self::campo( $c, 'categoryPath' ) ) ),
			'disponibile'  => 'out_of_stock' !== self::campo( $c, 'availability' ),
			'unico'        => false,
			'sommario'     => self::sommario( (string) self::campo( $c, 'shortDescription' ) ),
			'acquistabile' => false,
			'daScheda'     => false,
		);
	}

	/**
	 * La foto nella misura che serve davvero.
	 *
	 * Il servizio restituisce l'originale, che su questo catalogo arriva a
	 * 2500 pixel e a qualche centinaio di kilobyte. In una griglia di venti
	 * risultati sono decine di megabyte per una vetrina di 300 pixel.
	 * WooCommerce ha gia' i ritagli: si usano quelli.
	 */
	private static function immagine( WC_Product $p ): ?string {
		$id = (int) $p->get_image_id();

		if ( 0 === $id ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $id, 'woocommerce_thumbnail' );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	private static function alt( WC_Product $p ): string {
		$alt = (string) get_post_meta( (int) $p->get_image_id(), '_wp_attachment_image_alt', true );

		return '' !== trim( $alt ) ? $alt : $p->get_name();
	}

	/**
	 * Il prezzo come lo scrive il negozio.
	 *
	 * `get_price_html()` restituisce markup — con `<del>` e `<ins>` quando c'e'
	 * uno sconto, e un intervallo per i prodotti variabili. Al browser serve
	 * testo semplice, perche' viene inserito con textContent: le entita' HTML
	 * resterebbero visibili come "&euro;49,00".
	 */
	private static function prezzo( WC_Product $p ): ?string {
		$html = (string) $p->get_price_html();

		if ( '' === trim( $html ) ) {
			return null;
		}

		$testo = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$testo = trim( preg_replace( '/\s+/u', ' ', $testo ) ?? '' );

		return '' !== $testo ? $testo : null;
	}

	private static function formatta( float $importo ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return (string) $importo;
		}

		return html_entity_decode( wp_strip_all_tags( wc_price( $importo ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Il marchio, dalla tassonomia che il negozio usa davvero.
	 *
	 * WooCommerce ha introdotto `product_brand`; prima ci pensava un plugin,
	 * con nomi diversi. Si prova nell'ordine e ci si ferma alla prima che
	 * esiste, cosi' il plugin funziona su installazioni di eta' diverse.
	 */
	private static function marchio( WC_Product $p ): ?string {
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tassonomia ) {
			if ( ! taxonomy_exists( $tassonomia ) ) {
				continue;
			}

			$nomi = wp_get_post_terms( $p->get_id(), $tassonomia, array( 'fields' => 'names' ) );

			if ( ! is_wp_error( $nomi ) && ! empty( $nomi ) ) {
				return (string) $nomi[0];
			}
		}

		return null;
	}

	/**
	 * La categoria piu' precisa: quella senza figlie fra quelle assegnate.
	 *
	 * Un prodotto sta spesso in "Collane" e in "Collane > Pietre dure". La
	 * seconda dice qualcosa, la prima e' gia' evidente dal contesto.
	 */
	private static function categoria( WC_Product $p ): ?string {
		$termini = wp_get_post_terms( $p->get_id(), 'product_cat' );

		if ( is_wp_error( $termini ) || empty( $termini ) ) {
			return null;
		}

		$scelto = $termini[0];

		foreach ( $termini as $t ) {
			if ( $t->parent > 0 ) {
				$scelto = $t;
				break;
			}
		}

		return (string) $scelto->name;
	}

	/**
	 * Un pezzo unico e' un argomento di vendita, e va detto nel risultato.
	 *
	 * Vale solo con la giacenza gestita: senza, "1" non e' un numero ma
	 * l'assenza di un numero.
	 */
	private static function pezzo_unico( WC_Product $p ): bool {
		return $p->managing_stock() && 1 === (int) $p->get_stock_quantity() && $p->is_in_stock();
	}

	private static function sommario( string $testo ): ?string {
		$pulito = trim( wp_strip_all_tags( strip_shortcodes( $testo ) ) );

		if ( '' === $pulito ) {
			return null;
		}

		return wp_html_excerpt( $pulito, 160, '…' );
	}

	/**
	 * @param array<string,mixed> $c
	 * @return string
	 */
	private static function campo( array $c, string $campo ): string {
		if ( isset( $c[ $campo ] ) && is_scalar( $c[ $campo ] ) && '' !== $c[ $campo ] ) {
			return (string) $c[ $campo ];
		}

		if ( isset( $c['matchedVariant'][ $campo ] ) && is_scalar( $c['matchedVariant'][ $campo ] ) ) {
			return (string) $c['matchedVariant'][ $campo ];
		}

		$sfaccettature = $c['attributes']['attributes']['facets'] ?? array();

		if ( isset( $sfaccettature[ $campo ][0]['value'] ) && is_scalar( $sfaccettature[ $campo ][0]['value'] ) ) {
			return (string) $sfaccettature[ $campo ][0]['value'];
		}

		return '';
	}

	/** "collane/pietre-dure" diventa "Pietre dure". */
	public static function etichetta_categoria( string $percorso ): ?string {
		$percorso = trim( $percorso );

		if ( '' === $percorso ) {
			return null;
		}

		$segmenti = explode( '/', $percorso );
		$foglia   = (string) end( $segmenti );

		return ucfirst( strtolower( str_replace( array( '-', '_' ), ' ', $foglia ) ) );
	}

	/** Lo SKU con cui il servizio conosce un prodotto del negozio. */
	public static function sku( WC_Product $p ): string {
		return Mappatore::sku( $p );
	}

	/**
	 * Un risultato ridotto a cio' che serve davvero, e appiattito.
	 *
	 * Serve alla cache. Un risultato come arriva dal servizio pesa un paio di
	 * kilobyte: descrizioni intere, sfaccettature, punteggi di diagnostica.
	 * Quarantotto risultati fanno cento kilobyte, e una cache di quel peso in
	 * un'opzione di WordPress e' un rimedio peggiore del male.
	 *
	 * Si tiene lo SKU — che e' la chiave con cui si risolve il prodotto vero —
	 * e i pochi campi che servono come ripiego quando lo SKU non corrisponde
	 * piu' a niente. I campi si appiattiscono qui: dopo, `campo()` li trova al
	 * primo livello e non ha bisogno delle sfaccettature.
	 *
	 * @param array<string,mixed> $c
	 * @return array<string,mixed>
	 */
	public static function essenziale( array $c ): array {
		$ridotto = array(
			'sku'   => (string) ( $c['sku'] ?? '' ),
			'name'  => (string) ( $c['name'] ?? '' ),
			'score' => isset( $c['score'] ) ? (float) $c['score'] : null,
		);

		foreach ( array( 'url', 'imageUrl', 'price', 'brand', 'category', 'categoryPath', 'availability', 'shortDescription' ) as $campo ) {
			$valore = self::campo( $c, $campo );

			if ( '' !== $valore ) {
				$ridotto[ $campo ] = $valore;
			}
		}

		return $ridotto;
	}
}
