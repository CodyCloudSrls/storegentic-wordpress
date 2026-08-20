<?php
/**
 * Contenuti: cosa sa il servizio di questo sito, e ogni quanto lo rilegge.
 *
 * La pagina si adatta al sito. Con WooCommerce si parla di prodotti e di
 * catalogo; senza, di pagine e articoli, e compare la scelta di quali tipi di
 * contenuto mandare. Vedi Negozio.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Api\Contratto;
use Storegentic\Catalogo\Sincronizzazione;
use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $collegato */
/** @var array<string,mixed> $stato */

$sg_negozio = Negozio::c_e();

if ( ! $collegato ) {
	printf(
		'<p class="description sg-largo">%s</p>',
		esc_html__( 'I contenuti si sincronizzano a servizio collegato.', 'storegentic' )
	);

	return;
}

self::apri_modulo();
?>

<?php if ( ! $sg_negozio ) : ?>
	<h2 class="title"><?php esc_html_e( 'Cosa mandare', 'storegentic' ); ?></h2>

	<p class="description sg-largo">
		<?php esc_html_e( 'Su questo sito non c’è WooCommerce, quindi Storegentic non indicizza prodotti: legge i contenuti che scegli qui e risponde su quelli. È il modo di usarlo come base di conoscenza del sito.', 'storegentic' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Tipi di contenuto', 'storegentic' ); ?></th>
			<td>
				<fieldset>
					<?php
					$sg_scelti = (array) $i['tipi'];

					foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $sg_tipo ) :
						if ( 'attachment' === $sg_tipo->name ) {
							continue; // Un allegato non ha un testo da leggere.
						}

						$sg_quanti = (int) ( wp_count_posts( $sg_tipo->name )->publish ?? 0 );
						?>
						<label class="sg-riga-spunta">
							<input type="checkbox" name="tipi[]" value="<?php echo esc_attr( $sg_tipo->name ); ?>"
							       <?php checked( in_array( $sg_tipo->name, $sg_scelti, true ) ); ?>>
							<strong><?php echo esc_html( $sg_tipo->labels->name ); ?></strong>
							<span class="description">
								<?php
								printf(
									/* translators: %s: quanti contenuti pubblicati ci sono di questo tipo. */
									esc_html__( '— %s pubblicati', 'storegentic' ),
									esc_html( number_format_i18n( $sg_quanti ) )
								);
								?>
							</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Solo i contenuti pubblici: mandare un tipo interno vorrebbe dire farlo comparire nei risultati con un indirizzo che non porta da nessuna parte.', 'storegentic' ); ?></p>
			</td>
		</tr>
	</table>
<?php endif; ?>

