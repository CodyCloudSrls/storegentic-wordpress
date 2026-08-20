<?php
/**
 * I suggerimenti mentre si scrive.
 *
 * NON E' LA RICERCA SEMANTICA. Quella costa dagli otto ai quindici secondi ed
 * e' un costo fisso del servizio: lanciata a ogni tasto premuto darebbe una
 * fila di richieste lentissime e un cerchietto che gira per tutta la
 * digitazione. Parte all'Invio, quando chi cerca ha finito di formulare la
 * domanda e accetta di aspettare.
 *
 * QUI RISPONDONO DUE SORGENTI, E SI SOMMANO.
 *
 * Il database del negozio conosce i titoli e le categorie, e li trova
 * guardando l'inizio di OGNI parola: cerca "perle" e trova "Colana perle
 * Maiorca". Costa una query e venticinque millisecondi, e non consuma quota.
 *
 * La ricerca istantanea del servizio conosce l'indice, e soprattutto i
 * MARCHI. Misurato: "klk" e' un marchio di questo catalogo, il database non lo
 * trova — nessun titolo lo contiene — e il servizio restituisce tre prodotti.
 * Riconosce pero' solo l'INIZIO del nome: "perle" e "zirconi", che stanno in
 * mezzo, per lui non esistono.
 *
 * Nessuna delle due basta da sola, e insieme costano un decimo di secondo. Se
 * il servizio tarda, i risultati locali sono gia' pronti e si mostrano quelli:
 * chi scrive non aspetta mai la rete.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Api\Client;
use Storegentic\Api\Contratto;
use Storegentic\Impostazioni;
use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Suggerimenti {

	/** Quanti se ne mostrano in tutto. */
	private const QUANTI = 7;

	/** Per quanto si conserva la risposta a un dato principio di parola. */
	private const DURATA = 600;

	/** Quanto si aspetta il servizio prima di rinunciare, in secondi. */
	private const ATTESA = 1;

	/**
	 * Per quanto si smette di chiedere al servizio dopo che ha mancato.
	 *
	 * Senza questa memoria, un servizio lento costa l'attesa piena a OGNI
	 * lettera battuta: chi scrive "collana" pagherebbe sette secondi di
	 * silenzio per un contributo che il database locale ha gia' dato.
	 */
	private const RIPOSO = 300;

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

		$completo = true;

		$voci = array_merge(
			self::categorie( $inizio ),
			self::prodotti( $inizio ),
			self::dal_servizio( $inizio, $completo )
		);

		$voci = array_slice( self::senza_doppioni( $voci ), 0, self::QUANTI );

		/*
		 * UNA RISPOSTA MONCA NON SI CONSERVA A LUNGO.
		 *
		 * Se il servizio non ha risposto, l'elenco contiene solo cio' che sa il
		 * database. Conservarlo dieci minuti vorrebbe dire che chi cerca un
		 * marchio — l'unica cosa che il database non trova — continua a non
		 * trovare niente anche dopo che il servizio e' tornato.
		 *
		 * Visto succedere in prova: dopo un giro con il servizio spento, "klk"
		 * rispondeva zero dal browser mentre in PHP dava tre prodotti. Era la
		 * cache che teneva in vita il risultato vuoto.
		 */
		set_transient( $impronta, $voci, $completo ? self::DURATA : 30 );

		return $voci;
	}

	/**
	 * I suggerimenti che arrivano dalla ricerca istantanea del servizio.
	 *
	 * COSA AGGIUNGE, E COSA NO. Misurato sul catalogo di questo negozio: la
	 * modalita' `prefix` risponde in un decimo di secondo, ma riconosce solo
	 * l'INIZIO del nome o del marchio. "col" trova le collane; "perle",
	 * "zirconi" e "maiorca" — parole che stanno in mezzo ai nomi — tornano
	 * zero risultati. La ricerca sul database del negozio, che guarda l'inizio
	 * di ogni parola, quelle le trova.
	 *
	 * Quindi non sostituisce: si aggiunge. Il servizio conosce i marchi e
	 * l'indice, il database conosce i titoli e le categorie, e chi scrive vede
	 * l'unione senza doppioni.
	 *
	 * SI USA SOLO SE IL CONTRATTO LA DICHIARA. Oggi l'handshake non la nomina,
	 * anche se l'indirizzo esiste ed e' documentato: chi vuole accenderla
	 * subito lo fa con il filtro `storegentic_endpoint`, e quando il contratto
	 * la dichiarera' si accendera' da sola. Vedi Api\Contratto.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function dal_servizio( string $inizio, bool &$completo ): array {
		if ( ! Impostazioni::leggi( 'istantanea' ) ) {
			return array();
		}

		$indirizzo = Contratto::endpoint_in_cache( 'instantSearch' );

		// Nessuna istantanea dichiarata: l'elenco locale E' la risposta intera.
		if ( '' === $indirizzo ) {
			return array();
		}

		if ( false !== get_transient( 'sg_istantanea_ko' ) ) {
			$completo = false;

			return array();
		}

		/*
		 * Un tentativo solo e attesa corta: qui c'e' qualcuno che sta ancora
		 * digitando. Se il servizio non risponde entro un secondo, i
		 * suggerimenti locali sono gia' pronti e bastano.
		 */
		$risposta = ( new Client( null, null, self::ATTESA, 0 ) )->post(
			$indirizzo,
			array( 'query' => $inizio, 'mode' => 'prefix', 'limit' => self::QUANTI )
		);

		if ( is_wp_error( $risposta ) ) {
			set_transient( 'sg_istantanea_ko', time(), self::RIPOSO );
			$completo = false;

			return array();
		}

		$voci = array();

		foreach ( (array) ( $risposta['results'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) || empty( $r['sku'] ) ) {
				continue;
			}

			$prodotto = function_exists( 'wc_get_product_id_by_sku' )
				? wc_get_product( (int) wc_get_product_id_by_sku( (string) $r['sku'] ) )
				: null;

			// Cio' che il negozio non ha piu' non si suggerisce.
			if ( ! $prodotto || 'publish' !== $prodotto->get_status() ) {
				continue;
			}

			$voci[] = self::voce_prodotto( $prodotto );
		}

		return $voci;
	}

	/**
	 * Una voce di prodotto.
	 *
	 * Porta lo SKU perche' chi disegna possa risolverlo e usare la scheda vera
	 * — foto, nome, cartellino del prezzo — invece di una riga di solo testo.
	 * Etichetta e indirizzo restano per il confronto dei doppioni e come
	 * ripiego se la scheda non si potesse costruire.
	 *
	 * @return array<string,string>
	 */
	private static function voce_prodotto( \WC_Product $p ): array {
		return array(
			'tipo'      => 'prodotto',
			'sku'       => Risolutore::sku( $p ),
			'etichetta' => $p->get_name(),
			'nota'      => html_entity_decode( wp_strip_all_tags( (string) $p->get_price_html() ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'url'       => (string) $p->get_permalink(),
		);
	}

	/**
	 * Lo stesso prodotto puo' arrivare da due strade: si tiene la prima.
	 *
	 * @param array<int,array<string,string>> $voci
	 * @return array<int,array<string,string>>
	 */
	private static function senza_doppioni( array $voci ): array {
		$viste  = array();
		$pulite = array();

		foreach ( $voci as $v ) {
			$chiave = $v['tipo'] . '|' . mb_strtolower( $v['url'] );

			if ( isset( $viste[ $chiave ] ) ) {
				continue;
			}

			$viste[ $chiave ] = true;
			$pulite[]         = $v;
		}

		return $pulite;
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
		// Le categorie di prodotto esistono solo dove esiste un negozio.
		if ( ! Negozio::c_e() || ! taxonomy_exists( 'product_cat' ) ) {
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

		// I tipi che finiscono nell'indice, e nessun altro. Vedi Negozio.
		$tipi       = Negozio::tipi_indicizzati();
		$segnaposti = implode( ',', array_fill( 0, count( $tipi ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$righe = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title
				   FROM {$wpdb->posts}
				  WHERE post_type IN ( $segnaposti )
				    AND post_status = 'publish'
				    AND ( post_title LIKE %s OR post_title LIKE %s )
			   ORDER BY CHAR_LENGTH(post_title) ASC
				  LIMIT 6",
				array_merge( $tipi, array( $primo, $dopo ) )
			)
		);
		// phpcs:enable

		if ( empty( $righe ) ) {
			return array();
		}

		$voci = array();

		foreach ( $righe as $riga ) {
			if ( Negozio::c_e() ) {
				$prodotto = wc_get_product( (int) $riga->ID );

				if ( ! $prodotto || ! $prodotto->is_visible() ) {
					continue;
				}

				$voci[] = self::voce_prodotto( $prodotto );

				continue;
			}

			$voci[] = self::voce_contenuto( (int) $riga->ID );
		}

		return array_values( array_filter( $voci ) );
	}

	/**
	 * Una voce che punta a un contenuto del sito.
	 *
	 * Al posto del prezzo si scrive di che cosa si tratta — "Pagina",
	 * "Articolo" — perche' quello e' l'unico contorno utile: chi legge un
	 * suggerimento vuole sapere dove sta per andare.
	 *
	 * @return array<string,string>|null
	 */
	private static function voce_contenuto( int $id ): ?array {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$tipo = get_post_type_object( $post->post_type );

		return array(
			'tipo'      => 'prodotto',
			'sku'       => \Storegentic\Catalogo\Contenuti::sku( $post ),
			'etichetta' => (string) get_the_title( $post ),
			'nota'      => $tipo instanceof \WP_Post_Type ? (string) $tipo->labels->singular_name : '',
			'url'       => (string) get_permalink( $post ),
		);
	}
}
