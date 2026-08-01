<?php
/**
 * Impostazioni del plugin.
 *
 * Un'opzione sola nel database, non venti: le opzioni sparse si dimenticano
 * alla disinstallazione e non si leggono in blocco. Qui c'e' un array unico,
 * con i valori predefiniti dichiarati in un posto solo e la sanificazione
 * accanto a ogni campo.
 *
 * La chiave del negozio e' un segreto: non viene mai stampata in pagina, e
 * nell'amministrazione si mostra solo mascherata.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Impostazioni {

	public const CHIAVE = PREFISSO_OPZIONI . 'impostazioni';

	/**
	 * Valori predefiniti.
	 *
	 * `base` e' l'unico indirizzo scritto nel plugin, e serve solo a chiedere
	 * il contratto: da li' in poi gli indirizzi li dichiara il server.
	 *
	 * @return array<string,mixed>
	 */
	public static function predefinite(): array {
		return array(
			'base'                => 'https://adam.storegentic.eu',
			'chiave'              => '',
			'workspace'           => '',
			'attivo'              => false,

			// Presentazione
			'modalita'            => 'barra',   // barra | fluttuante | finestra
			'posizione'           => 'destra',  // destra | sinistra
			'colore'              => '#1A1815',
			'colore_testo'        => '#FFFFFF',
			'raggio'              => 8,
			'etichetta'           => '',
			'segnaposto'          => '',
			'saluto'              => '',
			'solo_su'             => array(),   // vuoto = ovunque
			'sostituisci_ricerca' => false,

			// Catalogo
			'sincro_automatica'   => true,
			'frequenza'           => 'daily',
			'lotto'               => 200,
			'includi_bozze'       => false,
			'includi_esauriti'    => true,
			'invia_categorie'     => true,
			'pota_mancanti'       => true,

			// Analisi
			'analitica'           => true,
		);
	}

	/** @return array<string,mixed> */
	public static function tutte(): array {
		$salvate = get_option( self::CHIAVE, array() );
		return array_merge( self::predefinite(), is_array( $salvate ) ? $salvate : array() );
	}

	/**
	 * @param string $nome
	 * @return mixed
	 */
	public static function leggi( string $nome ) {
		$tutte = self::tutte();
		return $tutte[ $nome ] ?? null;
	}

	/**
	 * Salva, sanificando ogni campo secondo il proprio tipo.
	 *
	 * @param array<string,mixed> $nuove
	 * @return array<string,mixed> Le impostazioni come sono state salvate.
	 */
	public static function salva( array $nuove ): array {
		$attuali = self::tutte();
		$pulite  = $attuali;

		foreach ( $nuove as $nome => $valore ) {
			if ( ! array_key_exists( $nome, $attuali ) ) {
				continue; // Chiavi sconosciute: ignorate, non salvate.
			}
			$pulite[ $nome ] = self::sanifica( $nome, $valore );
		}

		update_option( self::CHIAVE, $pulite, false );

		return $pulite;
	}

	/**
	 * @param string $nome
	 * @param mixed  $valore
	 * @return mixed
	 */
	private static function sanifica( string $nome, $valore ) {
		switch ( $nome ) {
			case 'base':
				$url = esc_url_raw( trim( (string) $valore ) );
				return untrailingslashit( $url );

			case 'chiave':
			case 'workspace':
			case 'etichetta':
			case 'segnaposto':
			case 'saluto':
				return sanitize_text_field( (string) $valore );

			case 'modalita':
				return in_array( $valore, array( 'barra', 'fluttuante', 'finestra' ), true ) ? (string) $valore : 'barra';

			case 'posizione':
				return 'sinistra' === $valore ? 'sinistra' : 'destra';

			case 'colore':
			case 'colore_testo':
				$colore = sanitize_hex_color( (string) $valore );
				return $colore ?: ( 'colore' === $nome ? '#1A1815' : '#FFFFFF' );

			case 'raggio':
				return max( 0, min( 40, (int) $valore ) );

			case 'lotto':
				// Il server divide comunque in lotti da 1000: oltre non serve.
				return max( 25, min( 1000, (int) $valore ) );

			case 'frequenza':
				$ammesse = array_keys( wp_get_schedules() );
				return in_array( $valore, $ammesse, true ) ? (string) $valore : 'daily';

			case 'solo_su':
				$valore = is_array( $valore ) ? $valore : array();
				return array_values( array_filter( array_map( 'sanitize_key', $valore ) ) );

			default:
				return (bool) $valore;
		}
	}

	/** Il plugin puo' parlare col servizio? */
	public static function configurato(): bool {
		$i = self::tutte();
		return '' !== trim( (string) $i['chiave'] ) && '' !== trim( (string) $i['base'] );
	}

	/** La chiave come si puo' mostrare a schermo: solo la coda. */
	public static function chiave_mascherata(): string {
		$chiave = (string) self::leggi( 'chiave' );
		$lung   = strlen( $chiave );

		if ( 0 === $lung ) {
			return '';
		}

		return $lung <= 8
			? str_repeat( '•', $lung )
			: str_repeat( '•', 8 ) . substr( $chiave, -4 );
	}
}
