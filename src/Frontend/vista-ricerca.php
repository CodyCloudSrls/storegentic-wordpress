<?php
/**
 * La pagina dei risultati.
 *
 * Si carica come qualunque modello di WordPress: testata e piede sono quelli
 * del tema, quindi la pagina eredita menu, logo, colori e piede senza che il
 * plugin debba saperne nulla.
 *
 * I risultati sono gia' qui dentro quando la pagina arriva. Il JavaScript
 * serve ad affinare, a ordinare e a cercare con una foto: se non parte, la
 * ricerca a parole funziona lo stesso, perche' il modulo e' un GET normale.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sg_domanda = Pagina::domanda();
$sg_esito   = null;
$sg_errore  = '';

if ( ! Pagina::disponibile() ) {
	$sg_errore = __( 'La ricerca non è disponibile in questo momento.', 'storegentic' );
} elseif ( '' !== $sg_domanda ) {
	$sg_risposta = Ricerca::testo( $sg_domanda, Ricerca::AMPIO );

	if ( is_wp_error( $sg_risposta ) ) {
		$sg_errore = $sg_risposta->get_error_message();
	} else {
		$sg_esito = $sg_risposta;

		/*
		 * L'evento si registra qui e non nel browser: la ricerca a parole
		 * avviene con una navigazione vera, e il pannello non e' piu' il posto
		 * dove passa. Contarla qui la conta una volta sola, anche quando la
		 * pagina si apre da un link condiviso.
		 */
		if ( empty( $sg_esito['daCache'] ) ) {
			\Storegentic\Analitica\Registratore::accoda( 'search_query', array( 'data' => array( 'query' => $sg_domanda ) ) );
			\Storegentic\Analitica\Registratore::accoda(
				'search_results',
				array( 'data' => array( 'query' => $sg_domanda, 'results' => count( (array) $sg_esito['risultati'] ) ) )
			);
		}
	}
}

$sg_risultati = (array) ( $sg_esito['risultati'] ?? array() );
$sg_categorie = (array) ( $sg_esito['categorie'] ?? array() );

get_header();
?>

