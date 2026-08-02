<?php
/**
 * Quali prodotti nomina una risposta dell'assistente.
 *
 * IL PROBLEMA. Sotto una risposta si mostrano le schede dei prodotti che la
 * risposta consiglia. Sapere quali sono non e' ovvio: il servizio restituisce
 * un elenco di "fonti", ma quelle sono i documenti che ha letto, non cio' che
 * ha consigliato. Misurato sulle dieci domande di collaudo, il servizio manda
 * ZERO fonti su tre di esse — e in una di quelle la risposta nomina cinque
 * parure con il titolo esatto. Ancorarsi alle fonti significa quindi perdere
 * un terzo dei casi, e mostrare prodotti diversi da quelli nominati negli
 * altri: su una prova la risposta proponeva un bracciale da 20 €, mentre le
 * fonti erano tre anelli da 69 e 98 €.
 *
 * L'ANCORA E' IL TESTO. Misurato sui dieci casi: il titolo del prodotto
 * compare per intero, contiguo e in ordine, nel testo della risposta in 33
 * citazioni su 35. Gli indirizzi dei prodotti dentro i collegamenti ne
 * coprono 7. Le due citazioni che sfuggono a entrambi i canali chiedono una
 * sola deformazione ciascuna, e sono le uniche due che si osservano davvero:
 *
 *   - una parola vuota aggiunta da chi scrive: la risposta scrive "Orecchini
 *     lunghi CON perla grigia", il catalogo ha "Orecchini lunghi perla
 *     grigia";
 *   - una lettera sbagliata: la risposta scrive "Collana perle Maiorca
 *     colorate" e il catalogo ha "Colana". Qui il refuso e' del catalogo, non
 *     dell'assistente.
 *
 * PERCHE' NON SI TOLLERA DI PIU'. Due deformazioni non sono piu' una
 * riscrittura: sono un altro prodotto. Questo catalogo contiene
 * "Bracciale in acciaio inox placcato oro con cuori sacri grandi",
 * "Bracciale in acciaio inox con cuori sacri grandi" e
 * "Bracciale in acciaio inox con cuori grandi placcati oro": tre prodotti
 * distinti, stesso prezzo, che differiscono per la posizione di due parole. Un
 * confronto a insieme di parole, o tollerante al riordino, li mostrerebbe
 * tutti e tre quando la risposta ne nomina uno.
 *
 * UN FALSO POSITIVO PESA PIU' DI UN FALSO NEGATIVO. Una scheda sbagliata sotto
 * una risposta mette le parole e le figure in contraddizione nella stessa
 * schermata, e chi legge non sa piu' a quale credere. Una scheda mancante e'
 * solo un'occasione persa. Ogni regola qui sotto, nel dubbio, tace.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Citazioni {

	/** Quanti prodotti si possono leggere dal catalogo per una risposta. */
	private const TETTO_CANDIDATI = 400;

	/** Sotto questa lunghezza una parola non tollera refusi. */
	private const LETTERE_PER_REFUSO = 5;

	/**
	 * Le parole che chi scrive aggiunge senza cambiare il senso.
	 *
	 * Elenco corto e chiuso di proposito: piu' e' lungo, piu' due prodotti
	 * diversi diventano lo stesso prodotto.
	 */
	private const VUOTE = array(
		'con', 'di', 'in', 'e', 'per', 'il', 'la', 'le', 'lo', 'i', 'gli',
		'un', 'una', 'da', 'a', 'del', 'della', 'dei', 'delle', 'al', 'alla',
	);

	/**
	 * Gli identificativi dei prodotti che la risposta nomina, in ordine.
	 *
	 * @param array<string,array<string,mixed>> $fonti Le fonti del servizio: qui
	 *                                                 servono solo a sciogliere
	 *                                                 gli omonimi, mai a
	 *                                                 introdurre un prodotto.
	 * @return array<int,int>
	 */
	public static function trova( string $risposta, array $fonti = array() ): array {
		$trovati = array();

		/*
		 * Primo canale: gli indirizzi dei prodotti dentro i collegamenti. Un
		 * indirizzo e' una chiave esatta, non una somiglianza: non puo'
		 * sbagliare prodotto. Vale poco da solo — 7 citazioni su 35 — ma e' la
		 * rete di sicurezza il giorno in cui l'assistente smettesse di
		 * ricopiare i titoli.
		 */
		foreach ( self::da_indirizzi( $risposta ) as $posizione => $id ) {
			$trovati[ $posizione ] = $id;
		}

		/*
		 * Gli indirizzi si cancellano prima di cercare i titoli, perche' anche
		 * un indirizzo e' fatto di parole. Trovato dal collaudo: la riga
		 * "guarda https://altrosito.example/prodotto/collana-zaffiro-in-acciaio-brunito/"
		 * contiene, una volta tolti i trattini, il titolo per intero — e faceva
		 * uscire la scheda di un prodotto che nessuno aveva nominato, citato da
		 * un sito che non e' il nostro. L'unico canale che ha diritto di leggere
		 * un indirizzo e' quello sopra, che lo tratta come chiave e ne verifica
		 * il dominio.
		 *
		 * Si sostituisce con spazi della stessa lunghezza e non con niente: le
		 * posizioni servono a rimettere le schede nell'ordine del testo.
		 */
		$parole = self::parole( self::senza_indirizzi( $risposta ) );

		if ( empty( $parole ) ) {
			ksort( $trovati );

			return array_values( $trovati );
		}

		// Secondo canale: i titoli del catalogo, cercati dentro il testo.
		foreach ( self::da_titoli( $parole, $fonti ) as $posizione => $id ) {
			if ( ! in_array( $id, $trovati, true ) ) {
				$trovati[ $posizione ] = $id;
			}
		}

		ksort( $trovati );

		return array_values( $trovati );
	}

	/**
	 * Le schede pronte, come le vuole il browser.
	 *
	 * NESSUN TETTO QUI DENTRO. Quanti risultati mostrare e' una decisione di
	 * vetrina, e tenerla qui la faceva sembrare un limite del riconoscimento:
	 * a una domanda sulle parure la risposta ne nominava dieci e se ne
	 * vedevano sei, con quattro citazioni vere scartate da un array_slice.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function schede( string $risposta, array $fonti = array() ): array {
		$grezzi = array();

		foreach ( self::trova( $risposta, $fonti ) as $id ) {
			$prodotto = wc_get_product( $id );

			if ( $prodotto instanceof WC_Product ) {
				$grezzi[] = array( 'sku' => Risolutore::sku( $prodotto ) );
			}
		}

		return Risolutore::schede( $grezzi );
	}

	/* ------------------------------------------------- il primo canale */

	/**
	 * I prodotti raggiunti da un collegamento dentro il testo.
	 *
	 * @return array<int,int> posizione nel testo => identificativo
	 */
	private static function da_indirizzi( string $risposta ): array {
		$casa = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! preg_match_all( '#https?://([^/\s]+)(/[^\s)\]]*)#u', $risposta, $trovate, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$esito = array();

		foreach ( $trovate[2] as $i => $pezzo ) {
			// Un indirizzo di un altro sito non parla dei nostri prodotti.
			if ( ( $trovate[1][ $i ][0] ?? '' ) !== $casa ) {
				continue;
			}

			$lumaca = self::lumaca( (string) $pezzo[0] );

			if ( '' === $lumaca ) {
				continue;
			}

			$pagina = get_page_by_path( $lumaca, OBJECT, 'product' );

			if ( $pagina && 'publish' === $pagina->post_status ) {
				$esito[ (int) $pezzo[1] ] = (int) $pagina->ID;
			}
		}

		return $esito;
	}

	/** Lo stesso testo, con gli indirizzi sbiancati e le posizioni intatte. */
	private static function senza_indirizzi( string $testo ): string {
		return (string) preg_replace_callback(
			'#https?://[^\s)\]]+#u',
			static fn( array $p ): string => str_repeat( ' ', strlen( $p[0] ) ),
			$testo
		);
	}

	/** L'ultimo segmento non vuoto di un percorso. */
	private static function lumaca( string $percorso ): string {
		$pezzi = array_values( array_filter( explode( '/', (string) wp_parse_url( $percorso, PHP_URL_PATH ) ) ) );

		return empty( $pezzi ) ? '' : sanitize_title( (string) end( $pezzi ) );
	}

	/* ------------------------------------------------ il secondo canale */

	/**
	 * I prodotti il cui titolo compare nel testo.
	 *
	 * @param array<int,array{p:string,o:int}>  $parole
	 * @param array<string,array<string,mixed>> $fonti
	 * @return array<int,int> posizione nel testo => identificativo
	 */
	private static function da_titoli( array $parole, array $fonti ): array {
		$candidati = self::candidati( $parole );

		if ( empty( $candidati ) ) {
			return array();
		}

		$combaciano = array();

		foreach ( $candidati as $titolo => $ids ) {
			$cercate = self::parole( (string) $titolo );

			// Un titolo di una parola sola non identifica niente.
			if ( count( $cercate ) < 2 ) {
				continue;
			}

			$dove = self::allinea( $parole, $cercate );

			if ( null === $dove ) {
				continue;
			}

			$combaciano[] = array(
				'da'      => $dove['da'],
				'a'       => $dove['a'],
				'esatto'  => $dove['esatto'],
				'lunghe'  => count( $cercate ),
				'ids'     => $ids,
				'offset'  => $parole[ $dove['da'] ]['o'],
			);
		}

		/*
		 * LA REGOLA DI CHIUSURA: il nome deve finire dove finisce il titolo.
		 *
		 * La regola del piu' lungo protegge solo quando il nome piu' lungo
		 * esiste davvero in catalogo. Quando l'assistente ne INVENTA uno —
		 * "Bracciale in acciaio inox con cuori sacri grandi placcato oro", che
		 * mette insieme due prodotti veri — dentro ci sta per intero il titolo
		 * di uno dei due, e senza questa regola si mostrava quello.
		 *
		 * Si guarda che cosa viene subito dopo. Punteggiatura o fine del testo
		 * vogliono dire che il nome e' finito li'. Se invece prosegue con una
		 * parola che nel catalogo compare nei titoli, la risposta stava
		 * nominando qualcos'altro, e si tace.
		 */
		$vocabolario = self::vocabolario( $candidati );

		$combaciano = array_values(
			array_filter(
				$combaciano,
				static function ( array $c ) use ( $parole, $vocabolario ): bool {
					if ( ! empty( $parole[ $c['a'] ]['chiusa'] ) ) {
						return true;
					}

					$dopo = $parole[ $c['a'] + 1 ]['p'] ?? '';

					// Una parola vuota da sola non dice che il titolo continua.
					if ( '' === $dopo || in_array( $dopo, self::VUOTE, true ) ) {
						return true;
					}

					return ! isset( $vocabolario[ $dopo ] );
				}
			)
		);

		/*
		 * Il piu' lungo vince, e a parita' vince quello senza deformazioni.
		 *
		 * Serve contro i titoli che sono il principio di un altro titolo:
		 * "Collana con zirconi in acciaio inox" sta dentro "Collana con zirconi
		 * in acciaio inox placcato oro". Se la risposta nomina il secondo, il
		 * primo combacia lo stesso, e senza questo ordine si mostrerebbe il
		 * prodotto sbagliato. Serve anche contro i gemelli con refuso: quando
		 * nel testo c'e' "elastico", il prodotto scritto "elastico" batte
		 * quello scritto "elestico".
		 */
		usort(
			$combaciano,
			static function ( array $a, array $b ): int {
				return array( $b['lunghe'], $b['esatto'] ) <=> array( $a['lunghe'], $a['esatto'] );
			}
		);

		$esito    = array();
		$occupate = array();

		foreach ( $combaciano as $c ) {
			/*
			 * Un tratto di testo nomina un prodotto solo. Il tratto resta
			 * occupato anche quando la citazione si scarta perche' ambigua:
			 * altrimenti il secondo classificato — quasi sempre un gemello —
			 * si prenderebbe il posto del prodotto vero.
			 */
			if ( self::gia_occupato( $occupate, $c['da'], $c['a'] ) ) {
				continue;
			}

			$occupate[] = array( $c['da'], $c['a'] );

			$id = self::scegli( $c['ids'], $fonti );

			// Titolo condiviso da piu' prodotti e nessun modo di distinguerli.
			if ( 0 === $id ) {
				continue;
			}

			$esito[ $c['offset'] ] = $id;
		}

		return $esito;
	}

	/**
	 * Le parole che compaiono nei titoli letti dal catalogo.
	 *
	 * Si ricava dai candidati gia' in memoria, non da una query in piu': serve
	 * solo a rispondere alla domanda "questa parola potrebbe far parte di un
	 * titolo?", e per quella bastano i titoli che stiamo confrontando.
	 *
	 * @param array<string,array<int,int>> $candidati
	 * @return array<string,true>
	 */
	private static function vocabolario( array $candidati ): array {
		$parole = array();

		foreach ( array_keys( $candidati ) as $titolo ) {
			foreach ( self::parole( (string) $titolo ) as $p ) {
				if ( ! in_array( $p['p'], self::VUOTE, true ) ) {
					$parole[ $p['p'] ] = true;
				}
			}
		}

		return $parole;
	}

	/**
	 * @param array<int,array{0:int,1:int}> $occupate
	 */
	private static function gia_occupato( array $occupate, int $da, int $a ): bool {
		foreach ( $occupate as $o ) {
			if ( $da <= $o[1] && $a >= $o[0] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * I prodotti che vale la pena confrontare, letti dal catalogo.
	 *
	 * PERCHE' UNA QUERY E NON UN INDICE IN MEMORIA. Un indice di tutti i titoli
	 * si tiene in cache e si invalida a ogni salvataggio di prodotto: e' molto
	 * codice, e sbagliarne l'invalidazione significa mostrare per giorni un
	 * prodotto che non c'e' piu'. Qui si legge dal database una volta per
	 * risposta — e una risposta dell'assistente costa gia' venti-trentacinque
	 * secondi di attesa sul servizio. Una lettura in piu' su quella scala non
	 * si misura nemmeno.
	 *
	 * Si cercano solo i titoli che cominciano come una parola del testo, con
	 * tre lettere sole: tre e non di piu' perche' il gruppo deve reggere i
	 * refusi — "Col" tiene insieme "Collana" e "Colana".
	 *
	 * @param array<int,array{p:string,o:int}> $parole
	 * @return array<string,array<int,int>> titolo normalizzato => identificativi
	 */
	private static function candidati( array $parole ): array {
		global $wpdb;

		$inizi = array();

		foreach ( $parole as $parola ) {
			$p = $parola['p'];

			if ( mb_strlen( $p ) < 4 || in_array( $p, self::VUOTE, true ) ) {
				continue;
			}

			$inizi[ mb_substr( $p, 0, 3 ) ] = true;
		}

		if ( empty( $inizi ) ) {
			return array();
		}

		$pezzi = array();
		$dati  = array();

		foreach ( array_keys( $inizi ) as $inizio ) {
			$pezzi[] = 'post_title LIKE %s';
			$dati[]  = $wpdb->esc_like( $inizio ) . '%';
		}

		$dati[] = self::TETTO_CANDIDATI;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$righe = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ID, post_title FROM ' . $wpdb->posts . "
				  WHERE post_type = 'product' AND post_status = 'publish'
				    AND ( " . implode( ' OR ', $pezzi ) . ' )
			   ORDER BY CHAR_LENGTH(post_title) DESC
				  LIMIT %d',
				$dati
			)
		);
		// phpcs:enable

		$per_titolo = array();

		foreach ( (array) $righe as $riga ) {
			$titolo = (string) $riga->post_title;

			// I titoli si ripetono: nove titoli su ventitre prodotti, qui.
			$per_titolo[ $titolo ][] = (int) $riga->ID;
		}

		return $per_titolo;
	}

	/**
	 * Fra piu' prodotti con lo stesso titolo, quale intendeva la risposta.
	 *
	 * Se non c'e' modo di saperlo si torna zero e non si mostra nulla: due
	 * gioielli diversi con lo stesso nome sono il caso in cui una scheda
	 * sbagliata e' piu' probabile che utile.
	 *
	 * @param array<int,int>                    $ids
	 * @param array<string,array<string,mixed>> $fonti
	 */
	private static function scegli( array $ids, array $fonti ): int {
		$ids = array_values( array_unique( $ids ) );

		if ( 1 === count( $ids ) ) {
			return $ids[0];
		}

		if ( empty( $ids ) ) {
			return 0;
		}

		/*
		 * QUI, E SOLO QUI, SERVONO LE FONTI. Non introducono mai un prodotto:
		 * sciolgono un dubbio fra prodotti che il testo ha gia' nominato.
		 */
		foreach ( $ids as $id ) {
			$prodotto = wc_get_product( $id );

			if ( $prodotto instanceof WC_Product && isset( $fonti[ Risolutore::sku( $prodotto ) ] ) ) {
				return $id;
			}
		}

		return 0;
	}

	/* ----------------------------------------------------- il confronto */

	/**
	 * Dove il titolo compare nel testo, se compare.
	 *
	 * Si scorre il testo e da ogni punto in cui la prima parola del titolo
	 * coincide si prova a stendere il titolo intero, in ordine e senza salti.
	 * E' ammessa UNA sola deformazione su tutto il nome, di due tipi soltanto:
	 * una parola vuota aggiunta dal testo, oppure una lettera sbagliata su una
	 * parola abbastanza lunga da non confondersi con un'altra.
	 *
	 * @param array<int,array{p:string,o:int}> $testo
	 * @param array<int,array{p:string,o:int}> $titolo
	 * @return array{da:int,a:int,esatto:int}|null
	 */
	private static function allinea( array $testo, array $titolo ): ?array {
		$quante = count( $testo );
		$prima  = $titolo[0]['p'];

		for ( $inizio = 0; $inizio < $quante; $inizio++ ) {
			if ( ! self::stessa_parola( $testo[ $inizio ]['p'], $prima ) ) {
				continue;
			}

			$dove = self::stendi( $testo, $titolo, $inizio );

			if ( null !== $dove ) {
				return $dove;
			}
		}

		return null;
	}

	/**
	 * @param array<int,array{p:string,o:int}> $testo
	 * @param array<int,array{p:string,o:int}> $titolo
	 * @return array{da:int,a:int,esatto:int}|null
	 */
	private static function stendi( array $testo, array $titolo, int $inizio ): ?array {
		$i          = $inizio;
		$quante     = count( $testo );
		$deformato  = false;

		foreach ( $titolo as $n => $cercata ) {
			if ( $i >= $quante ) {
				return null;
			}

			$qui = $testo[ $i ]['p'];

			if ( $qui === $cercata['p'] ) {
				++$i;
				continue;
			}

			// Deformazione a: il testo ha aggiunto una parola vuota.
			if ( ! $deformato && $n > 0 && in_array( $qui, self::VUOTE, true )
				&& isset( $testo[ $i + 1 ] ) && $testo[ $i + 1 ]['p'] === $cercata['p'] ) {
				$deformato = true;
				$i        += 2;
				continue;
			}

			// Deformazione b: una lettera sbagliata, di qua o di la'.
			if ( ! $deformato && self::quasi( $qui, $cercata['p'] ) ) {
				$deformato = true;
				++$i;
				continue;
			}

			return null;
		}

		return array( 'da' => $inizio, 'a' => $i - 1, 'esatto' => $deformato ? 0 : 1 );
	}

	private static function stessa_parola( string $a, string $b ): bool {
		return $a === $b || self::quasi( $a, $b );
	}

	/** Due parole che differiscono per una lettera sola. */
	private static function quasi( string $a, string $b ): bool {
		if ( $a === $b ) {
			return true;
		}

		// Sui numeri non si tollera nulla: "18 kt" e "14 kt" sono due cose.
		if ( is_numeric( $a ) || is_numeric( $b ) ) {
			return false;
		}

		if ( mb_strlen( $a ) < self::LETTERE_PER_REFUSO || mb_strlen( $b ) < self::LETTERE_PER_REFUSO ) {
			return false;
		}

		return 1 === levenshtein( $a, $b );
	}

	/* ------------------------------------------------------ il testo */

	/**
	 * Il testo ridotto a parole confrontabili, con la posizione di ciascuna.
	 *
	 * La posizione serve a rimettere le schede nell'ordine in cui la risposta
	 * le nomina: un elenco che a schermo cambia ordine rispetto al testo
	 * sopra si legge come un errore.
	 *
	 * @return array<int,array{p:string,o:int}>
	 */
	private static function parole( string $testo ): array {
		$piatto = strtolower( remove_accents( $testo ) );

		if ( ! preg_match_all( '/[a-z0-9]+/u', $piatto, $trovate, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$parole = array();
		$quante = count( $trovate[0] );

		foreach ( $trovate[0] as $i => $t ) {
			$parola = (string) $t[0];
			$da     = (int) $t[1];
			$dopo   = $da + strlen( $parola );

			/*
			 * Serve sapere se dopo la parola arriva punteggiatura o fine del
			 * testo: e' il segno che un nome di prodotto e' finito li'. Vedi
			 * la regola di chiusura in da_titoli().
			 */
			$seguito = $i + 1 < $quante ? substr( $piatto, $dopo, ( (int) $trovate[0][ $i + 1 ][1] ) - $dopo ) : '';
			$chiusa  = $i + 1 >= $quante || '' !== trim( str_replace( ' ', '', $seguito ) );

			$parole[] = array( 'p' => $parola, 'o' => $da, 'chiusa' => $chiusa );
		}

		return $parole;
	}
}
