<?php
/**
 * Le statistiche che restano nel negozio.
 *
 * PERCHE' IN CASA E NON DAL SERVIZIO. Il plugin manda gia' gli eventi a
 * Storegentic (vedi Analitica\Registratore), ma il servizio non offre nessun
 * indirizzo per rileggerli: provati, il 2026-08-20, tutti quelli plausibili
 * sotto `/v1/commerce/` — analytics/summary, analytics/queries, usage, stats —
 * e rispondono 404. Gli eventi partono e non tornano.
 *
 * Quindi chi gestisce il negozio, per sapere cosa cercano i suoi clienti,
 * dovrebbe uscire da WordPress e andare da un'altra parte. Qui invece il conto
 * si tiene anche in casa: e' il plugin che fa da tramite a ogni ricerca, quindi
 * e' il plugin che sa come e' andata.
 *
 * COSA SI CONTA, E COSA NO
 *
 * Si contano le domande finite: una ricerca a parole, una ricerca con la foto,
 * una domanda all'assistente. NON si contano i suggerimenti mentre si scrive:
 * partono a ogni tasto premuto, e "coll" non e' una domanda, e' un pezzo di
 * domanda. Contarli gonfierebbe i numeri e riempirebbe l'elenco di frammenti.
 *
 * LA DOMANDA CHE VALE PIU' DI TUTTE e' l'ultima colonna: quante volte quella
 * ricerca non ha trovato niente. Un negozio non impara granche' sapendo che
 * "collana" e' cercata spesso; impara molto sapendo che "cavigliera" e'
 * cercata dodici volte e non esiste a catalogo.
 *
 * QUANTO OCCUPA. Un'opzione per mese, senza autoload, con un tetto al numero di
 * domande distinte. Restano tre mesi: il quarto si cancella da solo alla prima
 * scrittura del mese nuovo, senza cron e senza indice.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Analitica;

use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Misure {

	/** Quante domande distinte si conservano al massimo in un mese. */
	private const TETTO_DOMANDE = 400;

	/** Quanti mesi restano leggibili. */
	public const MESI = 3;

	/** Oltre questa lunghezza una domanda si tronca: nessuno cerca un tema. */
	private const LUNGHEZZA = 120;

	/**
	 * Le funzioni di cui si tiene il conto.
	 *
	 * @var array<string,string>
	 */
	public const FUNZIONI = array(
		'ricerca'    => 'Ricerca a parole',
		'foto'       => 'Ricerca con una foto',
		'assistente' => 'Assistente',
	);

	/**
	 * Registra com'e' andata una domanda.
	 *
	 * DUE DOMANDE DIVERSE, DUE CONTI DIVERSI.
	 *
	 * "Riuscite" e "vuote" guardano cosa ha visto il CLIENTE, e insieme fanno
	 * il totale delle domande. "Fallite" guarda se il SERVIZIO ha risposto, e
	 * si somma alle altre due invece di escluderle: con il ripiego acceso una
	 * domanda puo' benissimo essere fallita per il servizio e riuscita per chi
	 * cercava, perche' i risultati sono arrivati dal catalogo del negozio.
	 *
	 * Tenerli separati e' l'unico modo perche' il pannello mostri il guasto
	 * anche quando il ripiego lo sta coprendo bene. Vedi Frontend\Ripiego.
	 *
	 * @param string      $funzione Una chiave di FUNZIONI.
	 * @param string      $domanda  Il testo cercato; vuoto per la ricerca con la foto.
	 * @param int         $quanti   Quanti risultati ha visto chi cercava.
	 * @param int         $ms       Quanto ci ha messo, in millisecondi.
	 * @param string|null $errore   Il messaggio, se il servizio non ha risposto.
	 * @param int         $codice   Il codice di stato, se il servizio non ha risposto.
	 */
	public static function segna( string $funzione, string $domanda, int $quanti, int $ms, ?string $errore = null, int $codice = 0 ): void {
		if ( ! isset( self::FUNZIONI[ $funzione ] ) || ! Impostazioni::leggi( 'statistiche' ) ) {
			return;
		}

		$mese = self::mese();
		$dati = self::leggi( $mese );

		$f = $dati['funzioni'][ $funzione ] ?? self::funzione_vuota();

		++$f['chiamate'];

		/*
		 * IL TEMPO SI SOMMA SOLO QUANDO C'E' STATA UNA CHIAMATA VERA.
		 *
		 * Una risposta presa dalla cache arriva in zero millisecondi, ed e'
		 * comunque una ricerca fatta da una persona: va contata fra le chiamate
		 * e fra le domande. Se pero' entrasse anche nella media dei tempi, quella
		 * media direbbe quanto e' piena la cache invece di quanto e' lento il
		 * servizio — cioe' il contrario di quello che si vuole sapere.
		 */
		if ( $ms > 0 ) {
			$f['ms'] += $ms;
			++$f['ms_conta'];
		}

		if ( null !== $errore ) {
			++$f['fallite'];

			/*
			 * Si tiene l'ULTIMO errore e non il primo. Un errore di tre
			 * settimane fa in cima al pannello fa credere che il guasto sia
			 * quello, quando intanto ne e' arrivato un altro.
			 */
			$f['ultimo_errore'] = array(
				'quando'    => time(),
				'codice'    => $codice,
				'messaggio' => mb_substr( $errore, 0, 200 ),
			);
		}

		if ( $quanti > 0 ) {
			++$f['riuscite'];
		} else {
			++$f['vuote'];
		}

		$dati['funzioni'][ $funzione ] = $f;

		/*
		 * La domanda si scrive SEMPRE, anche quando il servizio e' caduto.
		 *
		 * Prima si scriveva solo in caso di successo, e l'effetto era che
		 * durante un guasto la tabella "cosa cercano" restava vuota: si perdeva
		 * proprio il periodo in cui sapere cosa cercavano i clienti serve di
		 * piu'. Quello che l'errore non deve sporcare e' l'altra colonna —
		 * "a vuoto" — e infatti quella dipende da $quanti, cioe' da cosa ha
		 * visto il cliente, ripiego compreso.
		 */
		$dati = self::conta_domanda( $dati, $domanda, $quanti );

		self::scrivi( $mese, $dati );
	}

	/**
	 * Registra che qualcuno ha aperto un risultato.
	 *
	 * E' il segnale che dice se la ricerca ha funzionato davvero: dieci
	 * risultati che nessuno apre valgono meno di due che si aprono sempre.
	 */
	public static function segna_apertura( string $sku ): void {
		$sku = trim( $sku );

		if ( '' === $sku || ! Impostazioni::leggi( 'statistiche' ) ) {
			return;
		}

		$mese = self::mese();
		$dati = self::leggi( $mese );

		$sku = mb_substr( $sku, 0, 64 );

		// Stesso tetto delle domande, stessa ragione: l'opzione non deve crescere senza fine.
		if ( ! isset( $dati['aperti'][ $sku ] ) && count( $dati['aperti'] ) >= self::TETTO_DOMANDE ) {
			return;
		}

		$dati['aperti'][ $sku ] = (int) ( $dati['aperti'][ $sku ] ?? 0 ) + 1;

		self::scrivi( $mese, $dati );
	}

	/**
	 * Somma una domanda al conto del mese.
	 *
	 * @param array<string,mixed> $dati
	 * @return array<string,mixed>
	 */
	private static function conta_domanda( array $dati, string $domanda, int $quanti ): array {
		$testo = self::normalizza( $domanda );

		if ( '' === $testo ) {
			return $dati;
		}

		/*
		 * UNA CASELLA DI RICERCA RICEVE ANCHE COSE CHE NON SONO RICERCHE.
		 *
		 * Capita che ci finisca dentro un indirizzo di posta o un numero di
		 * telefono, per errore o per abitudine. Quel testo qui non serve a
		 * niente — non insegna cosa manca a catalogo — e conservarlo
		 * significherebbe tenere un dato personale in un'opzione di WordPress
		 * senza che nessuno l'abbia chiesto. Si contano, per non far sparire una
		 * riga dal totale, ma il testo non si scrive.
		 */
		if ( preg_match( '/[^\s@]@[^\s@]+\.[a-z]{2,}/i', $testo ) || preg_match( '/\d{8,}/', $testo ) ) {
			$dati['riservate'] = (int) ( $dati['riservate'] ?? 0 ) + 1;

			return $dati;
		}

		if ( ! isset( $dati['domande'][ $testo ] ) ) {
			$dati = self::fai_spazio( $dati );

			if ( count( $dati['domande'] ) >= self::TETTO_DOMANDE ) {
				$dati['scartate'] = (int) ( $dati['scartate'] ?? 0 ) + 1;

				return $dati;
			}

			$dati['domande'][ $testo ] = array( 'quante' => 0, 'senza' => 0 );
		}

		++$dati['domande'][ $testo ]['quante'];

		if ( 0 === $quanti ) {
			++$dati['domande'][ $testo ]['senza'];
		}

		return $dati;
	}

	/**
	 * Quando l'elenco e' pieno si buttano le domande cercate una volta sola.
	 *
	 * PERCHE' PROPRIO QUELLE. Un elenco pieno va sfoltito in qualche modo, e
	 * ogni criterio perde qualcosa. Buttare le meno frequenti tiene cio' che
	 * conta davvero — le domande che tornano — e sacrifica la coda lunga, che
	 * e' fatta di errori di battitura e di domande uniche. Buttare le piu'
	 * vecchie farebbe il contrario, e cancellerebbe proprio la domanda ricorrente
	 * che si vorrebbe scoprire.
	 *
	 * Quante ne sono state buttate resta scritto: il pannello lo dice, invece
	 * di far credere che l'elenco sia completo.
	 *
	 * @param array<string,mixed> $dati
	 * @return array<string,mixed>
	 */
	private static function fai_spazio( array $dati ): array {
		if ( count( $dati['domande'] ) < self::TETTO_DOMANDE ) {
			return $dati;
		}

		$tenute = array();
		$buttate = 0;

		foreach ( $dati['domande'] as $testo => $voce ) {
			if ( (int) $voce['quante'] > 1 ) {
				$tenute[ $testo ] = $voce;
			} else {
				++$buttate;
			}
		}

		if ( 0 === $buttate ) {
			return $dati; // Tutte ricorrenti: non si butta niente, si smette di aggiungere.
		}

		$dati['domande']  = $tenute;
		$dati['scartate'] = (int) ( $dati['scartate'] ?? 0 ) + $buttate;

		return $dati;
	}

	/**
	 * La stessa domanda scritta in modi diversi e' una domanda sola.
	 *
	 * "Collana Perle", "collana  perle" e "COLLANA PERLE " sono la stessa cosa
	 * per chi legge il pannello, e tenerle su tre righe diverse renderebbe
	 * l'elenco inservibile proprio dove serve: nel capire cosa si cerca spesso.
	 */
	private static function normalizza( string $domanda ): string {
		$pulito = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $domanda ) ) );

		return mb_substr( mb_strtolower( $pulito, 'UTF-8' ), 0, self::LUNGHEZZA );
	}

	/* --------------------------------------------------------- lettura */

	/**
	 * Il riepilogo di un mese, pronto da mostrare.
	 *
	 * @param string|null $mese Formato "2026_08"; null per il mese in corso.
	 * @return array<string,mixed>
	 */
	public static function riepilogo( ?string $mese = null ): array {
		$mese = $mese ?? self::mese();
		$dati = self::leggi( $mese );

		$funzioni = array();

		foreach ( self::FUNZIONI as $chiave => $nome ) {
			$f = $dati['funzioni'][ $chiave ] ?? self::funzione_vuota();

			$funzioni[ $chiave ] = array(
				'nome'          => $nome,
				'chiamate'      => (int) $f['chiamate'],
				'riuscite'      => (int) $f['riuscite'],
				'vuote'         => (int) $f['vuote'],
				'fallite'       => (int) $f['fallite'],
				// Media e non mediana: la somma e' l'unico dato che si tiene, e
				// tenere ogni singolo tempo per calcolare una mediana costerebbe
				// piu' spazio di quanto valga la precisione in piu'.
				'ms_medio'      => (int) ( $f['ms_conta'] ?? 0 ) > 0 ? (int) round( $f['ms'] / (int) $f['ms_conta'] ) : 0,
				'ultimo_errore' => $f['ultimo_errore'] ?? null,
			);
		}

		$domande = $dati['domande'];

		uasort( $domande, static fn( $a, $b ) => $b['quante'] <=> $a['quante'] );

		$senza = array_filter( $domande, static fn( $v ) => (int) $v['senza'] > 0 );

		uasort( $senza, static fn( $a, $b ) => $b['senza'] <=> $a['senza'] );

		$aperti = $dati['aperti'];

		arsort( $aperti );

		return array(
			'mese'      => $mese,
			'funzioni'  => $funzioni,
			'cercate'   => array_slice( $domande, 0, 20, true ),
			'senza'     => array_slice( $senza, 0, 20, true ),
			'aperti'    => array_slice( $aperti, 0, 10, true ),
			'distinte'  => count( $dati['domande'] ),
			'scartate'  => (int) ( $dati['scartate'] ?? 0 ),
			'riservate' => (int) ( $dati['riservate'] ?? 0 ),
		);
	}

	/**
	 * I mesi che hanno qualcosa da mostrare, dal piu' recente.
	 *
	 * @return array<int,string>
	 */
	public static function mesi(): array {
		$mesi = array();

		foreach ( self::finestra() as $mese ) {
			$dati = get_option( self::opzione( $mese ), null );

			if ( is_array( $dati ) ) {
				$mesi[] = $mese;
			}
		}

		return $mesi;
	}

	/** Cancella tutto il conto tenuto in casa. */
	public static function azzera(): void {
		foreach ( self::finestra() as $mese ) {
			delete_option( self::opzione( $mese ) );
		}
	}

	/* ------------------------------------------------------- magazzino */

	/**
	 * @return array<string,mixed>
	 */
	private static function leggi( string $mese ): array {
		$dati = get_option( self::opzione( $mese ), array() );

		if ( ! is_array( $dati ) ) {
			$dati = array();
		}

		return array(
			'funzioni' => is_array( $dati['funzioni'] ?? null ) ? $dati['funzioni'] : array(),
			'domande'  => is_array( $dati['domande'] ?? null ) ? $dati['domande'] : array(),
			'aperti'   => is_array( $dati['aperti'] ?? null ) ? $dati['aperti'] : array(),
			'scartate' => (int) ( $dati['scartate'] ?? 0 ),
			'riservate' => (int) ( $dati['riservate'] ?? 0 ),
		);
	}

	/**
	 * @param array<string,mixed> $dati
	 */
	private static function scrivi( string $mese, array $dati ): void {
		update_option( self::opzione( $mese ), $dati, false );

		/*
		 * Il mese piu' vecchio della finestra se ne va qui, alla prima scrittura
		 * del mese nuovo. Non serve un cron ne' un indice dei mesi scritti:
		 * cancellare un'opzione che non c'e' non costa nulla e non fa danno.
		 */
		delete_option( self::opzione( self::mese( -self::MESI ) ) );
	}

	/** @return array<string,mixed> */
	private static function funzione_vuota(): array {
		return array( 'chiamate' => 0, 'riuscite' => 0, 'vuote' => 0, 'fallite' => 0, 'ms' => 0, 'ms_conta' => 0 );
	}

	private static function opzione( string $mese ): string {
		return \Storegentic\PREFISSO_OPZIONI . 'misure_' . $mese;
	}

	/**
	 * Il nome del mese, eventualmente spostato indietro.
	 *
	 * Si usa l'ora del sito e non UTC: chi legge il pannello ragiona sul proprio
	 * calendario, e una ricerca fatta alle 23:30 del 31 deve stare nel mese in
	 * cui e' stata fatta secondo chi l'ha fatta.
	 *
	 * SI PARTE SEMPRE DAL PRIMO DEL MESE, e non da oggi.
	 *
	 * "Un mese fa" a partire dal 31 maggio, in PHP, fa 1 maggio: il 31 aprile
	 * non esiste e la data trabocca in avanti. Misurato: partendo dal 31/05
	 * l'elenco dei mesi conservati veniva fuori 05, 05, 03, 03 — il mese in
	 * corso contato due volte, aprile sparito, e febbraio mai cancellato.
	 * Il difetto si sarebbe visto solo negli ultimi giorni di certi mesi, che
	 * e' il modo migliore per non trovarlo mai in prova.
	 *
	 * Il primo del mese esiste sempre, in ogni mese: partendo da li' lo
	 * scorrimento e' esatto per costruzione.
	 */
	private static function mese( int $scarto = 0 ): string {
		return self::mese_da( (int) current_datetime()->getTimestamp(), $scarto );
	}

	/**
	 * Lo stesso conto, a partire da un istante qualunque.
	 *
	 * L'istante e' un parametro e non una lettura dell'orologio perche' questo
	 * calcolo sbaglia solo negli ultimi giorni di certi mesi: senza poter
	 * scegliere il giorno, una prova eseguita il 20 non vedrebbe mai il
	 * difetto. Vedi collaudo/misure.php.
	 *
	 * Il mezzogiorno serve a stare lontani dai fusi: l'ora del sito puo'
	 * scostarsi da UTC fino a dodici ore indietro e quattordici avanti, e a
	 * mezzanotte quello scostamento farebbe cadere il "primo del mese" nel mese
	 * prima.
	 */
	private static function mese_da( int $quando, int $scarto ): string {
		$primo = (int) strtotime( wp_date( 'Y-m-01', $quando ) . ' 12:00:00' );

		return wp_date( 'Y_m', (int) strtotime( $scarto . ' months', $primo ) );
	}

	/**
	 * I mesi conservati, dal piu' recente.
	 *
	 * @param int|null $quando L'istante da cui contare; null vuol dire adesso.
	 * @return array<int,string>
	 */
	public static function finestra( ?int $quando = null ): array {
		$quando = $quando ?? (int) current_datetime()->getTimestamp();
		$mesi   = array();

		for ( $i = 0; $i < self::MESI; $i++ ) {
			$mesi[] = self::mese_da( $quando, -$i );
		}

		return $mesi;
	}
}
