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
	 * Gli indirizzi ufficiali del servizio.
	 *
	 * E' l'UNICA cosa che il plugin sappia degli indirizzi di Storegentic:
	 * dove chiedere il contratto. Tutto il resto — quali funzioni esistono,
	 * quali indirizzi usare, quali limiti valgono — lo dichiara il contratto
	 * stesso a ogni installazione. Vedi Api\Contratto.
	 *
	 * Stanno in un elenco e non in una stringa libera perche' sbagliare a
	 * digitare un indirizzo qui scollega il negozio, e perche' quando il
	 * servizio ne aggiunge uno basta aggiornare il plugin invece di scrivere a
	 * ogni cliente.
	 *
	 * @var array<string,string>
	 */
	public const INDIRIZZI = array(
		'https://api.storegentic.eu'   => 'Produzione',
		'https://app.storegentic.eu'   => 'Produzione (app)',
		'https://embed.storegentic.eu' => 'Produzione (embed)',
	);

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
			'base'                => 'https://api.storegentic.eu',
			'chiave'              => '',
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
			 * LA FORMA DELL'INTERFACCIA. I colori dicono di chi e' il negozio;
			 * questi dicono come si comporta. Vedi Frontend\Forma, dove sta
			 * anche il perche' di ogni voce.
			 *
			 * Le misure sono in DECIMI di rem, interi: `larghezza => 680` vuol
			 * dire 68rem. Un intero si sanifica senza ambiguita' e non porta
			 * dietro il problema della virgola contro il punto decimale, che
			 * cambia da lingua a lingua.
			 */
			'forma'               => 'centro',   // centro | laterale | basso | pieno
			'larghezza'           => 680,        // 68rem
			'altezza'             => 520,        // 52rem
			'pulsante'            => 'pillola',  // pillola | tondo | barra
			'distanza'            => 10,         // 1rem dal bordo
			'densita'             => 'comoda',   // comoda | compatta
			'colonna'             => 130,        // 13rem: la scheda piu' stretta
			'velo'                => 100,        // per cento del velo disegnato
			'sfocatura'           => true,
			'movimento'           => true,
			'caratteri'           => 'tema',     // tema | sistema | grazie | stretto

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
			'segnaposto'          => '',
			'saluto'              => '',
			'solo_su'             => array(),   // vuoto = ovunque
			'sostituisci_ricerca' => false,

			/*
			 * Quando il servizio non risponde, si cerca nel catalogo del negozio.
			 *
			 * Acceso di serie perche' l'alternativa e' una schermata d'errore su
			 * una vetrina: succede a quota finita, a servizio in manutenzione e a
			 * rete dell'hosting ballerina. Vedi Frontend\Ripiego.
			 */
			'ripiego'             => true,

			/*
			 * I parametri che il contratto dichiara regolabili. Zero e stringa
			 * vuota vogliono dire "usa il valore del servizio": e' diverso dallo
			 * scrivere quel valore, perche' se il servizio lo cambia il negozio
			 * lo segue senza che nessuno tocchi nulla. Vedi Api\Parametri.
			 */
			'quanti'              => 0,
			'quanti_foto'         => 0,
			'soglia'              => '',
			'soglia_foto'         => '',

			/*
			 * La ricerca istantanea nei suggerimenti mentre si scrive. Si puo'
			 * spegnere: non consuma quota, ma e' pur sempre una chiamata di rete
			 * a ogni parola digitata. Vedi Frontend\Suggerimenti.
			 */
			'istantanea'          => true,

			/*
			 * Cosa si manda all'indice.
			 *
			 * Con WooCommerce sono i prodotti, e questo elenco non si usa. Senza,
			 * il plugin fa da base di conoscenza del sito e indicizza i tipi di
			 * contenuto scelti qui. Vedi Negozio e Catalogo\Contenuti.
			 */
			'tipi'                => array( 'page', 'post' ),

			// Catalogo
			'sincro_automatica'   => true,
			'frequenza'           => 'daily',
			'lotto'               => 200,
			'includi_bozze'       => false,
			'includi_esauriti'    => true,
			'invia_categorie'     => true,
			'pota_mancanti'       => true,

			/*
			 * Due interruttori, due domande diverse.
			 *
			 *   analitica    manda a Storegentic cosa si cerca. Serve al servizio
			 *                per migliorare le risposte.
			 *   statistiche  tiene il conto qui dentro, per il pannello. Serve a
			 *                chi gestisce il negozio per sapere cosa cercano i
			 *                clienti e cosa non trovano.
			 *
			 * Sono separati perche' un negozio puo' volere la seconda senza la
			 * prima: i propri dati li vuole vedere, ma non li vuole spedire.
			 * Vedi Analitica\Registratore e Analitica\Misure.
			 */
			'analitica'           => true,
			'statistiche'         => true,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function tutte(): array {
		$salvate = get_option( self::CHIAVE, array() );
		$salvate = is_array( $salvate ) ? $salvate : array();

		/*
		 * LE CHIAVI CHE NON ESISTONO PIU' NON TORNANO INDIETRO.
		 *
		 * Con un semplice array_merge, un'impostazione tolta dal plugin restava
		 * nell'opzione per sempre: continuava a essere letta, a essere
		 * scrivibile da salva() — che accetta le chiavi presenti in tutte() — e
		 * a comparire in ogni esportazione del database. Su questo negozio erano
		 * rimaste quattro voci di due versioni prima: `modalita`, `assistente`,
		 * `colore` e `colore_testo`, tutte senza piu' un solo lettore nel codice.
		 *
		 * L'intersezione le lascia fuori alla lettura; il primo salvataggio
		 * riscrive l'opzione senza di loro, e spariscono davvero.
		 */
		return array_merge( self::predefinite(), array_intersect_key( $salvate, self::predefinite() ) );
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
			case 'etichetta_avvio':
			case 'segnaposto':
			case 'saluto':
				return sanitize_text_field( (string) $valore );

			case 'posizione':
				return 'sinistra' === $valore ? 'sinistra' : 'destra';

			case 'forma':
				return isset( \Storegentic\Frontend\Forma::forme()[ $valore ] ) ? (string) $valore : 'centro';

			case 'pulsante':
				return isset( \Storegentic\Frontend\Forma::pulsanti()[ $valore ] ) ? (string) $valore : 'pillola';

			case 'densita':
				return isset( \Storegentic\Frontend\Forma::densita()[ $valore ] ) ? (string) $valore : 'comoda';

			case 'caratteri':
				return isset( \Storegentic\Frontend\Forma::caratteri()[ $valore ] ) ? (string) $valore : 'tema';

			/*
			 * LE MISURE HANNO UN MINIMO E UN MASSIMO, E NON SONO CAPRICCI.
			 *
			 * Sotto le 32rem una finestra non tiene due colonne di prodotti
			 * nemmeno su un monitor grande; sopra le 120rem le righe di testo
			 * diventano troppo lunghe per seguirle con l'occhio. Una scheda
			 * sotto le 9rem non mostra una fotografia riconoscibile, e sopra le
			 * 28rem su un telefono se ne vede una sola per schermata.
			 */
			case 'larghezza':
				return max( 320, min( 1200, (int) $valore ) );

			case 'altezza':
				return max( 300, min( 1200, (int) $valore ) );

			case 'colonna':
				return max( 90, min( 280, (int) $valore ) );

			case 'distanza':
				return max( 0, min( 80, (int) $valore ) );

			case 'velo':
				/*
				 * Cento vuol dire "come e' disegnato", non "nero pieno": il
				 * colore del velo lo decide la palette — su un fondo scuro un
				 * velo nero non si vedrebbe — e questo numero dice solo quanto
				 * di quel velo si applica. Cosi' il valore predefinito lascia
				 * l'aspetto identico a prima.
				 */
				return max( 0, min( 100, (int) $valore ) );

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

			case 'tipi':
				/*
				 * Solo tipi di contenuto che esistono davvero e che sono
				 * pubblici: indicizzare un tipo interno vorrebbe dire mandare al
				 * servizio roba che sul sito non si vede, e farla poi comparire
				 * nei risultati con un indirizzo che porta a una pagina vuota.
				 */
				$ammessi = get_post_types( array( 'public' => true ), 'names' );

				return array_values( array_intersect( $ammessi, array_map( 'sanitize_key', (array) $valore ) ) );

			case 'quanti':
			case 'quanti_foto':
				// Zero vuol dire "decide il servizio": si lascia passare.
				$modo = 'quanti_foto' === $nome ? 'image' : 'text';

				return max( 0, min( \Storegentic\Api\Parametri::quanti_al_massimo( $modo ), (int) $valore ) );

			case 'soglia':
			case 'soglia_foto':
				/*
				 * La stringa vuota si conserva com'e' e vuol dire "non mandarla".
				 * Un `(float) ''` darebbe 0.0, che invece e' una soglia vera —
				 * la piu' larga possibile — e cambierebbe il comportamento di
				 * chi non ha scelto niente.
				 */
				if ( '' === trim( (string) $valore ) ) {
					return '';
				}

				return (string) max( 0, min( 1, (float) str_replace( ',', '.', (string) $valore ) ) );

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

	/**
	 * L'indirizzo risponde davvero?
	 *
	 * Si chiede il contratto e si guarda se torna qualcosa di sensato. Serve a
	 * non salvare un indirizzo che non risponde: cambiare quel campo su un
	 * negozio in funzione ne spegne ricerca, ricerca per foto e assistente, e
	 * lo si scopre guardando il sito, non il pannello.
	 *
	 * Non giudica la chiave: una chiave rifiutata (401, 403) dimostra che
	 * dall'altra parte c'e' il servizio, che e' quello che si sta verificando.
	 *
	 * @return true|string true, oppure il motivo per cui non va.
	 */
	public static function base_risponde( string $url ) {
		$risposta = wp_remote_post(
			untrailingslashit( $url ) . '/v1/commerce/plugin/handshake',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . (string) self::leggi( 'chiave' ),
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode( array( 'platform' => 'woocommerce', 'pluginVersion' => \Storegentic\VERSIONE ) ),
			)
		);

		if ( is_wp_error( $risposta ) ) {
			return $risposta->get_error_message();
		}

		$codice = (int) wp_remote_retrieve_response_code( $risposta );

		/*
		 * IL 403 NON CONTA COME "RISPONDE".
		 *
		 * Verificato sul campo: mentre gli indirizzi di produzione erano ancora
		 * in configurazione, Cloudflare rispondeva 403 con il codice 1010 —
		 * cioe' bloccava la richiesta prima ancora di passarla al servizio.
		 * Accettando il 403 questa funzione dava per buono un indirizzo che
		 * avrebbe spento ricerca e assistente sul negozio.
		 *
		 * Un 401 invece si accetta: quello lo risponde il servizio, e dice
		 * "ci sono, ma questa chiave non va" — che e' esattamente cio' che si
		 * sta verificando, visto che qui si prova l'INDIRIZZO e non la chiave.
		 */
		if ( in_array( $codice, array( 200, 201, 401 ), true ) ) {
			$corpo = json_decode( (string) wp_remote_retrieve_body( $risposta ), true );

			if ( is_array( $corpo ) ) {
				return true;
			}

			return __( 'L’indirizzo risponde, ma non con una risposta del servizio: probabilmente c’è un filtro davanti.', 'storegentic' );
		}

		/* translators: %d: codice di stato HTTP. */
		return sprintf( __( 'L’indirizzo risponde con un errore (%d): il servizio non è raggiungibile lì.', 'storegentic' ), $codice );
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
