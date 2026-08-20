<?php
/**
 * La ricerca del negozio, per quando il servizio non risponde.
 *
 * PERCHE' SERVE. Il 2026-08-20 il piano di questo negozio ha finito le
 * ricerche comprese: il servizio ha cominciato a rispondere `429 Search quota
 * exceeded` a ogni domanda, e chi cercava una collana sul sito riceveva un
 * messaggio d'errore. Non un risultato peggiore: nessun risultato. Un negozio
 * che smette di far trovare i propri prodotti perche' un servizio esterno ha
 * un contatore a zero e' un negozio chiuso, e la colpa la prende il negozio.
 *
 * Puo' succedere per tre motivi diversi, e nessuno dei tre e' raro: la quota
 * del piano finisce, il servizio va giu' per manutenzione, la rete
 * dell'hosting fa i capricci. In tutti e tre i casi il catalogo e' li', intero,
 * nel database di WooCommerce.
 *
 * COSA FA, E COSA NON PRETENDE DI FARE. Questa non e' una ricerca semantica e
 * non finge di esserlo: cerca le parole nei titoli e nelle descrizioni brevi,
 * e mette davanti i prodotti che ne contengono di piu'. "Collana con pietre
 * verdi" trova le collane con la parola "verde" scritta da qualche parte, non
 * quelle con lo smeraldo. E' meno di quello che sa fare Storegentic, ed e'
 * infinitamente piu' di una schermata d'errore.
 *
 * SI DICE SEMPRE. L'esito porta `ripiego => true` e l'interfaccia lo scrive:
 * scambiare di nascosto una ricerca semantica con una ricerca per parole
 * vorrebbe dire far passare per rotta la funzione buona — "ho cercato pietre
 * verdi e mi ha dato spazzatura" — mentre e' solo spenta.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ripiego {

	/** Quante righe si guardano prima di ordinare. */
	private const CANDIDATI = 60;

	/** Sotto queste lettere una parola non discrimina niente. */
	private const LETTERE = 3;

	/**
	 * Parole che in italiano compaiono ovunque.
	 *
	 * Senza questo elenco "collana con pietre verdi" cercherebbe anche "con",
	 * che sta dentro "conchiglia", "contorno" e mezzo catalogo: il punteggio
	 * finirebbe dominato dalla parola che non significa nulla.
	 *
	 * @var array<int,string>
	 */
	private const VUOTE = array(
		'con', 'per', 'del', 'della', 'delle', 'degli', 'dei', 'dal', 'dalla',
		'una', 'uno', 'gli', 'che', 'non', 'più', 'piu', 'sono', 'come', 'nel',
		'nella', 'alla', 'allo', 'agli', 'sul', 'sulla', 'tra', 'fra', 'ogni',
	);

	/**
	 * I prodotti del negozio che rispondono a quelle parole.
	 *
	 * La forma e' la stessa che torna dalla ricerca del servizio, cosi'
	 * l'interfaccia non ha un secondo modo di disegnare i risultati.
	 *
	 * @return array<string,mixed>
	 */
	public static function cerca( string $domanda, int $quanti ): array {
		$parole = self::parole( $domanda );
		$schede = empty( $parole ) ? array() : Risolutore::schede( self::trova( $parole, $quanti ) );

		return array(
			'domanda'   => $domanda,
			'risultati' => $schede,
			'categorie' => array(),
			'tempoMs'   => 0,
			'daCache'   => false,
			'ripiego'   => true,
		);
	}

	/**
	 * Le parole che vale la pena cercare.
	 *
	 * @return array<int,string>
	 */
	private static function parole( string $domanda ): array {
		$pezzi = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( trim( $domanda ), 'UTF-8' ) );

		if ( ! is_array( $pezzi ) ) {
			return array();
		}

		$parole = array();

		foreach ( $pezzi as $p ) {
			if ( mb_strlen( $p ) >= self::LETTERE && ! in_array( $p, self::VUOTE, true ) ) {
				$parole[ $p ] = true;
			}
		}

		/*
		 * Piu' di cinque parole non aggiungono precisione e allungano la query:
		 * chi scrive una frase lunga la scrive attorno a due o tre parole che
		 * contano, e quelle stanno quasi sempre all'inizio.
		 */
		return array_map( array( self::class, 'radice' ), array_slice( array_keys( $parole ), 0, 5 ) );
	}

	/**
	 * La parola senza la vocale finale, quando toglierla e' sicuro.
	 *
	 * IL PLURALE ITALIANO E' IL MOTIVO PER CUI ESISTE QUESTA RIGA. Chi cerca
	 * "orecchini di perle" non trova "Orecchini con perla grigia", perche'
	 * "perle" e "perla" sono due stringhe diverse. Lo stesso vale per
	 * collana/collane, anello/anelli, verde/verdi: in un catalogo di gioielli
	 * e' la meta' delle ricerche.
	 *
	 * Si taglia solo l'ultima lettera, e solo se e' una vocale e la parola ne
	 * ha almeno cinque. Sotto le cinque il taglio fa danno: "oro" diventerebbe
	 * "or" e pescherebbe "ora", "orologio", "lavoro". Non e' un'analisi
	 * grammaticale, e non pretende di esserlo: e' la regola che copre il caso
	 * frequente senza aprire la porta ai falsi.
	 */
	private static function radice( string $parola ): string {
		return mb_strlen( $parola ) >= 5 && in_array( mb_substr( $parola, -1 ), array( 'a', 'e', 'i', 'o' ), true )
			? mb_substr( $parola, 0, -1 )
			: $parola;
	}

	/**
	 * @param array<int,string> $parole
	 * @return array<int,array<string,string>>
	 */
	private static function trova( array $parole, int $quanti ): array {
		global $wpdb;

		$dove   = array();
		$valori = array();

		foreach ( $parole as $parola ) {
			$come = '%' . $wpdb->esc_like( $parola ) . '%';

			$dove[]   = '(post_title LIKE %s OR post_excerpt LIKE %s)';
			$valori[] = $come;
			$valori[] = $come;
		}

		$valori[] = self::CANDIDATI;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$righe = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_excerpt
				   FROM {$wpdb->posts}
				  WHERE post_type = 'product'
				    AND post_status = 'publish'
				    AND ( " . implode( ' OR ', $dove ) . " )
			   ORDER BY CHAR_LENGTH(post_title) ASC
				  LIMIT %d",
				$valori
			)
		);
		// phpcs:enable

		if ( empty( $righe ) ) {
			return array();
		}

		return self::ordina( $righe, $parole, $quanti );
	}

	/**
	 * Prima i prodotti che contengono piu' parole della domanda.
	 *
	 * DUE PESI, E TUTTI E DUE SERVONO.
	 *
	 * Il primo e' dove sta la parola: nel titolo vale tre, nella descrizione
	 * uno. Una parola nel nome del prodotto dice cos'e' quel prodotto; la
	 * stessa parola in fondo a una descrizione dice molto meno.
	 *
	 * Il secondo e' la posizione nella domanda: la prima parola pesa piu'
	 * dell'ultima. In italiano l'oggetto si nomina per primo — "orecchini di
	 * perle" sono orecchini — e senza questo peso le due parole valevano
	 * uguale: a "orecchini di perle" il ripiego rispondeva con tre collane,
	 * perche' contenevano "perle" e nessun orecchino conteneva tutte e due.
	 *
	 * @param array<int,object>  $righe
	 * @param array<int,string>  $parole
	 * @return array<int,array<string,string>>
	 */
	private static function ordina( array $righe, array $parole, int $quanti ): array {
		$pesate = array();
		$quante = count( $parole );

		foreach ( $righe as $riga ) {
			$titolo = mb_strtolower( (string) $riga->post_title, 'UTF-8' );
			$corpo  = mb_strtolower( (string) $riga->post_excerpt, 'UTF-8' );
			$punti  = 0;

			foreach ( $parole as $posto => $parola ) {
				$peso = $quante - $posto;

				if ( str_contains( $titolo, $parola ) ) {
					$punti += 3 * $peso;
				} elseif ( str_contains( $corpo, $parola ) ) {
					$punti += $peso;
				}
			}

			$pesate[] = array( 'id' => (int) $riga->ID, 'punti' => $punti );
		}

		usort( $pesate, static fn( $a, $b ) => $b['punti'] <=> $a['punti'] );

		$risultati = array();

		foreach ( $pesate as $voce ) {
			if ( count( $risultati ) >= $quanti ) {
				break;
			}

			$prodotto = wc_get_product( $voce['id'] );

			// Cio' che il catalogo non mostra non si mostra nemmeno qui.
			if ( ! $prodotto || ! $prodotto->is_visible() ) {
				continue;
			}

			$risultati[] = array( 'sku' => Risolutore::sku( $prodotto ) );
		}

		return $risultati;
	}
}
