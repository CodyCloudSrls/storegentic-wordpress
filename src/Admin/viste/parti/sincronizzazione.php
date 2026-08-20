<?php
/**
 * Lo stato della sincronizzazione, e il diario.
 *
 * Sta in una parte a se' perche' serve in due posti: nella panoramica, dove
 * risponde a "va tutto bene?", e nella pagina dei contenuti, dove sta accanto
 * ai comandi che la fanno partire.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Catalogo\Sincronizzazione;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $stato */
?>

<?php if ( ! empty( $stato['potatura'] ) ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<strong><?php esc_html_e( 'Riconciliazione sospesa.', 'storegentic' ); ?></strong>
			<?php
			if ( ! empty( $stato['potatura']['ignoto'] ) ) {
				esc_html_e( 'La verifica non ha riportato quanti elementi ci sono in indice, quindi non si può sapere quanti ne verrebbero tolti. Conferma solo se sei sicuro che tutto sia stato spedito.', 'storegentic' );
			} else {
				printf(
					/* translators: 1: elementi che verrebbero rimossi, 2: elementi in indice. */
					esc_html__( 'Toglierebbe %1$d elementi su %2$d dall’indice. Se si sono davvero ridotti tanto, conferma; altrimenti controlla la sincronizzazione.', 'storegentic' ),
					(int) $stato['potatura']['da_potare'],
					(int) $stato['potatura']['in_catalogo']
				);
			}
			?>
		</p>
		<p><?php self::pulsante( 'conferma_potatura', __( 'Confermo, procedi', 'storegentic' ) ); ?></p>
	</div>
<?php endif; ?>

<?php $sg_diario = Sincronizzazione::diario(); ?>

<?php if ( ! empty( $sg_diario ) ) : ?>
	<h3><?php esc_html_e( 'Ultime operazioni', 'storegentic' ); ?></h3>
	<ol class="sg-diario">
		<?php foreach ( $sg_diario as $sg_riga ) : ?>
			<li>
				<code><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) $sg_riga['quando'] ) ); ?></code>
				<?php echo esc_html( (string) $sg_riga['testo'] ); ?>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>
