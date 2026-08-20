<?php
/**
 * Ricerca: come cerca, dove mostra i risultati, e cosa fa se il servizio tace.
 *
 * I PARAMETRI SONO QUELLI DEL CONTRATTO, NON UN ELENCO INVENTATO. Il servizio
 * non espone nessun indirizzo per leggere o scrivere impostazioni: le uniche
 * cose regolabili sono i parametri che si mandano dentro ogni richiesta, e il
 * contratto dichiara quali sono e quanto valgono di base. Qui si mostrano
 * quelli, con il valore del servizio scritto nel segnaposto: chi lascia il
 * campo vuoto continua a seguire il servizio anche se il servizio cambia idea.
 *
 * Vedi Api\Parametri.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Api\Parametri;
use Storegentic\Api\Contratto;
use Storegentic\Frontend\Pagina as PaginaRicerca;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $collegato */

if ( ! $collegato ) {
	printf(
		'<p class="description sg-largo">%s</p>',
		esc_html__( 'La ricerca si configura a servizio collegato: quanto si può chiedere lo dichiara il servizio.', 'storegentic' )
	);

	return;
}

self::apri_modulo();

$sg_foto = '' !== Contratto::endpoint( 'imageSearch' );
?>

<h2 class="title"><?php esc_html_e( 'Dove vanno i risultati', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Risultati della ricerca a parole', 'storegentic' ); ?></th>
		<td>
			<fieldset>
				<label class="sg-riga-spunta">
					<input type="radio" name="risultati" value="pagina" <?php checked( (string) $i['risultati'], 'pagina' ); ?>>
					<strong><?php esc_html_e( 'Nella pagina dei risultati', 'storegentic' ); ?></strong>
					<span class="description"> — <?php echo esc_html( sprintf( /* translators: %s: l'indirizzo della pagina dei risultati. */ __( 'un indirizzo vero (%s): si condivide, il tasto Indietro funziona, c’è spazio per i filtri.', 'storegentic' ), (string) wp_parse_url( PaginaRicerca::indirizzo(), PHP_URL_PATH ) ) ); ?></span>
				</label>
				<label class="sg-riga-spunta">
					<input type="radio" name="risultati" value="finestra" <?php checked( (string) $i['risultati'], 'finestra' ); ?>>
					<strong><?php esc_html_e( 'Dentro la finestra', 'storegentic' ); ?></strong>
					<span class="description"> — <?php esc_html_e( 'niente pagine in più nel sito: il widget basta a sé stesso.', 'storegentic' ); ?></span>
				</label>
			</fieldset>
			<p class="description"><?php esc_html_e( 'La ricerca con la foto e l’assistente restano sempre nella finestra: una foto non si può mettere in un indirizzo, e una conversazione non è una pagina.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Ricerca del sito', 'storegentic' ); ?></th>
		<td>
			<label><input type="checkbox" name="sostituisci_ricerca" <?php checked( (bool) $i['sostituisci_ricerca'] ); ?>>
				<?php esc_html_e( 'Sostituisci la ricerca del tema con quella di Storegentic', 'storegentic' ); ?></label>
			<p class="description"><?php esc_html_e( 'La lente e il campo di ricerca del tema apriranno la ricerca di Storegentic invece di quella di WordPress. Se il tema usa un markup particolare, il filtro storegentic_inneschi_ricerca permette di indicarlo.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Dove compare', 'storegentic' ); ?></th>
		<td>
			<fieldset>
				<?php
				$sg_dove = array(
					'home'     => __( 'Home', 'storegentic' ),
					'catalogo' => __( 'Catalogo e categorie', 'storegentic' ),
					'prodotto' => __( 'Schede prodotto', 'storegentic' ),
					'carrello' => __( 'Carrello', 'storegentic' ),
				);

				foreach ( $sg_dove as $sg_valore => $sg_etichetta ) :
					?>
					<label class="sg-riga-spunta sg-riga-spunta--fianco">
						<input type="checkbox" name="solo_su[]" value="<?php echo esc_attr( $sg_valore ); ?>"
						       <?php checked( in_array( $sg_valore, (array) $i['solo_su'], true ) ); ?>>
						<?php echo esc_html( $sg_etichetta ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description"><?php esc_html_e( 'Nessuna scelta significa: ovunque.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<h2 class="title"><?php esc_html_e( 'Come cerca', 'storegentic' ); ?></h2>

<p class="description sg-largo">
	<?php esc_html_e( 'Questi sono i parametri che il servizio accetta a ogni richiesta, con i valori che applica di suo. Lasciando un campo vuoto si continua a seguire il servizio: se un giorno cambia il proprio valore, questo sito lo segue senza che nessuno tocchi niente.', 'storegentic' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="sg-quanti"><?php esc_html_e( 'Quanti risultati al massimo', 'storegentic' ); ?></label></th>
		<td>
			<input type="number" id="sg-quanti" name="quanti" class="sg-numero" min="0"
			       max="<?php echo esc_attr( (string) Parametri::quanti_al_massimo( 'text' ) ); ?>"
			       value="<?php echo esc_attr( (string) ( (int) $i['quanti'] > 0 ? $i['quanti'] : '' ) ); ?>"
			       placeholder="<?php echo esc_attr( (string) Parametri::quanti_di_base( 'text' ) ); ?>">
			<p class="description">
				<?php
				printf(
					/* translators: 1: quanti ne chiede il servizio di suo, 2: il tetto dichiarato. */
					esc_html__( 'Il servizio ne manda %1$d di suo, e ne accetta al massimo %2$d. Abbassarlo rende la ricerca più veloce e i filtri meno utili.', 'storegentic' ),
					(int) Parametri::quanti_di_base( 'text' ),
					(int) Parametri::quanti_al_massimo( 'text' )
				);
				?>
			</p>
		</td>
	</tr>

	<?php if ( Parametri::soglia_regolabile( 'text' ) ) : ?>
		<tr>
			<th scope="row"><label for="sg-soglia"><?php esc_html_e( 'Quanto devono somigliare', 'storegentic' ); ?></label></th>
			<td>
				<input type="number" id="sg-soglia" name="soglia" class="sg-numero" min="0" max="1" step="0.05"
				       value="<?php echo esc_attr( (string) $i['soglia'] ); ?>"
				       placeholder="<?php echo esc_attr( (string) Parametri::soglia_di_base( 'text' ) ); ?>">
				<p class="description">
					<?php
					printf(
						/* translators: %s: la soglia che il servizio applica di suo. */
						esc_html__( 'Da 0 a 1. Il servizio usa %s. Un numero più basso allarga la rete e trova di più, anche cose meno pertinenti; più alto stringe, e certe domande tornano vuote.', 'storegentic' ),
						esc_html( (string) Parametri::soglia_di_base( 'text' ) )
					);
					?>
				</p>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( $sg_foto ) : ?>
		<tr>
			<th scope="row"><label for="sg-quanti-foto"><?php esc_html_e( 'Con la foto: quanti risultati', 'storegentic' ); ?></label></th>
			<td>
				<input type="number" id="sg-quanti-foto" name="quanti_foto" class="sg-numero" min="0"
				       max="<?php echo esc_attr( (string) Parametri::quanti_al_massimo( 'image' ) ); ?>"
				       value="<?php echo esc_attr( (string) ( (int) $i['quanti_foto'] > 0 ? $i['quanti_foto'] : '' ) ); ?>"
				       placeholder="<?php echo esc_attr( (string) Parametri::quanti_di_base( 'image' ) ); ?>">
			</td>
		</tr>

		<?php if ( Parametri::soglia_regolabile( 'image' ) ) : ?>
			<tr>
				<th scope="row"><label for="sg-soglia-foto"><?php esc_html_e( 'Con la foto: quanto devono somigliare', 'storegentic' ); ?></label></th>
				<td>
					<input type="number" id="sg-soglia-foto" name="soglia_foto" class="sg-numero" min="0" max="1" step="0.05"
					       value="<?php echo esc_attr( (string) $i['soglia_foto'] ); ?>"
					       placeholder="<?php echo esc_attr( (string) Parametri::soglia_di_base( 'image' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Per le foto il servizio tiene una soglia più bassa che per le parole: due oggetti si somigliano in modo meno netto di due frasi.', 'storegentic' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
	<?php endif; ?>
</table>

<h2 class="title"><?php esc_html_e( 'Mentre si scrive', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Suggerimenti immediati', 'storegentic' ); ?></th>
		<td>
			<?php $sg_istantanea = '' !== Contratto::endpoint( 'instantSearch' ); ?>

			<label><input type="checkbox" name="istantanea" <?php checked( (bool) $i['istantanea'] ); ?> <?php disabled( ! $sg_istantanea ); ?>>
				<?php esc_html_e( 'Chiedi i suggerimenti anche al servizio, non solo al sito', 'storegentic' ); ?></label>

			<?php if ( $sg_istantanea ) : ?>
				<p class="description"><?php esc_html_e( 'Il sito conosce i titoli e le categorie; il servizio conosce anche i marchi. Insieme rispondono in un decimo di secondo e non consumano il piano.', 'storegentic' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Il servizio non dichiara questa funzione: i suggerimenti arrivano solo dal sito. Non c’è niente da sistemare qui.', 'storegentic' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<h2 class="title"><?php esc_html_e( 'Se il servizio non risponde', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Ripiego', 'storegentic' ); ?></th>
		<td>
			<label><input type="checkbox" name="ripiego" <?php checked( (bool) $i['ripiego'] ); ?>>
				<?php esc_html_e( 'Cerca nei contenuti del sito quando il servizio non risponde', 'storegentic' ); ?></label>
			<p class="description"><?php esc_html_e( 'Succede a piano finito, durante una manutenzione o se la rete cade. Il ripiego cerca le parole nei nomi e nelle descrizioni brevi: trova meno cose della ricerca intelligente, ma il visitatore vede dei risultati invece di un errore.', 'storegentic' ); ?></p>
			<p class="description"><?php esc_html_e( 'Quando entra in funzione lo dichiara a schermo: i risultati dicono che arrivano dai contenuti del sito.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<?php self::chiudi_modulo(); ?>
