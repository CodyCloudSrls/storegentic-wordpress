<?php
/**
 * La pagina dei risultati.
 *
 * PERCHE' ESISTE. Il pannello che si apre dalla lente va bene per il colpo
 * d'occhio: si scrive, si guarda, si entra in una scheda. Non va bene per il
 * resto. Un pannello non si manda a un'amica, non torna indietro col tasto
 * del browser, non regge venti risultati da confrontare, e sparisce appena si
 * clicca. Una ricerca vera ha bisogno di un indirizzo suo.
 *
 * PERCHE' NON UNA PAGINA DI WORDPRESS. Una pagina vera si puo' cestinare, si
 * puo' rinominare, un editor ci puo' incollare dentro un blocco. Qui l'
 * indirizzo e' una regola di riscrittura del plugin: c'e' finche' il plugin
 * e' attivo, e non c'e' niente da amministrare.
 *
 * I RISULTATI SONO GIA' NEL DOCUMENTO. Chi arriva da un link condiviso trova
 * i prodotti gia' scritti nella pagina, non un cerchietto che gira. Vale per
 * chi ha JavaScript spento, per i lettori di schermo, e per la velocita'
 * percepita, che e' la differenza fra una ricerca e un'attesa.
 *
 * NON SI FA INDICIZZARE. Le pagine di risultati generate da una query sono
 * contenuto duplicato: si dichiarano `noindex`, e si lascia `follow` perche' i
 * link ai prodotti restano utili.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Api\Contratto;
use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pagina {

	/** La variabile che distingue questa pagina da tutte le altre. */
	public const VARIABILE = 'sg_ricerca';

	/**
	 * Segna quale forma hanno le regole gia' scritte.
	 *
	 * Riscrivere le regole a ogni caricamento e' una delle voci piu' costose
	 * che un plugin possa aggiungere a WordPress: ricalcola e riscrive
	 * un'opzione grande a ogni richiesta. Si fa una volta, quando la forma
	 * cambia.
	 */
	private const VERSIONE_REGOLE = 'storegentic_regole';

	public static function avvia(): void {
		add_action( 'init', array( self::class, 'regole' ) );
		add_filter( 'query_vars', array( self::class, 'variabile' ) );
		add_action( 'template_redirect', array( self::class, 'niente_404' ) );
		add_filter( 'template_include', array( self::class, 'modello' ) );
		add_filter( 'document_title_parts', array( self::class, 'titolo' ) );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'rank_math/frontend/robots', array( self::class, 'robots' ) );
		add_filter( 'the_seo_framework_robots_meta', array( self::class, 'robots_tsf' ) );
		add_filter( 'wpseo_robots_array', array( self::class, 'robots_elenco' ) );
		add_action( 'wp_head', array( self::class, 'testa' ), 999 );
	}

	/** L'indirizzo pubblico della ricerca. */
	public static function indirizzo( string $domanda = '' ): string {
		$base = home_url( '/' . self::fetta() . '/' );

		return '' === $domanda ? $base : add_query_arg( 'q', rawurlencode( $domanda ), $base );
	}

	/**
	 * La parola nell'indirizzo.
	 *
	 * Si puo' cambiare con un filtro: un negozio in un'altra lingua non deve
	 * essere costretto a un indirizzo inglese, e chi ha gia' un /deepsearch
	 * suo non deve andare in conflitto.
	 */
	public static function fetta(): string {
		$fetta = (string) apply_filters( 'storegentic_fetta_ricerca', 'deepsearch' );

		return sanitize_title( '' !== $fetta ? $fetta : 'deepsearch' );
	}

	public static function regole(): void {
		$fetta = self::fetta();

		add_rewrite_rule( '^' . preg_quote( $fetta, '#' ) . '/?$', 'index.php?' . self::VARIABILE . '=1', 'top' );

		$impronta = $fetta . '|' . \Storegentic\VERSIONE;

		if ( get_option( self::VERSIONE_REGOLE ) !== $impronta ) {
			flush_rewrite_rules( false );
			self::svuota_cache_pagine();
			update_option( self::VERSIONE_REGOLE, $impronta, false );
		}
	}

	/**
	 * Svuota la cache delle pagine dopo un aggiornamento del plugin.
	 *
	 * PERCHE' SERVE. Il plugin stampa markup e richiama fogli di stile e script
	 * con la propria versione nell'indirizzo. Chi ha una cache di pagina
	 * continua a servire l'HTML di prima, quindi l'HTML vecchio che richiama i
	 * file vecchi: il sito resta indietro finche' la cache non scade da sola.
	 * Verificato su questo hosting — LiteSpeed rispondeva `x-litespeed-cache:
	 * hit` con la versione precedente per ore dopo l'aggiornamento.
	 *
	 * Si svuota solo quando la versione cambia, non a ogni caricamento: una
	 * cache svuotata di continuo e' peggio di nessuna cache.
	 *
	 * Si chiamano i ganci che le cache piu' diffuse dichiarano, e nessuna
	 * funzione loro diretta: se il plugin non c'e', il gancio semplicemente non
	 * ascolta nessuno.
	 */
	private static function svuota_cache_pagine(): void {
		foreach ( array( 'litespeed_purge_all', 'rocket_purge_cache', 'w3tc_flush_posts', 'ce_clear_cache' ) as $gancio ) {
			do_action( $gancio );
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * @param array<int,string> $variabili
	 * @return array<int,string>
	 */
	public static function variabile( array $variabili ): array {
		$variabili[] = self::VARIABILE;
		$variabili[] = 'q';

		return $variabili;
	}

	public static function nostra(): bool {
		return '' !== (string) get_query_var( self::VARIABILE );
	}

	/**
	 * Una pagina che risponde non deve dichiararsi mancante.
	 *
	 * Senza questo, WordPress vede una richiesta che non corrisponde a nessun
	 * contenuto e imposta 404: la pagina si vedrebbe lo stesso, ma il browser
	 * e i motori riceverebbero "questa pagina non esiste".
	 */
	public static function niente_404(): void {
		if ( ! self::nostra() ) {
			return;
		}

		global $wp_query;

		$wp_query->is_404 = false;
		status_header( 200 );
	}

	public static function modello( string $modello ): string {
		if ( ! self::nostra() ) {
			return $modello;
		}

		/*
		 * Il tema puo' prendersi la pagina: basta che metta un file con questo
		 * nome. E' la convenzione di WordPress per i modelli, e vale la pena
		 * rispettarla anche qui.
		 */
		$del_tema = locate_template( array( 'storegentic-ricerca.php' ) );

		return '' !== $del_tema ? $del_tema : \Storegentic\PERCORSO . '/src/Frontend/vista-ricerca.php';
	}

	/**
	 * @param array<string,string> $parti
	 * @return array<string,string>
	 */
	public static function titolo( array $parti ): array {
		if ( ! self::nostra() ) {
			return $parti;
		}

		$domanda = self::domanda();

		$parti['title'] = '' !== $domanda
			/* translators: %s: le parole cercate. */
			? sprintf( __( 'Ricerca: %s', 'storegentic' ), $domanda )
			: __( 'Cerca nel catalogo', 'storegentic' );

		unset( $parti['tagline'] );

		return $parti;
	}

	/**
	 * Una pagina di risultati non si fa indicizzare.
	 *
	 * E' contenuto che nasce da una domanda: due domande diverse producono
	 * pagine quasi identiche, e un motore le legge come contenuto duplicato.
	 * Si lascia pero' `follow`, perche' i collegamenti ai prodotti restano
	 * utili da seguire.
	 *
	 * PERCHE' QUATTRO FILTRI. Chi ha un plugin SEO non usa l'uscita di
	 * WordPress: quel plugin la sostituisce con la propria. Aggiungendo solo un
	 * tag nostro il documento ne conteneva due, uno che diceva `noindex` e uno
	 * che non lo diceva. Si parla quindi la lingua di chi sta scrivendo quel
	 * tag, e il nostro si stampa soltanto se non l'ha scritto nessuno.
	 *
	 * @param array<string,mixed> $regole
	 * @return array<string,mixed>
	 */
	public static function robots( $regole ) {
		if ( ! self::nostra() ) {
			return $regole;
		}

		self::$robots_detti = true;

		if ( ! is_array( $regole ) ) {
			return $regole;
		}

		/*
		 * WordPress e Rank Math vogliono una mappa: la chiave e' la direttiva,
		 * il valore `true` la stampa da sola. Passando qui un elenco si
		 * otteneva "0:noindex, 1:follow", perche' con un valore non booleano
		 * WordPress stampa "chiave:valore". Le due forme non si possono
		 * distinguere guardando l'array — su un array vuoto sono la stessa
		 * cosa — quindi non si indovina: ogni filtro ha la sua funzione.
		 */
		unset( $regole['index'] );
		$regole['noindex'] = true;
		$regole['follow']  = true;

		return $regole;
	}

	/**
	 * Yoast passa un elenco di direttive gia' scritte.
	 *
	 * @param array<int,string>|mixed $regole
	 * @return array<int,string>|mixed
	 */
	public static function robots_elenco( $regole ) {
		if ( ! self::nostra() || ! is_array( $regole ) ) {
			return $regole;
		}

		self::$robots_detti = true;

		$regole = array_values(
			array_filter( $regole, static fn( $r ) => 'index' !== $r && 'noindex' !== $r && 'follow' !== $r && 'nofollow' !== $r )
		);

		array_unshift( $regole, 'noindex', 'follow' );

		return $regole;
	}

	/** The SEO Framework passa una stringa gia' composta. */
	public static function robots_tsf( $meta ) {
		if ( ! self::nostra() || ! is_array( $meta ) ) {
			return $meta;
		}

		self::$robots_detti = true;

		$meta['noindex'] = 'noindex';
		$meta['nofollow'] = '';

		return $meta;
	}

	/** Vero quando qualcun altro ha gia' scritto la regola per i motori. */
	private static bool $robots_detti = false;

	public static function testa(): void {
		if ( ! self::nostra() || self::$robots_detti ) {
			return;
		}

		echo '<meta name="robots" content="noindex,follow">' . "\n";
	}

	/** Le parole cercate, ripulite. */
	public static function domanda(): string {
		return mb_substr( trim( sanitize_text_field( (string) get_query_var( 'q' ) ) ), 0, 200 );
	}

	/**
	 * La pagina e' raggiungibile solo se la ricerca c'e' davvero.
	 *
	 * Si guarda il contratto gia' in cache e mai se ne chiede uno nuovo: qui
	 * c'e' una persona che aspetta il caricamento.
	 */
	public static function disponibile(): bool {
		return (bool) Impostazioni::leggi( 'attivo' )
			&& Impostazioni::configurato()
			&& '' !== Contratto::endpoint_in_cache( 'search' );
	}
}