<h2 class="title"><?php esc_html_e( 'Sincronizzazione', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Da sola', 'storegentic' ); ?></th>
		<td>
			<label><input type="checkbox" name="sincro_automatica" <?php checked( (bool) $i['sincro_automatica'] ); ?>>
				<?php esc_html_e( 'Sincronizza a intervalli regolari', 'storegentic' ); ?></label>
			<p>
				<label for="sg-frequenza"><?php esc_html_e( 'Ogni', 'storegentic' ); ?></label>
				<select id="sg-frequenza" name="frequenza">
					<?php foreach ( wp_get_schedules() as $sg_chiave => $sg_s ) : ?>
						<option value="<?php echo esc_attr( $sg_chiave ); ?>" <?php selected( (string) $i['frequenza'], $sg_chiave ); ?>>
							<?php echo esc_html( (string) ( $sg_s['display'] ?? $sg_chiave ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sg-lotto"><?php esc_html_e( 'Quanti per volta', 'storegentic' ); ?></label></th>
		<td>
			<input type="number" id="sg-lotto" name="lotto" min="25" max="1000" class="sg-numero"
			       value="<?php echo esc_attr( (string) $i['lotto'] ); ?>"
			       <?php disabled( Sincronizzazione::in_corso() ); ?>>
			<p class="description">
				<?php
				echo Sincronizzazione::in_corso()
					? esc_html__( 'Non si cambia a sincronizzazione avviata: le pagine sono già calcolate sul valore attuale.', 'storegentic' )
					: esc_html__( 'Abbassalo se l’hosting interrompe le sincronizzazioni a metà.', 'storegentic' );
				?>
			</p>
		</td>
	</tr>
	<?php if ( $sg_negozio ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Cosa mandare', 'storegentic' ); ?></th>
			<td>
				<label class="sg-riga-spunta"><input type="checkbox" name="includi_esauriti" <?php checked( (bool) $i['includi_esauriti'] ); ?>>
					<?php esc_html_e( 'Includi i prodotti esauriti', 'storegentic' ); ?></label>
				<label class="sg-riga-spunta"><input type="checkbox" name="includi_bozze" <?php checked( (bool) $i['includi_bozze'] ); ?>>
					<?php esc_html_e( 'Includi i prodotti privati', 'storegentic' ); ?></label>
				<label class="sg-riga-spunta"><input type="checkbox" name="invia_categorie" <?php checked( (bool) $i['invia_categorie'] ); ?>>
					<?php esc_html_e( 'Manda anche le categorie, con le loro descrizioni', 'storegentic' ); ?></label>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'A fine sincronizzazione', 'storegentic' ); ?></th>
		<td>
			<label><input type="checkbox" name="pota_mancanti" <?php checked( (bool) $i['pota_mancanti'] ); ?>>
				<?php esc_html_e( 'Togli dall’indice quello che non c’è più sul sito', 'storegentic' ); ?></label>
			<p class="description"><?php esc_html_e( 'Se una sincronizzazione si interrompe a metà, questa operazione resta sospesa e chiede conferma invece di svuotare l’indice.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<h2 class="title"><?php esc_html_e( 'Analisi e statistiche', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Cosa se ne fa', 'storegentic' ); ?></th>
		<td>
			<label class="sg-riga-spunta">
				<input type="checkbox" name="analitica" <?php checked( (bool) $i['analitica'] ); ?>>
				<?php esc_html_e( 'Manda a Storegentic cosa viene cercato e cosa viene aperto', 'storegentic' ); ?>
			</label>
			<label class="sg-riga-spunta">
				<input type="checkbox" name="statistiche" <?php checked( (bool) $i['statistiche'] ); ?>>
				<?php esc_html_e( 'Tieni il conto anche qui, per le statistiche di questo pannello', 'storegentic' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Sono due cose separate. La prima serve al servizio per migliorare le risposte. La seconda resta nel tuo database: Storegentic non offre un modo per rileggere quello che gli mandi.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<?php
self::chiudi_modulo();

if ( '' !== Contratto::endpoint( 'catalogUpsert' ) ) :
	?>
	<h2 class="title"><?php esc_html_e( 'Adesso', 'storegentic' ); ?></h2>

	<p>
		<?php if ( Sincronizzazione::in_corso() ) : ?>
			<?php self::pulsante( 'passo', __( 'Esegui il passo successivo', 'storegentic' ) ); ?>
		<?php else : ?>
			<?php self::pulsante( 'sincronizza', __( 'Sincronizza ora', 'storegentic' ), true ); ?>
		<?php endif; ?>

		<?php if ( Sincronizzazione::FALLITA === $stato['fase'] || ! empty( $stato['potatura'] ) ) : ?>
			<?php
			/*
			 * Anche da "potatura sospesa" ci vuole un'uscita non distruttiva:
			 * altrimenti l'unico pulsante offerto sarebbe quello che cancella.
			 */
			self::pulsante( 'azzera', __( 'Annulla e azzera lo stato', 'storegentic' ) );
			?>
		<?php endif; ?>
	</p>

	<?php require __DIR__ . '/parti/sincronizzazione.php'; ?>
<?php endif; ?>
