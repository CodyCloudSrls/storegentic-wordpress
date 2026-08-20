<?php
/**
 * Sincronizzazione del catalogo: macchina a stati a due fasi.
 *
 * -----------------------------------------------------------------------------
 * PERCHE' UNA MACCHINA A STATI E NON UN CICLO
 *
 * Il caricamento del catalogo ha due fasi:
 *
 *   FASE A  /catalog/upsert     si manda il catalogo a pagine
 *   FASE B  /catalog/reconcile  si dice al server "ho finito", e il server
 *                               cancella tutto cio' che non ha visto in
 *                               questa sessione
 *
 * La fase B e' distruttiva per costruzione. Se si chiama dopo aver spedito
 * solo meta' del catalogo — perche' il processo e' andato in timeout, perche'
 * una pagina ha fallito, perche' il negozio ha 40.000 prodotti e PHP si e'
 * fermato al minuto — il server cancella l'altra meta'. Il catalogo del
 * cliente si svuota, e nessuno se ne accorge finche' la ricerca non
 * restituisce zero risultati.
 *
 * Per questo la riconciliazione NON e' un'istruzione che segue il ciclo: e'
 * uno stato che si raggiunge solo se ogni singola pagina e' andata a buon
 * fine. Lo stato vive nel database, non in memoria, quindi sopravvive a un
 * timeout, a un riavvio e a un cron che riparte.
 *
 * Stati:
 *   inattiva    nessuna sincronizzazione in corso
 *   in_corso    si stanno spedendo pagine; `pagina` dice a che punto siamo
 *   da_chiudere tutte le pagine sono passate, manca solo la riconciliazione
 *   fallita     una pagina non e' passata: la riconciliazione NON si fa
 *
 * Da `fallita` non si va a `da_chiudere`. Si riparte da capo.
 *
 * -----------------------------------------------------------------------------
 * PROVA A VUOTO
 *
 * `reconcile` accetta `dryRun`: il server calcola cosa cancellerebbe e lo
 * dice, senza cancellare. Il plugin la usa prima di ogni riconciliazione
 * vera quando la potatura supera una soglia: se una sincronizzazione
 * dichiara di voler cancellare mezzo catalogo, quasi sempre e' rotta la
 * sincronizzazione, non il catalogo.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Catalogo;

use Storegentic\Api\Client;
use Storegentic\Api\Contratto;
use Storegentic\Impostazioni;
use Storegentic\Negozio;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sincronizzazione {

	private const STATO = \Storegentic\PREFISSO_OPZIONI . 'sincro_stato';
	private const DIARIO = \Storegentic\PREFISSO_OPZIONI . 'sincro_diario';

	public const INATTIVA    = 'inattiva';
	public const IN_CORSO    = 'in_corso';
	public const DA_CHIUDERE = 'da_chiudere';
	public const FALLITA     = 'fallita';

	/**
	 * Oltre questa quota di catalogo da cancellare, la riconciliazione si
	 * ferma e chiede conferma. Mezzo catalogo che sparisce e' quasi sempre
	 * un difetto della sincronizzazione, non una scelta del negoziante.
	 */
	private const SOGLIA_POTATURA = 0.30;

	/* ------------------------------------------------------------- stato */

	/**
	 * @return array<string,mixed>
	 */
	public static function stato(): array {
		$stato = get_option( self::STATO, array() );

		if ( ! is_array( $stato ) || empty( $stato['fase'] ) ) {
			return array(
				'fase'      => self::INATTIVA,
				'pagina'    => 0,
				'pagine'    => 0,
				'inviati'   => 0,
				'totale'    => 0,
				'iniziata'  => 0,
				'conclusa'  => 0,
				'errore'    => '',
				'sessione'  => '',
			);
		}

		return $stato;
	}

	/**
	 * @param array<string,mixed> $modifiche
	 */
	private static function aggiorna( array $modifiche ): void {
		update_option( self::STATO, array_merge( self::stato(), $modifiche ), false );
	}

	public static function in_corso(): bool {
		return in_array( self::stato()['fase'], array( self::IN_CORSO, self::DA_CHIUDERE ), true );
	}

	/* ------------------------------------------------------------ avvio */

	/**
	 * Prepara una nuova sessione.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function avvia() {
		if ( ! Impostazioni::configurato() ) {
			return new WP_Error( 'storegentic_non_configurato', __( 'Inserisci la chiave del negozio.', 'storegentic' ) );
		}

		if ( self::in_corso() ) {
			return new WP_Error( 'storegentic_gia_in_corso', __( 'Una sincronizzazione è già in corso.', 'storegentic' ) );
		}

		if ( ! Contratto::puo( 'catalogIngest' ) && '' === Contratto::endpoint( 'catalogUpsert' ) ) {
			return new WP_Error(
				'storegentic_ingest_non_disponibile',
				__( 'Il servizio non dichiara il caricamento del catalogo per questo negozio.', 'storegentic' )
			);
		}

		$ids = self::identificativi();

		if ( empty( $ids ) ) {
			return new WP_Error( 'storegentic_catalogo_vuoto', __( 'Non ci sono prodotti da sincronizzare.', 'storegentic' ) );
		}

		$lotto = (int) Impostazioni::leggi( 'lotto' );

		update_option(
			self::STATO,
			array(
				'fase'     => self::IN_CORSO,
				'pagina'   => 0,
				'pagine'   => (int) ceil( count( $ids ) / $lotto ),
				/*
				 * Il lotto si congela con la sessione, come gli identificativi.
				 * Rileggerlo a ogni passo produce un danno silenzioso: le
				 * pagine sono calcolate all'avvio, ma l'offset no. Se
				 * l'amministratore alza il lotto a meta' sincronizzazione
				 * l'offset salta in avanti e un blocco di prodotti non viene
				 * spedito da nessuna pagina; se lo abbassa, la sessione si
				 * ferma a `pagine` avendo coperto solo una parte dell'elenco.
				 * In entrambi i casi la riconciliazione poi cancella
				 * dall'indice prodotti che sono vivi a catalogo.
				 */
				'lotto'    => $lotto,
				'inviati'  => 0,
				'totale'   => count( $ids ),
				'iniziata' => time(),
				'conclusa' => 0,
				'errore'   => '',
				'sessione' => wp_generate_password( 12, false, false ),
				'ids'      => $ids,
			),
			false
		);

		self::annota( sprintf( 'Sessione avviata: %d prodotti in %d pagine.', count( $ids ), (int) ceil( count( $ids ) / $lotto ) ) );

		return self::stato();
	}

	/* ------------------------------------------------------------- passo */

	/**
	 * Esegue un passo: una pagina, oppure la riconciliazione finale.
	 *
	 * Un passo per chiamata, non un ciclo: cosi' ogni passo sta dentro il
	 * tempo massimo di esecuzione, e se il processo muore lo stato resta
	 * scritto e il passo successivo riprende da dove si era arrivati.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function passo() {
		$stato = self::stato();

		switch ( $stato['fase'] ) {
			case self::IN_CORSO:
				return self::invia_pagina( $stato );

			case self::DA_CHIUDERE:
				return self::riconcilia();

			default:
				return new WP_Error( 'storegentic_nessuna_sessione', __( 'Nessuna sincronizzazione in corso.', 'storegentic' ) );
		}
	}

	/**
	 * @param array<string,mixed> $stato
	 * @return array<string,mixed>|WP_Error
	 */
	private static function invia_pagina( array $stato ) {
		$percorso = Contratto::endpoint( 'catalogUpsert' );

		if ( '' === $percorso ) {
			return self::fallisci( __( 'Il servizio non dichiara l\'indirizzo per il caricamento del catalogo.', 'storegentic' ) );
		}

		// Il lotto della sessione, non quello attuale: vedi avvia().
		$lotto   = (int) ( $stato['lotto'] ?? Impostazioni::leggi( 'lotto' ) );
		$ids     = is_array( $stato['ids'] ?? null ) ? $stato['ids'] : array();
		$fetta   = array_slice( $ids, (int) $stato['pagina'] * $lotto, $lotto );

		if ( empty( $fetta ) ) {
			// Non c'e' piu' niente da mandare: si passa alla chiusura.
			self::aggiorna( array( 'fase' => self::DA_CHIUDERE ) );
			return self::stato();
		}

		$prodotti = array();

		/*
		 * Da che cosa si parte dipende dal sito: prodotti se c'e' WooCommerce,
		 * contenuti altrimenti. Cio' che si spedisce ha la stessa forma nei due
		 * casi, quindi da qui in giu' non cambia nulla. Vedi Negozio.
		 */
		foreach ( $fetta as $id ) {
			if ( Negozio::c_e() ) {
				$p = wc_get_product( $id );

				if ( $p instanceof \WC_Product ) {
					$prodotti[] = Mappatore::prodotto( $p );
				}

				continue;
			}

			$post = get_post( (int) $id );

			if ( $post instanceof \WP_Post ) {
				$prodotti[] = Contenuti::contenuto( $post );
			}
		}

		if ( empty( $prodotti ) ) {
			/*
			 * Nessun prodotto valido in questa pagina: puo' capitare se sono
			 * stati eliminati fra l'avvio e adesso. Non e' un errore, ma la
			 * pagina si considera comunque spedita per non bloccarsi.
			 */
			self::avanza( $stato, 0 );
			return self::stato();
		}

		$carico = array( 'products' => $prodotti );

		// Le categorie viaggiano una volta sola, con la prima pagina.
		if ( 0 === (int) $stato['pagina'] && Impostazioni::leggi( 'invia_categorie' ) ) {
			$categorie = Mappatore::categorie_payload( self::termini_categoria() );
			if ( ! empty( $categorie ) ) {
				$carico['categories'] = $categorie;
			}
		}

		$client   = new Client();
		$risposta = $client->post( $percorso, $carico );

		if ( is_wp_error( $risposta ) ) {
			return self::fallisci(
				sprintf(
					/* translators: 1: numero di pagina, 2: messaggio d'errore. */
					__( 'Pagina %1$d non accettata: %2$s', 'storegentic' ),
					(int) $stato['pagina'] + 1,
					$risposta->get_error_message()
				)
			);
		}

		self::avanza( $stato, count( $prodotti ) );

		return self::stato();
	}

	/**
	 * @param array<string,mixed> $stato
	 */
	private static function avanza( array $stato, int $inviati ): void {
		$pagina = (int) $stato['pagina'] + 1;
		$fatte  = $pagina >= (int) $stato['pagine'];

		self::aggiorna(
			array(
				'pagina'  => $pagina,
				'inviati' => (int) $stato['inviati'] + $inviati,
				'fase'    => $fatte ? self::DA_CHIUDERE : self::IN_CORSO,
			)
		);
	}

	/* ------------------------------------------------------ chiusura */

	/**
	 * Fase B. Si arriva qui solo da `da_chiudere`, cioe' solo se ogni pagina
	 * e' passata.
	 *
	 * @param bool $conferma_potatura Se true, esegue anche se la potatura
	 *                                supera la soglia di guardia.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function riconcilia( bool $conferma_potatura = false ) {
		$stato = self::stato();

		if ( self::DA_CHIUDERE !== $stato['fase'] ) {
			return new WP_Error(
				'storegentic_chiusura_non_ammessa',
				__( 'La riconciliazione si esegue solo dopo che tutte le pagine sono state accettate.', 'storegentic' )
			);
		}

		$percorso = Contratto::endpoint( 'catalogReconcile' );

		if ( '' === $percorso ) {
			/*
			 * Senza indirizzo di riconciliazione il catalogo resta caricato
			 * ma non potato: e' uno stato accettabile, molto meglio di un
			 * catalogo svuotato. Si chiude con successo parziale.
			 */
			self::concludi( __( 'Catalogo caricato. Il servizio non dichiara la riconciliazione: gli articoli rimossi restano in indice.', 'storegentic' ) );
			return self::stato();
		}

		if ( ! Impostazioni::leggi( 'pota_mancanti' ) ) {
			self::concludi( __( 'Catalogo caricato. Potatura disattivata nelle impostazioni.', 'storegentic' ) );
			return self::stato();
		}

		$client = new Client();

		/*
		 * Prima a vuoto: si chiede al server cosa cancellerebbe. Costa una
		 * chiamata in piu' e vale il prezzo — e' l'unico modo di accorgersi
		 * PRIMA che una sincronizzazione difettosa stava per svuotare il
		 * catalogo.
		 */
		if ( ! $conferma_potatura ) {
			$prova = $client->post(
				$percorso,
				array(
					'dryRun'          => true,
					'pruneCategories' => (bool) Impostazioni::leggi( 'invia_categorie' ),
				)
			);

			if ( is_wp_error( $prova ) ) {
				return self::fallisci(
					sprintf(
						/* translators: %s: messaggio d'errore. */
						__( 'Verifica della riconciliazione fallita: %s', 'storegentic' ),
						$prova->get_error_message()
					)
				);
			}

			/*
			 * La guardia deve chiudersi, non aprirsi, quando non capisce la
			 * risposta. Se il server accetta la prova a vuoto in modo
			 * asincrono (202 senza corpo) o cambia il nome del campo, senza
			 * questo controllo `catalogSkus` varrebbe 0, la condizione
			 * sarebbe falsa e si passerebbe dritti alla cancellazione vera:
			 * l'unica rete di sicurezza contro lo svuotamento del catalogo si
			 * disattiverebbe in silenzio proprio quando serve di piu'.
			 */
			$noto        = isset( $prova['catalogSkus'] ) && is_numeric( $prova['catalogSkus'] );
			$in_catalogo = $noto ? (int) $prova['catalogSkus'] : 0;
			$da_potare   = (int) ( $prova['prunedSkus'] ?? 0 );

			if ( ! $noto || $in_catalogo <= 0 || ( $da_potare / $in_catalogo ) > self::SOGLIA_POTATURA ) {
				self::aggiorna(
					array(
						'fase'      => self::DA_CHIUDERE,
						'errore'    => '',
						'potatura'  => array(
							'in_catalogo' => $in_catalogo,
							'da_potare'   => $da_potare,
							'visti'       => (int) ( $prova['seenSkus'] ?? 0 ),
							'ignoto'      => ! $noto,
						),
					)
				);

				self::annota(
					$noto && $in_catalogo > 0
						? sprintf(
							'Riconciliazione sospesa: cancellerebbe %d SKU su %d (%d%%). Serve conferma.',
							$da_potare,
							$in_catalogo,
							(int) round( $da_potare / $in_catalogo * 100 )
						)
						: 'Riconciliazione sospesa: la prova a vuoto non ha detto quanti prodotti ci sono in indice. Serve conferma.'
				);

				return new WP_Error(
					'storegentic_potatura_ampia',
					$noto && $in_catalogo > 0
						? sprintf(
							/* translators: 1: SKU da cancellare, 2: SKU in catalogo. */
							__( 'La riconciliazione cancellerebbe %1$d prodotti su %2$d. Controlla la sincronizzazione prima di confermare.', 'storegentic' ),
							$da_potare,
							$in_catalogo
						)
						: __( 'La verifica non ha riportato quanti prodotti ci sono in indice, quindi non si può sapere quanti ne verrebbero tolti. La potatura resta sospesa.', 'storegentic' ),
					array( 'da_potare' => $da_potare, 'in_catalogo' => $in_catalogo, 'ignoto' => ! $noto )
				);
			}
		}

		$risposta = $client->post(
			$percorso,
			array(
				'dryRun'          => false,
				'pruneCategories' => (bool) Impostazioni::leggi( 'invia_categorie' ),
			)
		);

		if ( is_wp_error( $risposta ) ) {
			return self::fallisci(
				sprintf(
					/* translators: %s: messaggio d'errore. */
					__( 'Riconciliazione fallita: %s', 'storegentic' ),
					$risposta->get_error_message()
				)
			);
		}

		self::concludi(
			sprintf(
				/* translators: 1: prodotti inviati, 2: prodotti rimossi dall'indice. */
				__( 'Sincronizzazione completata: %1$d prodotti inviati, %2$d rimossi dall\'indice.', 'storegentic' ),
				(int) $stato['inviati'],
				(int) ( $risposta['prunedSkus'] ?? 0 )
			)
		);

		return self::stato();
	}

	/* ---------------------------------------------------------- esiti */

	private static function concludi( string $messaggio ): void {
		$stato = self::stato();

		update_option(
			self::STATO,
			array(
				'fase'     => self::INATTIVA,
				'pagina'   => (int) $stato['pagina'],
				'pagine'   => (int) $stato['pagine'],
				'inviati'  => (int) $stato['inviati'],
				'totale'   => (int) $stato['totale'],
				'iniziata' => (int) $stato['iniziata'],
				'conclusa' => time(),
				'errore'   => '',
				'sessione' => (string) $stato['sessione'],
			),
			false
		);

		update_option( \Storegentic\PREFISSO_OPZIONI . 'ultima_sincro', time(), false );
		self::annota( $messaggio );
	}

	private static function fallisci( string $messaggio ): WP_Error {
		self::aggiorna( array( 'fase' => self::FALLITA, 'errore' => $messaggio, 'conclusa' => time() ) );
		self::annota( 'ERRORE: ' . $messaggio );

		return new WP_Error( 'storegentic_sincro_fallita', $messaggio );
	}

	/** Azzera lo stato: si usa per ripartire dopo un fallimento. */
	public static function azzera(): void {
		delete_option( self::STATO );
		self::annota( 'Stato azzerato.' );
	}

	/* ------------------------------------------------------- catalogo */

	/**
	 * Gli identificativi dei prodotti da sincronizzare, decisi una volta
	 * sola all'avvio.
	 *
	 * Si congela l'elenco perche' la sessione dura: se si rileggesse a ogni
	 * pagina, un prodotto pubblicato a meta' sincronizzazione sposterebbe
	 * l'ordinamento e qualche prodotto verrebbe saltato o spedito due volte.
	 * L'ordinamento e' per identificativo, che non cambia.
	 *
	 * @return array<int,int>
	 */
	private static function identificativi(): array {
		// Senza negozio si indicizzano i contenuti del sito: vedi Catalogo\Contenuti.
		if ( ! Negozio::c_e() ) {
			return Contenuti::identificativi();
		}

		$stati = array( 'publish' );

		if ( Impostazioni::leggi( 'includi_bozze' ) ) {
			$stati[] = 'private';
		}

		$args = array(
			'status'  => $stati,
			'limit'   => -1,
			'return'  => 'ids',
			'orderby' => 'ID',
			'order'   => 'ASC',
		);

		if ( ! Impostazioni::leggi( 'includi_esauriti' ) ) {
			$args['stock_status'] = 'instock';
		}

		$ids = wc_get_products( $args );

		/**
		 * Permette di escludere prodotti dalla sincronizzazione.
		 *
		 * @param array<int,int> $ids
		 */
		return array_values( array_map( 'intval', (array) apply_filters( 'storegentic_prodotti_da_sincronizzare', $ids ) ) );
	}

	/**
	 * @return array<int,\WP_Term>
	 */
	private static function termini_categoria(): array {
		// Le categorie di prodotto esistono solo se esiste WooCommerce.
		if ( ! Negozio::c_e() || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$termini = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);

		return is_wp_error( $termini ) ? array() : $termini;
	}

	/* --------------------------------------------------------- diario */

	/**
	 * Un diario corto delle ultime operazioni.
	 *
	 * Non e' un log completo: sono le venti righe che servono a capire, in
	 * amministrazione, cosa e' successo l'ultima volta e perche'.
	 */
	public static function annota( string $riga ): void {
		$diario = get_option( self::DIARIO, array() );
		$diario = is_array( $diario ) ? $diario : array();

		array_unshift( $diario, array( 'quando' => time(), 'testo' => $riga ) );

		update_option( self::DIARIO, array_slice( $diario, 0, 20 ), false );
	}

	/**
	 * @return array<int,array{quando:int,testo:string}>
	 */
	public static function diario(): array {
		$diario = get_option( self::DIARIO, array() );

		return is_array( $diario ) ? $diario : array();
	}
}
