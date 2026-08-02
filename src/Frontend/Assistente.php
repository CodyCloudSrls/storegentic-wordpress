<?php
/**
 * L'assistente: la risposta mentre si scrive.
 *
 * PERCHE' UN PROXY E NON UNA CHIAMATA DIRETTA. La chiave del negozio autorizza
 * a leggere e a scrivere il catalogo. Nel browser sarebbe leggibile da
 * chiunque. Qui il browser parla con WordPress, WordPress parla con
 * Storegentic.
 *
 * PERCHE' SI RISCRIVE IL FLUSSO. Il servizio manda, a ogni pezzo di testo,
 * anche tutte le fonti che ha consultato: dodici prodotti interi, ripetuti
 * identici a ogni pezzo. Misurato su una domanda sola: 1.037.534 byte, di cui
 * 388 caratteri di risposta. Inoltrarlo cosi' com'e' vorrebbe dire spedire un
 * megabyte a un telefono per tre righe di testo.
 *
 * Il ponte legge quel flusso, tiene il testo e gli SKU, e ne manda al browser
 * una versione essenziale: i pezzi di testo mentre arrivano, e una sola volta
 * l'elenco dei prodotti, risolto sul catalogo del negozio.
 *
 * PERCHE' L'ASSISTENTE NON RICORDA DA SOLO. L'indirizzo dichiarato dal
 * contratto accetta `message`, `chatMode` e `attachments`: nessun
 * identificativo di conversazione. Il filo del discorso lo tiene il browser,
 * e viene rimandato a ogni domanda dentro il messaggio. E' un limite del
 * servizio, non una scelta: si dichiara qui invece di far finta di niente.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Api\Client;
use Storegentic\Api\Contratto;
use Storegentic\Analitica\Registratore;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assistente {

	/** Quante domande e risposte si rimandano indietro come contesto. */
	private const MEMORIA = 6;

	/** Quanti caratteri al massimo occupa il contesto rimandato. */
	private const MEMORIA_CARATTERI = 2000;

	/** Quanto si aspetta una risposta prima di rinunciare, in secondi. */
	private const ATTESA = 60;

	/**
	 * Risponde in streaming e termina il processo.
	 *
	 * NON restituisce una risposta REST. Il formato degli eventi del server
	 * richiede di scrivere sul canale mentre la risposta si forma, mentre
	 * l'API REST di WordPress costruisce un corpo intero e lo serve alla fine.
	 * Si prendono quindi in mano le intestazioni e si chiude con `exit`:
	 * WordPress non arriva mai a scrivere il proprio corpo.
	 *
	 * @return never
	 */
	public static function rispondi( WP_REST_Request $richiesta ) {
		$domanda = trim( (string) $richiesta->get_param( 'messaggio' ) );

		self::apri_canale();

		if ( '' === $domanda ) {
			self::manda( array( 'errore' => __( 'Scrivi una domanda.', 'storegentic' ) ) );
			exit;
		}

		$indirizzo = Contratto::endpoint( 'agentChat' );

		if ( '' === $indirizzo || ! Contratto::puo( 'agentChat' ) ) {
			self::manda( array( 'errore' => __( 'L’assistente non è disponibile su questo negozio.', 'storegentic' ) ) );
			exit;
		}

		$risposta = '';
		$fonti    = array();

		$errore = ( new Client( null, null, self::ATTESA, 0 ) )->flusso(
			$indirizzo,
			array(
				'message'  => self::messaggio( $domanda, (array) $richiesta->get_param( 'storia' ) ),
				'chatMode' => 'query',
			),
			static function ( array $evento ) use ( &$risposta, &$fonti ): bool {
				$tipo = (string) ( $evento['type'] ?? '' );

				if ( 'abort' === $tipo || ! empty( $evento['error'] ) ) {
					self::manda( array( 'errore' => __( 'La risposta si è interrotta. Riprova.', 'storegentic' ) ) );

					return false;
				}

				/*
				 * Il testo arriva a pezzi, non ripetuto: si inoltra ogni pezzo
				 * appena arriva, che e' tutto il punto dello streaming.
				 */
				$pezzo = (string) ( $evento['textResponse'] ?? '' );

				if ( '' !== $pezzo ) {
					$risposta .= $pezzo;
					self::manda( array( 'testo' => $pezzo ) );
				}

				/*
				 * Le fonti si raccolgono, non si mandano: servono alla fine,
				 * quando la risposta e' completa e si puo' vedere quali
				 * prodotti nomina davvero. Sono anche la parte pesante del
				 * flusso — 74 kB ripetuti a ogni pezzo — ed e' quella che qui
				 * non attraversa mai la rete verso il browser.
				 */
				self::raccogli( $evento, $fonti );

				if ( ! empty( $evento['close'] ) || 'finalizeResponseStream' === $tipo ) {
					return false;
				}

				// Chi guarda ha chiuso la pagina: si smette di consumare quota.
				return ! connection_aborted();
			}
		);

		$testo_totale = mb_strlen( $risposta );

		if ( null !== $errore && 0 === $testo_totale ) {
			self::manda( array( 'errore' => $errore->get_error_message() ) );
		}

		/*
		 * La risposta impaginata arriva alla fine, e prende il posto del testo
		 * grezzo mostrato durante l'attesa.
		 *
		 * PERCHE' NON SI IMPAGINA PEZZO PER PEZZO. Un pezzo puo' tagliare in
		 * due un asterisco doppio o un collegamento, e impaginare meta' segno
		 * produce spazzatura a schermo. Si impagina quando la frase e' intera.
		 * Il testo continua a partire a pezzi, cosi' il giorno in cui il
		 * servizio streamera' davvero si vedra' comparire mentre si scrive.
		 */
		if ( '' !== trim( $risposta ) ) {
			self::manda( array( 'html' => self::impagina( $risposta ) ) );
		}

		/*
		 * Le schede dei prodotti che la risposta nomina. Il riconoscimento sta
		 * in Frontend\Citazioni; le fonti si passano soltanto perche' servono a
		 * sciogliere gli omonimi, e non introducono mai un prodotto da sole.
		 *
		 * Nessun tetto: quante schede mostrare e' una decisione di vetrina, e
		 * tenerla qui faceva sparire citazioni vere. A una domanda sulle parure
		 * la risposta ne nominava dieci e se ne vedevano sei.
		 */
		$citati = Scheda::con_html( Citazioni::schede( $risposta, $fonti ), 'riga' );

		if ( ! empty( $citati ) ) {
			self::manda( array( 'prodotti' => $citati ) );
		}

		Registratore::accoda( 'agent_chat', array( 'data' => array( 'query' => mb_substr( $domanda, 0, 200 ) ) ) );
		Registratore::accoda(
			'agent_results',
			array( 'data' => array( 'characters' => $testo_totale, 'products' => count( $citati ) ) )
		);

		self::manda( array( 'fine' => true ) );
		exit;
	}

	/**
	 * Raccoglie le fonti di un evento, senza doppioni.
	 *
	 * Il campo `commerceResults` previsto dal contratto arriva sempre vuoto su
	 * questo servizio; i prodotti stanno in `sources`, con SKU e indirizzo.
	 * Si leggono entrambi: se un domani il primo si popola, funziona lo stesso.
	 *
	 * @param array<string,mixed>            $evento
	 * @param array<string,array<string,mixed>> $fonti Si riempie qui dentro.
	 */
	private static function raccogli( array $evento, array &$fonti ): void {
		/*
		 * `productCards` e' il paniere piu' ricco che il servizio dichiari, e
		 * NON e' l'elenco dei consigli. Misurato su sei domande: contiene tutti
		 * e ventuno i prodotti che le risposte nominano — recall perfetto — ma
		 * ne porta sessantanove in tutto, cioe' il settanta per cento di
		 * rumore. Alla domanda "un regalo sotto i 50 euro" ci sono dentro un
		 * bracciale da 159 € e due anelli da 69 €: mostrarli sotto quel testo
		 * sarebbe la contraddizione che si vuole evitare.
		 *
		 * Entra quindi qui, dove i candidati servono soltanto a sciogliere gli
		 * omonimi, e non nell'esito. Vale piu' delle fonti perche' e' piu'
		 * ampio e perche' porta lo SKU, che e' la chiave.
		 */
		foreach ( array( 'productCards', 'commerceResults' ) as $campo ) {
			foreach ( (array) ( $evento[ $campo ] ?? array() ) as $r ) {
				if ( is_array( $r ) && ! empty( $r['sku'] ) ) {
					$fonti[ (string) $r['sku'] ] = $r;
				}
			}
		}

		foreach ( (array) ( $evento['sources'] ?? array() ) as $f ) {
			if ( ! is_array( $f ) || empty( $f['sku'] ) ) {
				continue;
			}

			$sku = (string) $f['sku'];

			if ( isset( $fonti[ $sku ] ) ) {
				continue;
			}

			$fonti[ $sku ] = array(
				'sku'   => $sku,
				'name'  => (string) ( $f['title'] ?? '' ),
				'url'   => (string) ( $f['url'] ?? '' ),
				'score' => $f['score'] ?? null,
			);
		}
	}

	/**
	 * La risposta, impaginata.
	 *
	 * PERCHE' SERVE. L'assistente scrive in Markdown: elenchi numerati,
	 * grassetti, collegamenti, e — su questo catalogo — anche le figure dei
	 * prodotti. Il browser inserisce il testo con textContent, quindi quei
	 * segni si vedevano tali e quali: righe come
	 * "![Immagine](https://www.onilli.it/wp-content/uploads/…​.jpg)" stampate
	 * per intero dentro la risposta.
	 *
	 * PERCHE' NON SI USA UNA LIBRERIA. Qui non si impagina un documento: si
	 * impagina la frase di un servizio esterno, cioe' testo di cui non ci si
	 * fida. Un convertitore generico accetta HTML dentro il Markdown, e da li'
	 * passa qualunque cosa. Si fa il contrario: prima si mette al sicuro TUTTO
	 * il testo, poi si riaccendono cinque segni sulla stringa gia' sicura, e
	 * alla fine si passa comunque da wp_kses con un elenco chiuso di tag. Tre
	 * sbarramenti, nessun tag che non sia stato scritto qui dentro.
	 */
	private static function impagina( string $grezzo ): string {
		/*
		 * Le figure si tolgono. Le foto dei prodotti stanno gia' nelle schede
		 * sotto la risposta: ripeterle dentro il testo raddoppia il peso della
		 * pagina e sposta le righe in basso mentre si caricano.
		 */
		$testo = self::sostituisci( '/!\[[^\]]*\]\([^)\s]*\)/u', '', $grezzo );

		/*
		 * E con le figure se ne va anche il marcatore che le annunciava.
		 *
		 * Successo davvero: l'assistente elencava le opzioni come "1. <figura>",
		 * e tolta la figura restava la riga "1." da sola. Quella riga non e' piu'
		 * una voce di elenco — le manca il contenuto — quindi il blocco intero
		 * smetteva di essere riconosciuto come elenco e precipitava a capoverso,
		 * portandosi dietro le voci vere. A schermo restava un buco fra la frase
		 * d'apertura e quella di chiusura.
		 */
		$testo = self::sostituisci( '/^[ \t]*(?:[-*•]|\d+[.)])[ \t]*$/mu', '', $testo );

		// Da qui in poi non esiste piu' HTML: esiste solo testo.
		$testo = esc_html( $testo );

		$fuori = array();

		foreach ( preg_split( '/\R{2,}/u', trim( $testo ) ) ?: array() as $blocco ) {
			$blocco = trim( $blocco );

			if ( '' === $blocco ) {
				continue;
			}

			$fatto = self::blocco( $blocco );

			if ( '' !== $fatto ) {
				$fuori[] = $fatto;
			}
		}

		/*
		 * Due elenchi attaccati sono un elenco solo. L'assistente separa le voci
		 * numerate con una riga vuota, e ogni voce diventava quindi un elenco a
		 * se': a schermo la numerazione ripartiva da 1 a ogni riga.
		 */
		$html = implode( "\n", $fuori );
		$html = self::sostituisci( '#</(ol|ul)>\s*<\1>#u', '', $html );

		/*
		 * Una risposta fatta di sole figure non lascia nulla da leggere. Meglio
		 * dirlo con una stringa vuota — chi disegna toglie la bolla e restano le
		 * schede — che mostrare un rettangolo vuoto.
		 */
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return '';
		}

		return wp_kses(
			$html,
			array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'a'      => array( 'href' => array(), 'rel' => array() ),
			)
		);
	}

	/**
	 * Una sostituzione che, se fallisce, non distrugge il testo.
	 *
	 * `preg_replace` con il modificatore `u` torna `null` davanti a un byte che
	 * non e' UTF-8 valido, e un `(string) null` vale stringa vuota: un solo
	 * carattere malformato in arrivo dal servizio avrebbe cancellato l'intera
	 * risposta invece di lasciarla imperfetta. Qui, se la sostituzione non
	 * riesce, si tiene il testo di partenza.
	 */
	private static function sostituisci( string $modello, string $con, string $testo ): string {
		$esito = preg_replace( $modello, $con, $testo );

		return null === $esito ? $testo : $esito;
	}

	/** Un blocco separato da riga vuota: elenco o capoverso. */
	private static function blocco( string $blocco ): string {
		$righe = array_values(
			array_filter(
				array_map( 'trim', preg_split( '/\R/u', $blocco ) ?: array() ),
				static fn( $r ) => '' !== $r
			)
		);

		if ( empty( $righe ) ) {
			return '';
		}

		$puntato  = true;
		$numerato = true;

		foreach ( $righe as $riga ) {
			if ( ! preg_match( '/^[-*•]\s+\S/u', $riga ) ) {
				$puntato = false;
			}

			if ( ! preg_match( '/^\d+[.)]\s+\S/u', $riga ) ) {
				$numerato = false;
			}
		}

		if ( $puntato || $numerato ) {
			$voci = array();

			foreach ( $righe as $riga ) {
				$contenuto = self::segni( self::sostituisci( '/^([-*•]|\d+[.)])\s+/u', '', $riga ) );

				// Una voce senza contenuto non e' una voce: non si stampa.
				if ( '' === trim( wp_strip_all_tags( $contenuto ) ) ) {
					continue;
				}

				$voci[] = '<li>' . $contenuto . '</li>';
			}

			if ( empty( $voci ) ) {
				return '';
			}

			$tag = $puntato ? 'ul' : 'ol';

			return '<' . $tag . '>' . implode( '', $voci ) . '</' . $tag . '>';
		}

		/*
		 * Un a capo singolo dentro un capoverso e' un a capo voluto: l'assistente
		 * lo usa per spezzare un elenco che non ha marcato come tale.
		 */
		$capoverso = self::segni( implode( '<br>', $righe ) );

		return '' === trim( wp_strip_all_tags( $capoverso ) ) ? '' : '<p>' . $capoverso . '</p>';
	}

	/**
	 * I cinque segni che si riaccendono, sul testo gia' messo al sicuro.
	 *
	 * I collegamenti valgono solo se portano a questo sito. Un indirizzo
	 * scritto da un modello puo' portare ovunque, e un negozio non manda i
	 * propri clienti dove capita: se l'indirizzo e' altrove resta il testo,
	 * senza il collegamento.
	 */
	private static function segni( string $testo ): string {
		$nostro = wp_parse_url( home_url(), PHP_URL_HOST );

		$testo = (string) preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u',
			static function ( array $pezzi ) use ( $nostro ): string {
				$url = html_entity_decode( $pezzi[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				if ( wp_parse_url( $url, PHP_URL_HOST ) !== $nostro ) {
					return $pezzi[1];
				}

				return '<a href="' . esc_url( $url ) . '" rel="nofollow">' . $pezzi[1] . '</a>';
			},
			$testo
		);

		// Grassetto e corsivo. Il corsivo solo con l'underscore: l'asterisco
		// singolo compare troppo spesso da solo per essere un segno affidabile.
		$testo = (string) preg_replace( '/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $testo );
		$testo = (string) preg_replace( '/(?<![\w_])_([^_]+)_(?![\w_])/u', '<em>$1</em>', $testo );

		/*
		 * I cancelletti dei titoli si tolgono a ogni riga, non solo alla prima:
		 * dentro un capoverso le righe sono gia' unite da <br>, e un ancoraggio
		 * al solo inizio della stringa ne avrebbe ripulita una su tre.
		 */
		$testo = (string) preg_replace( '/(^|<br>)\s*#{1,6}\s+/u', '$1', $testo );

		return $testo;
	}

	/**
	 * La domanda, con quel tanto di conversazione che serve a capirla.
	 *
	 * Senza contesto "e in argento?" e' una domanda senza senso. Il contesto
	 * si tiene corto di proposito: e' testo che il servizio deve rileggere a
	 * ogni giro, e una conversazione lunga finirebbe per costare piu' della
	 * risposta.
	 *
	 * @param array<int,mixed> $storia
	 */
	private static function messaggio( string $domanda, array $storia ): string {
		$righe = array();

		foreach ( array_slice( $storia, -self::MEMORIA ) as $turno ) {
			if ( ! is_array( $turno ) ) {
				continue;
			}

			$chi   = 'assistente' === (string) ( $turno['chi'] ?? '' ) ? 'Assistente' : 'Cliente';
			$testo = trim( wp_strip_all_tags( (string) ( $turno['testo'] ?? '' ) ) );

			if ( '' !== $testo ) {
				$righe[] = $chi . ': ' . mb_substr( $testo, 0, 400 );
			}
		}

		if ( empty( $righe ) ) {
			return $domanda;
		}

		$contesto = mb_substr( implode( "\n", $righe ), -self::MEMORIA_CARATTERI );

		return "Conversazione fin qui:\n" . $contesto . "\n\nNuova domanda del cliente: " . $domanda;
	}

	/**
	 * Prepara il canale a una risposta che arriva a pezzi.
	 *
	 * Ogni strato fra PHP e il browser tende ad accumulare l'uscita per
	 * spedirla in un colpo solo: e' la cosa giusta per una pagina, e la cosa
	 * sbagliata qui, perche' il testo arriverebbe tutto insieme alla fine.
	 * Le intestazioni sotto sono i modi di dire "non accumulare" ai server
	 * piu' diffusi; quella di LiteSpeed serve su questo hosting.
	 */
	private static function apri_canale(): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Connection: keep-alive' );
			header( 'X-Accel-Buffering: no' );
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}

		// Se il visitatore chiude, PHP deve accorgersene e fermarsi.
		ignore_user_abort( false );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( self::ATTESA + 15 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}

	/**
	 * @param array<string,mixed> $dati
	 */
	private static function manda( array $dati ): void {
		echo 'data: ' . wp_json_encode( $dati ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		flush();
	}
}
