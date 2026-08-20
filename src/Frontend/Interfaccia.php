<?php
/**
 * L'interfaccia sul sito: cosa si carica, dove, e con quali testi.
 *
 * QUESTO FILE NON DISEGNA NIENTE. Decide se il plugin deve comparire su questa
 * pagina, carica il foglio di stile e lo script, e passa al browser i testi
 * tradotti e le impostazioni. Il disegno sta in Frontend\Finestra.
 *
 * UN COMANDO SOLO, TRE MODI. Prima erano due cose separate — un pannello per
 * la ricerca e un riquadro per l'assistente — con due comandi diversi. Su
 * questo negozio funzionava; su un negozio qualunque no, perche' meta' del
 * plugin dipendeva da un elemento del tema che poteva non esserci. Oggi c'e'
 * un pulsante, disegnato dal plugin, e una finestra che contiene tutto:
 * cercare a parole, cercare con una foto, chiedere all'assistente.
 *
 * TUTTI I TESTI PASSANO DI QUI, TRADOTTI. Il JavaScript non ne scrive
 * nessuno: un plugin che stampa frasi scritte nel codice non si puo'
 * tradurre, e chi lo installa in un'altra lingua se ne accorge quando e' gia'
 * in produzione.
 *
 * Niente CSS del tema viene toccato. Tutte le classi hanno il prefisso `sg-`
 * e i valori di stile passano da variabili con un ripiego: un tema che vuole
 * adattare l'aspetto ridefinisce le variabili, senza dover combattere con la
 * specificita' dei selettori.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Api\Contratto;
use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Interfaccia {

	public static function avvia(): void {
		add_shortcode( 'storegentic', array( self::class, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'risorse' ) );
		add_action( 'wp_footer', array( self::class, 'pannello' ) );
	}

	/** Il plugin deve comparire su questa pagina? */
	private static function visibile(): bool {
		if ( ! Impostazioni::leggi( 'attivo' ) || ! Impostazioni::configurato() ) {
			return false;
		}

		/*
		 * Si guarda SOLO il contratto gia' in cache, mai chiedendone uno
		 * nuovo. Questa funzione gira su ogni pagina pubblica: con una
		 * chiamata all'handshake qui dentro, alla scadenza della cache il
		 * primo visitatore avrebbe pagato l'handshake nel proprio tempo di
		 * caricamento, e con il servizio irraggiungibile lo avrebbero pagato
		 * tutti, ognuno con i tentativi e le attese del client.
		 *
		 * Il contratto lo rinnova il cron o l'amministratore. Finche' non
		 * c'e', il negozio si comporta come se Storegentic non fosse
		 * configurato: nessun comando in pagina.
		 */
		/*
		 * Basta che ci sia UN modo disponibile. Prima si pretendeva la ricerca,
		 * e un negozio che avesse solo l'assistente non vedeva nulla.
		 */
		if ( empty( Finestra::modi() ) ) {
			return false;
		}

		// La pagina dei risultati ha bisogno di tutto, ovunque sia.
		if ( Pagina::nostra() ) {
			return true;
		}

		$solo_su = (array) Impostazioni::leggi( 'solo_su' );

		if ( empty( $solo_su ) ) {
			return true;
		}

		foreach ( $solo_su as $dove ) {
			switch ( $dove ) {
				case 'home':
					if ( is_front_page() ) { return true; }
					break;
				case 'catalogo':
					if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) { return true; }
					break;
				case 'prodotto':
					if ( function_exists( 'is_product' ) && is_product() ) { return true; }
					break;
				case 'carrello':
					if ( function_exists( 'is_cart' ) && is_cart() ) { return true; }
					break;
			}
		}

		return false;
	}

	public static function risorse(): void {
		if ( ! self::visibile() ) {
			return;
		}

		$base = plugin_dir_url( \Storegentic\FILE_PRINCIPALE );

		wp_enqueue_style( 'storegentic', $base . 'assets/css/storegentic.css', array(), self::versione( 'assets/css/storegentic.css' ) );
		wp_enqueue_script( 'storegentic', $base . 'assets/js/storegentic.js', array(), self::versione( 'assets/js/storegentic.js' ), true );

		/*
		 * I risultati contengono pulsanti "Aggiungi" con il markup di
		 * WooCommerce. Quel markup lo anima uno script di WooCommerce che
		 * fuori dalle pagine del negozio non viene caricato: senza questa
		 * riga il pulsante ricaricherebbe la pagina invece di aggiungere in
		 * silenzio, e il contatore del carrello non si aggiornerebbe.
		 */
		if ( wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}

		$i = Impostazioni::tutte();

		wp_localize_script(
			'storegentic',
			'storegenticConfig',
			array(
				/*
				 * Qui non c'e' la chiave: il browser chiama WordPress, che
				 * poi chiama Storegentic. Vedi Frontend\Ponte.
				 */
				'ponte'      => esc_url_raw( rest_url( 'storegentic/v1' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'pagina'     => esc_url_raw( Pagina::indirizzo() ),
				'suPagina'   => Pagina::nostra(),
				'modi'       => array_keys( Finestra::modi() ),
				/*
				 * Se il negozio ha la pagina dei risultati, l'Invio ci porta:
				 * e' un indirizzo vero, si condivide e il tasto Indietro
				 * funziona. La finestra tiene i risultati solo quando quella
				 * pagina non c'e' — ed e' allora che deve bastare a se stessa.
				 */
				'inPagina'   => 'pagina' === (string) $i['risultati'] && Pagina::disponibile(),
				'posizione'  => (string) $i['posizione'],
				'segnaposto' => '' !== $i['segnaposto'] ? (string) $i['segnaposto'] : __( 'Che cosa stai cercando?', 'storegentic' ),
				'saluto'     => '' !== $i['saluto'] ? (string) $i['saluto'] : __( 'Dimmi che cosa cerchi o per chi è il regalo: ti propongo qualcosa.', 'storegentic' ),
				'analitica'  => (bool) $i['analitica'],
				/*
				 * Il peso massimo di una foto dopo il rimpicciolimento nel
				 * browser. Il server ne accetta fino a quattro megabyte, ma
				 * spedirli da un telefono in 4G vuol dire venti secondi di
				 * attesa: si rimpicciolisce prima di partire.
				 */
				'fotoLato'   => 1024,
				/*
				 * Quando e' acceso, il plugin si prende la ricerca del sito:
				 * i comandi di ricerca del tema aprono il pannello di
				 * Storegentic invece del modulo di WordPress.
				 *
				 * I selettori non sono legati a questo tema. Sono le forme
				 * piu' diffuse — un modulo con role="search", le classi che
				 * WordPress genera da sempre — piu' un filtro per i temi che
				 * usano nomi propri. Un plugin universale non puo' conoscere
				 * il markup di chi lo installa: puo' solo riconoscere le
				 * convenzioni e lasciare una porta aperta per le eccezioni.
				 */
				'sostituisci' => (bool) $i['sostituisci_ricerca'],
				'inneschi'    => array_values(
					array_unique(
						array_map(
							'strval',
							(array) apply_filters(
								'storegentic_inneschi_ricerca',
								array(
									'[data-storegentic]',
									'form[role="search"]',
									'form.search-form',
									'form.woocommerce-product-search',
									'.search-toggle',
									'[data-oni-apri-ricerca]',
								)
							)
						)
					)
				),
				/*
				 * DUE ELENCHI DIVERSI, PER DUE STRUMENTI DIVERSI.
				 *
				 * Ne avevo usato uno solo, e i suggerimenti del pannello di
				 * ricerca erano domande da assistente. Chi ne toccava una
				 * lanciava una ricerca su "Che cosa abbinate a un vestito nero?"
				 * e finiva su una pagina che diceva "nessun risultato". Misurato
				 * sul servizio: quella domanda torna 0 risultati, mentre
				 * "gioielli per un vestito nero" ne torna 48.
				 * Non e' un difetto della ricerca — la forma interrogativa non
				 * e' una descrizione di prodotto — ed era il suggerimento a
				 * mandare le persone nel posto sbagliato.
				 *
				 * La ricerca suggerisce cose da cercare; l'assistente suggerisce
				 * cose da chiedere.
				 */
				'esempi'      => array_values( (array) apply_filters( 'storegentic_esempi_ricerca', array(
					__( 'una cosa che regge il freddo', 'storegentic' ),
					__( 'qualcosa di leggero per l’estate', 'storegentic' ),
					__( 'un regalo sotto i 50 euro', 'storegentic' ),
					__( 'come questa, ma di un altro colore', 'storegentic' ),
				) ) ),
				/*
				 * LE CATEGORIE DEL NEGOZIO, per lo stato d'apertura.
				 *
				 * Una finestra che si apre su quattro righe di suggerimenti e
				 * settecento pixel di vuoto non dice cosa c'e' in negozio. Le
				 * categorie lo dicono in un colpo d'occhio, e sono un modo di
				 * cominciare per chi non ha una parola in mente.
				 *
				 * Si leggono da WooCommerce, quindi funzionano su qualunque
				 * catalogo senza configurare niente.
				 */
				'categorie'   => self::categorie_in_vetrina(),
				'esempiChat'  => array_values( (array) apply_filters( 'storegentic_esempi_assistente', array(
					__( 'Un regalo sotto i 60 €, per mia madre', 'storegentic' ),
					__( 'Che cosa mi consigliate per un matrimonio?', 'storegentic' ),
					__( 'Qual è la differenza fra questi due?', 'storegentic' ),
				) ) ),
				/*
				 * Tutti i testi passano da qui, tradotti: il JavaScript non ne
				 * scrive nessuno. Un plugin che stampa frasi scritte nel codice
				 * non si puo' tradurre, e chi lo installa in un'altra lingua se
				 * ne accorge quando e' gia' in produzione.
				 */
				'testi'      => array(
					'chiudi'        => __( 'Chiudi', 'storegentic' ),
					'cerca'         => __( 'Cerca', 'storegentic' ),
					'inCorso'       => __( 'Sto cercando…', 'storegentic' ),
					'nessuno'       => __( 'Nessun risultato. Prova con parole diverse.', 'storegentic' ),
					'errore'        => __( 'La ricerca non ha risposto. Riprova fra poco.', 'storegentic' ),
					/*
					 * Si dice cosa e' successo e cosa si sta guardando, senza
					 * scuse e senza gergo: chi legge deve capire in una riga
					 * perche' i risultati potrebbero essere meno precisi del
					 * solito. Vedi Frontend\Ripiego.
					 */
					'ripiego'       => __( 'La ricerca intelligente non risponde in questo momento: questi risultati arrivano dal catalogo del negozio, cercando le parole che hai scritto.', 'storegentic' ),
					'recenti'       => __( 'Le tue ultime ricerche', 'storegentic' ),
					'suggeriti'     => __( 'Prova con', 'storegentic' ),
					'sfoglia'       => __( 'Oppure sfoglia', 'storegentic' ),
					/* translators: %s: le parole scritte da chi cerca. */
					'cercaNel'      => __( 'Cerca «%s» nel catalogo', 'storegentic' ),
					'tutti'         => __( 'Apri la pagina dei risultati', 'storegentic' ),
					'pulisci'       => __( 'Cancella', 'storegentic' ),

					'ordina'        => __( 'Ordina i risultati', 'storegentic' ),
					'piuPertinenti' => __( 'Più pertinenti', 'storegentic' ),
					'prezzoSu'      => __( 'Prezzo crescente', 'storegentic' ),
					'prezzoGiu'     => __( 'Prezzo decrescente', 'storegentic' ),
					'unGioiello'    => __( '1 risultato', 'storegentic' ),
					/* translators: %d: quanti risultati. */
					'nGioielli'     => __( '%d risultati', 'storegentic' ),

					'fotoTitolo'    => __( 'Cerca con una foto', 'storegentic' ),
					'fotoSpiega'    => __( 'Scegli una foto o trascinala qui: ti mostro i prodotti che le somigliano di più.', 'storegentic' ),
					'fotoInCorso'   => __( 'Sto guardando la foto…', 'storegentic' ),
					'fotoErrore'    => __( 'Non riesco a leggere questa foto. Prova con un altro file.', 'storegentic' ),
					'fotoSimili'    => __( 'I più simili alla tua foto', 'storegentic' ),
					'fotoAltra'     => __( 'Cambia foto', 'storegentic' ),

					'sto'           => __( 'Sto pensando…', 'storegentic' ),
					'fermata'       => __( 'Va bene, ho smesso.', 'storegentic' ),
					'assErrore'     => __( 'Non riesco a rispondere adesso. Riprova fra poco.', 'storegentic' ),
				),
			)
		);

		wp_add_inline_style( 'storegentic', self::variabili() );
	}

	/**
	 * La versione di un file, presa dal file stesso.
	 *
	 * PERCHE' NON IL NUMERO DEL PLUGIN. Il numero di versione cambia quando si
	 * pubblica; il contenuto di un foglio di stile cambia molte volte prima di
	 * arrivarci. In quell'intervallo l'indirizzo resta identico e il contenuto
	 * no: un browser che ha gia' scaricato quel file continua a usare la copia
	 * vecchia, e non c'e' modo di dirgli il contrario.
	 *
	 * Successo davvero, e non in teoria: un telefono aveva in cache un foglio
	 * di stile precedente sotto lo stesso indirizzo, e mostrava la pagina dei
	 * risultati senza alcuno stile — schede impilate come testo, collegamenti
	 * blu sottolineati — mentre su ogni altro dispositivo era corretta.
	 *
	 * Con la data di modifica del file, un contenuto nuovo ha sempre un
	 * indirizzo nuovo. E' anche cio' che fa gia' il tema di questo negozio.
	 */
	private static function versione( string $relativo ): string {
		$percorso = \Storegentic\PERCORSO . '/' . ltrim( $relativo, '/' );
		$quando   = is_readable( $percorso ) ? filemtime( $percorso ) : false;

		return false !== $quando ? (string) $quando : \Storegentic\VERSIONE;
	}

	/**
	 * Le categorie da mostrare quando la finestra si apre.
	 *
	 * Solo quelle di primo livello e non vuote, ordinate per numero di
	 * prodotti: sono la mappa del negozio, non l'albero completo. Il risultato
	 * si conserva un'ora, perche' e' uguale per tutti i visitatori e cambia
	 * quando cambia il catalogo, non quando cambia la pagina.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function categorie_in_vetrina(): array {
		$pronte = get_transient( 'storegentic_categorie_vetrina' );

		if ( is_array( $pronte ) ) {
			return $pronte;
		}

		$voci = array();

		if ( taxonomy_exists( 'product_cat' ) ) {
			$termini = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => 0,
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 8,
				)
			);

			if ( ! is_wp_error( $termini ) ) {
				foreach ( $termini as $t ) {
					$url = get_term_link( $t );

					if ( is_wp_error( $url ) ) {
						continue;
					}

					$voci[] = array(
						'etichetta' => (string) $t->name,
						'conteggio' => (int) $t->count,
						'url'       => (string) $url,
					);
				}
			}
		}

		set_transient( 'storegentic_categorie_vetrina', $voci, HOUR_IN_SECONDS );

		return $voci;
	}

	/**
	 * Le scelte dell'amministrazione diventano variabili CSS.
	 *
	 * Due classi, due domande: Palette dice di che colore e', Forma dice com'e'
	 * fatta. Escono insieme perche' vanno nello stesso blocco `:root`, ma
	 * restano separate perche' chi le compila ragiona in due modi diversi.
	 */
	private static function variabili(): string {
		return Palette::css() . Forma::css();
	}

	/**
	 * Lo shortcode [storegentic]: un comando dentro il contenuto.
	 *
	 * Non e' una seconda ricerca. E' un pulsante che apre la stessa finestra
	 * del lanciatore, perche' due campi di ricerca diversi nella stessa pagina
	 * sono due comportamenti da spiegare invece di uno.
	 *
	 * Serve a chi vuole la ricerca dentro una pagina — in una sezione della
	 * home, in fondo a un articolo — senza dipendere dal pulsante fisso.
	 *
	 * @param array<string,mixed>|string $attributi
	 */
	public static function shortcode( $attributi = array() ): string {
		if ( ! self::visibile() ) {
			return '';
		}

		$a = shortcode_atts(
			array(
				'etichetta' => '',
				'modo'      => '',
			),
			is_array( $attributi ) ? $attributi : array(),
			'storegentic'
		);

		$modi      = Finestra::modi();
		$modo      = isset( $modi[ $a['modo'] ] ) ? (string) $a['modo'] : '';
		$etichetta = '' !== $a['etichetta'] ? (string) $a['etichetta'] : Finestra::etichetta();

		ob_start();
		?>
		<button type="button" class="sg-invito" data-sg-apri
		        <?php echo '' !== $modo ? 'data-sg-modo-iniziale="' . esc_attr( $modo ) . '"' : ''; ?>
		        aria-haspopup="dialog" aria-controls="sg-finestra">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
			     stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
				<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.6-3.6"></path>
			</svg>
			<?php echo esc_html( $etichetta ); ?>
		</button>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pannello e assistente, stampati una volta sola a fine pagina.
	 *
	 * Stanno in fondo al documento e non dentro il contenuto perche' devono
	 * poter coprire la pagina senza dipendere da dove si trova il comando che
	 * li apre: un contenitore del tema con `overflow: hidden` li taglierebbe.
	 */
	public static function pannello(): void {
		if ( ! self::visibile() ) {
			return;
		}

		Finestra::lanciatore();
		Finestra::disegna();
	}
}
