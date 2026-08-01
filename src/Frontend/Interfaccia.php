<?php
/**
 * L'interfaccia sul negozio.
 *
 * Tre modalita', una sola implementazione: cambia dove sta il comando, non
 * cosa fa. Il pannello dei risultati e' lo stesso in tutti e tre i casi.
 *
 *   barra        un campo di ricerca dentro la pagina, via shortcode o blocco
 *   fluttuante   un pulsante tondo fisso in un angolo
 *   finestra     si apre da qualunque elemento con data-storegentic
 *
 * Niente CSS del tema viene toccato. Tutte le classi hanno il prefisso `sg-`
 * e le variabili di colore arrivano dalle impostazioni: il plugin deve
 * poter convivere con qualunque tema senza che l'uno rompa l'altro.
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

		// Se il servizio non dichiara la ricerca, non c'e' niente da mostrare.
		if ( '' === Contratto::endpoint( 'search' ) ) {
			return false;
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

		wp_enqueue_style( 'storegentic', $base . 'assets/css/storegentic.css', array(), \Storegentic\VERSIONE );
		wp_enqueue_script( 'storegentic', $base . 'assets/js/storegentic.js', array(), \Storegentic\VERSIONE, true );

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
				'modalita'   => (string) $i['modalita'],
				'posizione'  => (string) $i['posizione'],
				'etichetta'  => '' !== $i['etichetta'] ? (string) $i['etichetta'] : __( 'Cerca nel catalogo', 'storegentic' ),
				'segnaposto' => '' !== $i['segnaposto'] ? (string) $i['segnaposto'] : __( 'Che cosa stai cercando?', 'storegentic' ),
				'saluto'     => '' !== $i['saluto'] ? (string) $i['saluto'] : '',
				'analitica'  => (bool) $i['analitica'],
				'testi'      => array(
					'chiudi'      => __( 'Chiudi', 'storegentic' ),
					'cerca'       => __( 'Cerca', 'storegentic' ),
					'inCorso'     => __( 'Sto cercando…', 'storegentic' ),
					'nessuno'     => __( 'Nessun risultato. Prova con parole diverse.', 'storegentic' ),
					'errore'      => __( 'La ricerca non ha risposto. Riprova fra poco.', 'storegentic' ),
					'risultati'   => __( 'Risultati', 'storegentic' ),
					'categorie'   => __( 'Categorie suggerite', 'storegentic' ),
				),
			)
		);

		wp_add_inline_style( 'storegentic', self::variabili() );
	}

	/** I colori scelti nell'amministrazione diventano variabili CSS. */
	private static function variabili(): string {
		$i = Impostazioni::tutte();

		return sprintf(
			':root{--sg-colore:%s;--sg-testo:%s;--sg-raggio:%dpx}',
			esc_attr( (string) $i['colore'] ),
			esc_attr( (string) $i['colore_testo'] ),
			(int) $i['raggio']
		);
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

		ob_start();
		?>
		<form class="sg-barra" role="search" data-sg-barra>
			<label class="sg-fuori-schermo" for="sg-campo-<?php echo esc_attr( (string) wp_unique_id() ); ?>">
				<?php echo esc_html( '' !== $a['etichetta'] ? $a['etichetta'] : __( 'Cerca nel catalogo', 'storegentic' ) ); ?>
			</label>
			<input type="search" class="sg-barra__campo" data-sg-campo
			       placeholder="<?php echo esc_attr( $segnaposto ); ?>" autocomplete="off">
			<button type="submit" class="sg-barra__invio"><?php esc_html_e( 'Cerca', 'storegentic' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Il pannello dei risultati, stampato una volta sola a fine pagina.
	 *
	 * Sta in fondo al documento e non dentro il contenuto perche' deve poter
	 * coprire la pagina senza dipendere da dove si trova il comando che lo
	 * apre: un contenitore del tema con `overflow: hidden` lo taglierebbe.
	 */
	public static function pannello(): void {
		if ( ! self::visibile() ) {
			return;
		}

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
					<form class="sg-barra sg-barra--pannello" role="search" data-sg-barra>
						<label class="sg-fuori-schermo" for="sg-campo-pannello"><?php esc_html_e( 'Cerca nel catalogo', 'storegentic' ); ?></label>
						<input type="search" id="sg-campo-pannello" class="sg-barra__campo" data-sg-campo
						       placeholder="<?php echo esc_attr( (string) ( Impostazioni::leggi( 'segnaposto' ) ?: __( 'Che cosa stai cercando?', 'storegentic' ) ) ); ?>"
						       autocomplete="off">
						<button type="submit" class="sg-barra__invio"><?php esc_html_e( 'Cerca', 'storegentic' ); ?></button>
					</form>
					<button type="button" class="sg-pannello__chiudi" data-sg-chiudi
					        aria-label="<?php esc_attr_e( 'Chiudi la ricerca', 'storegentic' ); ?>">&times;</button>
				</div>
				<div class="sg-pannello__corpo" data-sg-esiti aria-live="polite"></div>
			</div>
		</div>
		<?php
	}
}
