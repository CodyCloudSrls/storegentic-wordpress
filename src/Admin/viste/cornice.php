<?php
/**
 * La cornice comune a tutte le pagine.
 *
 * Titolo, linguette, avvisi: le cose che stanno uguali dappertutto stanno qui
 * una volta sola, e ogni pagina si occupa solo del proprio contenuto.
 *
 * Le variabili arrivano da Admin\Pagina::rendi().
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Api\Consumi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $configurato */
/** @var array<string,mixed>|\WP_Error|null $contratto */
/** @var bool $collegato */
/** @var array<string,mixed> $stato */
/** @var array<int,array<string,mixed>> $consumi */
/** @var array<string,mixed> $riepilogo */
/** @var array<int,string> $mesi */
/** @var string|null $mese */
/** @var string $slug */
/** @var array<string,array<string,string>> $pagine */
/** @var array<string,string> $pagina */

$sg_errore = isset( $_GET['errore'] ) ? sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['errore'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- sola lettura.
$sg_finiti = array_values( array_filter( $consumi, static fn( $c ) => $c['esaurito'] ) );
?>
<div class="wrap sg-wrap">

	<h1 class="sg-titolo">
		<?php esc_html_e( 'Storegentic', 'storegentic' ); ?>
		<span class="sg-titolo__pagina"><?php echo esc_html( (string) $pagina['titolo'] ); ?></span>
	</h1>

	<?php
	/*
	 * Le linguette ripetono il menu di sinistra. Non e' un doppione inutile:
	 * su uno schermo stretto il menu di WordPress si chiude in icone, e senza
	 * queste non si saprebbe piu' dove si e' ne' dove si puo' andare.
	 */
	?>
	<nav class="nav-tab-wrapper sg-linguette" aria-label="<?php esc_attr_e( 'Sezioni di Storegentic', 'storegentic' ); ?>">
		<?php foreach ( $pagine as $sg_slug => $sg_p ) : ?>
			<a href="<?php echo esc_url( Menu::url( $sg_slug ) ); ?>"
			   class="nav-tab<?php echo $sg_slug === $slug ? ' nav-tab-active' : ''; ?>"
			   <?php echo $sg_slug === $slug ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( (string) $sg_p['titolo'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( isset( $_GET['salvato'] ) && '' === $sg_errore ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Impostazioni salvate.', 'storegentic' ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $sg_errore ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $sg_errore ); ?></p></div>
	<?php endif; ?>

	<?php
	/*
	 * DUE AVVISI CHE VALGONO SU OGNI PAGINA.
	 *
	 * Non collegato, e piano finito. Il secondo e' il piu' importante del
	 * pannello: un contatore a zero non e' un dettaglio, e' la ricerca del
	 * negozio che smette di rispondere ai clienti. Prima si vedeva solo sulla
	 * pagina principale, ed era possibile passare mezz'ora sulle impostazioni
	 * dei colori senza sapere che il sito era fermo.
	 */
	?>
	<?php if ( ! $configurato ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Inserisci la chiave del servizio per collegare Storegentic.', 'storegentic' ); ?>
				<a href="<?php echo esc_url( Menu::url( 'storegentic-collegamento' ) ); ?>"><?php esc_html_e( 'Vai al collegamento', 'storegentic' ); ?></a>
			</p>
		</div>
	<?php elseif ( ! $collegato ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Non collegato.', 'storegentic' ); ?></strong>
				<?php echo esc_html( $contratto instanceof \WP_Error ? $contratto->get_error_message() : '' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $sg_finiti ) ) : ?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Il piano ha finito.', 'storegentic' ); ?></strong></p>
			<ul class="sg-elenco">
				<?php foreach ( $sg_finiti as $sg_c ) : ?>
					<li>
						<strong><?php echo esc_html( (string) $sg_c['nome'] ); ?></strong><?php echo ': '; ?>
						<?php
						printf(
							/* translators: 1: quanti se ne sono usati, 2: quanti ne consente il piano. */
							esc_html__( 'usate %1$s su %2$s.', 'storegentic' ),
							esc_html( Consumi::scrivi( $sg_c['usato'], (string) $sg_c['unita'] ) ),
							esc_html( Consumi::scrivi( $sg_c['limite'], (string) $sg_c['unita'] ) )
						);
						?>
						<?php echo esc_html( (string) $sg_c['spiega'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php $sg_rinnovo = Consumi::rinnovo(); ?>
			<?php if ( $sg_rinnovo && $sg_rinnovo['passata'] ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: la data che il servizio dichiara per il rinnovo. */
						esc_html__( 'Il servizio dichiara che i contatori si rinnovano il %s, cioè una data già passata: aspettare non li rimette a zero. Scrivi a Storegentic.', 'storegentic' ),
						esc_html( wp_date( 'd/m/Y', (int) $sg_rinnovo['quando'] ) )
					);
					?>
				</p>
			<?php elseif ( $sg_rinnovo ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: data del rinnovo dei contatori. */
						esc_html__( 'I contatori si rinnovano il %s.', 'storegentic' ),
						esc_html( wp_date( 'd/m/Y', (int) $sg_rinnovo['quando'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<p>
				<?php if ( (bool) $i['ripiego'] ) : ?>
					<?php esc_html_e( 'Nel frattempo la ricerca del sito continua a funzionare sui contenuti del sito: trova le parole nei nomi e nelle descrizioni, non i concetti.', 'storegentic' ); ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'La ricerca del sito mostra un errore ai visitatori.', 'storegentic' ); ?></strong>
					<a href="<?php echo esc_url( Menu::url( 'storegentic-ricerca' ) ); ?>"><?php esc_html_e( 'Accendi il ripiego', 'storegentic' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php require __DIR__ . '/' . $pagina['vista'] . '.php'; ?>
</div>
