<?php
/**
 * La pagina di amministrazione.
 *
 * Solo presentazione: la logica sta in Admin\Pagina. Le variabili $i,
 * $configurato, $contratto, $collegato e $stato arrivano da li'.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Api\Contratto;
use Storegentic\Catalogo\Sincronizzazione;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $configurato */
/** @var array<string,mixed>|\WP_Error|null $contratto */
/** @var bool $collegato */
/** @var array<string,mixed> $stato */

$errore_url = isset( $_GET['errore'] ) ? sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['errore'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Storegentic', 'storegentic' ); ?></h1>

	<?php if ( isset( $_GET['salvato'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Impostazioni salvate.', 'storegentic' ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $errore_url ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $errore_url ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $configurato ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Inserisci la chiave del negozio per collegare Storegentic. La trovi nella console Storegentic, sotto le chiavi API del tuo workspace.', 'storegentic' ); ?></p>
		</div>
	<?php elseif ( ! $collegato ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Non collegato.', 'storegentic' ); ?></strong>
				<?php echo esc_html( $contratto instanceof \WP_Error ? $contratto->get_error_message() : '' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<h2 class="title"><?php esc_html_e( 'Collegamento', 'storegentic' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="storegentic_salva">
		<?php wp_nonce_field( 'storegentic_salva' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sg-base"><?php esc_html_e( 'Indirizzo del servizio', 'storegentic' ); ?></label></th>
				<td>
					<input type="url" id="sg-base" name="base" class="regular-text code"
					       value="<?php echo esc_attr( (string) $i['base'] ); ?>">
					<p class="description"><?php esc_html_e( 'Cambialo solo se Storegentic ti ha dato un indirizzo diverso. Tutti gli altri indirizzi li dichiara il servizio.', 'storegentic' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sg-chiave"><?php esc_html_e( 'Chiave del negozio', 'storegentic' ); ?></label></th>
				<td>
					<input type="password" id="sg-chiave" name="chiave" class="regular-text code" autocomplete="off"
					       placeholder="<?php echo esc_attr( \Storegentic\Impostazioni::chiave_mascherata() ); ?>">
					<p class="description"><?php esc_html_e( 'Lascia vuoto per non cambiarla. La chiave resta sul server: non viene mai stampata nelle pagine pubbliche.', 'storegentic' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Attivo sul negozio', 'storegentic' ); ?></th>
				<td>
					<label><input type="checkbox" name="attivo" <?php checked( (bool) $i['attivo'] ); ?>>
						<?php esc_html_e( 'Mostra la ricerca Storegentic ai visitatori', 'storegentic' ); ?></label>
				</td>
			</tr>
		</table>

		<?php if ( $collegato ) : ?>
			<?php // Dice a salva() quali gruppi erano davvero in pagina. ?>
			<input type="hidden" name="gruppi[]" value="aspetto">
			<input type="hidden" name="gruppi[]" value="catalogo">
			<h2 class="title"><?php esc_html_e( 'Aspetto', 'storegentic' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Come compare', 'storegentic' ); ?></th>
					<td>
						<fieldset>
							<?php
							$modi = array(
								'barra'      => __( 'Barra di ricerca — la inserisci dove vuoi con lo shortcode [storegentic]', 'storegentic' ),
								'fluttuante' => __( 'Pulsante fluttuante — sempre presente in un angolo', 'storegentic' ),
								'finestra'   => __( 'Solo finestra — si apre da un tuo elemento con attributo data-storegentic', 'storegentic' ),
							);
							foreach ( $modi as $valore => $etichetta ) :
								?>
								<label style="display:block;margin-block-end:.4rem">
									<input type="radio" name="modalita" value="<?php echo esc_attr( $valore ); ?>"
									       <?php checked( (string) $i['modalita'], $valore ); ?>>
									<?php echo esc_html( $etichetta ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sg-posizione"><?php esc_html_e( 'Angolo del pulsante', 'storegentic' ); ?></label></th>
					<td>
						<select id="sg-posizione" name="posizione">
							<option value="destra" <?php selected( (string) $i['posizione'], 'destra' ); ?>><?php esc_html_e( 'In basso a destra', 'storegentic' ); ?></option>
							<option value="sinistra" <?php selected( (string) $i['posizione'], 'sinistra' ); ?>><?php esc_html_e( 'In basso a sinistra', 'storegentic' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Colori', 'storegentic' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Sfondo', 'storegentic' ); ?>
							<input type="color" name="colore" value="<?php echo esc_attr( (string) $i['colore'] ); ?>"></label>
						&nbsp;
						<label><?php esc_html_e( 'Testo', 'storegentic' ); ?>
							<input type="color" name="colore_testo" value="<?php echo esc_attr( (string) $i['colore_testo'] ); ?>"></label>
						&nbsp;
						<label><?php esc_html_e( 'Angoli', 'storegentic' ); ?>
							<input type="number" name="raggio" min="0" max="40" style="width:5rem"
							       value="<?php echo esc_attr( (string) $i['raggio'] ); ?>"> px</label>
						<p class="description"><?php esc_html_e( 'Scegli un contrasto sufficiente fra sfondo e testo: sotto 4,5:1 il pulsante non è leggibile da tutti.', 'storegentic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sg-segnaposto"><?php esc_html_e( 'Testo nel campo', 'storegentic' ); ?></label></th>
					<td>
						<input type="text" id="sg-segnaposto" name="segnaposto" class="regular-text"
						       value="<?php echo esc_attr( (string) $i['segnaposto'] ); ?>"
						       placeholder="<?php esc_attr_e( 'Che cosa stai cercando?', 'storegentic' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dove compare', 'storegentic' ); ?></th>
					<td>
						<fieldset>
							<?php
							$dove = array(
								'home'     => __( 'Home', 'storegentic' ),
								'catalogo' => __( 'Catalogo e categorie', 'storegentic' ),
								'prodotto' => __( 'Schede prodotto', 'storegentic' ),
								'carrello' => __( 'Carrello', 'storegentic' ),
							);
							foreach ( $dove as $valore => $etichetta ) :
								?>
								<label style="display:inline-block;margin-inline-end:1rem">
									<input type="checkbox" name="solo_su[]" value="<?php echo esc_attr( $valore ); ?>"
									       <?php checked( in_array( $valore, (array) $i['solo_su'], true ) ); ?>>
									<?php echo esc_html( $etichetta ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Nessuna scelta significa: ovunque.', 'storegentic' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Catalogo', 'storegentic' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Sincronizzazione', 'storegentic' ); ?></th>
					<td>
						<label><input type="checkbox" name="sincro_automatica" <?php checked( (bool) $i['sincro_automatica'] ); ?>>
							<?php esc_html_e( 'Sincronizza da sola, a intervalli regolari', 'storegentic' ); ?></label>
						<p>
							<label for="sg-frequenza"><?php esc_html_e( 'Ogni', 'storegentic' ); ?></label>
							<select id="sg-frequenza" name="frequenza">
								<?php foreach ( wp_get_schedules() as $chiave => $s ) : ?>
									<option value="<?php echo esc_attr( $chiave ); ?>" <?php selected( (string) $i['frequenza'], $chiave ); ?>>
										<?php echo esc_html( (string) ( $s['display'] ?? $chiave ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sg-lotto"><?php esc_html_e( 'Prodotti per pagina', 'storegentic' ); ?></label></th>
					<td>
						<input type="number" id="sg-lotto" name="lotto" min="25" max="1000" style="width:6rem"
						       value="<?php echo esc_attr( (string) $i['lotto'] ); ?>"
						       <?php disabled( Sincronizzazione::in_corso() ); ?>>
						<p class="description">
							<?php
							echo Sincronizzazione::in_corso()
								? esc_html__( 'Non si cambia a sincronizzazione avviata: le pagine sono già calcolate sul valore attuale.', 'storegentic' )
								: esc_html__( 'Abbassalo se l\'hosting interrompe le sincronizzazioni a metà.', 'storegentic' );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cosa mandare', 'storegentic' ); ?></th>
					<td>
						<label style="display:block"><input type="checkbox" name="includi_esauriti" <?php checked( (bool) $i['includi_esauriti'] ); ?>>
							<?php esc_html_e( 'Includi i prodotti esauriti', 'storegentic' ); ?></label>
						<label style="display:block"><input type="checkbox" name="includi_bozze" <?php checked( (bool) $i['includi_bozze'] ); ?>>
							<?php esc_html_e( 'Includi i prodotti privati', 'storegentic' ); ?></label>
						<label style="display:block"><input type="checkbox" name="invia_categorie" <?php checked( (bool) $i['invia_categorie'] ); ?>>
							<?php esc_html_e( 'Manda anche le categorie, con le loro descrizioni', 'storegentic' ); ?></label>
						<label style="display:block"><input type="checkbox" name="pota_mancanti" <?php checked( (bool) $i['pota_mancanti'] ); ?>>
							<?php esc_html_e( 'A fine sincronizzazione, togli dall\'indice i prodotti non più a catalogo', 'storegentic' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Analisi', 'storegentic' ); ?></th>
					<td>
						<label><input type="checkbox" name="analitica" <?php checked( (bool) $i['analitica'] ); ?>>
							<?php esc_html_e( 'Manda a Storegentic cosa viene cercato e cosa viene aperto', 'storegentic' ); ?></label>
						<p class="description"><?php esc_html_e( 'Servono a capire cosa cercano i clienti e cosa non trovano. Non contengono dati personali.', 'storegentic' ); ?></p>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php submit_button( __( 'Salva le impostazioni', 'storegentic' ) ); ?>
	</form>

	<hr>

	<h2 class="title"><?php esc_html_e( 'Stato', 'storegentic' ); ?></h2>

	<p>
		<?php self::pulsante( 'verifica', __( 'Verifica il collegamento', 'storegentic' ) ); ?>
		<?php if ( $collegato && '' !== Contratto::endpoint( 'catalogUpsert' ) ) : ?>
			<?php if ( Sincronizzazione::in_corso() ) : ?>
				<?php self::pulsante( 'passo', __( 'Esegui il passo successivo', 'storegentic' ) ); ?>
			<?php else : ?>
				<?php self::pulsante( 'sincronizza', __( 'Sincronizza ora il catalogo', 'storegentic' ), true ); ?>
			<?php endif; ?>
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

	<?php if ( ! empty( $stato['potatura'] ) ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Riconciliazione sospesa.', 'storegentic' ); ?></strong>
				<?php
				if ( ! empty( $stato['potatura']['ignoto'] ) ) {
					esc_html_e( 'La verifica non ha riportato quanti prodotti ci sono in indice, quindi non si può sapere quanti ne verrebbero tolti. Conferma solo se sei sicuro che il catalogo sia stato spedito per intero.', 'storegentic' );
				} else {
					printf(
						/* translators: 1: prodotti che verrebbero rimossi, 2: prodotti in indice. */
						esc_html__( 'Toglierebbe %1$d prodotti su %2$d dall\'indice. Se il catalogo si è davvero ridotto tanto, conferma; altrimenti controlla la sincronizzazione.', 'storegentic' ),
						(int) $stato['potatura']['da_potare'],
						(int) $stato['potatura']['in_catalogo']
					);
				}
				?>
			</p>
			<p><?php self::pulsante( 'conferma_potatura', __( 'Confermo, procedi', 'storegentic' ) ); ?></p>
		</div>
	<?php endif; ?>

	<table class="widefat striped" style="max-width:52rem">
		<tbody>
			<tr>
				<td style="width:16rem"><strong><?php esc_html_e( 'Collegamento', 'storegentic' ); ?></strong></td>
				<td><?php echo $collegato ? esc_html__( 'Attivo', 'storegentic' ) : esc_html__( 'Non collegato', 'storegentic' ); ?></td>
			</tr>
			<?php if ( $collegato ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Funzioni dichiarate', 'storegentic' ); ?></strong></td>
					<td>
						<?php
						$capacita = (array) ( $contratto['capabilities'] ?? array() );
						$accese   = array_keys( array_filter( $capacita ) );
						echo $accese
							? esc_html( implode( ', ', $accese ) )
							: esc_html__( 'Il servizio non dichiara alcuna funzione per questa chiave.', 'storegentic' );
						?>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<td><strong><?php esc_html_e( 'Sincronizzazione', 'storegentic' ); ?></strong></td>
				<td>
					<?php
					$etichette = array(
						Sincronizzazione::INATTIVA    => __( 'Ferma', 'storegentic' ),
						Sincronizzazione::IN_CORSO    => __( 'In corso', 'storegentic' ),
						Sincronizzazione::DA_CHIUDERE => __( 'Pagine inviate, manca la chiusura', 'storegentic' ),
						Sincronizzazione::FALLITA     => __( 'Fallita', 'storegentic' ),
					);
					echo esc_html( $etichette[ $stato['fase'] ] ?? (string) $stato['fase'] );

					if ( (int) $stato['pagine'] > 0 ) {
						printf(
							' — %s',
							esc_html(
								sprintf(
									/* translators: 1: pagina corrente, 2: pagine totali, 3: prodotti inviati. */
									__( 'pagina %1$d di %2$d, %3$d prodotti inviati', 'storegentic' ),
									(int) $stato['pagina'],
									(int) $stato['pagine'],
									(int) $stato['inviati']
								)
							)
						);
					}

					if ( '' !== (string) $stato['errore'] ) {
						printf( '<br><span style="color:#b32d2e">%s</span>', esc_html( (string) $stato['errore'] ) );
					}
					?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Prodotti a catalogo', 'storegentic' ); ?></strong></td>
				<td><?php echo esc_html( (string) count( (array) wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Shortcode', 'storegentic' ); ?></strong></td>
				<td><code>[storegentic]</code></td>
			</tr>
		</tbody>
	</table>

	<?php $diario = Sincronizzazione::diario(); ?>
	<?php if ( ! empty( $diario ) ) : ?>
		<h3><?php esc_html_e( 'Ultime operazioni', 'storegentic' ); ?></h3>
		<ol style="max-width:52rem">
			<?php foreach ( $diario as $riga ) : ?>
				<li>
					<code><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) $riga['quando'] ) ); ?></code>
					<?php echo esc_html( (string) $riga['testo'] ); ?>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
