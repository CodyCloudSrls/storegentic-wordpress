<?php
/**
 * Amministrazione: cosa succede quando si apre, si salva, si preme un pulsante.
 *
 * Il menu e l'elenco delle pagine stanno in Admin\Menu; il disegno di ogni
 * pagina sta in `viste/`. Qui c'e' solo il collegamento fra le due cose, piu'
 * il salvataggio e le azioni, che sono comuni a tutte.
 *
 * REGOLA SEGUITA OVUNQUE: si mostra solo cio' che il servizio dichiara di saper
 * fare. Se il contratto non dichiara la ricerca, la pagina Aspetto non offre
 * di configurarla; se non dichiara il caricamento, il pulsante di
 * sincronizzazione non c'e'. Un comando che risponde "non autorizzato" e'
 * peggio di un comando assente.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Analitica\Misure;
use Storegentic\Api\Consumi;
use Storegentic\Api\Contratto;
use Storegentic\Catalogo\Pianificatore;
use Storegentic\Catalogo\Sincronizzazione;
use Storegentic\Frontend\Palette;
use Storegentic\Impostazioni;
use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pagina {

	public static function avvia(): void {
		add_action( 'admin_menu', array( Menu::class, 'registra' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'risorse' ) );
		add_action( 'admin_post_storegentic_salva', array( self::class, 'salva' ) );
		add_action( 'admin_post_storegentic_azione', array( self::class, 'azione' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( \Storegentic\FILE_PRINCIPALE ), array( self::class, 'collegamento' ) );
	}

	/**
	 * Il foglio di stile e lo script delle nostre pagine, e di nessun'altra.
	 *
	 * Un plugin che carica le proprie risorse su tutta l'amministrazione
	 * rallenta il lavoro di chi non lo sta usando, e prima o poi ne rompe una.
	 * Il riquadro della bacheca ha bisogno del solo foglio di stile, e se lo
	 * chiede da se': vedi Admin\Riquadro.
	 */
	public static function risorse( string $schermata ): void {
		if ( ! str_contains( $schermata, Menu::RADICE ) ) {
			return;
		}

		self::stile();

		$percorso = \Storegentic\PERCORSO . '/assets/js/amministrazione.js';

		wp_enqueue_script(
			'storegentic-admin',
			plugin_dir_url( \Storegentic\FILE_PRINCIPALE ) . 'assets/js/amministrazione.js',
			array(),
			is_readable( $percorso ) ? (string) filemtime( $percorso ) : \Storegentic\VERSIONE,
			true
		);

		/*
		 * L'anteprima deve poter mostrare una combinazione senza ricaricare la
		 * pagina, quindi i colori dei preparati devono essere gia' qui.
		 */
		$colori = array();

		foreach ( Palette::preparate() as $nome => $p ) {
			$colori[ $nome ] = $p['colori'];
		}

		wp_localize_script( 'storegentic-admin', 'storegenticAdmin', array( 'preparate' => $colori ) );
	}

	/**
	 * Il foglio di stile, da solo.
	 *
	 * Sta in un metodo suo perche' serve anche al riquadro della bacheca, che
	 * non e' una nostra pagina e non deve caricare anche lo script
	 * dell'anteprima dei colori.
	 */
	public static function stile(): void {
		$percorso = \Storegentic\PERCORSO . '/assets/css/amministrazione.css';

		wp_enqueue_style(
			'storegentic-admin',
			plugin_dir_url( \Storegentic\FILE_PRINCIPALE ) . 'assets/css/amministrazione.css',
			array(),
			is_readable( $percorso ) ? (string) filemtime( $percorso ) : \Storegentic\VERSIONE
		);
	}

	/**
	 * @param array<int,string> $link
	 * @return array<int,string>
	 */
	public static function collegamento( array $link ): array {
		array_unshift(
			$link,
			sprintf( '<a href="%s">%s</a>', esc_url( Menu::url() ), esc_html__( 'Impostazioni', 'storegentic' ) )
		);

		return $link;
	}

	/* ------------------------------------------------------------ salva */

	public static function salva(): void {
		if ( ! current_user_can( Negozio::permesso() ) ) {
			wp_die( esc_html__( 'Non hai i permessi per modificare queste impostazioni.', 'storegentic' ) );
		}

		check_admin_referer( 'storegentic_salva' );

		$inviate = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanificato campo per campo da Impostazioni::salva().

		/*
		 * Si salva solo quello che il modulo ha davvero stampato.
		 *
		 * Ogni pagina dichiara il proprio gruppo. Trattando ogni casella assente
		 * come "non spuntata", aprire la pagina Aspetto e salvarla spegnerebbe
		 * in blocco la sincronizzazione automatica, l'invio delle categorie e le
		 * analisi — che stanno su un'altra pagina, e che nessuno ha toccato.
		 * Era gia' successo quando i gruppi erano sezioni di una pagina sola.
		 */
		$gruppi = array_filter( array_map( 'sanitize_key', (array) ( $inviate['gruppi'] ?? array() ) ) );
		$nuove  = array();

		if ( in_array( 'collegamento', $gruppi, true ) ) {
			$nuove += array(
				'base'   => $inviate['base'] ?? '',
				'attivo' => isset( $inviate['attivo'] ),
			);
		}

		if ( in_array( 'aspetto', $gruppi, true ) ) {
			$nuove += array(
				'posizione'       => $inviate['posizione'] ?? 'destra',
				'palette'         => $inviate['palette'] ?? 'tema',
				'colori'          => (array) ( $inviate['colori'] ?? array() ),
				'raggio'          => $inviate['raggio'] ?? 10,
				'segnaposto'      => $inviate['segnaposto'] ?? '',
				'saluto'          => $inviate['saluto'] ?? '',
				'modi'            => (array) ( $inviate['modi'] ?? array() ),
				'etichetta_avvio' => $inviate['etichetta_avvio'] ?? '',

				// La forma della finestra: vedi Frontend\Forma.
				'forma'           => $inviate['forma'] ?? 'centro',
				'larghezza'       => $inviate['larghezza'] ?? 680,
				'altezza'         => $inviate['altezza'] ?? 520,
				'pulsante'        => $inviate['pulsante'] ?? 'pillola',
				'distanza'        => $inviate['distanza'] ?? 10,
				'densita'         => $inviate['densita'] ?? 'comoda',
				'colonna'         => $inviate['colonna'] ?? 130,
				'velo'            => $inviate['velo'] ?? 100,
				'caratteri'       => $inviate['caratteri'] ?? 'tema',
				'sfocatura'       => isset( $inviate['sfocatura'] ),
				'movimento'       => isset( $inviate['movimento'] ),
			);
		}

		if ( in_array( 'ricerca', $gruppi, true ) ) {
			$nuove += array(
				'risultati'           => $inviate['risultati'] ?? 'pagina',
				'solo_su'             => $inviate['solo_su'] ?? array(),
				'sostituisci_ricerca' => isset( $inviate['sostituisci_ricerca'] ),
				'ripiego'             => isset( $inviate['ripiego'] ),
				'quanti'              => $inviate['quanti'] ?? 0,
				'quanti_foto'         => $inviate['quanti_foto'] ?? 0,
				'soglia'              => $inviate['soglia'] ?? '',
				'soglia_foto'         => $inviate['soglia_foto'] ?? '',
				'istantanea'          => isset( $inviate['istantanea'] ),
			);
		}

		if ( in_array( 'contenuti', $gruppi, true ) ) {
			$nuove += array(
				'sincro_automatica' => isset( $inviate['sincro_automatica'] ),
				'frequenza'         => $inviate['frequenza'] ?? 'daily',
				'includi_bozze'     => isset( $inviate['includi_bozze'] ),
				'includi_esauriti'  => isset( $inviate['includi_esauriti'] ),
				'invia_categorie'   => isset( $inviate['invia_categorie'] ),
				'pota_mancanti'     => isset( $inviate['pota_mancanti'] ),
				'analitica'         => isset( $inviate['analitica'] ),
				'statistiche'       => isset( $inviate['statistiche'] ),
				'tipi'              => (array) ( $inviate['tipi'] ?? array() ),
			);

			/*
			 * Il lotto non si cambia a sincronizzazione avviata: le pagine sono
			 * gia' calcolate su quello vecchio, e cambiarlo a meta' salterebbe
			 * blocchi che poi verrebbero tolti dall'indice.
			 */
			if ( ! Sincronizzazione::in_corso() ) {
				$nuove['lotto'] = $inviate['lotto'] ?? 200;
			}
		}

		/*
		 * La chiave si sovrascrive solo se ne e' stata scritta una nuova: il
		 * campo mostra la versione mascherata, e un salvataggio senza toccare
		 * quel campo non deve cancellare la chiave esistente.
		 */
		$chiave = trim( (string) ( $inviate['chiave'] ?? '' ) );

		if ( in_array( 'collegamento', $gruppi, true ) && '' !== $chiave && ! str_contains( $chiave, '•' ) ) {
			$nuove['chiave'] = $chiave;
		}

		$avviso = self::prova_indirizzo( $nuove );

		Impostazioni::salva( $nuove );

		if ( '' === $avviso ) {
			$avviso = self::guarda_i_colori( $gruppi );
		}

		self::segui_il_cron();

		$dove = sanitize_key( (string) ( $inviate['_sg_pagina'] ?? Menu::RADICE ) );
		$dove = isset( Menu::pagine()[ $dove ] ) ? $dove : Menu::RADICE;

		wp_safe_redirect(
			Menu::url(
				$dove,
				'' === $avviso
					? array( 'salvato' => 1 )
					: array( 'salvato' => 1, 'errore' => rawurlencode( $avviso ) )
			)
		);
		exit;
	}

	/**
	 * UN INDIRIZZO NUOVO SI PROVA PRIMA DI SALVARLO.
	 *
	 * Quel campo e' l'unico che possa scollegare il negozio: sbagliarlo spegne
	 * ricerca, ricerca per foto e assistente su tutte le pagine, e lo si scopre
	 * guardando il sito, non il pannello. Se non risponde si tiene quello di
	 * prima e si salva tutto il resto: chi stava cambiando altre impostazioni
	 * non le perde per colpa di un indirizzo sbagliato.
	 *
	 * @param array<string,mixed> $nuove Si modifica qui dentro.
	 */
	private static function prova_indirizzo( array &$nuove ): string {
		$nuovo = untrailingslashit( trim( (string) ( $nuove['base'] ?? '' ) ) );

		if ( '' === $nuovo || $nuovo === untrailingslashit( (string) Impostazioni::leggi( 'base' ) ) ) {
			return '';
		}

		$esito = Impostazioni::base_risponde( $nuovo );

		if ( true === $esito ) {
			return '';
		}

		unset( $nuove['base'] );

		return (string) $esito;
	}

	/**
	 * I COLORI SI SALVANO COMUNQUE, MA NON IN SILENZIO.
	 *
	 * Sette colori scelti a occhio possono benissimo produrre un testo che non
	 * si legge, e un contrasto non si vede guardando: si misura. Qui non si
	 * impedisce di salvare — i colori di un negozio sono una scelta di chi lo
	 * gestisce, e ci sono ragioni di marchio che un plugin non conosce — ma
	 * nessuno li sbaglia senza che glielo si dica.
	 *
	 * @param array<int,string> $gruppi
	 */
	private static function guarda_i_colori( array $gruppi ): string {
		if ( ! in_array( 'aspetto', $gruppi, true ) || 'propria' !== (string) Impostazioni::leggi( 'palette' ) ) {
			return '';
		}

		$guai = Palette::verifica( Palette::colori() );

		if ( empty( $guai ) ) {
			return '';
		}

		$pezzi = array();

		foreach ( $guai as $g ) {
			$pezzi[] = sprintf( '%s (%s:1)', $g['cosa'], number_format_i18n( $g['rapporto'], 1 ) );
		}

		return sprintf(
			/* translators: %s: l'elenco degli accostamenti che non si leggono, con il loro rapporto. */
			__( 'Colori salvati, ma questi accostamenti sono sotto il 4,5:1 richiesto e non si leggono bene: %s.', 'storegentic' ),
			implode( '; ', $pezzi )
		);
	}

	/**
	 * Il cron segue lo stato.
	 *
	 * `spegni_periodica()` toglie anche il passo pendente, quindi si evita di
	 * chiamarla mentre una sincronizzazione e' in corso: la lascerebbe ferma a
	 * meta' senza nulla che la riprenda fino al giro periodico successivo.
	 */
	private static function segui_il_cron(): void {
		if ( Impostazioni::leggi( 'attivo' ) && Impostazioni::leggi( 'sincro_automatica' ) && Impostazioni::configurato() ) {
			Pianificatore::accendi_periodica();
		} elseif ( Sincronizzazione::in_corso() ) {
			wp_clear_scheduled_hook( Pianificatore::AGGANCIO_PERIODICO );
		} else {
			Pianificatore::spegni_periodica();
		}
	}

	/* ---------------------------------------------------------- azioni */

	public static function azione(): void {
		if ( ! current_user_can( Negozio::permesso() ) ) {
			wp_die( esc_html__( 'Non hai i permessi per questa operazione.', 'storegentic' ) );
		}

		$cosa = sanitize_key( (string) ( $_REQUEST['cosa'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- verificato subito sotto.
		check_admin_referer( 'storegentic_azione_' . $cosa );

		$esito = null;

		switch ( $cosa ) {
			case 'verifica':
				$esito = Contratto::rinnova();
				break;

			case 'sincronizza':
				$esito = Sincronizzazione::avvia();
				if ( ! is_wp_error( $esito ) ) {
					Pianificatore::programma_passo();
				}
				break;

			case 'passo':
				$esito = Sincronizzazione::passo();
				break;

			case 'conferma_potatura':
				$esito = Sincronizzazione::riconcilia( true );
				break;

			case 'azzera':
				Sincronizzazione::azzera();
				break;

			case 'azzera_misure':
				Misure::azzera();
				break;
		}

		$messaggio = is_wp_error( $esito ) ? $esito->get_error_message() : '';

		$dove = sanitize_key( (string) ( $_REQUEST['dove'] ?? Menu::RADICE ) ); // phpcs:ignore WordPress.Security.NonceVerification -- gia' verificato sopra.
		$dove = isset( Menu::pagine()[ $dove ] ) ? $dove : Menu::RADICE;

		wp_safe_redirect(
			Menu::url(
				$dove,
				array_filter(
					array(
						'fatto'  => $cosa,
						'errore' => '' !== $messaggio ? rawurlencode( $messaggio ) : null,
					)
				)
			)
		);
		exit;
	}

	/**
	 * Un pulsante-azione.
	 *
	 * Ogni azione ha il suo nonce, legato al nome dell'azione: un nonce unico
	 * per tutta la pagina permetterebbe di far scattare la sincronizzazione a
	 * chi ne ha ottenuto uno per la sola verifica.
	 */
	public static function pulsante( string $cosa, string $etichetta, bool $primario = false ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'storegentic_azione',
					'cosa'   => $cosa,
					// Si torna dove si era, non sempre alla panoramica.
					'dove'   => Menu::corrente(),
				),
				admin_url( 'admin-post.php' )
			),
			'storegentic_azione_' . $cosa
		);

		printf(
			'<a href="%s" class="button %s">%s</a> ',
			esc_url( $url ),
			$primario ? 'button-primary' : 'button-secondary',
			esc_html( $etichetta )
		);
	}

	/**
	 * L'inizio di un modulo di impostazioni.
	 *
	 * I due campi nascosti sono il contratto con salva(): `gruppi[]` dice quali
	 * caselle erano davvero stampate — senza, quelle delle altre pagine
	 * verrebbero lette come "non spuntate" e spente — e `_sg_pagina` dice dove
	 * riportare chi ha salvato.
	 *
	 * Il gruppo NON si passa: si legge da Admin\Menu, che e' l'unico posto dove
	 * e' scritto. Passandolo, la stessa parola starebbe in due file, e bastava
	 * una svista per avere una pagina che accetta il modulo e non salva niente,
	 * senza dirlo a chi ha premuto il pulsante.
	 */
	public static function apri_modulo(): void {
		$gruppo = (string) ( Menu::pagine()[ Menu::corrente() ]['gruppo'] ?? '' );

		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		echo '<input type="hidden" name="action" value="storegentic_salva">';
		printf( '<input type="hidden" name="gruppi[]" value="%s">', esc_attr( $gruppo ) );
		printf( '<input type="hidden" name="_sg_pagina" value="%s">', esc_attr( Menu::corrente() ) );
		wp_nonce_field( 'storegentic_salva' );
	}

	/** La fine di un modulo, con il suo pulsante. */
	public static function chiudi_modulo(): void {
		submit_button( __( 'Salva le impostazioni', 'storegentic' ) );
		echo '</form>';
	}

	/* ----------------------------------------------------------- resa */

	public static function rendi(): void {
		if ( ! current_user_can( Negozio::permesso() ) ) {
			return;
		}

		$slug   = Menu::corrente();
		$pagine = Menu::pagine();
		$pagina = $pagine[ $slug ];

		$i           = Impostazioni::tutte();
		$configurato = Impostazioni::configurato();
		$contratto   = $configurato ? Contratto::ottieni() : null;
		$collegato   = is_array( $contratto );
		$stato       = Sincronizzazione::stato();
		$consumi     = $collegato ? Consumi::contatori() : array();

		/*
		 * Il mese da guardare lo sceglie chi legge. Si accetta solo la forma
		 * "2026_08" e solo fra i mesi che esistono davvero: una stringa
		 * qualunque diventerebbe il nome di un'opzione da leggere.
		 */
		$mesi = Misure::mesi();
		$mese = sanitize_key( (string) ( $_GET['mese'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- sola lettura.
		$mese = in_array( $mese, $mesi, true ) ? $mese : ( $mesi[0] ?? null );

		$riepilogo = Misure::riepilogo( $mese );

		require __DIR__ . '/viste/cornice.php';
	}
}
