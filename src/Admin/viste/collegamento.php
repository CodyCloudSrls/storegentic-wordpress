<?php
/**
 * Collegamento: a chi siamo collegati, e con quale chiave.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $collegato */
/** @var array<string,mixed>|\WP_Error|null $contratto */

self::apri_modulo();
?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="sg-base"><?php esc_html_e( 'Indirizzo del servizio', 'storegentic' ); ?></label></th>
		<td>
			<input type="url" id="sg-base" name="base" class="regular-text code"
			       list="sg-indirizzi" value="<?php echo esc_attr( (string) $i['base'] ); ?>">
			<datalist id="sg-indirizzi">
				<?php foreach ( Impostazioni::INDIRIZZI as $sg_url => $sg_nome ) : ?>
					<option value="<?php echo esc_attr( $sg_url ); ?>"><?php echo esc_html( $sg_nome ); ?></option>
				<?php endforeach; ?>
			</datalist>
			<p class="description"><?php esc_html_e( 'Gli indirizzi ufficiali sono già nell’elenco. Cambialo solo se Storegentic te ne ha dato un altro: tutti gli altri indirizzi li dichiara il servizio.', 'storegentic' ); ?></p>
			<p class="description"><?php esc_html_e( 'Prima di salvarlo viene provato: se non risponde, resta quello di prima e te lo diciamo, invece di spegnere la ricerca sul sito.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sg-chiave"><?php esc_html_e( 'Chiave', 'storegentic' ); ?></label></th>
		<td>
			<input type="password" id="sg-chiave" name="chiave" class="regular-text code" autocomplete="off"
			       placeholder="<?php echo esc_attr( Impostazioni::chiave_mascherata() ); ?>">
			<p class="description"><?php esc_html_e( 'Lascia vuoto per non cambiarla. La chiave resta sul server: non viene mai stampata nelle pagine pubbliche.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Attivo sul sito', 'storegentic' ); ?></th>
		<td>
			<label><input type="checkbox" name="attivo" <?php checked( (bool) $i['attivo'] ); ?>>
				<?php esc_html_e( 'Mostra la ricerca Storegentic ai visitatori', 'storegentic' ); ?></label>
			<p class="description"><?php esc_html_e( 'Spegnendolo il servizio resta collegato e il catalogo resta sincronizzato, ma sul sito non compare nulla.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<?php
self::chiudi_modulo();

if ( $collegato ) :
	?>
	<h2 class="title"><?php esc_html_e( 'Cosa dichiara il servizio', 'storegentic' ); ?></h2>

	<p class="description sg-largo">
		<?php esc_html_e( 'Il plugin non conosce nessun indirizzo oltre a questo: quali funzioni esistono e dove stanno lo dichiara il servizio a ogni collegamento. Se qui manca qualcosa, manca sul servizio, non nel plugin.', 'storegentic' ); ?>
	</p>

	<table class="widefat striped sg-largo">
		<tbody>
			<tr>
				<td class="sg-cella-nome"><strong><?php esc_html_e( 'Funzioni accese', 'storegentic' ); ?></strong></td>
				<td>
					<?php
					$sg_accese = array_keys( array_filter( (array) ( $contratto['capabilities'] ?? array() ) ) );

					echo $sg_accese
						? esc_html( implode( ', ', $sg_accese ) )
						: esc_html__( 'Il servizio non dichiara alcuna funzione per questa chiave.', 'storegentic' );
					?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Indirizzi dichiarati', 'storegentic' ); ?></strong></td>
				<td>
					<?php
					$sg_indirizzi = (array) ( $contratto['endpoints'] ?? array() );
					?>
					<?php if ( empty( $sg_indirizzi ) ) : ?>
						<?php esc_html_e( 'Nessuno.', 'storegentic' ); ?>
					<?php else : ?>
						<ul class="sg-elenco sg-elenco--fitto">
							<?php foreach ( $sg_indirizzi as $sg_nome => $sg_percorso ) : ?>
								<?php if ( is_string( $sg_percorso ) ) : ?>
									<li><code><?php echo esc_html( (string) $sg_nome ); ?></code> <span class="sg-tenue"><?php echo esc_html( $sg_percorso ); ?></span></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Identificativo di questa installazione', 'storegentic' ); ?></strong></td>
				<td>
					<code><?php echo esc_html( (string) get_option( \Storegentic\PREFISSO_OPZIONI . 'installazione', '' ) ); ?></code>
					<p class="description"><?php esc_html_e( 'Serve a Storegentic per riconoscere questo sito. Non cambia se il sito cambia indirizzo.', 'storegentic' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<p><?php self::pulsante( 'verifica', __( 'Verifica il collegamento', 'storegentic' ) ); ?></p>
<?php endif; ?>
