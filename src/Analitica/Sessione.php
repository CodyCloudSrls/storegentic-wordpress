<?php
/**
 * Il filo che tiene insieme il percorso di chi visita.
 *
 * PERCHE' SERVE. Senza un filo, gli eventi sono coriandoli: si sa che qualcuno
 * ha cercato "collana di perle" e che qualcuno ha comprato, ma non che sia la
 * stessa persona. Il funnel di Storegentic — cerca, guarda, aggiunge, paga —
 * ha senso solo se i sei eventi si possono ricucire, e per ricucirli serve un
 * identificativo che duri dalla ricerca all'ordine, che possono essere giorni.
 *
 * COSA E', E COSA NON E'. Un numero casuale, generato qui, che non dice niente
 * di chi visita: non l'indirizzo IP, non l'email, non l'ordine. Serve solo a
 * dire "queste dieci cose le ha fatte la stessa sessione". Non identifica una
 * persona fra un dispositivo e l'altro e non segue nessuno fuori da questo
 * sito.
 *
 * IL COOKIE SI SCRIVE TARDI, NON SUBITO. Chi arriva sul sito e non tocca
 * Storegentic non riceve niente: il cookie nasce alla prima interazione vera —
 * una ricerca, una domanda all'assistente — che e' anche il momento in cui
 * comincia ad avere senso. Un cookie messo a ogni visitatore "per sicurezza"
 * e' un cookie da dichiarare e da far accettare, in cambio di un dato che nel
 * novanta per cento dei casi resta vuoto.
 *
 * IL CONSENSO VIENE PRIMA. Se il sito espone la WP Consent API si chiede a
 * lei; altrimenti vale l'interruttore delle analisi. In dubbio non si scrive:
 * un cookie di statistica senza consenso e' un problema legale, e nessun dato
 * di funnel vale quel prezzo.
 *
 * IL SERVER DECIDE, NON IL BROWSER. L'identificativo lo legge e lo scrive solo
 * il PHP. Se lo mandasse il browser, chiunque potrebbe spedire quello di un
 * altro e attribuirsi — o attribuire ad altri — un acquisto.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Analitica;

use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sessione {

	/** Il nome del cookie. Corto e con il nostro prefisso. */
	public const COOKIE = 'sg_percorso';

	/** Quanto dura il filo, in secondi. */
	private const DURATA = 30 * DAY_IN_SECONDS;

	/**
	 * Quanti prodotti si ricordano per sessione.
	 *
	 * Chi guarda venti schede e ne compra una: servono i venti per sapere quale
	 * ricerca ha portato all'acquisto. Oltre non aggiunge nulla e fa crescere
	 * un'opzione temporanea per ogni visitatore.
	 */
	private const RICORDATI = 20;

	/** L'identificativo di questa sessione, se ce n'e' uno. */
	public static function id(): string {
		$grezzo = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Solo la forma che generiamo noi: qualunque altra cosa si ignora.
		return preg_match( '/^[a-f0-9]{32}$/', $grezzo ) ? $grezzo : '';
	}

	/**
	 * L'identificativo, creandolo se serve e se si puo'.
	 *
	 * Torna stringa vuota quando non si puo' scrivere il cookie: manca il
	 * consenso, oppure le intestazioni sono gia' partite. Chi chiama deve
	 * saperlo gestire, e lo gestisce nel modo piu' semplice — manda l'evento
	 * senza sessione, che vale meno ma non e' un errore.
	 */
	public static function apri(): string {
		$id = self::id();

		if ( '' !== $id ) {
			return $id;
		}

		if ( ! self::si_puo() ) {
			return '';
		}

		$id = bin2hex( random_bytes( 16 ) );

		/*
		 * `headers_sent` non e' pignoleria: su una rotta REST le intestazioni
		 * non sono ancora partite, ma questa funzione puo' finire chiamata da
		 * un hook a pagina gia' avviata, e li' setcookie() stamperebbe un
		 * avviso di PHP in mezzo all'HTML del negozio.
		 */
		if ( headers_sent() ) {
			return '';
		}

		setcookie(
			self::COOKIE,
			$id,
			array(
				'expires'  => time() + self::DURATA,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				/*
				 * Il browser non deve poterlo leggere: gli eventi passano dal
				 * nostro ponte, che lo legge dal server. Un cookie leggibile da
				 * JavaScript e' un cookie che qualcun altro puo' copiare.
				 */
				'httponly' => true,
				/*
				 * `Lax` e non `Strict`: chi torna sul negozio da un link in una
				 * email deve portarsi dietro il filo, altrimenti l'acquisto
				 * risulta arrivato dal nulla.
				 */
				'samesite' => 'Lax',
			)
		);

		// Vale gia' in questa richiesta: senza, il primo evento perderebbe la sessione.
		$_COOKIE[ self::COOKIE ] = $id;

		return $id;
	}

	/**
	 * Si puo' scrivere il filo?
	 *
	 * @return bool
	 */
	public static function si_puo(): bool {
		if ( ! Impostazioni::leggi( 'analitica' ) ) {
			return false;
		}

		/*
		 * La WP Consent API e' lo standard che i plugin di consenso italiani ed
		 * europei stanno adottando: se c'e', comanda lei. Il nostro e' un
		 * cookie di statistica, non tecnico, e va dichiarato come tale.
		 */
		if ( function_exists( 'wp_has_consent' ) ) {
			return (bool) wp_has_consent( 'statistics' );
		}

		/**
		 * Per i siti che gestiscono il consenso in un altro modo.
		 *
		 * Torna `false` per impedire al plugin di scrivere il cookie del
		 * percorso finche' chi visita non ha acconsentito.
		 *
		 * @param bool $consentito Cosa farebbe il plugin da solo.
		 */
		return (bool) apply_filters( 'storegentic_consenso_statistiche', true );
	}

	/**
	 * Segna che questo prodotto e' stato scoperto tramite Storegentic.
	 *
	 * E' il fatto che rende attribuibile un acquisto: senza, si sa che qualcuno
	 * ha comprato, non che l'ha trovato cercando.
	 *
	 * @param string $sku  Lo SKU aperto.
	 * @param string $modo Da dove veniva: agent_search, agent_chat, image_search.
	 */
	public static function ricorda( string $sku, string $modo ): void {
		$id  = self::apri();
		$sku = trim( $sku );

		if ( '' === $id || '' === $sku ) {
			return;
		}

		$visti = self::visti();

		/*
		 * Il PRIMO modo che ha portato a un prodotto, non l'ultimo: se qualcuno
		 * lo trova con l'assistente e poi lo ritrova cercandolo per nome, il
		 * merito e' dell'assistente, che e' quello che gliel'ha fatto scoprire.
		 */
		if ( ! isset( $visti[ $sku ] ) ) {
			$visti[ $sku ] = array( 'modo' => $modo, 'quando' => time() );
		}

		// I piu' vecchi escono per primi: si tiene la coda della sessione.
		if ( count( $visti ) > self::RICORDATI ) {
			$visti = array_slice( $visti, -self::RICORDATI, null, true );
		}

		set_transient( self::deposito( $id ), $visti, self::DURATA );
	}

	/**
	 * I prodotti scoperti in questa sessione.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function visti(): array {
		$id = self::id();

		if ( '' === $id ) {
			return array();
		}

		$visti = get_transient( self::deposito( $id ) );

		return is_array( $visti ) ? $visti : array();
	}

	/**
	 * Da dove e' stato scoperto questo prodotto, se lo e' stato.
	 *
	 * @return string Modo, oppure stringa vuota.
	 */
	public static function modo_di( string $sku ): string {
		$visti = self::visti();

		return (string) ( $visti[ trim( $sku ) ]['modo'] ?? '' );
	}

	/** Il filo si spezza: si usa quando si revoca il consenso. */
	public static function dimentica(): void {
		$id = self::id();

		if ( '' !== $id ) {
			delete_transient( self::deposito( $id ) );
		}

		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', time() - 3600, defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/' );
		}

		unset( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * Dove si conservano i prodotti visti da una sessione.
	 *
	 * Si passa dall'hash e non dall'identificativo nudo: cosi' chi legge il
	 * database non ha in mano una chiave utilizzabile come cookie.
	 */
	private static function deposito( string $id ): string {
		return 'sg_perc_' . hash( 'sha256', $id );
	}
}
