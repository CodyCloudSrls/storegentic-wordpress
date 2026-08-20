<?php
/**
 * Le statistiche, nella pagina di Storegentic.
 *
 * Una pagina che si legge, non un modulo da compilare: qui non si salva
 * niente. Le variabili arrivano da Admin\Pagina.
 *
 * COSA SI GUARDA, IN ORDINE DI UTILITA'
 *
 *   1. Cosa non si trova. E' la tabella che vale il pannello intero: dice quali
 *      domande tornano a mani vuote. Ogni riga e' un cliente che cercava
 *      qualcosa e non l'ha avuto, e spesso e' un prodotto che manca a catalogo,
 *      o che c'e' ma si chiama in un altro modo.
 *   2. Come sta andando ogni funzione, con l'ultimo errore in chiaro.
 *   3. Cosa si cerca di piu', e cosa si apre.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Analitica\Misure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var array<string,mixed> $riepilogo */
/** @var array<int,string> $mesi */
/** @var string|null $mese */

/** Il nome del mese come lo si legge: "agosto 2026". */
$sg_nome_mese = static function ( string $chiave ): string {
	$quando = strtotime( str_replace( '_', '-', $chiave ) . '-01' );

	return $quando ? wp_date( 'F Y', $quando ) : $chiave;
};

$sg_totale = 0;

foreach ( (array) $riepilogo['funzioni'] as $sg_f ) {
	$sg_totale += (int) $sg_f['chiamate'];
}
?>

<h2 class="title"><?php esc_html_e( 'Statistiche', 'storegentic' ); ?></h2>

<?php if ( ! (bool) $i['statistiche'] ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'Le statistiche sono spente: non si sta contando niente.', 'storegentic' ); ?>
			<a href="<?php echo esc_url( Menu::url( 'storegentic-contenuti' ) ); ?>"><?php esc_html_e( 'Accendile', 'storegentic' ); ?></a>
		</p>
	</div>
<?php elseif ( empty( $mesi ) || 0 === $sg_totale ) : ?>
	<p class="description sg-largo">
		<?php esc_html_e( 'Ancora niente da mostrare. Le prime ricerche dei clienti compaiono qui: cosa cercano, cosa non trovano, e quanto ci mette il servizio a rispondere.', 'storegentic' ); ?>
	</p>
