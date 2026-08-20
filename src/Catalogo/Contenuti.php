<?php
/**
 * I contenuti del sito, per quando non c'e' un negozio.
 *
 * PERCHE' ESISTE. Il plugin sa fare tre cose — indicizzare, cercare,
 * rispondere — e nessuna delle tre ha bisogno di un carrello. Su un sito senza
 * WooCommerce diventa la base di conoscenza del sito: manda al servizio le
 * pagine e gli articoli scelti nelle impostazioni, e da li' in poi la ricerca
 * a parole e l'assistente lavorano su quelli.
 *
 * E' IL GEMELLO DI Catalogo\Mappatore, per un'altra materia prima. Mappatore
 * traduce un prodotto WooCommerce nella forma che il servizio si aspetta;
 * questa classe traduce un contenuto di WordPress nella stessa forma. Chi
 * spedisce — Catalogo\Sincronizzazione — non sa quale delle due sta usando, e
 * non deve saperlo.
 *
 * COSA NON C'E'. Un articolo non ha prezzo, disponibilita' ne' marchio, e quei
 * campi non si spediscono: mandare `price: 0` farebbe comparire "0,00 €" sotto
 * il titolo di una pagina. Un campo che non si applica si omette, non si
 * riempie di zeri.
 *
 * LO SKU DI UN CONTENUTO. Il servizio identifica tutto per SKU, quindi ne
 * serve uno anche qui. Si costruisce dall'identificativo del contenuto, con un
 * prefisso che non puo' collidere con uno SKU di WooCommerce: l'indirizzo di
 * una pagina cambia quando si cambia il titolo, l'identificativo no, e la
 * riconciliazione ha bisogno di una chiave che non si muove.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Catalogo;

use Storegentic\Impostazioni;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contenuti {

	/**
	 * Il prefisso degli identificativi dei contenuti.
	 *
	 * `wp-` e non `wc-`: quest'ultimo lo usa Mappatore per i prodotti senza
	 * SKU, e due prefissi uguali su due tabelle diverse vorrebbero dire che il
	 * prodotto 12 e la pagina 12 sono la stessa cosa per il servizio.
	 */
	private const PREFISSO = 'wp-';

	/** Quanti caratteri di testo si mandano al massimo per contenuto. */
	private const TESTO = 12000;

	/** Lo SKU con cui il servizio conosce questo contenuto. */
	public static function sku( WP_Post $post ): string {
		return self::PREFISSO . $post->ID;
	}

	/** Questo SKU appartiene a un contenuto del sito? */
	public static function e_un_contenuto( string $sku ): bool {
		return str_starts_with( $sku, self::PREFISSO );
	}

	/** L'identificativo dentro lo SKU, o zero se non e' uno dei nostri. */
	public static function id_da_sku( string $sku ): int {
		return self::e_un_contenuto( $sku ) ? (int) substr( $sku, strlen( self::PREFISSO ) ) : 0;
	}

	/**
	 * Gli identificativi dei contenuti da mandare.
	 *
	 * Si ordina per identificativo, che non cambia: la sessione di
	 * sincronizzazione congela questo elenco e lo scorre a pagine, quindi un
	 * ordinamento che si muove — per data, per titolo — farebbe saltare o
	 * ripetere qualcosa a ogni pubblicazione fatta nel frattempo.
	 *
	 * @return array<int,int>
	 */
	public static function identificativi(): array {
		$tipi = (array) Impostazioni::leggi( 'tipi' );

		if ( empty( $tipi ) ) {
			return array();
		}

		$stati = array( 'publish' );

		if ( Impostazioni::leggi( 'includi_bozze' ) ) {
			$stati[] = 'private';
		}

		$ids = get_posts(
			array(
				'post_type'        => $tipi,
				'post_status'      => $stati,
				'numberposts'      => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		/**
		 * Permette di escludere contenuti dalla sincronizzazione.
		 *
		 * @param array<int,int> $ids
		 */
		return array_values( array_map( 'intval', (array) apply_filters( 'storegentic_contenuti_da_sincronizzare', $ids ) ) );
	}

	/**
	 * Un contenuto, nella forma che il servizio si aspetta.
	 *
	 * @return array<string,mixed>
	 */
	public static function contenuto( WP_Post $post ): array {
		$tipo = get_post_type_object( $post->post_type );

		$voce = array(
			'sku'              => self::sku( $post ),
			'name'             => self::titolo( $post ),
			'description'      => self::testo( $post ),
			'shortDescription' => self::sommario( $post ),
			'productUrl'       => (string) get_permalink( $post ),
			'imageUrl'         => self::immagine( $post ),
			'categoryPath'     => $tipo instanceof \WP_Post_Type ? $tipo->name : $post->post_type,
			'categories'       => self::categorie( $post ),
			'metadata'         => array(
				'type'       => $post->post_type,
				'typeLabel'  => $tipo instanceof \WP_Post_Type ? $tipo->labels->singular_name : $post->post_type,
				'permalink'  => (string) get_permalink( $post ),
				'updatedAt'  => (string) get_post_modified_time( 'c', true, $post ),
				'categories' => self::categorie( $post ),
			),
		);

		// I campi vuoti non si spediscono: non aggiungono nulla e pesano.
		$voce = array_filter( $voce, static fn( $v ) => null !== $v && '' !== $v && array() !== $v );

		/**
		 * Permette a un sito di aggiungere campi propri senza toccare il plugin.
		 *
		 * @param array<string,mixed> $voce
		 * @param WP_Post             $post
		 */
		return (array) apply_filters( 'storegentic_contenuto', $voce, $post );
	}

	private static function titolo( WP_Post $post ): string {
		$titolo = trim( wp_strip_all_tags( (string) get_the_title( $post ) ) );

		/*
		 * Un contenuto senza titolo esiste — capita con certi tipi costruiti da
		 * altri plugin — e senza un nome nell'indice sarebbe una riga vuota nei
		 * risultati. Si ripiega sul tipo e sull'identificativo, che almeno
		 * dicono di cosa si tratta.
		 */
		return '' !== $titolo
			? $titolo
			: sprintf(
				/* translators: 1: tipo di contenuto, 2: identificativo. */
				__( '%1$s numero %2$d', 'storegentic' ),
				$post->post_type,
				$post->ID
			);
	}

	/**
	 * Il testo su cui si cerca.
	 *
	 * QUATTRO PASSAGGI, E OGNUNO TOGLIE UNA COSA CHE ROVINEREBBE L'INDICE.
	 *
	 * 1. `the_content` fa girare i blocchi e gli shortcode registrati, cosi'
	 *    quello che si legge e' il testo reso e non il sorgente.
	 *
	 * 2. `strip_shortcodes` toglie quelli registrati che il filtro non ha
	 *    consumato.
	 *
	 * 3. Poi restano gli shortcode di NESSUNO, ed e' il caso che conta davvero.
	 *    Misurato su questo sito: una pagina scritta anni fa con Visual
	 *    Composer, su un sito che oggi usa Divi. Quegli shortcode non li
	 *    registra piu' nessuno, quindi WordPress li lascia passare come testo, e
	 *    la descrizione che sarebbe finita nell'indice cominciava con
	 *    "[vc_row][vc_column width=&#8221;1/6&#8243;]". Un indice a cui si
	 *    insegna quella roba poi la usa per rispondere.
	 *
	 *    Il modello pretende che il nome cominci con una lettera, cosi' un
	 *    riferimento come "[1]" in un testo resta dov'e'. Una parola sola fra
	 *    parentesi quadre — "[sic]" — se ne va insieme agli shortcode: e' il
	 *    prezzo da pagare, ed e' molto piu' basso di quello di indicizzare il
	 *    codice di un costruttore di pagine.
	 *
	 * 4. Le entita' HTML tornano caratteri: al servizio serve la parola
	 *    "perché", non "perch&eacute;".
	 */
	private static function testo( WP_Post $post ): string {
		$grezzo = (string) apply_filters( 'the_content', $post->post_content );
		$grezzo = strip_shortcodes( $grezzo );
		$grezzo = (string) preg_replace( '#\[/?[a-zA-Z][a-zA-Z0-9_-]*[^\]]*\]#', ' ', $grezzo );

		$pulito = html_entity_decode( wp_strip_all_tags( $grezzo, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$pulito = trim( (string) preg_replace( '/\s+/u', ' ', $pulito ) );

		return mb_substr( $pulito, 0, self::TESTO );
	}

	private static function sommario( WP_Post $post ): ?string {
		$estratto = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );

		if ( '' !== $estratto ) {
			return mb_substr( $estratto, 0, 300 );
		}

		$testo = self::testo( $post );

		return '' !== $testo ? mb_substr( $testo, 0, 300 ) : null;
	}

	private static function immagine( WP_Post $post ): ?string {
		$url = get_the_post_thumbnail_url( $post, 'large' );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	/**
	 * Le tassonomie del contenuto, appiattite in un elenco di nomi.
	 *
	 * Servono al servizio per raggruppare e per capire di cosa parla una
	 * pagina. Si prendono solo le tassonomie pubbliche: le altre sono
	 * organizzazione interna, e non descrivono il contenuto a chi lo cerca.
	 *
	 * @return array<int,string>
	 */
	private static function categorie( WP_Post $post ): array {
		$nomi = array();

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tassonomia ) {
			if ( ! $tassonomia->public ) {
				continue;
			}

			$termini = get_the_terms( $post, $tassonomia->name );

			if ( is_wp_error( $termini ) || empty( $termini ) ) {
				continue;
			}

			foreach ( $termini as $t ) {
				$nomi[] = (string) $t->name;
			}
		}

		return array_values( array_unique( $nomi ) );
	}
}
