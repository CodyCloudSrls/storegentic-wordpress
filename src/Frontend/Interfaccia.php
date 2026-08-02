<?php
/**
 * L'interfaccia sul negozio.
 *
 * DUE COSE, NON UNA. Il pannello di ricerca risponde a "trova questo"; l'
 * assistente risponde a "aiutami a scegliere". Sono due bisogni diversi e
 * hanno due comandi diversi: chi sa cosa vuole non deve passare da una
 * conversazione, e chi non lo sa non deve indovinare le parole giuste.
 *
 * Il pannello e' un trampolino, non un magazzino: mostra i primi risultati e
 * porta alla pagina della ricerca, che e' il posto dove si affina, si ordina
 * e si condivide un indirizzo.
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
		if ( '' === Contratto::endpoint_in_cache( 'search' ) ) {
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

	/** L'assistente si mostra solo se il servizio lo dichiara acceso. */
	private static function assistente_acceso(): bool {
		return (bool) Impostazioni::leggi( 'assistente' )
			&& '' !== Contratto::endpoint_in_cache( 'agentChat' );
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
				'modalita'   => (string) $i['modalita'],
				'posizione'  => (string) $i['posizione'],
				'segnaposto' => '' !== $i['segnaposto'] ? (string) $i['segnaposto'] : __( 'Che cosa stai cercando?', 'storegentic' ),
				'saluto'     => '' !== $i['saluto'] ? (string) $i['saluto'] : __( 'Dimmi che cosa cerchi o per chi è il regalo: ti propongo qualcosa.', 'storegentic' ),
				'assistente' => self::assistente_acceso(),
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
				'esempi'      => array_values( (array) apply_filters( 'storegentic_esempi_assistente', array(
					__( 'Un regalo per mia madre sotto i 60 €', 'storegentic' ),
					__( 'Che cosa abbinate a un vestito nero?', 'storegentic' ),
					__( 'Avete orecchini con perle?', 'storegentic' ),
				) ) ),
				'testi'      => array(
					'chiudi'        => __( 'Chiudi', 'storegentic' ),
					'cerca'         => __( 'Cerca', 'storegentic' ),
					'inCorso'       => __( 'Sto cercando…', 'storegentic' ),
					'nessuno'       => __( 'Nessun risultato. Prova con parole diverse.', 'storegentic' ),
					'errore'        => __( 'La ricerca non ha risposto. Riprova fra poco.', 'storegentic' ),
					'risultati'     => __( 'Risultati', 'storegentic' ),
					'categorie'     => __( 'Categorie suggerite', 'storegentic' ),
					'recenti'       => __( 'Le tue ultime ricerche', 'storegentic' ),
					'suggeriti'     => __( 'Prova con', 'storegentic' ),
					/* translators: %s: le parole scritte da chi cerca. */
					'cercaNel'      => __( 'Cerca «%s» nel catalogo', 'storegentic' ),
					'tutti'         => __( 'Vedi tutti i risultati', 'storegentic' ),
					'fotoInCorso'   => __( 'Sto guardando la foto…', 'storegentic' ),
					'fotoErrore'    => __( 'Non riesco a leggere questa foto. Prova con un altro file.', 'storegentic' ),
					'fotoTroppo'    => __( 'La foto è troppo grande.', 'storegentic' ),
					'fotoSimili'    => __( 'Gioielli che somigliano alla tua foto', 'storegentic' ),
					'assistente'    => __( 'Assistente', 'storegentic' ),
					'apriAssistente' => __( 'Chiedi all’assistente', 'storegentic' ),
					'scrivi'        => __( 'Scrivi la tua domanda', 'storegentic' ),
					'invia'         => __( 'Invia', 'storegentic' ),
					'sto'           => __( 'Sto pensando…', 'storegentic' ),
					'staCercando'   => __( 'Sto leggendo il catalogo. Ci vuole qualche secondo.', 'storegentic' ),
					'fermata'       => __( 'Va bene, ho smesso.', 'storegentic' ),
					'assErrore'     => __( 'Non riesco a rispondere adesso. Riprova fra poco.', 'storegentic' ),
					'pulisci'       => __( 'Ricomincia', 'storegentic' ),
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

	/** I colori scelti nell'amministrazione diventano variabili CSS. */
	private static function variabili(): string {
		return Palette::css();
	}

	/**
	 * @param array<string,mixed>|string $attributi
	 */
	public static function shortcode( $attributi = array() ): string {
		if ( ! self::visibile() ) {
			return '';
		}

		$a = shortcode_atts(
			array(
				'etichetta'  => (string) Impostazioni::leggi( 'etichetta' ),
				'segnaposto' => (string) Impostazioni::leggi( 'segnaposto' ),
			),
			is_array( $attributi ) ? $attributi : array(),
			'storegentic'
		);

		$segnaposto = '' !== $a['segnaposto'] ? $a['segnaposto'] : __( 'Che cosa stai cercando?', 'storegentic' );
		$id         = 'sg-campo-' . (string) wp_unique_id();

		ob_start();
		?>
		<form class="sg-barra" role="search" method="get" action="<?php echo esc_url( Pagina::indirizzo() ); ?>" data-sg-barra>
			<label class="sg-fuori-schermo" for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( '' !== $a['etichetta'] ? $a['etichetta'] : __( 'Cerca nel catalogo', 'storegentic' ) ); ?>
			</label>
			<input type="search" id="<?php echo esc_attr( $id ); ?>" name="q" class="sg-barra__campo" data-sg-campo
			       placeholder="<?php echo esc_attr( $segnaposto ); ?>" autocomplete="off">
			<button type="submit" class="sg-barra__invio"><?php esc_html_e( 'Cerca', 'storegentic' ); ?></button>
		</form>
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

		self::pannello_ricerca();

		if ( self::assistente_acceso() ) {
			self::widget_assistente();
		}
	}

	private static function pannello_ricerca(): void {
		$modalita = (string) Impostazioni::leggi( 'modalita' );
		?>
		<?php if ( 'fluttuante' === $modalita ) : ?>
		<button type="button" class="sg-bolla sg-bolla--<?php echo esc_attr( (string) Impostazioni::leggi( 'posizione' ) ); ?>"
		        data-storegentic aria-haspopup="dialog" aria-controls="sg-pannello">
			<span class="sg-fuori-schermo"><?php esc_html_e( 'Apri la ricerca', 'storegentic' ); ?></span>
			<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false" fill="none"
			     stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
				<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
			</svg>
		</button>
		<?php endif; ?>

		<div class="sg-pannello" id="sg-pannello" role="dialog" aria-modal="true"
		     aria-label="<?php esc_attr_e( 'Ricerca nel catalogo', 'storegentic' ); ?>" hidden>
			<div class="sg-pannello__velo" data-sg-chiudi></div>
			<div class="sg-pannello__foglio">
				<div class="sg-pannello__testa">
					<form class="sg-barra sg-barra--pannello" role="search" method="get"
					      action="<?php echo esc_url( Pagina::indirizzo() ); ?>" data-sg-barra>
						<label class="sg-fuori-schermo" for="sg-campo-pannello"><?php esc_html_e( 'Cerca nel catalogo', 'storegentic' ); ?></label>
						<svg class="sg-barra__lente" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"
						     fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
							<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
						</svg>
						<input type="search" id="sg-campo-pannello" name="q" class="sg-barra__campo" data-sg-campo
						       placeholder="<?php echo esc_attr( (string) ( Impostazioni::leggi( 'segnaposto' ) ?: __( 'Che cosa stai cercando?', 'storegentic' ) ) ); ?>"
						       autocomplete="off" enterkeyhint="search">
						<button type="button" class="sg-barra__pulisci" data-sg-pulisci hidden
						        aria-label="<?php esc_attr_e( 'Cancella la ricerca', 'storegentic' ); ?>">&times;</button>
						<button type="button" class="sg-barra__foto" data-sg-scegli-foto
						        aria-label="<?php esc_attr_e( 'Cerca con una foto', 'storegentic' ); ?>">
							<svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false" fill="none"
							     stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
								<path d="M3 8.5A1.5 1.5 0 014.5 7h2L8 5h8l1.5 2h2A1.5 1.5 0 0121 8.5v9A1.5 1.5 0 0119.5 19h-15A1.5 1.5 0 013 17.5z"></path>
								<circle cx="12" cy="12.5" r="3.2"></circle>
							</svg>
						</button>
						<input type="file" accept="image/*" class="sg-fuori-schermo" data-sg-file
						       aria-label="<?php esc_attr_e( 'Scegli una foto dal dispositivo', 'storegentic' ); ?>">
					</form>
					<button type="button" class="sg-pannello__chiudi" data-sg-chiudi
					        aria-label="<?php esc_attr_e( 'Chiudi la ricerca', 'storegentic' ); ?>">&times;</button>
				</div>
				<div class="sg-pannello__corpo" id="sg-esiti" data-sg-esiti aria-live="polite"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * L'assistente.
	 *
	 * Il pulsante che lo apre e' un elemento solo, sempre visibile: e' l'
	 * unico modo perche' chi non sa cosa cercare sappia che puo' chiedere.
	 * La conversazione vive nella pagina e non sul server, per la ragione
	 * spiegata in Frontend\Assistente.
	 */
	private static function widget_assistente(): void {
		?>
		<button type="button" class="sg-chiama sg-chiama--<?php echo esc_attr( (string) Impostazioni::leggi( 'posizione' ) ); ?>"
		        data-sg-apri-assistente aria-haspopup="dialog" aria-controls="sg-assistente" aria-expanded="false">
			<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false" fill="none"
			     stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
				<path d="M21 11.5a7.5 7.5 0 01-10.9 6.7L4 20l1.9-5.7A7.5 7.5 0 1121 11.5z"></path>
			</svg>
			<span class="sg-chiama__testo"><?php esc_html_e( 'Ti aiuto a scegliere', 'storegentic' ); ?></span>
		</button>

		<div class="sg-assistente" id="sg-assistente" role="dialog" aria-modal="true"
		     aria-label="<?php esc_attr_e( 'Assistente del negozio', 'storegentic' ); ?>" hidden>
			<div class="sg-assistente__velo" data-sg-chiudi-assistente></div>
			<div class="sg-assistente__foglio">
				<header class="sg-assistente__testa">
					<span class="sg-assistente__titolo"><?php esc_html_e( 'Ti aiuto a scegliere', 'storegentic' ); ?></span>
					<button type="button" class="sg-assistente__pulisci" data-sg-pulisci-chat hidden><?php esc_html_e( 'Ricomincia', 'storegentic' ); ?></button>
					<button type="button" class="sg-assistente__chiudi" data-sg-chiudi-assistente
					        aria-label="<?php esc_attr_e( 'Chiudi l’assistente', 'storegentic' ); ?>">&times;</button>
				</header>

				<div class="sg-assistente__corpo" data-sg-conversazione aria-live="polite" aria-atomic="false"></div>

				<form class="sg-assistente__modulo" data-sg-chiedi>
					<label class="sg-fuori-schermo" for="sg-chat-campo"><?php esc_html_e( 'Scrivi la tua domanda', 'storegentic' ); ?></label>
					<textarea id="sg-chat-campo" class="sg-assistente__campo" data-sg-chat-campo rows="1"
					          placeholder="<?php esc_attr_e( 'Scrivi qui…', 'storegentic' ); ?>"
					          enterkeyhint="send" maxlength="500"></textarea>
					<button type="submit" class="sg-assistente__invia" data-sg-chat-invia
					        aria-label="<?php esc_attr_e( 'Invia', 'storegentic' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none"
						     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M4 12h15M13 6l6 6-6 6"></path>
						</svg>
					</button>

					<?php
					/*
					 * Misurato sul servizio: una risposta arriva dopo venti-trenta
					 * secondi. Chi cambia idea deve poter smettere invece di
					 * guardare i puntini fino alla fine.
					 */
					?>
					<button type="button" class="sg-assistente__ferma" data-sg-ferma hidden
					        aria-label="<?php esc_attr_e( 'Ferma la risposta', 'storegentic' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="currentColor">
							<rect x="7" y="7" width="10" height="10" rx="2"></rect>
						</svg>
					</button>
				</form>

				<p class="sg-assistente__nota"><?php esc_html_e( 'Risponde una intelligenza artificiale. Per gli ordini scrivi o telefona al negozio.', 'storegentic' ); ?></p>
			</div>
		</div>
		<?php
	}
}