<main class="sg-pagina" id="sg-ricerca">

	<header class="sg-pagina__testa">
		<h1 class="sg-pagina__titolo">
			<?php
			echo '' !== $sg_domanda
				/* translators: %s: le parole cercate. */
				? esc_html( sprintf( __( 'Risultati per «%s»', 'storegentic' ), $sg_domanda ) )
				: esc_html__( 'Cerca nel catalogo', 'storegentic' );
			?>
		</h1>

		<form class="sg-cerca" role="search" method="get" action="<?php echo esc_url( Pagina::indirizzo() ); ?>" data-sg-modulo>
			<div class="sg-cerca__riga">
				<label class="sg-fuori-schermo" for="sg-q"><?php esc_html_e( 'Che cosa cerchi', 'storegentic' ); ?></label>
				<input type="search" id="sg-q" name="q" class="sg-cerca__campo" value="<?php echo esc_attr( $sg_domanda ); ?>"
				       placeholder="<?php esc_attr_e( 'Che cosa stai cercando?', 'storegentic' ); ?>"
				       autocomplete="off" enterkeyhint="search">

				<?php /* Il campo per la foto sta fuori dal flusso: lo apre il pulsante. */ ?>
				<button type="button" class="sg-cerca__foto" data-sg-scegli-foto
				        aria-label="<?php esc_attr_e( 'Cerca con una foto', 'storegentic' ); ?>">
					<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none"
					     stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
						<path d="M3 8.5A1.5 1.5 0 014.5 7h2L8 5h8l1.5 2h2A1.5 1.5 0 0121 8.5v9A1.5 1.5 0 0119.5 19h-15A1.5 1.5 0 013 17.5z"></path>
						<circle cx="12" cy="12.5" r="3.2"></circle>
					</svg>
					<span class="sg-cerca__foto-testo"><?php esc_html_e( 'Foto', 'storegentic' ); ?></span>
				</button>

				<button type="submit" class="sg-cerca__invio"><?php esc_html_e( 'Cerca', 'storegentic' ); ?></button>
			</div>

			<input type="file" accept="image/*" class="sg-fuori-schermo" data-sg-file
			       aria-label="<?php esc_attr_e( 'Scegli una foto dal dispositivo', 'storegentic' ); ?>">
		</form>

		<?php /* L'anteprima della foto compare qui, sopra i risultati. */ ?>
		<div class="sg-foto-scelta" data-sg-foto-scelta hidden>
			<?php /* L'anteprima la inserisce il JavaScript: un img senza sorgente e' un'immagine rotta. */ ?>
			<span class="sg-foto-scelta__posto" data-sg-foto-posto></span>
			<p class="sg-foto-scelta__testo" data-sg-foto-stato></p>
			<button type="button" class="sg-foto-scelta__via" data-sg-foto-via><?php esc_html_e( 'Togli la foto', 'storegentic' ); ?></button>
		</div>

		<?php
		/*
		 * Quando i risultati arrivano dal catalogo del negozio invece che dal
		 * servizio, lo si scrive. Vedi Frontend\Ripiego: una ricerca per parole
		 * spacciata per ricerca intelligente fa sembrare rotta la funzione buona.
		 */
		?>
		<?php if ( ! empty( $sg_esito['ripiego'] ) ) : ?>
			<p class="sg-nota-ripiego"><?php esc_html_e( 'La ricerca intelligente non risponde in questo momento: questi risultati arrivano dal catalogo del negozio, cercando le parole che hai scritto.', 'storegentic' ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( '' !== $sg_errore ) : ?>

		<p class="sg-avviso sg-avviso--male"><?php echo esc_html( $sg_errore ); ?></p>

	<?php elseif ( '' === $sg_domanda ) : ?>

		<div class="sg-vuoto">
			<p class="sg-vuoto__testo"><?php esc_html_e( 'Scrivi che cosa cerchi, con parole tue. Puoi anche caricare una foto e trovare quello che le somiglia.', 'storegentic' ); ?></p>
			<?php
			$sg_esempi = (array) apply_filters(
				'storegentic_esempi_ricerca',
				array(
					__( 'una cosa che regge il freddo', 'storegentic' ),
					__( 'qualcosa di leggero per l’estate', 'storegentic' ),
					__( 'un regalo sotto i 50 euro', 'storegentic' ),
					__( 'come questa, ma di un altro colore', 'storegentic' ),
				)
			);
			?>
			<ul class="sg-esempi">
				<?php foreach ( $sg_esempi as $sg_e ) : ?>
					<li><a class="sg-pastiglia" href="<?php echo esc_url( Pagina::indirizzo( (string) $sg_e ) ); ?>"><?php echo esc_html( (string) $sg_e ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

	<?php elseif ( empty( $sg_risultati ) ) : ?>

		<div class="sg-vuoto">
			<p class="sg-vuoto__testo"><?php esc_html_e( 'Nessun risultato per questa ricerca.', 'storegentic' ); ?></p>
			<p class="sg-vuoto__aiuto"><?php esc_html_e( 'Prova con meno parole, oppure descrivi quello che cerchi: colore, materiale, occasione.', 'storegentic' ); ?></p>
			<p><a class="sg-pastiglia" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Vedi tutto il catalogo', 'storegentic' ); ?></a></p>
		</div>

	<?php else : ?>

		<div class="sg-pagina__corpo">

			<aside class="sg-filtri" data-sg-filtri>
				<h2 class="sg-filtri__titolo"><?php esc_html_e( 'Affina', 'storegentic' ); ?></h2>

				<?php if ( count( $sg_categorie ) > 1 ) : ?>
					<fieldset class="sg-filtri__gruppo">
						<legend><?php esc_html_e( 'Categoria', 'storegentic' ); ?></legend>
						<ul class="sg-filtri__voci">
							<?php foreach ( $sg_categorie as $sg_c ) : ?>
								<li>
									<label class="sg-spunta">
										<input type="checkbox" data-sg-categoria="<?php echo esc_attr( (string) $sg_c['etichetta'] ); ?>">
										<span><?php echo esc_html( (string) $sg_c['etichetta'] ); ?></span>
										<span class="sg-spunta__conto"><?php echo esc_html( (string) $sg_c['conteggio'] ); ?></span>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					</fieldset>
				<?php endif; ?>

				<fieldset class="sg-filtri__gruppo">
					<legend><?php esc_html_e( 'Prezzo', 'storegentic' ); ?></legend>
					<div class="sg-prezzo-limiti">
						<label>
							<span class="sg-fuori-schermo"><?php esc_html_e( 'Prezzo minimo', 'storegentic' ); ?></span>
							<input type="number" inputmode="numeric" min="0" step="1" placeholder="<?php esc_attr_e( 'da', 'storegentic' ); ?>" data-sg-da>
						</label>
						<span aria-hidden="true">–</span>
						<label>
							<span class="sg-fuori-schermo"><?php esc_html_e( 'Prezzo massimo', 'storegentic' ); ?></span>
							<input type="number" inputmode="numeric" min="0" step="1" placeholder="<?php esc_attr_e( 'a', 'storegentic' ); ?>" data-sg-a>
						</label>
					</div>
				</fieldset>

				<fieldset class="sg-filtri__gruppo">
					<legend><?php esc_html_e( 'Disponibilità', 'storegentic' ); ?></legend>
					<label class="sg-spunta">
						<input type="checkbox" data-sg-solo-disponibili>
						<span><?php esc_html_e( 'Solo quelli disponibili', 'storegentic' ); ?></span>
					</label>
				</fieldset>

				<button type="button" class="sg-filtri__azzera" data-sg-azzera hidden><?php esc_html_e( 'Togli i filtri', 'storegentic' ); ?></button>
			</aside>

			<div class="sg-esiti">
				<div class="sg-esiti__barra">
					<p class="sg-esiti__conto" data-sg-conto aria-live="polite">
						<?php
						printf(
							/* translators: %d: quanti risultati. */
							esc_html( _n( '%d risultato', '%d risultati', count( $sg_risultati ), 'storegentic' ) ),
							count( $sg_risultati )
						);
						?>
					</p>

					<label class="sg-ordina">
						<span class="sg-ordina__etichetta"><?php esc_html_e( 'Ordina', 'storegentic' ); ?></span>
						<select data-sg-ordina>
							<option value="rilevanza"><?php esc_html_e( 'Più pertinenti', 'storegentic' ); ?></option>
							<option value="crescente"><?php esc_html_e( 'Prezzo crescente', 'storegentic' ); ?></option>
							<option value="decrescente"><?php esc_html_e( 'Prezzo decrescente', 'storegentic' ); ?></option>
						</select>
					</label>
				</div>

				<div class="sg-griglia" data-sg-griglia>
					<?php
					foreach ( $sg_risultati as $sg_i => $sg_s ) {
						printf(
							'<div class="sg-cella" data-ordine="%d" data-categoria="%s" data-valore="%s" data-disponibile="%s">%s</div>',
							(int) $sg_i,
							esc_attr( (string) ( $sg_s['categoria'] ?? '' ) ),
							esc_attr( null === $sg_s['valore'] ? '' : (string) $sg_s['valore'] ),
							empty( $sg_s['disponibile'] ) ? '0' : '1',
							Scheda::html( $sg_s ) // phpcs:ignore WordPress.Security.EscapeOutput
						);
					}
					?>
				</div>

				<p class="sg-niente-filtri" data-sg-niente hidden>
					<?php esc_html_e( 'Nessun risultato con questi filtri.', 'storegentic' ); ?>
					<button type="button" class="sg-collegamento" data-sg-azzera><?php esc_html_e( 'Togli i filtri', 'storegentic' ); ?></button>
				</p>
			</div>
		</div>

	<?php endif; ?>
</main>

<?php
get_footer();
