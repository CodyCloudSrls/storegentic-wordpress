<?php
/**
 * Amministrazione.
 *
 * Una pagina sola, sotto WooCommerce, dove sta tutto: collegamento,
 * aspetto, catalogo, diagnostica. Un plugin che sparpaglia le proprie
 * impostazioni in quattro menu costringe chi lo usa a ricordarsi dove
 * stanno le cose.
 *
 * Regola seguita ovunque: si mostra solo cio' che il servizio dichiara di
 * saper fare. Se il contratto non dichiara la ricerca, la sezione aspetto
 * non compare; se non dichiara il caricamento del catalogo, il pulsante di
 * sincronizzazione non c'e'. Un comando che risponde "non autorizzato" e'
 * peggio di un comando assente.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Api\Contratto;
use Storegentic\Catalogo\Pianificatore;
use Storegentic\Catalogo\Sincronizzazione;
use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pagina {

	private const SLUG = 'storegentic';

	public static function avvia(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'risorse' ) );
		add_action( 'admin_post_storegentic_salva', array( self::class, 'salva' ) );
		add_action( 'admin_post_storegentic_azione', array( self::class, 'azione' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( \Storegentic\FILE_PRINCIPALE ), array( self::class, 'collegamento' ) );
	}

	/**
	 * Il foglio di stile e lo script della nostra pagina, e di nessun'altra.
	 *
	 * Un plugin che carica le proprie risorse su tutta l'amministrazione
	 * rallenta il lavoro di chi non lo sta usando, e prima o poi ne rompe una.
	 */
	public static function risorse( string $schermata ): void {
		if ( ! str_contains( $schermata, self::SLUG ) ) {
			return;
		}

		$base = plugin_dir_url( \Storegentic\FILE_PRINCIPALE );

		foreach ( array( 'css' => 'style', 'js' => 'script' ) as $tipo => $_ ) {
			$relativo = 'assets/' . $tipo . '/amministrazione.' . $tipo;
			$percorso = \Storegentic\PERCORSO . '/' . $relativo;
			$versione = is_readable( $percorso ) ? (string) filemtime( $percorso ) : \Storegentic\VERSIONE;

			if ( 'css' === $tipo ) {
				wp_enqueue_style( 'storegentic-admin', $base . $relativo, array(), $versione );
			} else {
				wp_enqueue_script( 'storegentic-admin', $base . $relativo, array(), $versione, true );
			}
		}

		/*
		 * L'anteprima deve poter mostrare una combinazione senza ricaricare la
		 * pagina, quindi i colori dei preparati devono essere gia' qui.
		 */
		$colori = array();

		foreach ( \Storegentic\Frontend\Palette::preparate() as $nome => $p ) {
			$colori[ $nome ] = $p['colori'];
		}

		wp_localize_script( 'storegentic-admin', 'storegenticAdmin', array( 'preparate' => $colori ) );
	}

	/**
	 * @param array<int,string> $link
	 * @return array<int,string>
	 */
	public static function collegamento( array $link ): array {
		array_unshift(
			$link,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Impostazioni', 'storegentic' ) )
		);
		return $link;
	}

	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Storegentic', 'storegentic' ),
			__( 'Storegentic', 'storegentic' ),
			'manage_woocommerce',
			self::SLUG,
			array( self::class, 'rendi' )
		);
	}

	private static function url( array $extra = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $extra ), admin_url( 'admin.php' ) );
	}

	/* ------------------------------------------------------------ salva */

	public static function salva(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per modificare queste impostazioni.', 'storegentic' ) );
		}

		check_admin_referer( 'storegentic_salva' );

		$inviate = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanificato campo per campo da Impostazioni::salva().

		/*
		 * Si salva solo quello che il modulo ha davvero stampato.
		 *
		 * Le sezioni Aspetto e Catalogo compaiono solo a negozio collegato,
		 * ma il pulsante di salvataggio c'e' sempre. Trattando ogni casella
		 * assente come "non spuntata", un salvataggio fatto mentre il
		 * servizio era irraggiungibile spegneva in blocco sincronizzazione
		 * automatica, invio delle categorie, potatura e analisi — senza che
		 * nessuno avesse toccato quelle voci, e senza dirlo.
		 *
		 * Il campo sentinella dice quali gruppi erano in pagina; le caselle
		 * di un gruppo assente non vengono nemmeno guardate.
		 */
		$gruppi = array_filter( array_map( 'sanitize_key', (array) ( $inviate['gruppi'] ?? array() ) ) );

		$nuove = array(
			'base'   => $inviate['base'] ?? '',
			'attivo' => isset( $inviate['attivo'] ),
		);

		if ( in_array( 'aspetto', $gruppi, true ) ) {
			$nuove += array(
				'modalita'   => $inviate['modalita'] ?? 'barra',
				'posizione'  => $inviate['posizione'] ?? 'destra',
				'palette'    => $inviate['palette'] ?? 'tema',
				'colori'     => (array) ( $inviate['colori'] ?? array() ),
				'raggio'     => $inviate['raggio'] ?? 10,
				'etichetta'  => $inviate['etichetta'] ?? '',
				'segnaposto' => $inviate['segnaposto'] ?? '',
				'saluto'     => $inviate['saluto'] ?? '',
				'solo_su'    => $inviate['solo_su'] ?? array(),
				'sostituisci_ricerca' => isset( $inviate['sostituisci_ricerca'] ),
				'assistente'          => isset( $inviate['assistente'] ),
			);
		}

		if ( in_array( 'catalogo', $gruppi, true ) ) {
			$nuove += array(
				'sincro_automatica' => isset( $inviate['sincro_automatica'] ),
				'frequenza'         => $inviate['frequenza'] ?? 'daily',
				'includi_bozze'     => isset( $inviate['includi_bozze'] ),
				'includi_esauriti'  => isset( $inviate['includi_esauriti'] ),
				'invia_categorie'   => isset( $inviate['invia_categorie'] ),
				'pota_mancanti'     => isset( $inviate['pota_mancanti'] ),
				'analitica'         => isset( $inviate['analitica'] ),
			);

			/*
			 * Il lotto non si cambia a sincronizzazione avviata: le pagine
			 * sono gia' calcolate su quello vecchio, e cambiarlo a meta'
			 * salterebbe blocchi di prodotti che poi verrebbero tolti
			 * dall'indice. La sessione lo congela comunque, ma non ha senso
			 * far credere che il nuovo valore sia gia' in uso.
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
		if ( '' !== $chiave && ! str_contains( $chiave, '•' ) ) {
			$nuove['chiave'] = $chiave;
		}

		Impostazioni::salva( $nuove );

		/*
		 * Il cron segue lo stato. `spegni_periodica()` toglie anche il passo
		 * pendente, quindi si evita di chiamarla mentre una sincronizzazione
		 * e' in corso: la lascerebbe ferma a meta' senza nulla che la
		 * riprenda fino al giro periodico successivo.
		 */
		if ( Impostazioni::leggi( 'attivo' ) && Impostazioni::leggi( 'sincro_automatica' ) && Impostazioni::configurato() ) {
			Pianificatore::accendi_periodica();
		} elseif ( Sincronizzazione::in_corso() ) {
			wp_clear_scheduled_hook( Pianificatore::AGGANCIO_PERIODICO );
		} else {
			Pianificatore::spegni_periodica();
		}

		wp_safe_redirect( self::url( array( 'salvato' => 1 ) ) );
		exit;
	}

	/* ---------------------------------------------------------- azioni */

	public static function azione(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
		}

		$messaggio = is_wp_error( $esito ) ? $esito->get_error_message() : '';

		wp_safe_redirect(
			self::url(
				array_filter(
					array(
						'fatto'   => $cosa,
						'errore'  => '' !== $messaggio ? rawurlencode( $messaggio ) : null,
					)
				)
			)
		);
		exit;
	}

	/**
	 * Un pulsante-azione.
	 *
	 * Ogni azione ha il suo nonce, legato al nome dell'azione: un nonce
	 * unico per tutta la pagina permetterebbe di far scattare la
	 * sincronizzazione a chi ne ha ottenuto uno per la sola verifica.
	 */
	private static function pulsante( string $cosa, string $etichetta, bool $primario = false ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'storegentic_azione', 'cosa' => $cosa ),
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

	/* ----------------------------------------------------------- resa */

	public static function rendi(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$i          = Impostazioni::tutte();
		$configurato = Impostazioni::configurato();
		$contratto  = $configurato ? Contratto::ottieni() : null;
		$collegato  = is_array( $contratto );
		$stato      = Sincronizzazione::stato();

		require __DIR__ . '/vista-pagina.php';
	}
}
