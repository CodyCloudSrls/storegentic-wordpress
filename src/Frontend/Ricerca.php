<?php
/**
 * La ricerca, in un posto solo.
 *
 * Tre porte portano alla stessa stanza: il pannello che si apre dalla lente,
 * la pagina dei risultati, e l'assistente quando cita dei prodotti. Se ognuna
 * chiamasse il servizio a modo suo, i tre elenchi mostrerebbero prezzi
 * calcolati in modo diverso e categorie ripulite in modo diverso.
 *
 * Qui sta l'unica implementazione. Chi la usa passa una domanda — parole, una
 * foto, o tutte e due — e riceve sempre la stessa forma.
 *
 * TESTO E FOTO INSIEME. Il contratto dichiara tre indirizzi: uno unificato,
 * uno solo testo, uno solo immagine. L'unificato accetta entrambi nella stessa
 * chiamata, e questo abilita la domanda che conta davvero in gioielleria:
 * "come questa foto, ma in argento". Si usa quello quando c'e' una foto; per
 * il solo testo si usa l'indirizzo dedicato, che il contratto indica come
 * quello con la resa migliore.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Analitica\Misure;
use Storegentic\Api\Client;
use Storegentic\Api\Contratto;
use Storegentic\Api\Parametri;
use Storegentic\Impostazioni;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ricerca {

	/**
	 * Quanti risultati chiedere quando l'utente puo' affinare.
	 *
	 * Il servizio non filtra per categoria, prezzo o disponibilita': accetta
	 * solo la domanda e quanti risultati si vogliono. I filtri della pagina
	 * lavorano quindi sull'insieme gia' ricevuto. Chiederne pochi vorrebbe
	 * dire che il primo filtro svuota la pagina; il tetto del contratto e' 50.
	 */
	public const AMPIO = 48;

	/** Quanti ne bastano nel pannello rapido. */
	public const RAPIDO = 12;

	/** Il peso massimo accettato per una foto, in byte, gia' decodificata. */
	public const FOTO_MASSIMA = 4194304;

	/**
	 * Quanto si conserva la risposta del servizio.
	 *
	 * Misurato su questo negozio: ogni ricerca a parole costa otto secondi
	 * pieni, sempre, sia che se ne chiedano otto risultati sia quarantotto, e
	 * il servizio non conserva nulla fra una chiamata e l'altra. Senza questa
	 * cache due persone che cercano "collana di perle" nello stesso pomeriggio
	 * aspettano otto secondi a testa e consumano due chiamate di quota.
	 *
	 * Un quarto d'ora e' scelto in modo che la stessa persona che torna
	 * indietro, riprova, o condivide il link non ripaghi l'attesa, e che il
	 * catalogo non resti indietro piu' di tanto.
	 */
	private const DURATA_CACHE = 900;

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function testo( string $domanda, int $quanti = self::RAPIDO ) {
		$domanda = trim( $domanda );

		if ( '' === $domanda ) {
			return new WP_Error( 'storegentic_domanda_vuota', __( 'Scrivi che cosa cerchi.', 'storegentic' ), array( 'status' => 400 ) );
		}

		return self::chiama(
			self::indirizzo( 'text' ),
			array( 'query' => $domanda ),
			$quanti,
			$domanda,
			'ricerca'
		);
	}

	/**
	 * @param string      $immagine Base64, con o senza intestazione `data:`.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function foto( string $immagine, string $mime = '', string $domanda = '', int $quanti = self::AMPIO ) {
		$immagine = self::solo_base64( $immagine );

		if ( '' === $immagine ) {
			return new WP_Error( 'storegentic_foto_illeggibile', __( 'La foto non è leggibile. Riprova con un altro file.', 'storegentic' ), array( 'status' => 400 ) );
		}

		$carico = array( 'imageBase64' => $immagine );

		if ( '' !== $mime ) {
			$carico['mimeType'] = $mime;
		}

		$domanda = trim( $domanda );

		/*
		 * Con le parole si passa all'indirizzo unificato, l'unico che accetta
		 * i due segnali insieme.
		 */
		if ( '' !== $domanda ) {
			$carico['query'] = $domanda;
			return self::chiama( self::indirizzo( 'unified' ), $carico, $quanti, $domanda, 'foto' );
		}

		return self::chiama( self::indirizzo( 'image' ), $carico, $quanti, '', 'foto' );
	}

	/**
	 * L'indirizzo giusto per la modalita', con ripiego sull'unificato.
	 *
	 * I nomi sono quelli che il contratto dichiara davvero, verificati
	 * sull'handshake e non supposti: `search` e' l'indirizzo unificato,
	 * `imageSearch` quello dedicato alle foto. Il contratto ne dichiara un
	 * terzo, `searchLegacyText`, che il nome stesso indica come superato: non
	 * si usa.
	 *
	 * L'ordine e' una scala di ripiego. Un contratto che dichiara solo
	 * l'unificato resta servito: le foto passano di li', perche' quell'
	 * indirizzo accetta anche `imageBase64`.
	 */
	private static function indirizzo( string $modalita ): string {
		$mappa = array(
			'text'    => array( 'search' ),
			'image'   => array( 'imageSearch', 'search' ),
			'unified' => array( 'search', 'imageSearch' ),
		);

		foreach ( $mappa[ $modalita ] ?? array() as $nome ) {
			$indirizzo = Contratto::endpoint( $nome );

			if ( '' !== $indirizzo ) {
				return $indirizzo;
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $carico
	 * @param string              $funzione Come si chiama questa chiamata nelle misure.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function chiama( string $indirizzo, array $carico, int $quanti, string $domanda, string $funzione ) {
		if ( '' === $indirizzo ) {
			return self::fallita(
				$funzione,
				$domanda,
				$quanti,
				0,
				new WP_Error(
					'storegentic_ricerca_assente',
					__( 'La ricerca non è disponibile per questo negozio.', 'storegentic' ),
					array( 'status' => 503 )
				)
			);
		}

		/*
		 * IL TETTO LO DICE IL CONTRATTO, NON IL PLUGIN.
		 *
		 * Prima qui c'era `min( 50, ... )`, scritto a mano. Cinquanta e' il tetto
		 * che questo servizio dichiara oggi: il giorno che lo alza, ogni
		 * installazione resta ferma a cinquanta finche' qualcuno non aggiorna il
		 * plugin, e il giorno che lo abbassa le richieste cominciano a essere
		 * rifiutate. Vedi Api\Parametri.
		 *
		 * La soglia e gli altri parametri arrivano dalle impostazioni, e si
		 * mandano solo se qualcuno li ha scelti davvero.
		 */
		$modo = isset( $carico['imageBase64'] ) && ! isset( $carico['query'] ) ? 'image' : 'text';

		/*
		 * Il valore scelto nelle impostazioni fa da TETTO, non da valore fisso.
		 *
		 * Chi chiama chiede quanti gliene servono, e ha le sue ragioni: il
		 * pannello rapido ne vuole dodici perche' ci stanno, la pagina dei
		 * risultati quarantotto perche' li filtra. Un valore fisso romperebbe la
		 * seconda. Un tetto invece rispetta tutti e due: chi ne vuole meno di
		 * quanti ne chiede il pannello li ottiene ovunque.
		 */
		$scelti = (int) Impostazioni::leggi( 'image' === $modo ? 'quanti_foto' : 'quanti' );

		if ( $scelti > 0 ) {
			$quanti = min( $quanti, $scelti );
		}

		$carico['topK'] = max( 1, min( Parametri::quanti_al_massimo( $modo ), $quanti ) );

		$carico += Parametri::per(
			$modo,
			0,
			Impostazioni::leggi( 'image' === $modo ? 'soglia_foto' : 'soglia' )
		);

		$impronta = self::impronta( $indirizzo, $carico );
		$in_cache = get_transient( $impronta );

		if ( is_array( $in_cache ) ) {
			$esito = self::componi( $in_cache, $domanda, true );

			// Zero millisecondi: e' una ricerca vera, ma non ha misurato il servizio.
			Misure::segna( $funzione, $domanda, count( $esito['risultati'] ), 0 );

			return $esito;
		}

		/*
		 * Un solo tentativo e attesa corta: c'e' una persona che guarda lo
		 * schermo. I ritentativi con attesa crescente vanno bene nel cron, non
		 * qui, dove tenevano occupato un processo di PHP per quasi un minuto.
		 *
		 * L'attesa massima e' larga di proposito. Misurato su questo negozio,
		 * il servizio impiega otto secondi tondi per qualunque ricerca a
		 * parole, e il tempo non dipende da quanti risultati si chiedono:
		 * e' un costo fisso suo. Con un timeout di dieci secondi si sarebbe
		 * stati a due secondi dal fallimento a ogni ricerca.
		 */
		$attesa   = isset( $carico['imageBase64'] ) ? 30 : 20;
		$client   = new Client( null, null, $attesa, 0 );
		$partito  = microtime( true );
		$risposta = $client->post( $indirizzo, $carico );
		$ms       = (int) round( ( microtime( true ) - $partito ) * 1000 );

		if ( is_wp_error( $risposta ) ) {
			$stato = (int) ( $risposta->get_error_data()['stato'] ?? 502 );
			$risposta->add_data( array( 'status' => $stato >= 400 ? $stato : 502 ) );

			return self::fallita( $funzione, $domanda, $quanti, $ms, $risposta );
		}

		$magro = array(
			'results'    => array_map(
				array( Risolutore::class, 'essenziale' ),
				array_filter( (array) ( $risposta['results'] ?? array() ), 'is_array' )
			),
			'categories' => (array) ( $risposta['categories'] ?? $risposta['topCategories'] ?? array() ),
			'tookMs'     => (int) ( $risposta['tookMs'] ?? 0 ),
		);

		set_transient( $impronta, $magro, self::DURATA_CACHE );

		$esito = self::componi( $magro, $domanda, false );

		Misure::segna( $funzione, $domanda, count( $esito['risultati'] ), $ms );

		return $esito;
	}

	/**
	 * Il servizio non ha risposto: si segna, e si prova con il catalogo in casa.
	 *
	 * PERCHE' NON SI RESTITUISCE E BASTA L'ERRORE. Il messaggio che arriva dal
	 * servizio e' scritto per chi sviluppa, non per chi compra: "Search quota
	 * exceeded", in inglese, sulla vetrina di un negozio italiano. E soprattutto
	 * lascia a mani vuote una persona che stava cercando di comprare qualcosa,
	 * mentre il prodotto che cercava e' nel catalogo, a un metro di distanza.
	 *
	 * Il ripiego si puo' spegnere dalle impostazioni: c'e' chi preferisce dire
	 * chiaramente che la ricerca intelligente e' ferma, invece di offrire una
	 * ricerca per parole che potrebbe far pensare che funzioni male.
	 *
	 * L'errore si segna SEMPRE, anche quando il ripiego riesce: e' l'unico modo
	 * perche' nel pannello si veda che il servizio e' fermo. Un ripiego che
	 * funziona bene nasconderebbe il guasto per settimane.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private static function fallita( string $funzione, string $domanda, int $quanti, int $ms, WP_Error $errore ) {
		/*
		 * Senza parole non c'e' ripiego possibile: una ricerca per foto non si
		 * traduce in una query sul database del negozio. In quel caso l'errore
		 * torna com'e', ed e' giusto cosi'.
		 */
		$esito = '' !== trim( $domanda ) && Impostazioni::leggi( 'ripiego' )
			? Ripiego::cerca( $domanda, $quanti )
			: null;

		// Un ripiego che non trova niente non aiuta: meglio dire cosa e' successo.
		$riuscito = null !== $esito && ! empty( $esito['risultati'] );

		/*
		 * Si segna una volta sola, e DOPO aver provato il ripiego: cosi' il
		 * conteggio dei risultati e' quello che ha visto il cliente davvero.
		 * L'errore si segna comunque, anche quando il ripiego ha rimediato,
		 * altrimenti un guasto coperto bene resterebbe invisibile nel pannello
		 * per tutto il tempo in cui dura.
		 */
		Misure::segna(
			$funzione,
			$domanda,
			$riuscito ? count( $esito['risultati'] ) : 0,
			$ms,
			$errore->get_error_message(),
			(int) ( $errore->get_error_data()['status'] ?? 0 )
		);

		return $riuscito ? $esito : $errore;
	}

	/**
	 * Da risposta del servizio a esito per chi disegna.
	 *
	 * La risoluzione sul catalogo avviene sempre, anche quando la risposta
	 * arriva dalla cache: e' il motivo per cui la cache e' sicura. Prezzo,
	 * disponibilita' e foto si leggono dal negozio nel momento in cui si
	 * disegna, quindi cio' che si conserva e' solo QUALI prodotti rispondono
	 * alla domanda e in che ordine. Quello non invecchia in un quarto d'ora;
	 * un prezzo si'.
	 *
	 * @param array<string,mixed> $magro
	 * @return array<string,mixed>
	 */
	private static function componi( array $magro, string $domanda, bool $da_cache ): array {
		$schede = Risolutore::schede( (array) ( $magro['results'] ?? array() ) );

		return array(
			'domanda'   => $domanda,
			'risultati' => $schede,
			'categorie' => self::categorie( (array) ( $magro['categories'] ?? array() ), $schede ),
			'tempoMs'   => (int) ( $magro['tookMs'] ?? 0 ),
			'daCache'   => $da_cache,
		);
	}

	/**
	 * La chiave di cache di una richiesta.
	 *
	 * Comprende il carico intero, foto compresa: la stessa foto rimandata due
	 * volte — cosa che succede quando si riprova dopo un errore — non paga due
	 * volte. La chiave e' un digest, quindi non conserva ne' la domanda ne'
	 * l'immagine.
	 *
	 * @param array<string,mixed> $carico
	 */
	private static function impronta( string $indirizzo, array $carico ): string {
		ksort( $carico );

		return 'sg_ric_' . md5( $indirizzo . '|' . (string) wp_json_encode( $carico ) );
	}

	/**
	 * Le categorie con cui si affina, senza doppioni e con i conti veri.
	 *
	 * Il servizio le manda sia come percorso ("collane") sia come nome
	 * ("Collane"), e a schermo diventavano due pastiglie per la stessa cosa.
	 * I conteggi del servizio riguardano poi l'indice intero, mentre le
	 * pastiglie filtrano cio' che si vede: un "Collane (37)" sopra dodici
	 * risultati e' un numero che non torna. Si contano le schede in mano.
	 *
	 * @param array<int,array<string,mixed>> $grezze
	 * @param array<int,array<string,mixed>> $schede
	 * @return array<int,array<string,mixed>>
	 */
	private static function categorie( array $grezze, array $schede ): array {
		$conti = array();

		foreach ( $schede as $s ) {
			$nome = (string) ( $s['categoria'] ?? '' );

			if ( '' === $nome ) {
				continue;
			}

			$chiave = self::chiave( $nome );

			if ( ! isset( $conti[ $chiave ] ) ) {
				$conti[ $chiave ] = array( 'etichetta' => $nome, 'conteggio' => 0 );
			}

			++$conti[ $chiave ]['conteggio'];
		}

		/*
		 * Le categorie suggerite dal servizio entrano solo se corrispondono a
		 * schede presenti: una pastiglia che filtra a zero e' una via chiusa.
		 */
		$ordinate = array();

		foreach ( $grezze as $c ) {
			$etichetta = Risolutore::etichetta_categoria( (string) ( $c['categoryPath'] ?? '' ) );

			if ( null === $etichetta ) {
				continue;
			}

			$chiave = self::chiave( $etichetta );

			if ( isset( $conti[ $chiave ] ) && ! isset( $ordinate[ $chiave ] ) ) {
				$ordinate[ $chiave ] = $conti[ $chiave ];
			}
		}

		// Poi le restanti, dalla piu' numerosa.
		foreach ( $conti as $chiave => $voce ) {
			if ( ! isset( $ordinate[ $chiave ] ) ) {
				$ordinate[ $chiave ] = $voce;
			}
		}

		uasort( $ordinate, static fn( $a, $b ) => $b['conteggio'] <=> $a['conteggio'] );

		return array_values( array_slice( $ordinate, 0, 8 ) );
	}

	private static function chiave( string $nome ): string {
		return strtolower( str_replace( array( '-', '_', ' ' ), '', $nome ) );
	}

	/**
	 * Il base64 nudo, verificato.
	 *
	 * Arriva sia come stringa pura sia come `data:image/jpeg;base64,...`, a
	 * seconda di come il browser ha letto il file. Si accetta solo cio' che si
	 * decodifica davvero e che sta sotto il tetto: senza il controllo, una
	 * stringa di quattro megabyte di spazzatura verrebbe spedita al servizio
	 * e pagata come una chiamata vera.
	 */
	private static function solo_base64( string $grezzo ): string {
		$grezzo = trim( $grezzo );

		if ( preg_match( '#^data:[^;]*;base64,#i', $grezzo ) ) {
			$grezzo = (string) preg_replace( '#^data:[^;]*;base64,#i', '', $grezzo );
		}

		$grezzo = (string) preg_replace( '/\s+/', '', $grezzo );

		if ( '' === $grezzo || strlen( $grezzo ) > (int) ceil( self::FOTO_MASSIMA / 3 ) * 4 ) {
			return '';
		}

		$binario = base64_decode( $grezzo, true );

		if ( false === $binario || strlen( $binario ) < 100 ) {
			return '';
		}

		// Deve essere un'immagine vera, non un file rinominato.
		$info = @getimagesizefromstring( $binario ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $info || empty( $info['mime'] ) || ! str_starts_with( (string) $info['mime'], 'image/' ) ) {
			return '';
		}

		return $grezzo;
	}
}
