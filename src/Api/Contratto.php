<?php
/**
 * Il contratto dichiarato dal server.
 *
 * Questa classe e' il motivo per cui nel plugin non c'e' un solo indirizzo
 * scritto a mano oltre a quello dell'handshake.
 *
 * A ogni handshake il server risponde con:
 *   endpoints    quali indirizzi usare per catalogo, ricerca, chat, analisi
 *   capabilities quali funzioni sono accese per questo negozio
 *   plan/usage   quanto e' consentito e quanto e' gia' stato consumato
 *   rateLimits   ogni quanto si puo' chiamare
 *   agentChat    come si parla con l'agente (canale, formato, parametri)
 *   search       come si cerca
 *   ingestion    come si carica il catalogo (dimensione dei lotti, ecc.)
 *
 * Il plugin legge di qui. Se il server sposta un indirizzo o spegne una
 * funzione, le installazioni si adeguano al ciclo successivo, senza
 * aggiornare il plugin su ogni negozio.
 *
 * CONSEGUENZA IMPORTANTE: se una capacita' non e' dichiarata, il plugin non
 * la offre. Non si "prova comunque": si nasconde il comando. Un pulsante che
 * risponde 403 e' peggio di un pulsante che non c'e'.
 *
 * Il contratto viene tenuto in cache. La cache scade da sola; in piu' viene
 * buttata via quando cambia la chiave o la base, perche' un contratto
 * ottenuto con un'altra chiave non descrive questo negozio.
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

final class Contratto {

	private const CACHE      = \Storegentic\PREFISSO_OPZIONI . 'contratto';
	private const IMPRONTA   = \Storegentic\PREFISSO_OPZIONI . 'contratto_impronta';
	private const FALLIMENTO = \Storegentic\PREFISSO_OPZIONI . 'contratto_ko';
	private const DURATA     = 6 * HOUR_IN_SECONDS;

	/** Quanto si ricorda un handshake fallito prima di riprovare. */
	private const DURATA_FALLIMENTO = 5 * MINUTE_IN_SECONDS;

	/**
	 * L'unico percorso scritto nel plugin.
	 *
	 * Serve a chiedere il contratto. Da qui in poi comanda il server.
	 */
	private const PERCORSO_HANDSHAKE = '/v1/commerce/plugin/handshake';

	/**
	 * Il contratto valido, dalla cache o dal server.
	 *
	 * @param bool $forza Salta la cache e richiedilo comunque.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function ottieni( bool $forza = false ) {
		if ( ! Impostazioni::configurato() ) {
			return new WP_Error(
				'storegentic_non_configurato',
				__( 'Inserisci la chiave del negozio per collegare Storegentic.', 'storegentic' )
			);
		}

		if ( ! $forza ) {
			$cache = get_transient( self::CACHE );
			if ( is_array( $cache ) && self::impronta_valida() ) {
				return $cache;
			}

			// Handshake fallito da poco: non si ritenta a ogni chiamata.
			if ( false !== get_transient( self::FALLIMENTO ) ) {
				$vecchio = get_option( self::CACHE . '_ultimo', null );

				return is_array( $vecchio ) && self::impronta_valida()
					? $vecchio
					: new WP_Error(
						'storegentic_non_raggiungibile',
						__( 'Storegentic non risponde. Il collegamento verrà ritentato fra qualche minuto.', 'storegentic' )
					);
			}
		}

		return self::rinnova();
	}

	/**
	 * Chiede il contratto al server e lo mette in cache.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function rinnova() {
		$client = new Client();

		$risposta = $client->post( self::PERCORSO_HANDSHAKE, self::presentazione() );

		if ( is_wp_error( $risposta ) ) {
			update_option( \Storegentic\PREFISSO_OPZIONI . 'ultimo_errore', array(
				'quando'    => time(),
				'messaggio' => $risposta->get_error_message(),
			), false );

			/*
			 * Cache negativa. Senza, ogni chiamata a puo() o endpoint() —
			 * e ce n'e' una su ogni pagina pubblica — rifarebbe l'handshake
			 * in modo bloccante: con il servizio irraggiungibile ogni
			 * visitatore aspetterebbe i tentativi e le attese del client
			 * prima di vedere la pagina. Si ricorda il fallimento per pochi
			 * minuti, quel tanto che basta a non ripeterlo a ogni visita.
			 */
			set_transient( self::FALLIMENTO, time(), self::DURATA_FALLIMENTO );

			$stato = (int) ( $risposta->get_error_data()['stato'] ?? 0 );

			/*
			 * Su 401 e 403 NON si ripiega sul contratto vecchio. Il ripiego
			 * serve a superare un servizio momentaneamente giu', non a far
			 * finta che una chiave revocata sia ancora valida: con il
			 * ripiego il negozio resterebbe "collegato" per sempre,
			 * continuando a mostrare comandi che rispondono "non
			 * autorizzato" a ogni clic.
			 */
			if ( in_array( $stato, array( 401, 403 ), true ) ) {
				self::dimentica();
				return $risposta;
			}

			$vecchio = get_option( self::CACHE . '_ultimo', null );

			return is_array( $vecchio ) && self::impronta_valida() ? $vecchio : $risposta;
		}

		/*
		 * Non basta un 2xx: deve essere un contratto. Una risposta vuota o
		 * un corpo che non descrive ne' capacita' ne' indirizzi non e' un
		 * contratto, e metterlo in cache distruggerebbe la copia di
		 * sicurezza sostituendola con nulla.
		 */
		if ( ! self::pare_un_contratto( $risposta ) ) {
			update_option( \Storegentic\PREFISSO_OPZIONI . 'ultimo_errore', array(
				'quando'    => time(),
				'messaggio' => __( 'Il servizio ha risposto senza dichiarare capacità né indirizzi.', 'storegentic' ),
			), false );

			set_transient( self::FALLIMENTO, time(), self::DURATA_FALLIMENTO );

			$vecchio = get_option( self::CACHE . '_ultimo', null );

			return is_array( $vecchio ) && self::impronta_valida()
				? $vecchio
				: new WP_Error(
					'storegentic_contratto_illeggibile',
					__( 'Il servizio ha risposto senza dichiarare capacità né indirizzi.', 'storegentic' )
				);
		}

		delete_option( \Storegentic\PREFISSO_OPZIONI . 'ultimo_errore' );
		delete_transient( self::FALLIMENTO );

		set_transient( self::CACHE, $risposta, self::DURATA );
		// Copia persistente, usata solo come rete di sicurezza se il servizio cade.
		update_option( self::CACHE . '_ultimo', $risposta, false );
		update_option( self::IMPRONTA, self::impronta_attuale(), false );

		return $risposta;
	}

	/**
	 * Un contratto dichiara almeno le capacita' o gli indirizzi.
	 *
	 * @param array<string,mixed> $risposta
	 */
	private static function pare_un_contratto( array $risposta ): bool {
		foreach ( array( 'capabilities', 'endpoints' ) as $campo ) {
			if ( isset( $risposta[ $campo ] ) && is_array( $risposta[ $campo ] ) && ! empty( $risposta[ $campo ] ) ) {
				return true;
			}
		}

		return false;
	}

	/** Butta via il contratto: si usa quando cambiano chiave o base. */
	public static function dimentica(): void {
		delete_transient( self::CACHE );
		delete_transient( self::FALLIMENTO );
		delete_option( self::CACHE . '_ultimo' );
		delete_option( self::IMPRONTA );
	}

	/**
	 * Come endpoint(), ma senza mai contattare il servizio.
	 *
	 * La usa il frontend pubblico: li' una chiamata di rete dentro il
	 * rendering si trasformerebbe in attesa per il visitatore.
	 */
	public static function endpoint_in_cache( string $nome ): string {
		/*
		 * Prima il contratto fresco: basta che nessuna impronta lo smentisca.
		 * Poi la copia persistente, che invece la prova la deve dare, perche'
		 * non scade mai. Vedi impronta_smentita().
		 */
		$cache = get_transient( self::CACHE );

		if ( is_array( $cache ) && ! self::impronta_smentita() ) {
			return self::cerca_endpoint( $cache, $nome );
		}

		$vecchio = get_option( self::CACHE . '_ultimo', null );

		if ( is_array( $vecchio ) && self::impronta_valida() ) {
			return self::cerca_endpoint( $vecchio, $nome );
		}

		return '';
	}

	/**
	 * L'indirizzo dichiarato dal server per una funzione.
	 *
	 * @param string $nome Nome della funzione nel contratto, es. "catalogUpsert".
	 * @return string Percorso, oppure stringa vuota se il server non lo dichiara.
	 */
	public static function endpoint( string $nome ): string {
		$contratto = self::ottieni();

		if ( is_wp_error( $contratto ) ) {
			return '';
		}

		return self::cerca_endpoint( $contratto, $nome );
	}

	/**
	 * @param array<string,mixed> $contratto
	 */
	/**
	 * Una via d'uscita per quando il servizio pubblica prima di dichiarare.
	 *
	 * Il plugin usa solo cio' che il contratto dichiara, ed e' la regola che
	 * lo tiene in piedi su un parco di installazioni sparse. Succede pero' che
	 * un indirizzo esista, sia documentato e funzioni, e l'handshake non lo
	 * nomini ancora: e' successo con la ricerca istantanea, presente nello
	 * swagger e assente dal contratto.
	 *
	 * In quel caso l'alternativa sarebbe scrivere l'indirizzo nel codice, che
	 * e' esattamente cio' che questo plugin non fa. Con questo filtro
	 * l'indirizzo lo fornisce chi installa, in una riga, e sparisce da solo
	 * appena il contratto lo dichiara: il valore del contratto ha la
	 * precedenza, e il filtro riceve la stringa vuota solo quando non c'e'.
	 *
	 * add_filter( 'storegentic_endpoint', function ( $trovato, $nome ) {
	 *     return ( '' === $trovato && 'instantSearch' === $nome )
	 *         ? '/v1/commerce/search/instant'
	 *         : $trovato;
	 * }, 10, 2 );
	 */
	private static function cerca_endpoint( array $contratto, string $nome ): string {
		$trovato = self::nel_contratto( $contratto, $nome );

		/**
		 * @param string              $trovato   L'indirizzo dichiarato, o '' se assente.
		 * @param string              $nome      Il nome con cui e' stato chiesto.
		 * @param array<string,mixed> $contratto Il contratto intero.
		 */
		return (string) apply_filters( 'storegentic_endpoint', $trovato, $nome, $contratto );
	}

	/** L'indirizzo come lo dichiara il contratto, e nient'altro. */
	private static function nel_contratto( array $contratto, string $nome ): string {
		$endpoints = $contratto['endpoints'] ?? array();

		if ( ! is_array( $endpoints ) ) {
			return '';
		}

		/*
		 * Il contratto puo' nominare la stessa cosa in modi diversi a seconda
		 * della versione del server ("catalogUpsert", "catalog_upsert",
		 * "catalog.upsert"). Si accettano tutte le forme dello stesso nome
		 * invece di pretenderne una: e' il server che detta, non il plugin.
		 */
		foreach ( self::varianti( $nome ) as $variante ) {
			if ( isset( $endpoints[ $variante ] ) && is_string( $endpoints[ $variante ] ) && '' !== $endpoints[ $variante ] ) {
				return $endpoints[ $variante ];
			}
		}

		// Alcune versioni annidano gli indirizzi per sottosistema.
		foreach ( $endpoints as $gruppo ) {
			if ( ! is_array( $gruppo ) ) {
				continue;
			}
			foreach ( self::varianti( $nome ) as $variante ) {
				if ( isset( $gruppo[ $variante ] ) && is_string( $gruppo[ $variante ] ) && '' !== $gruppo[ $variante ] ) {
					return $gruppo[ $variante ];
				}
			}
		}

		return '';
	}

	/**
	 * I nomi con cui il server puo' chiamare la stessa capacita'.
	 *
	 * `varianti()` copre le differenze di grafia dello STESSO nome
	 * (catalogUpsert, catalog_upsert, catalog.upsert). Non copre i
	 * sinonimi, che sono nomi diversi per la stessa cosa.
	 *
	 * Serve davvero: il plugin chiedeva "catalogIngest" e il contratto di
	 * questo negozio dichiara "ingest". La domanda tornava NO su una
	 * capacita' che era accesa. Il caricamento del catalogo funzionava lo
	 * stesso solo perche' il controllo guarda anche l'indirizzo, ma era
	 * fortuna, non progetto.
	 *
	 * Qui il plugin fa una domanda di senso — "posso caricare il catalogo?"
	 * — e la mappa elenca i nomi sotto cui quella risposta puo' arrivare.
	 * Il primo nome trovato nel contratto vince.
	 *
	 * @var array<string,array<int,string>>
	 */
	private const SINONIMI = array(
		'catalogIngest' => array( 'catalogIngest', 'ingest', 'catalog' ),
		'search'        => array( 'search' ),
		'imageSearch'   => array( 'imageSearch', 'image' ),
		'agentChat'     => array( 'agentChat', 'chat', 'agent' ),
		'analytics'     => array( 'analytics' ),
		'instantSearch' => array( 'instantSearch', 'searchInstant', 'instant' ),
		'streaming'     => array( 'streaming' ),
	);

	/**
	 * Una capacita' e' accesa per questo negozio?
	 *
	 * In dubbio si risponde di no: meglio un comando assente di un comando
	 * che risponde "non autorizzato".
	 */
	public static function puo( string $capacita ): bool {
		$contratto = self::ottieni();

		if ( is_wp_error( $contratto ) ) {
			return false;
		}

		$capacita_dichiarate = $contratto['capabilities'] ?? array();

		if ( ! is_array( $capacita_dichiarate ) ) {
			return false;
		}

		$nomi = self::SINONIMI[ $capacita ] ?? array( $capacita );

		foreach ( $nomi as $nome ) {
			foreach ( self::varianti( $nome ) as $variante ) {
				if ( array_key_exists( $variante, $capacita_dichiarate ) ) {
					return (bool) $capacita_dichiarate[ $variante ];
				}
			}
		}

		return false;
	}

	/**
	 * Un ramo del contratto, es. "agentChat" o "ingestion".
	 *
	 * @return array<string,mixed>
	 */
	public static function sezione( string $nome ): array {
		$contratto = self::ottieni();

		if ( is_wp_error( $contratto ) ) {
			return array();
		}

		foreach ( self::varianti( $nome ) as $variante ) {
			if ( isset( $contratto[ $variante ] ) && is_array( $contratto[ $variante ] ) ) {
				return $contratto[ $variante ];
			}
		}

		return array();
	}

	/**
	 * Le forme in cui lo stesso nome puo' comparire nel contratto.
	 *
	 * @return array<int,string>
	 */
	private static function varianti( string $nome ): array {
		$camel  = lcfirst( str_replace( ' ', '', ucwords( str_replace( array( '_', '.', '-' ), ' ', $nome ) ) ) );
		$snake  = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $camel ) );
		$punto  = str_replace( '_', '.', $snake );
		$trat   = str_replace( '_', '-', $snake );

		return array_values( array_unique( array( $nome, $camel, $snake, $punto, $trat ) ) );
	}

	/**
	 * Cosa dichiara il plugin di se' stesso.
	 *
	 * Serve al server per sapere con chi parla: versione del plugin, della
	 * piattaforma, indirizzo del negozio. L'identificativo di installazione
	 * e' stabile e casuale, non ricavato dall'URL: un negozio che cambia
	 * dominio resta lo stesso negozio.
	 *
	 * @return array<string,mixed>
	 */
	private static function presentazione(): array {
		return array(
			'connectorType'    => 'plugin',
			'integrationType'  => 'plugin',
			'platform'         => 'woocommerce',
			'ecommercePlatform' => 'woocommerce',
			'pluginName'       => 'storegentic-woocommerce',
			'pluginVersion'    => \Storegentic\VERSIONE,
			'platformVersion'  => get_bloginfo( 'version' ),
			'ecommerceVersion' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'storeUrl'         => home_url( '/' ),
			'shopUrl'          => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
			'environment'      => self::ambiente(),
			'installationId'   => self::identificativo(),
			'metadata'         => array(
				'locale'      => get_locale(),
				'currency'    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : null,
				'phpVersion'  => PHP_VERSION,
				'multisite'   => is_multisite(),
			),
		);
	}

	private static function ambiente(): string {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return wp_get_environment_type();
		}
		return 'production';
	}

	/** Identificativo stabile dell'installazione, generato una volta sola. */
	private static function identificativo(): string {
		$chiave = \Storegentic\PREFISSO_OPZIONI . 'installazione';
		$id     = get_option( $chiave, '' );

		if ( ! is_string( $id ) || '' === $id ) {
			$id = 'inst_' . wp_generate_password( 24, false, false );
			update_option( $chiave, $id, false );
		}

		return $id;
	}

	/**
	 * Impronta di chiave e base.
	 *
	 * Un contratto ottenuto con un'altra chiave, o da un'altra base, non
	 * descrive questo negozio: se l'impronta non coincide la cache non vale.
	 * Si tiene l'hash e non i valori, cosi' la chiave non finisce in chiaro
	 * in un'altra riga del database.
	 */
	private static function impronta_attuale(): string {
		return hash( 'sha256', (string) Impostazioni::leggi( 'base' ) . '|' . (string) Impostazioni::leggi( 'chiave' ) );
	}

	private static function impronta_valida(): bool {
		return hash_equals( (string) get_option( self::IMPRONTA, '' ), self::impronta_attuale() );
	}

	/**
	 * L'impronta c'e' ed e' di un'altra chiave.
	 *
	 * PERCHE' SERVE UNA DOMANDA DIVERSA DA impronta_valida(). Quella chiede
	 * "posso dimostrare che questo contratto e' della chiave giusta?", e
	 * risponde no in due casi molto diversi: quando l'impronta e' di un'altra
	 * chiave — e allora il contratto va buttato — e quando l'impronta
	 * semplicemente non c'e'.
	 *
	 * Il secondo caso non e' un contratto sbagliato: e' un pezzo di
	 * contabilita' perso. Trattarlo come il primo significa spegnere ricerca e
	 * assistente su tutto il sito, in silenzio, per un'opzione mancante.
	 * Visto succedere: dopo aver cancellato il contratto per rileggerlo, la
	 * ricerca e' rimasta assente finche' l'impronta non e' ricomparsa.
	 *
	 * Chi ha in mano il contratto ancora fresco puo' fidarsene: quel contratto
	 * viene buttato ogni volta che chiave o indirizzo cambiano, quindi se e'
	 * ancora li' e' della chiave in uso. Chi invece pesca dalla copia
	 * persistente — che non scade mai — deve pretendere la prova, e usa
	 * impronta_valida().
	 */
	private static function impronta_smentita(): bool {
		$salvata = (string) get_option( self::IMPRONTA, '' );

		return '' !== $salvata && ! hash_equals( $salvata, self::impronta_attuale() );
	}
}
