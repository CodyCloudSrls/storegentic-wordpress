<?php
/**
 * La finestra: un contenitore solo, tre modi di cercare.
 *
 * PERCHE' UNA SOLA. Prima erano due cose separate — un pannello per la ricerca
 * e un riquadro per l'assistente — piu' una pagina per i risultati e un
 * comando per la foto che stava solo su quella pagina. Funzionava su questo
 * negozio, dove il tema ha una lente in testata e la pagina dei risultati e'
 * raggiungibile. Su un negozio qualunque no: se il tema non ha una lente da
 * intercettare, e nel menu non c'e' la pagina dei risultati, meta' del plugin
 * diventa irraggiungibile.
 *
 * Qui c'e' un pulsante, che il plugin disegna da se', e una finestra che
 * contiene tutto: cercare a parole, cercare con una foto, chiedere
 * all'assistente. I risultati compaiono dentro la finestra. Non serve nessuna
 * pagina, nessuna voce di menu, nessun elemento del tema.
 *
 * PERCHE' AL CENTRO E GRANDE. Un riquadro in un angolo va bene per
 * l'assistenza, dove si scrive e si legge una riga per volta. Qui dentro ci
 * sono griglie di prodotti con le fotografie: in un angolo si vedrebbero due
 * schede per volta. La finestra prende quasi tutto lo schermo, ed e' al centro
 * perche' e' li' che si guarda quando si e' deciso di cercare qualcosa.
 *
 * COSA SI VEDE LO DECIDONO IN DUE. Le impostazioni dicono quali modi si
 * VOGLIONO; il contratto del servizio dice quali si POSSONO. Un modo compare
 * solo se entrambi sono d'accordo: un comando che risponde "non disponibile"
 * e' peggio di un comando assente.
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

final class Finestra {

	/**
	 * I modi, nell'ordine in cui compaiono, con cosa serve perche' esistano.
	 *
	 * `endpoint` e' l'indirizzo che deve essere dichiarato dal contratto.
	 * Nessun indirizzo scritto qui: solo il NOME con cui chiederlo.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function modi(): array {
		$tutti = array(
			'cerca' => array(
				'etichetta' => __( 'Cerca', 'storegentic' ),
				'endpoint'  => array( 'search' ),
			),
			'foto'  => array(
				'etichetta' => __( 'Foto', 'storegentic' ),
				'endpoint'  => array( 'imageSearch', 'search' ),
			),
			'chat'  => array(
				'etichetta' => __( 'Assistente', 'storegentic' ),
				'endpoint'  => array( 'agentChat' ),
			),
		);

		$voluti  = (array) Impostazioni::leggi( 'modi' );
		$possibili = array();

		foreach ( $tutti as $nome => $modo ) {
			if ( ! in_array( $nome, $voluti, true ) ) {
				continue;
			}

			foreach ( $modo['endpoint'] as $indirizzo ) {
				if ( '' !== Contratto::endpoint_in_cache( $indirizzo ) ) {
					$possibili[ $nome ] = $modo;
					break;
				}
			}
		}

		return $possibili;
	}

	/** L'etichetta del pulsante che apre la finestra. */
	public static function etichetta(): string {
		$scelta = trim( (string) Impostazioni::leggi( 'etichetta_avvio' ) );

		if ( '' !== $scelta ) {
			return $scelta;
		}

		/*
		 * L'etichetta predefinita dice cosa fa il pulsante, e cambia con i modi
		 * accesi: "Ti aiuto a scegliere" su un negozio senza assistente
		 * prometterebbe una conversazione che non c'e'.
		 */
		$modi = array_keys( self::modi() );

		if ( array( 'cerca' ) === $modi ) {
			return __( 'Cerca nel catalogo', 'storegentic' );
		}

		return in_array( 'chat', $modi, true )
			? __( 'Ti aiuto a scegliere', 'storegentic' )
			: __( 'Cerca', 'storegentic' );
	}

	/**
	 * Il pulsante che apre la finestra.
	 *
	 * E' un elemento nostro, disegnato dal plugin: non si aggancia a niente del
	 * tema, perche' un plugin universale non puo' sapere se il tema ha una
	 * lente, dove sta, e cosa fa quando la si tocca.
	 */
	public static function lanciatore(): void {
		$posizione = 'sinistra' === Impostazioni::leggi( 'posizione' ) ? 'sinistra' : 'destra';
		?>
		<?php
		/*
		 * Senza JavaScript il pulsante non aprirebbe niente, e un comando che
		 * non fa nulla e' peggio di un comando assente: si nasconde. Chi ha
		 * JavaScript spento continua a usare la ricerca del proprio tema, che
		 * il plugin in quel caso non intercetta.
		 */
		?>
		<noscript><style>.sg-lancia,.sg-invito{display:none !important}</style></noscript>
		<?php
		?>
		<button type="button" class="sg-lancia sg-lancia--<?php echo esc_attr( $posizione ); ?>"
		        data-sg-apri aria-haspopup="dialog" aria-controls="sg-finestra" aria-expanded="false">
			<span class="sg-lancia__segno" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor"
				     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.6-3.6"></path>
				</svg>
			</span>
			<span class="sg-lancia__testo"><?php echo esc_html( self::etichetta() ); ?></span>
		</button>
		<?php
	}

	/**
	 * La finestra.
	 *
	 * Si stampa una volta sola, a fine documento: deve poter coprire la pagina
	 * senza dipendere da dove sta il comando che la apre, e un contenitore del
	 * tema con `overflow: hidden` la taglierebbe.
	 */
	public static function disegna(): void {
		$modi   = self::modi();
		$primo  = (string) array_key_first( $modi );
		$titolo = self::etichetta();
		?>
		<div class="sg-finestra" id="sg-finestra" role="dialog" aria-modal="true"
		     aria-label="<?php echo esc_attr( $titolo ); ?>" hidden>

			<div class="sg-finestra__velo" data-sg-chiudi></div>

			<div class="sg-finestra__foglio" role="document">

				<header class="sg-finestra__testa">
					<?php if ( count( $modi ) > 1 ) : ?>
						<div class="sg-modi" role="tablist" aria-label="<?php esc_attr_e( 'Come vuoi cercare', 'storegentic' ); ?>">
							<?php foreach ( $modi as $nome => $modo ) : ?>
								<button type="button" class="sg-modo" role="tab"
								        id="sg-tab-<?php echo esc_attr( $nome ); ?>"
								        aria-controls="sg-pannello-<?php echo esc_attr( $nome ); ?>"
								        aria-selected="<?php echo $nome === $primo ? 'true' : 'false'; ?>"
								        tabindex="<?php echo $nome === $primo ? '0' : '-1'; ?>"
								        data-sg-modo="<?php echo esc_attr( $nome ); ?>">
									<?php echo esc_html( (string) $modo['etichetta'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<span class="sg-finestra__titolo"><?php echo esc_html( $titolo ); ?></span>
					<?php endif; ?>

					<button type="button" class="sg-finestra__chiudi" data-sg-chiudi
					        aria-label="<?php esc_attr_e( 'Chiudi', 'storegentic' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
						     stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
							<path d="M6 6l12 12M18 6L6 18"></path>
						</svg>
					</button>
				</header>

				<?php
				foreach ( $modi as $nome => $modo ) {
					self::pannello( $nome, $nome === $primo );
				}
				?>
			</div>
		</div>
		<?php
	}

	/** Il pannello di un modo: corpo che scorre, e sotto la riga di comando. */
	private static function pannello( string $nome, bool $primo ): void {
		?>
		<section class="sg-pannello sg-pannello--<?php echo esc_attr( $nome ); ?>"
		         id="sg-pannello-<?php echo esc_attr( $nome ); ?>"
		         role="tabpanel" aria-labelledby="sg-tab-<?php echo esc_attr( $nome ); ?>"
		         data-sg-pannello="<?php echo esc_attr( $nome ); ?>" <?php echo $primo ? '' : 'hidden'; ?>>

			<div class="sg-pannello__corpo" data-sg-corpo="<?php echo esc_attr( $nome ); ?>"
			     <?php echo 'chat' === $nome ? 'aria-live="polite"' : ''; ?>></div>

			<?php
			switch ( $nome ) {
				case 'cerca':
					self::riga_ricerca();
					break;
				case 'foto':
					self::riga_foto();
					break;
				case 'chat':
					self::riga_chat();
					break;
			}
			?>
		</section>
		<?php
	}

	private static function riga_ricerca(): void {
		$segnaposto = (string) Impostazioni::leggi( 'segnaposto' );
		?>
		<form class="sg-riga-comando" role="search" method="get"
		      action="<?php echo esc_url( Pagina::indirizzo() ); ?>" data-sg-cerca>
			<label class="sg-fuori-schermo" for="sg-campo-cerca"><?php esc_html_e( 'Che cosa cerchi', 'storegentic' ); ?></label>
			<div class="sg-campo">
				<svg class="sg-campo__segno" viewBox="0 0 24 24" width="18" height="18" fill="none"
				     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
					<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.6-3.6"></path>
				</svg>
				<input type="search" id="sg-campo-cerca" name="q" data-sg-campo-cerca
				       placeholder="<?php echo esc_attr( '' !== $segnaposto ? $segnaposto : __( 'Che cosa stai cercando?', 'storegentic' ) ); ?>"
				       autocomplete="off" enterkeyhint="search">
				<button type="button" class="sg-campo__pulisci" data-sg-pulisci hidden
				        aria-label="<?php esc_attr_e( 'Cancella', 'storegentic' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
					     stroke-width="2" stroke-linecap="round" aria-hidden="true">
						<path d="M6 6l12 12M18 6L6 18"></path>
					</svg>
				</button>
			</div>
			<button type="submit" class="sg-invia"><?php esc_html_e( 'Cerca', 'storegentic' ); ?></button>
		</form>
		<?php
	}

	private static function riga_foto(): void {
		?>
		<div class="sg-riga-comando sg-riga-comando--foto">
			<input type="file" accept="image/*" class="sg-fuori-schermo" data-sg-file
			       aria-label="<?php esc_attr_e( 'Scegli una foto', 'storegentic' ); ?>">
			<button type="button" class="sg-invia sg-invia--largo" data-sg-scegli-foto>
				<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor"
				     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M3 8.5A1.5 1.5 0 014.5 7h2L8 5h8l1.5 2h2A1.5 1.5 0 0121 8.5v9A1.5 1.5 0 0119.5 19h-15A1.5 1.5 0 013 17.5z"></path>
					<circle cx="12" cy="12.5" r="3.2"></circle>
				</svg>
				<?php esc_html_e( 'Scegli una foto', 'storegentic' ); ?>
			</button>
		</div>
		<?php
	}

	private static function riga_chat(): void {
		?>
		<form class="sg-riga-comando sg-riga-comando--chat" data-sg-chiedi>
			<label class="sg-fuori-schermo" for="sg-campo-chat"><?php esc_html_e( 'Scrivi la tua domanda', 'storegentic' ); ?></label>
			<textarea id="sg-campo-chat" class="sg-campo sg-campo--testo" data-sg-campo-chat rows="1"
			          placeholder="<?php esc_attr_e( 'Scrivi qui…', 'storegentic' ); ?>"
			          enterkeyhint="send" maxlength="500"></textarea>
			<button type="submit" class="sg-tondo" data-sg-invia-chat
			        aria-label="<?php esc_attr_e( 'Invia', 'storegentic' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
				     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M4 12h15M13 6l6 6-6 6"></path>
				</svg>
			</button>
			<button type="button" class="sg-tondo sg-tondo--fermo" data-sg-ferma hidden
			        aria-label="<?php esc_attr_e( 'Ferma la risposta', 'storegentic' ); ?>">
				<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true">
					<rect x="7" y="7" width="10" height="10" rx="2"></rect>
				</svg>
			</button>
		</form>
		<p class="sg-nota">
			<?php esc_html_e( 'Risponde un’intelligenza artificiale. Per gli ordini scrivi o telefona al negozio.', 'storegentic' ); ?>
			<button type="button" class="sg-ricomincia" data-sg-ricomincia hidden><?php esc_html_e( 'Ricomincia', 'storegentic' ); ?></button>
		</p>
		<?php
	}
}
