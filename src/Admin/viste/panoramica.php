<?php
/**
 * Panoramica: va tutto bene?
 *
 * E' la pagina che si apre per prima, quindi risponde a una domanda sola e in
 * fretta. Prima lo stato — collegato, sincronizzato, quanto resta del piano —
 * poi un riassunto di cosa e' successo, con il collegamento alla pagina che
 * approfondisce. Niente moduli da compilare: quelli stanno nelle loro pagine.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Analitica\Misure;
use Storegentic\Catalogo\Sincronizzazione;
use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $collegato */
/** @var array<string,mixed> $stato */
/** @var array<int,array<string,mixed>> $consumi */
/** @var array<string,mixed> $riepilogo */

$sg_fasi = array(
	Sincronizzazione::INATTIVA    => __( 'Ferma', 'storegentic' ),
	Sincronizzazione::IN_CORSO    => __( 'In corso', 'storegentic' ),
	Sincronizzazione::DA_CHIUDERE => __( 'Inviato tutto, manca la chiusura', 'storegentic' ),
	Sincronizzazione::FALLITA     => __( 'Fallita', 'storegentic' ),
);

$sg_quanti = Negozio::c_e()
	? count( (array) wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) )
	: array_sum( array_map( static fn( $t ) => (int) ( wp_count_posts( $t )->publish ?? 0 ), (array) $i['tipi'] ) );
?>

<div class="sg-quadri">
	<div class="sg-quadro">
		<span class="sg-quadro__voce"><?php esc_html_e( 'Collegamento', 'storegentic' ); ?></span>
		<strong class="sg-quadro__valore<?php echo $collegato ? '' : ' sg-allarme'; ?>">
			<?php echo $collegato ? esc_html__( 'Attivo', 'storegentic' ) : esc_html__( 'Non collegato', 'storegentic' ); ?>
		</strong>
		<span class="sg-quadro__nota">
			<?php echo esc_html( (string) wp_parse_url( (string) $i['base'], PHP_URL_HOST ) ); ?>
		</span>
	</div>

	<div class="sg-quadro">
		<span class="sg-quadro__voce"><?php esc_html_e( 'Visibile sul sito', 'storegentic' ); ?></span>
		<strong class="sg-quadro__valore<?php echo (bool) $i['attivo'] ? '' : ' sg-attenzione'; ?>">
			<?php echo (bool) $i['attivo'] ? esc_html__( 'Sì', 'storegentic' ) : esc_html__( 'No', 'storegentic' ); ?>
		</strong>
		<span class="sg-quadro__nota">
			<?php
			$sg_modi = array_keys( \Storegentic\Frontend\Finestra::modi() );

			echo $sg_modi
				? esc_html( implode( ', ', $sg_modi ) )
				: esc_html__( 'nessuna modalità attiva', 'storegentic' );
			?>
		</span>
	</div>

	<div class="sg-quadro">
		<span class="sg-quadro__voce"><?php echo esc_html( Negozio::c_e() ? __( 'Prodotti', 'storegentic' ) : __( 'Contenuti', 'storegentic' ) ); ?></span>
		<strong class="sg-quadro__valore"><?php echo esc_html( number_format_i18n( $sg_quanti ) ); ?></strong>
		<span class="sg-quadro__nota">
			<?php
			$sg_ultima = (int) get_option( \Storegentic\PREFISSO_OPZIONI . 'ultima_sincro', 0 );

			echo $sg_ultima > 0
				? esc_html(
					sprintf(
						/* translators: %s: quanto tempo fa. */
						__( 'sincronizzati %s fa', 'storegentic' ),
						human_time_diff( $sg_ultima )
					)
				)
				: esc_html__( 'mai sincronizzati', 'storegentic' );
			?>
		</span>
	</div>

	<div class="sg-quadro">
		<span class="sg-quadro__voce"><?php esc_html_e( 'Sincronizzazione', 'storegentic' ); ?></span>
		<strong class="sg-quadro__valore<?php echo Sincronizzazione::FALLITA === $stato['fase'] ? ' sg-allarme' : ''; ?>">
			<?php echo esc_html( $sg_fasi[ $stato['fase'] ] ?? (string) $stato['fase'] ); ?>
		</strong>
		<span class="sg-quadro__nota">
			<?php
			if ( (int) $stato['pagine'] > 0 ) {
				printf(
					/* translators: 1: pagina corrente, 2: pagine totali. */
					esc_html__( 'pagina %1$d di %2$d', 'storegentic' ),
					(int) $stato['pagina'],
					(int) $stato['pagine']
				);
			} else {
				echo esc_html( (string) $i['frequenza'] === 'daily' ? __( 'ogni giorno', 'storegentic' ) : (string) $i['frequenza'] );
			}
			?>
		</span>
	</div>
