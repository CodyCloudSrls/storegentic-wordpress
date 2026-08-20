<?php
/**
 * Piano e consumi: quanto consente il piano, e quanto e' gia' andato.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Api\Consumi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<int,array<string,mixed>> $consumi */

if ( empty( $consumi ) ) {
	return;
}
?>

<h2 class="title">
	<?php esc_html_e( 'Piano e consumi', 'storegentic' ); ?>
	<?php if ( '' !== Consumi::piano() ) : ?>
		<span class="sg-piano"><?php echo esc_html( Consumi::piano() ); ?></span>
	<?php endif; ?>
</h2>

<p class="description sg-largo">
	<?php esc_html_e( 'Questi numeri li dichiara Storegentic a ogni collegamento. Nelle statistiche c’è invece com’è andata davvero: le due cose possono non coincidere.', 'storegentic' ); ?>
</p>

<div class="sg-tabella-larga">
	<table class="widefat striped sg-consumi sg-largo">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Cosa', 'storegentic' ); ?></th>
				<th class="sg-col-media"><?php esc_html_e( 'Usato', 'storegentic' ); ?></th>
				<th class="sg-col-stretta"><?php esc_html_e( 'Restano', 'storegentic' ); ?></th>
				<th class="sg-col-larga"><?php esc_html_e( 'Quanto ne resta', 'storegentic' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $consumi as $sg_c ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( (string) $sg_c['nome'] ); ?></strong>
						<?php if ( $sg_c['esaurito'] && '' !== (string) $sg_c['spiega'] ) : ?>
							<br><span class="sg-allarme"><?php echo esc_html( (string) $sg_c['spiega'] ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						printf(
							/* translators: 1: quanti usati, 2: quanti ne consente il piano. */
							esc_html__( '%1$s di %2$s', 'storegentic' ),
							esc_html( Consumi::scrivi( $sg_c['usato'], (string) $sg_c['unita'] ) ),
							esc_html( Consumi::scrivi( $sg_c['limite'], (string) $sg_c['unita'] ) )
						);
						?>
					</td>
					<td<?php echo $sg_c['esaurito'] ? ' class="sg-allarme"' : ''; ?>>
						<?php echo esc_html( Consumi::scrivi( $sg_c['rimasto'], (string) $sg_c['unita'] ) ); ?>
					</td>
					<td>
						<?php
						/*
						 * La barra dice quanto ne RESTA, non quanto se n'e' usato:
						 * e' la domanda che si fa chi guarda. Il numero le sta
						 * accanto perche' una barra da sola non e' leggibile da chi
						 * usa uno screen reader.
						 */
						$sg_resta = (int) round( ( 1 - (float) $sg_c['quota'] ) * 100 );
						?>
						<span class="sg-barra<?php echo $sg_c['esaurito'] ? ' sg-barra--finita' : ( $sg_c['stretto'] ? ' sg-barra--stretta' : '' ); ?>" aria-hidden="true">
							<span style="width:<?php echo esc_attr( (string) $sg_resta ); ?>%"></span>
						</span>
						<?php
						printf(
							/* translators: %d: percentuale rimasta. */
							esc_html__( '%d%%', 'storegentic' ),
							(int) $sg_resta
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
