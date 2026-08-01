<?php
/**
 * Trasporto verso Storegentic.
 *
 * Una sola classe parla con la rete. Tutto il resto del plugin le passa un
 * indirizzo e un carico, e riceve indietro o un array o un WP_Error: nessun
 * altro punto del codice deve sapere come si costruisce un'intestazione di
 * autenticazione o come si legge un corpo JSON.
 *
 * Cosa fa in piu' rispetto a wp_remote_post():
 *
 *   - riprova sugli errori di rete e sui 5xx, con attesa crescente. Un
 *     negozio che sincronizza 200 pagine non puo' fallire l'intera
 *     sincronizzazione perche' una pagina ha trovato un timeout;
 *   - NON riprova su 4xx: se la chiave e' sbagliata o la quota e' finita,
 *     riprovare peggiora e basta;
 *   - rispetta Retry-After quando il server lo manda (429);
 *   - normalizza gli errori, cosi' chi chiama vede sempre la stessa forma;
 *   - non scrive mai la chiave nei log.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Api;

use Storegentic\Impostazioni;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Client {

	/** Quante volte si riprova, oltre al primo tentativo. */
	private const TENTATIVI = 3;

	/** Attesa di base fra un tentativo e l'altro, in secondi. */
	private const ATTESA_BASE = 2;

	/** Attesa massima accettata da Retry-After: oltre, si rinuncia. */
	private const ATTESA_MASSIMA = 30;

	private string $base;
	private string $chiave;
	private int $timeout;
	private int $tentativi;

	/**
	 * @param int|null $tentativi Ritentativi oltre al primo. `null` usa il
	 *                            valore predefinito; 0 significa un tentativo
	 *                            solo, e serve sulle rotte pubbliche, dove
	 *                            c'e' una persona che aspetta.
	 */
	public function __construct( ?string $base = null, ?string $chiave = null, int $timeout = 30, ?int $tentativi = null ) {
		$this->base      = untrailingslashit( $base ?? (string) Impostazioni::leggi( 'base' ) );
		$this->chiave    = $chiave ?? (string) Impostazioni::leggi( 'chiave' );
		$this->timeout   = $timeout;
		$this->tentativi = null === $tentativi ? self::TENTATIVI : max( 0, $tentativi );
	}

	/**
	 * @param array<string,mixed> $carico
	 * @return array<string,mixed>|WP_Error
	 */
	public function post( string $percorso, array $carico = array() ) {
		return $this->chiama( 'POST', $percorso, $carico );
	}

	/**
	 * @param array<string,scalar> $parametri
	 * @return array<string,mixed>|WP_Error
	 */
	public function get( string $percorso, array $parametri = array() ) {
		if ( ! empty( $parametri ) ) {
			$percorso .= ( str_contains( $percorso, '?' ) ? '&' : '?' ) . http_build_query( $parametri );
		}
		return $this->chiama( 'GET', $percorso );
	}

	/**
	 * @param array<string,mixed>|null $carico
	 * @return array<string,mixed>|WP_Error
	 */
	private function chiama( string $metodo, string $percorso, ?array $carico = null ) {
		if ( '' === $this->chiave ) {
			return new WP_Error(
				'storegentic_senza_chiave',
				__( 'Manca la chiave del negozio. Inseriscila nelle impostazioni di Storegentic.', 'storegentic' )
			);
		}

		$url  = $this->url( $percorso );
		$args = array(
			'method'      => $metodo,
			'timeout'     => $this->timeout,
			'redirection' => 2,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->chiave,
				'Accept'        => 'application/json',
				'User-Agent'    => $this->firma(),
			),
		);

		if ( null !== $carico ) {
			$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			$args['body']                    = (string) wp_json_encode( $carico );
		}

		$ultimo = null;

		for ( $tentativo = 0; $tentativo <= $this->tentativi; $tentativo++ ) {
			if ( $tentativo > 0 ) {
				sleep( $this->attesa( $tentativo, $ultimo ) );
			}

			$risposta = wp_remote_request( $url, $args );

			/*
			 * Errore di trasporto: DNS, connessione rifiutata, timeout. Vale
			 * la pena riprovare: il server potrebbe essere solo occupato.
			 */
			if ( is_wp_error( $risposta ) ) {
				$ultimo = $risposta;
				continue;
			}

			$codice = (int) wp_remote_retrieve_response_code( $risposta );
			$corpo  = (string) wp_remote_retrieve_body( $risposta );

			if ( $codice >= 200 && $codice < 300 ) {
				return $this->decodifica( $corpo, $codice );
			}

			$errore = $this->errore_http( $codice, $corpo, $risposta );

			/*
			 * 4xx: il problema e' nella richiesta o nell'autorizzazione, e
			 * riprovare produce lo stesso risultato. L'unica eccezione e' il
			 * 429, dove il server dice esplicitamente quando ritentare.
			 */
			if ( $codice < 500 && 429 !== $codice ) {
				return $errore;
			}

			/*
			 * Se il server chiede di aspettare piu' di quanto siamo disposti
			 * ad aspettare, si rinuncia e si riferisce l'attesa richiesta.
			 * Ritentare prima del tempo indicato non fa che consumare un
			 * altro colpo di quota e ottenere lo stesso 429.
			 */
			$dati = $errore->get_error_data();
			if ( isset( $dati['retry_after'] ) && (int) $dati['retry_after'] > self::ATTESA_MASSIMA ) {
				return $errore;
			}

			$ultimo = $errore;
		}

		return $ultimo instanceof WP_Error
			? $ultimo
			: new WP_Error( 'storegentic_irraggiungibile', __( 'Storegentic non risponde.', 'storegentic' ) );
	}

	/**
	 * Attesa prima del prossimo tentativo.
	 *
	 * Si rispetta Retry-After se il server lo ha indicato, altrimenti si
	 * raddoppia a ogni giro. Il tetto evita che una richiesta blocchi un
	 * processo di WordPress per mezzo minuto a vuoto.
	 *
	 * @param WP_Error|null $ultimo
	 */
	private function attesa( int $tentativo, $ultimo ): int {
		if ( $ultimo instanceof WP_Error ) {
			$dati = $ultimo->get_error_data();
			if ( is_array( $dati ) && isset( $dati['retry_after'] ) ) {
				return (int) min( self::ATTESA_MASSIMA, max( 1, (int) $dati['retry_after'] ) );
			}
		}

		return (int) min( self::ATTESA_MASSIMA, self::ATTESA_BASE ** $tentativo );
	}

	/**
	 * @param array<string,mixed>|string $risposta
	 */
	private function errore_http( int $codice, string $corpo, $risposta ): WP_Error {
		$decodificato = json_decode( $corpo, true );
		$messaggio    = '';

		if ( is_array( $decodificato ) ) {
			foreach ( array( 'message', 'error', 'detail', 'reason' ) as $campo ) {
				if ( ! empty( $decodificato[ $campo ] ) && is_string( $decodificato[ $campo ] ) ) {
					$messaggio = $decodificato[ $campo ];
					break;
				}
			}
		}

		if ( '' === $messaggio ) {
			$messaggio = $this->messaggio_predefinito( $codice );
		}

		$dati = array( 'stato' => $codice );

		$retry = wp_remote_retrieve_header( $risposta, 'retry-after' );
		if ( '' !== $retry ) {
			// Retry-After puo' essere un numero di secondi o una data HTTP.
			$dati['retry_after'] = is_numeric( $retry )
				? (int) $retry
				: max( 0, (int) ( strtotime( (string) $retry ) - time() ) );
		}

		return new WP_Error( 'storegentic_http_' . $codice, $messaggio, $dati );
	}

	private function messaggio_predefinito( int $codice ): string {
		switch ( $codice ) {
			case 401:
				return __( 'Chiave rifiutata: controlla la chiave del negozio.', 'storegentic' );
			case 403:
				return __( 'Questa chiave non ha i permessi per questa operazione.', 'storegentic' );
			case 404:
				return __( 'Indirizzo non trovato sul servizio.', 'storegentic' );
			case 429:
				return __( 'Limite del piano raggiunto: riprova più tardi.', 'storegentic' );
			default:
				/* translators: %d: codice di stato HTTP. */
				return sprintf( __( 'Storegentic ha risposto con un errore (%d).', 'storegentic' ), $codice );
		}
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	private function decodifica( string $corpo, int $codice ) {
		if ( '' === trim( $corpo ) ) {
			// 202 senza corpo e' una risposta legittima: lavoro accettato.
			return array( 'stato' => $codice );
		}

		$dati = json_decode( $corpo, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $dati ) ) {
			return new WP_Error(
				'storegentic_risposta_illeggibile',
				__( 'Storegentic ha risposto in un formato non atteso.', 'storegentic' ),
				array( 'stato' => $codice )
			);
		}

		$dati['stato'] = $codice;

		return $dati;
	}

	/**
	 * Un percorso assoluto resta com'e'.
	 *
	 * Serve perche' gli indirizzi arrivano dal contratto del server: quel
	 * contratto puo' dichiararli relativi ("/v1/commerce/search") oppure
	 * assoluti, se un domani il servizio separa i sottosistemi su host
	 * diversi. Il plugin deve funzionare in entrambi i casi.
	 */
	private function url( string $percorso ): string {
		if ( str_starts_with( $percorso, 'http://' ) || str_starts_with( $percorso, 'https://' ) ) {
			return $percorso;
		}

		return $this->base . '/' . ltrim( $percorso, '/' );
	}

	/** Chi sta chiamando: utile lato server per capire i parchi installati. */
	private function firma(): string {
		return sprintf(
			'Storegentic-WooCommerce/%s (WordPress/%s; WooCommerce/%s; PHP/%s)',
			\Storegentic\VERSIONE,
			get_bloginfo( 'version' ),
			defined( 'WC_VERSION' ) ? WC_VERSION : '?',
			PHP_VERSION
		);
	}
}
