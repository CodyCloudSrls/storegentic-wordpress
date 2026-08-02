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
			'posizione'           => 'destra',  // destra | sinistra
			/*
			 * La combinazione di colori. `tema` non scrive nulla e lascia
			 * decidere al tema; vedi Frontend\Palette.
			 */
			'palette'             => 'tema',
			'colori'              => array(),
			'raggio'              => 10,

			/*
			 * Quali modalita' offre la finestra. Sono tre bisogni diversi:
			 * "trova questo" (cerca), "trova qualcosa che somigli a questo"
			 * (foto), "aiutami a scegliere" (assistente). Un negozio puo'
			 * accenderne una sola.
			 *
			 * Cio' che il servizio non dichiara non compare comunque, anche se
			 * qui e' acceso: le impostazioni dicono cosa si vuole, il contratto
			 * dice cosa si puo'.
			 */
			'modi'                => array( 'cerca', 'foto', 'chat' ),
			'etichetta_avvio'     => '',

			/*
			 * Dove finiscono i risultati della ricerca a parole.
			 *
			 *   pagina    si va alla pagina dei risultati, che e' un indirizzo
			 *             vero: si condivide, il tasto Indietro funziona, e c'e'
			 *             spazio per i filtri.
			 *   finestra  restano dentro il widget. Serve a chi non vuole una
			 *             pagina in piu' nel sito, o non la mette nel menu.
			 *
			 * La foto e l'assistente restano SEMPRE nella finestra: una foto non
			 * si puo' mettere in un indirizzo, e una conversazione non e' una
			 * pagina.
			 */
			'risultati'           => 'pagina',
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
				return self::base_ammessa( (string) $valore );

			case 'chiave':
			case 'workspace':
			case 'etichetta':
			case 'etichetta_avvio':
			case 'segnaposto':
			case 'saluto':
				return sanitize_text_field( (string) $valore );

			case 'posizione':
				return 'sinistra' === $valore ? 'sinistra' : 'destra';

			case 'palette':
				$ammesse = array_keys( \Storegentic\Frontend\Palette::preparate() );
				return in_array( $valore, $ammesse, true ) ? (string) $valore : 'tema';

			case 'colori':
				$puliti = array();
				foreach ( \Storegentic\Frontend\Palette::VOCI as $voce ) {
					$colore = isset( $valore[ $voce ] ) ? sanitize_hex_color( (string) $valore[ $voce ] ) : null;
					if ( $colore ) {
						$puliti[ $voce ] = $colore;
					}
				}
				return $puliti;

			case 'raggio':
				/*
				 * Oltre i venti pixel gli angoli si mangiano il contenuto dei
				 * comandi piccoli, e sotto i due non si distinguono da zero.
				 */
				return max( 0, min( 24, (int) $valore ) );

			case 'lotto':
				// Il server divide comunque in lotti da 1000: oltre non serve.
				return max( 25, min( 1000, (int) $valore ) );

			case 'frequenza':
				$ammesse = array_keys( wp_get_schedules() );
				return in_array( $valore, $ammesse, true ) ? (string) $valore : 'daily';

			case 'solo_su':
				$valore = is_array( $valore ) ? $valore : array();
				return array_values( array_filter( array_map( 'sanitize_key', $valore ) ) );

			case 'risultati':
				return 'finestra' === $valore ? 'finestra' : 'pagina';

			case 'modi':
				$ammessi = array( 'cerca', 'foto', 'chat' );
				$scelti  = array_values( array_intersect( $ammessi, array_map( 'sanitize_key', (array) $valore ) ) );

				/*
				 * Spegnere tutte le modalita' equivale a spegnere il plugin, ma
				 * in un modo che non si capisce: il pulsante sparisce e le
				 * impostazioni continuano a dire "attivo". Se non ne resta
				 * nessuna si torna alla ricerca, che e' la funzione di base.
				 */
				return empty( $scelti ) ? array( 'cerca' ) : $scelti;

			default:
				return (bool) $valore;
		}
	}

	/**
	 * La base del servizio, se e' un indirizzo a cui si puo' mandare la chiave.
	 *
	 * `esc_url_raw` da solo accetta qualsiasi host: http in chiaro, un
	 * indirizzo di rete interna, l'indirizzo dei metadati di un provider
	 * cloud. Chi riesce a scrivere questa impostazione potrebbe farsi
	 * spedire la chiave del negozio insieme a ogni handshake, e usare il
	 * server del negozio per bussare a servizi raggiungibili solo da dentro
	 * la rete.
	 *
	 * Qui si pretende https e un host pubblico. Se il valore non e'
	 * accettabile si tiene quello attuale invece di svuotare
	 * l'impostazione: un campo svuotato scollegherebbe il negozio.
	 */
	private static function base_ammessa( string $grezzo ): string {
		$attuale = (string) ( get_option( self::CHIAVE, array() )['base'] ?? self::predefinite()['base'] );
		$url     = untrailingslashit( esc_url_raw( trim( $grezzo ) ) );

		if ( '' === $url ) {
			return $attuale;
		}

		$pezzi = wp_parse_url( $url );

		if ( ! is_array( $pezzi ) || empty( $pezzi['host'] ) ) {
			return $attuale;
		}

		// In chiaro la chiave viaggerebbe leggibile: mai.
		if ( 'https' !== strtolower( (string) ( $pezzi['scheme'] ?? '' ) ) ) {
			return $attuale;
		}

		$host = strtolower( (string) $pezzi['host'] );

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true ) ) {
			return $attuale;
		}

		/*
		 * Se l'host e' gia' un indirizzo IP si controlla che sia pubblico.
		 * Non si risolve un nome a dominio: la risoluzione al momento del
		 * salvataggio non dice nulla su dove puntera' al momento della
		 * chiamata, e darebbe una falsa sicurezza.
		 */
		if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var(
			$host,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) ) {
			return $attuale;
		}

		return $url;
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
