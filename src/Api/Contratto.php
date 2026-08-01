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

	private const CACHE     = \Storegentic\PREFISSO_OPZIONI . 'contratto';
	private const IMPRONTA  = \Storegentic\PREFISSO_OPZIONI . 'contratto_impronta';
	private const DURATA    = 6 * HOUR_IN_SECONDS;

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
			/*
			 * Un contratto vecchio vale piu' di nessun contratto: se il
			 * servizio e' momentaneamente giu', il negozio continua a
			 * funzionare con l'ultimo contratto ricevuto. Si segna l'errore
			 * per la diagnostica, ma non si spegne tutto.
			 */
			update_option( \Storegentic\PREFISSO_OPZIONI . 'ultimo_errore', array(
				'quando'    => time(),
				'messaggio' => $risposta->get_error_message(),
			), false );

			$vecchio = get_option( self::CACHE . '_ultimo', null );

			return is_array( $vecchio ) && self::impronta_valida() ? $vecchio : $risposta;
		}

		delete_option( \Storegentic\PREFISSO_OPZIONI . 'ultimo_errore' );

		set_transient( self::CACHE, $risposta, self::DURATA );
		// Copia persistente, usata solo come rete di sicurezza se il servizio cade.
		update_option( self::CACHE . '_ultimo', $risposta, false );
		update_option( self::IMPRONTA, self::impronta_attuale(), false );

		return $risposta;
	}

	/** Butta via il contratto: si usa quando cambiano chiave o base. */
	public static function dimentica(): void {
		delete_transient( self::CACHE );
		delete_option( self::CACHE . '_ultimo' );
		delete_option( self::IMPRONTA );
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

		foreach ( self::varianti( $capacita ) as $variante ) {
			if ( array_key_exists( $variante, $capacita_dichiarate ) ) {
				return (bool) $capacita_dichiarate[ $variante ];
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
}