</div>

<?php if ( '' !== (string) $stato['errore'] ) : ?>
	<p class="sg-allarme sg-largo"><strong><?php esc_html_e( 'Ultimo errore di sincronizzazione:', 'storegentic' ); ?></strong> <?php echo esc_html( (string) $stato['errore'] ); ?></p>
<?php endif; ?>

<p>
	<?php self::pulsante( 'verifica', __( 'Verifica il collegamento', 'storegentic' ) ); ?>
	<?php if ( $collegato && '' !== \Storegentic\Api\Contratto::endpoint( 'catalogUpsert' ) && ! Sincronizzazione::in_corso() ) : ?>
		<?php self::pulsante( 'sincronizza', __( 'Sincronizza ora', 'storegentic' ) ); ?>
	<?php endif; ?>
</p>

<?php require __DIR__ . '/parti/consumi.php'; ?>

<h2 class="title"><?php esc_html_e( 'Questo mese', 'storegentic' ); ?></h2>

<?php
$sg_totale = 0;
$sg_vuote  = 0;
$sg_muto   = 0;

foreach ( (array) $riepilogo['funzioni'] as $sg_f ) {
	$sg_totale += (int) $sg_f['chiamate'];
	$sg_vuote  += (int) $sg_f['vuote'];
	$sg_muto   += (int) $sg_f['fallite'];
}
?>

<?php if ( 0 === $sg_totale ) : ?>
	<p class="description sg-largo">
		<?php esc_html_e( 'Ancora nessuna ricerca da mostrare. Le prime compaiono qui: cosa cercano i visitatori, cosa non trovano, e quanto ci mette il servizio a rispondere.', 'storegentic' ); ?>
	</p>
<?php else : ?>
	<div class="sg-quadri">
		<div class="sg-quadro">
			<span class="sg-quadro__voce"><?php esc_html_e( 'Domande', 'storegentic' ); ?></span>
			<strong class="sg-quadro__valore"><?php echo esc_html( number_format_i18n( $sg_totale ) ); ?></strong>
		</div>
		<div class="sg-quadro">
			<span class="sg-quadro__voce"><?php esc_html_e( 'Senza risultati', 'storegentic' ); ?></span>
			<strong class="sg-quadro__valore<?php echo $sg_vuote > 0 ? ' sg-attenzione' : ''; ?>"><?php echo esc_html( number_format_i18n( $sg_vuote ) ); ?></strong>
		</div>
		<div class="sg-quadro">
			<span class="sg-quadro__voce"><?php esc_html_e( 'Servizio muto', 'storegentic' ); ?></span>
			<strong class="sg-quadro__valore<?php echo $sg_muto > 0 ? ' sg-allarme' : ''; ?>"><?php echo esc_html( number_format_i18n( $sg_muto ) ); ?></strong>
		</div>
		<div class="sg-quadro">
			<span class="sg-quadro__voce"><?php esc_html_e( 'Domande diverse', 'storegentic' ); ?></span>
			<strong class="sg-quadro__valore"><?php echo esc_html( number_format_i18n( (int) $riepilogo['distinte'] ) ); ?></strong>
		</div>
	</div>

	<?php if ( ! empty( $riepilogo['senza'] ) ) : ?>
		<h3><?php esc_html_e( 'Cercato e non trovato', 'storegentic' ); ?></h3>
		<ul class="sg-elenco sg-elenco--fitto sg-largo">
			<?php foreach ( array_slice( (array) $riepilogo['senza'], 0, 5, true ) as $sg_testo => $sg_voce ) : ?>
				<li>
					<strong><?php echo esc_html( (string) $sg_testo ); ?></strong>
					<span class="sg-tenue">
						<?php
						printf(
							/* translators: %s: quante volte. */
							esc_html( _n( '%s volta', '%s volte', (int) $sg_voce['senza'], 'storegentic' ) ),
							esc_html( number_format_i18n( (int) $sg_voce['senza'] ) )
						);
						?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( Menu::url( 'storegentic-statistiche' ) ); ?>" class="button"><?php esc_html_e( 'Vedi tutte le statistiche', 'storegentic' ); ?></a></p>
<?php endif; ?>

<?php require __DIR__ . '/parti/sincronizzazione.php'; ?>