<?php else : ?>

	<?php if ( count( $mesi ) > 1 ) : ?>
		<p class="sg-mesi">
			<?php foreach ( $mesi as $sg_m ) : ?>
				<?php if ( $sg_m === $mese ) : ?>
					<strong><?php echo esc_html( $sg_nome_mese( $sg_m ) ); ?></strong>
				<?php else : ?>
					<a href="<?php echo esc_url( Menu::url( 'storegentic-statistiche', array( 'mese' => $sg_m ) ) ); ?>">
						<?php echo esc_html( $sg_nome_mese( $sg_m ) ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<div class="sg-tabella-larga" id="statistiche">
	<table class="widefat striped sg-largo">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Funzione', 'storegentic' ); ?></th>
				<th class="sg-col-stretta"><?php esc_html_e( 'Domande', 'storegentic' ); ?></th>
				<th class="sg-col-media"><?php esc_html_e( 'Senza risultati', 'storegentic' ); ?></th>
				<th class="sg-col-media"><?php esc_html_e( 'Servizio muto', 'storegentic' ); ?></th>
				<th class="sg-col-stretta"><?php esc_html_e( 'Tempo medio', 'storegentic' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( (array) $riepilogo['funzioni'] as $sg_f ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( (string) $sg_f['nome'] ); ?></strong>
						<?php if ( ! empty( $sg_f['ultimo_errore'] ) ) : ?>
							<br>
							<span class="sg-allarme">
								<?php
								printf(
									/* translators: 1: data e ora, 2: il messaggio del servizio. */
									esc_html__( 'Ultimo errore il %1$s: %2$s', 'storegentic' ),
									esc_html( wp_date( 'd/m/Y H:i', (int) $sg_f['ultimo_errore']['quando'] ) ),
									esc_html( (string) $sg_f['ultimo_errore']['messaggio'] )
								);
								?>
							</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( number_format_i18n( (int) $sg_f['chiamate'] ) ); ?></td>
					<td<?php echo (int) $sg_f['vuote'] > 0 ? ' class="sg-attenzione"' : ''; ?>>
						<?php echo esc_html( number_format_i18n( (int) $sg_f['vuote'] ) ); ?>
					</td>
					<td<?php echo (int) $sg_f['fallite'] > 0 ? ' class="sg-allarme"' : ''; ?>>
						<?php echo esc_html( number_format_i18n( (int) $sg_f['fallite'] ) ); ?>
					</td>
					<td>
						<?php
						echo (int) $sg_f['ms_medio'] > 0
							? esc_html( sprintf( '%.1f s', (int) $sg_f['ms_medio'] / 1000 ) )
							: '—';
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<p class="description sg-largo">
		<?php esc_html_e( '«Senza risultati» conta le domande a cui il cliente non ha visto nulla. «Servizio muto» conta le volte in cui Storegentic non ha risposto: le due colonne possono non coincidere, perché con il ripiego acceso il cliente vede lo stesso i prodotti del catalogo. Il tempo medio non conta le risposte già in cache, che arrivano subito.', 'storegentic' ); ?>
	</p>

	<?php if ( ! empty( $riepilogo['senza'] ) ) : ?>
		<h3><?php esc_html_e( 'Cosa cercano e non trovano', 'storegentic' ); ?></h3>
		<p class="description sg-largo">
			<?php esc_html_e( 'È l’elenco più utile di questa pagina. Ogni riga è un cliente che ha cercato e non ha avuto niente: a volte manca il prodotto, più spesso c’è ma si chiama in un altro modo.', 'storegentic' ); ?>
		</p>
		<div class="sg-tabella-larga">
		<table class="widefat striped sg-largo">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Cercato', 'storegentic' ); ?></th>
					<th class="sg-col-media"><?php esc_html_e( 'Volte a vuoto', 'storegentic' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( (array) $riepilogo['senza'] as $sg_testo => $sg_voce ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $sg_testo ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $sg_voce['senza'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $riepilogo['cercate'] ) ) : ?>
		<h3><?php esc_html_e( 'Cosa cercano di più', 'storegentic' ); ?></h3>
		<div class="sg-tabella-larga">
		<table class="widefat striped sg-largo">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Cercato', 'storegentic' ); ?></th>
					<th class="sg-col-stretta"><?php esc_html_e( 'Volte', 'storegentic' ); ?></th>
					<th class="sg-col-media"><?php esc_html_e( 'Di cui a vuoto', 'storegentic' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( (array) $riepilogo['cercate'] as $sg_testo => $sg_voce ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $sg_testo ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $sg_voce['quante'] ) ); ?></td>
						<td<?php echo (int) $sg_voce['senza'] > 0 ? ' class="sg-attenzione"' : ''; ?>>
							<?php echo esc_html( number_format_i18n( (int) $sg_voce['senza'] ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $riepilogo['aperti'] ) ) : ?>
		<h3><?php esc_html_e( 'Cosa si apre dai risultati', 'storegentic' ); ?></h3>
		<div class="sg-tabella-larga">
		<table class="widefat striped sg-largo">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Prodotto', 'storegentic' ); ?></th>
					<th class="sg-col-stretta"><?php esc_html_e( 'Aperture', 'storegentic' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( (array) $riepilogo['aperti'] as $sg_sku => $sg_quante ) : ?>
					<?php
					/*
					 * Si conserva lo SKU e non il titolo: il titolo cambia, lo SKU
					 * no, e conservarlo vorrebbe dire tenere in un'opzione una
					 * copia del catalogo che invecchia. Qui si rilegge il prodotto
					 * vero; se non c'e' piu' si mostra lo SKU, che e' comunque
					 * un'informazione.
					 */
					$sg_id  = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( (string) $sg_sku ) : 0;
					$sg_pro = $sg_id > 0 ? wc_get_product( $sg_id ) : null;
					?>
					<tr>
						<td>
							<?php if ( $sg_pro ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $sg_id ) ); ?>"><?php echo esc_html( $sg_pro->get_name() ); ?></a>
							<?php else : ?>
								<code><?php echo esc_html( (string) $sg_sku ); ?></code>
								<span class="description"><?php esc_html_e( '(non più a catalogo)', 'storegentic' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $sg_quante ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * Cosa NON c'e' in queste tabelle si dice, invece di lasciar credere che
	 * l'elenco sia completo. Un conto troncato in silenzio e' un conto che
	 * mente.
	 */
	$sg_note = array();

	if ( (int) $riepilogo['scartate'] > 0 ) {
		$sg_note[] = sprintf(
			/* translators: %s: quante domande non sono state contate. */
			esc_html__( '%s domande cercate una volta sola non sono nell’elenco: lo spazio riservato al mese era pieno.', 'storegentic' ),
			esc_html( number_format_i18n( (int) $riepilogo['scartate'] ) )
		);
	}

	if ( (int) $riepilogo['riservate'] > 0 ) {
		$sg_note[] = sprintf(
			/* translators: %s: quante ricerche sono state contate ma non trascritte. */
			esc_html__( '%s ricerche contengono un indirizzo di posta o un numero lungo: sono contate, ma il testo non viene conservato.', 'storegentic' ),
			esc_html( number_format_i18n( (int) $riepilogo['riservate'] ) )
		);
	}
	?>

	<?php if ( ! empty( $sg_note ) ) : ?>
		<p class="description sg-largo"><?php echo esc_html( implode( ' ', $sg_note ) ); ?></p>
	<?php endif; ?>

	<p>
		<?php self::pulsante( 'azzera_misure', __( 'Cancella le statistiche', 'storegentic' ) ); ?>
		<span class="description">
			<?php
			printf(
				/* translators: %d: per quanti mesi si conservano le statistiche. */
				esc_html__( 'Si conservano %d mesi; il più vecchio se ne va da solo.', 'storegentic' ),
				(int) Misure::MESI
			);
			?>
		</span>
	</p>
<?php endif; ?>
