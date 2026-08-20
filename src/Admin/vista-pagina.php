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

use Storegentic\Analitica\Misure;
use Storegentic\Api\Consumi;
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
/** @var array<int,array<string,mixed>> $consumi */
/** @var array<string,mixed> $riepilogo */
/** @var array<int,string> $mesi */
/** @var string|null $mese */

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

	<?php
	/*
	 * L'AVVISO PIU' IMPORTANTE DELLA PAGINA, E STA IN CIMA.
	 *
	 * Un contatore a zero non e' un dettaglio del piano: e' la ricerca del
	 * negozio che smette di rispondere ai clienti. Prima di questa riga la
	 * pagina diceva "Collegamento: attivo" mentre ogni ricerca del sito tornava
	 * un errore, perche' l'handshake riesce anche a quota finita — non consuma
	 * nulla — e il pannello guardava solo quello.
	 */
	$sg_finiti = array_values( array_filter( $consumi, static fn( $c ) => $c['esaurito'] ) );
	$sg_stretti = array_values( array_filter( $consumi, static fn( $c ) => $c['stretto'] ) );
	?>

	<?php if ( ! empty( $sg_finiti ) ) : ?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Il piano ha finito.', 'storegentic' ); ?></strong></p>
			<ul style="list-style:disc;margin-inline-start:1.5rem">
				<?php foreach ( $sg_finiti as $sg_c ) : ?>
					<li>
						<?php // Il due punti sta attaccato al nome: a capo diventerebbe uno spazio a schermo. ?>
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
					<?php esc_html_e( 'Nel frattempo la ricerca del sito continua a funzionare sul catalogo del negozio: trova le parole nei nomi e nelle descrizioni, non i concetti.', 'storegentic' ); ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'La ricerca del sito mostra un errore ai clienti.', 'storegentic' ); ?></strong>
					<?php esc_html_e( 'Accendi «Cerca nel catalogo quando il servizio non risponde», qui sotto, per farla comunque funzionare.', 'storegentic' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php elseif ( ! empty( $sg_stretti ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Il piano è quasi finito.', 'storegentic' ); ?></strong>
				<?php
				foreach ( $sg_stretti as $sg_c ) {
					printf(
						/* translators: 1: nome del contatore, 2: quanti ne restano. */
						esc_html__( '%1$s: ne restano %2$s. ', 'storegentic' ),
						esc_html( (string) $sg_c['nome'] ),
						esc_html( Consumi::scrivi( $sg_c['rimasto'], (string) $sg_c['unita'] ) )
					);
				}
				?>
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
					       list="sg-indirizzi" value="<?php echo esc_attr( (string) $i['base'] ); ?>">
					<datalist id="sg-indirizzi">
						<?php foreach ( \Storegentic\Impostazioni::INDIRIZZI as $sg_url => $sg_nome ) : ?>
							<option value="<?php echo esc_attr( $sg_url ); ?>"><?php echo esc_html( $sg_nome ); ?></option>
						<?php endforeach; ?>
					</datalist>
					<p class="description"><?php esc_html_e( 'Gli indirizzi ufficiali sono già nell’elenco. Cambialo solo se Storegentic te ne ha dato un altro: tutti gli altri indirizzi li dichiara il servizio.', 'storegentic' ); ?></p>
					<p class="description"><?php esc_html_e( 'Prima di salvarlo viene provato: se non risponde, resta quello di prima e te lo diciamo, invece di spegnere la ricerca sul negozio.', 'storegentic' ); ?></p>
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
						<?php
						$sg_preparate = \Storegentic\Frontend\Palette::preparate();
						$sg_scelta    = (string) $i['palette'];
						$sg_proprie   = (array) $i['colori'];
						?>
						<fieldset class="sg-palette">
							<?php foreach ( $sg_preparate as $sg_nome => $sg_p ) : ?>
								<label class="sg-palette__scelta">
									<input type="radio" name="palette" value="<?php echo esc_attr( $sg_nome ); ?>"
									       <?php checked( $sg_scelta, $sg_nome ); ?>>
									<span class="sg-palette__campioni" aria-hidden="true">
										<?php foreach ( array( 'sfondo', 'superficie', 'bordo', 'testo', 'accento' ) as $sg_v ) : ?>
											<i style="background:<?php echo esc_attr( (string) $sg_p['colori'][ $sg_v ] ); ?>"></i>
										<?php endforeach; ?>
									</span>
									<span class="sg-palette__nome"><?php echo esc_html( (string) $sg_p['nome'] ); ?></span>
									<span class="sg-palette__spiega"><?php echo esc_html( (string) $sg_p['spiega'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</fieldset>

						<div class="sg-propria" data-sg-propria <?php echo 'propria' === $sg_scelta ? '' : 'hidden'; ?>>
							<?php
							$sg_etichette = array(
								'sfondo'      => __( 'Sfondo', 'storegentic' ),
								'superficie'  => __( 'Superficie', 'storegentic' ),
								'testo'       => __( 'Testo', 'storegentic' ),
								'testo_tenue' => __( 'Testo tenue', 'storegentic' ),
								'bordo'       => __( 'Bordo', 'storegentic' ),
								'accento'     => __( 'Accento', 'storegentic' ),
								'su_accento'  => __( 'Sopra l’accento', 'storegentic' ),
							);
							$sg_base = $sg_preparate['neutro']['colori'];
							foreach ( \Storegentic\Frontend\Palette::VOCI as $sg_v ) :
								$sg_val = (string) ( $sg_proprie[ $sg_v ] ?? $sg_base[ $sg_v ] );
								?>
								<label class="sg-propria__voce">
									<span><?php echo esc_html( $sg_etichette[ $sg_v ] ); ?></span>
									<input type="color" name="colori[<?php echo esc_attr( $sg_v ); ?>]"
									       value="<?php echo esc_attr( $sg_val ); ?>" data-sg-colore="<?php echo esc_attr( $sg_v ); ?>">
								</label>
							<?php endforeach; ?>
						</div>

						<p class="sg-angoli">
							<label><?php esc_html_e( 'Angoli', 'storegentic' ); ?>
								<input type="number" name="raggio" min="0" max="24" style="width:5rem"
								       value="<?php echo esc_attr( (string) $i['raggio'] ); ?>" data-sg-raggio> px</label>
						</p>

						<?php
						/*
						 * L'anteprima non e' un vezzo. Sette colori scelti a numeri
						 * non dicono niente finche' non si vedono uno accanto
						 * all'altro: e' guardando la bolla scura sopra la carta
						 * chiara che ci si accorge che il testo non si legge.
						 */
						?>
						<div class="sg-anteprima" data-sg-anteprima>
							<p class="sg-anteprima__titolo"><?php esc_html_e( 'Anteprima', 'storegentic' ); ?></p>
							<div class="sg-anteprima__foglio">
								<div class="sg-anteprima__msg sg-anteprima__msg--assistente"><?php esc_html_e( 'Dimmi che cosa cerchi: ti propongo qualcosa.', 'storegentic' ); ?></div>
								<div class="sg-anteprima__msg sg-anteprima__msg--cliente"><?php esc_html_e( 'Un regalo sotto i 60 €', 'storegentic' ); ?></div>
								<div class="sg-anteprima__scheda">
									<span class="sg-anteprima__foto"></span>
									<span class="sg-anteprima__corpo">
										<b><?php esc_html_e( 'Nome del prodotto', 'storegentic' ); ?></b>
										<em><?php esc_html_e( 'Categoria', 'storegentic' ); ?></em>
									</span>
									<span class="sg-anteprima__prezzo">49,00 €</span>
								</div>
							</div>
						</div>

						<?php
						/*
						 * Il rapporto misurato, accanto all'anteprima. Prima qui
						 * c'era solo la regola scritta — "sotto 4,5:1 non si
						 * legge" — che e' un'informazione inutile finche' non si
						 * sa a quanto si sta. Lo riempie il JavaScript a ogni
						 * tocco; senza JavaScript resta vuoto, e il controllo lo
						 * fa comunque il salvataggio.
						 */
						?>
						<p class="sg-contrasto" data-sg-contrasto aria-live="polite"></p>

						<p class="description"><?php esc_html_e( 'Il testo deve staccare dallo sfondo: sotto un rapporto di 4,5:1 non è leggibile da tutti. I numeri qui sopra misurano gli accostamenti che contano davvero.', 'storegentic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Che cosa offre la finestra', 'storegentic' ); ?></th>
					<td>
						<fieldset>
							<?php
							$sg_modi = array(
								'cerca' => array( __( 'Ricerca a parole', 'storegentic' ), __( 'Chi sa che cosa vuole lo descrive e lo trova.', 'storegentic' ) ),
								'foto'  => array( __( 'Ricerca con una foto', 'storegentic' ), __( 'Si carica una foto e si trovano i prodotti che le somigliano.', 'storegentic' ) ),
								'chat'  => array( __( 'Assistente', 'storegentic' ), __( 'Chi non sa che cosa vuole lo chiede a parole sue.', 'storegentic' ) ),
							);
							$sg_accesi     = (array) $i['modi'];
							$sg_disponibili = array_keys( \Storegentic\Frontend\Finestra::modi() );
							foreach ( $sg_modi as $sg_nome => $sg_voce ) :
								?>
								<label style="display:block;margin-block-end:.5rem">
									<input type="checkbox" name="modi[]" value="<?php echo esc_attr( $sg_nome ); ?>"
									       <?php checked( in_array( $sg_nome, $sg_accesi, true ) ); ?>>
									<strong><?php echo esc_html( $sg_voce[0] ); ?></strong>
									<span class="description"> — <?php echo esc_html( $sg_voce[1] ); ?></span>
									<?php if ( in_array( $sg_nome, $sg_accesi, true ) && ! in_array( $sg_nome, $sg_disponibili, true ) ) : ?>
										<em style="color:#b32d2e"><?php esc_html_e( '(il servizio non la dichiara: non compare)', 'storegentic' ); ?></em>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Una modalità compare solo se la vuoi tu e se il servizio la dichiara. Un comando che risponde «non disponibile» è peggio di un comando assente.', 'storegentic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dove vanno i risultati', 'storegentic' ); ?></th>
					<td>
						<fieldset>
							<label style="display:block;margin-block-end:.4rem">
								<input type="radio" name="risultati" value="pagina" <?php checked( (string) $i['risultati'], 'pagina' ); ?>>
								<strong><?php esc_html_e( 'Nella pagina dei risultati', 'storegentic' ); ?></strong>
								<span class="description"> — <?php echo esc_html( sprintf( __( 'un indirizzo vero (%s): si condivide, il tasto Indietro funziona, c’è spazio per i filtri.', 'storegentic' ), wp_parse_url( \Storegentic\Frontend\Pagina::indirizzo(), PHP_URL_PATH ) ) ); ?></span>
							</label>
							<label style="display:block">
								<input type="radio" name="risultati" value="finestra" <?php checked( (string) $i['risultati'], 'finestra' ); ?>>
								<strong><?php esc_html_e( 'Dentro la finestra', 'storegentic' ); ?></strong>
								<span class="description"> — <?php esc_html_e( 'niente pagine in più nel sito: il widget basta a sé stesso.', 'storegentic' ); ?></span>
							</label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'La ricerca con la foto e l’assistente restano sempre nella finestra: una foto non si può mettere in un indirizzo, e una conversazione non è una pagina.', 'storegentic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sg-etichetta-avvio"><?php esc_html_e( 'Testo del pulsante', 'storegentic' ); ?></label></th>
					<td>
						<input type="text" id="sg-etichetta-avvio" name="etichetta_avvio" class="regular-text"
						       value="<?php echo esc_attr( (string) $i['etichetta_avvio'] ); ?>"
						       placeholder="<?php echo esc_attr( \Storegentic\Frontend\Finestra::etichetta() ); ?>">
						<p class="description"><?php esc_html_e( 'Lasciandolo vuoto il testo si adatta alle modalità accese.', 'storegentic' ); ?></p>
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
					<th scope="row"><label for="sg-saluto"><?php esc_html_e( 'Prima frase dell’assistente', 'storegentic' ); ?></label></th>
					<td>
						<input type="text" id="sg-saluto" name="saluto" class="large-text"
						       value="<?php echo esc_attr( (string) $i['saluto'] ); ?>"
						       placeholder="<?php esc_attr_e( 'Dimmi che cosa cerchi o per chi è il regalo: ti propongo qualcosa.', 'storegentic' ); ?>">
						<p class="description"><?php esc_html_e( 'È la frase che il cliente legge appena apre l’assistente. Lasciala vuota per quella predefinita.', 'storegentic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ricerca del sito', 'storegentic' ); ?></th>
					<td>
						<label><input type="checkbox" name="sostituisci_ricerca" <?php checked( (bool) $i['sostituisci_ricerca'] ); ?>>
							<?php esc_html_e( 'Sostituisci la ricerca del tema con quella di Storegentic', 'storegentic' ); ?></label>
						<p class="description"><?php esc_html_e( 'La lente e il campo di ricerca del tuo tema apriranno la ricerca semantica invece di quella di WordPress. Se il tuo tema usa un markup particolare, il filtro storegentic_inneschi_ricerca permette di indicarlo.', 'storegentic' ); ?></p>
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
			</table>

			<?php
			/*
			 * I titoli servono a chi legge; a chi salva serve il campo sentinella
			 * `gruppi[]`, che sta piu' su. Le righe qui sotto appartengono al
			 * gruppo "catalogo" anche se il titolo e' un altro: la divisione in
			 * gruppi dice quali caselle erano stampate, non come sono raccolte a
			 * schermo.
			 */
			?>
			<h2 class="title"><?php esc_html_e( 'Se il servizio non risponde', 'storegentic' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Ripiego', 'storegentic' ); ?></th>
					<td>
						<label><input type="checkbox" name="ripiego" <?php checked( (bool) $i['ripiego'] ); ?>>
							<?php esc_html_e( 'Cerca nel catalogo del negozio quando il servizio non risponde', 'storegentic' ); ?></label>
						<p class="description"><?php esc_html_e( 'Succede a quota finita, durante una manutenzione o se la rete cade. Il ripiego cerca le parole nei nomi e nelle descrizioni brevi: trova meno cose della ricerca intelligente, ma il cliente vede dei prodotti invece di un errore.', 'storegentic' ); ?></p>
						<p class="description"><?php esc_html_e( 'Quando entra in funzione, il cliente lo legge: la ricerca dice che i risultati arrivano dal catalogo del negozio.', 'storegentic' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Analisi e statistiche', 'storegentic' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Cosa se ne fa', 'storegentic' ); ?></th>
					<td>
						<label style="display:block;margin-block-end:.4rem">
							<input type="checkbox" name="analitica" <?php checked( (bool) $i['analitica'] ); ?>>
							<?php esc_html_e( 'Manda a Storegentic cosa viene cercato e cosa viene aperto', 'storegentic' ); ?>
						</label>
						<label style="display:block">
							<input type="checkbox" name="statistiche" <?php checked( (bool) $i['statistiche'] ); ?>>
							<?php esc_html_e( 'Tieni il conto anche qui, per le statistiche di questa pagina', 'storegentic' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Sono due cose separate. La prima serve al servizio per migliorare le risposte. La seconda resta nel tuo database e riempie le statistiche qui sotto: Storegentic non offre un modo per rileggere quello che gli mandi.', 'storegentic' ); ?></p>
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

	<?php if ( ! empty( $consumi ) ) : ?>
		<h2 class="title">
			<?php esc_html_e( 'Piano e consumi', 'storegentic' ); ?>
			<?php if ( '' !== Consumi::piano() ) : ?>
				<span class="sg-piano"><?php echo esc_html( Consumi::piano() ); ?></span>
			<?php endif; ?>
		</h2>

		<p class="description" style="max-width:52rem">
			<?php esc_html_e( 'Questi numeri li dichiara Storegentic a ogni collegamento. Sotto, nelle statistiche, c’è invece com’è andata davvero: le due cose possono non coincidere.', 'storegentic' ); ?>
		</p>

		<table class="widefat striped sg-consumi" style="max-width:52rem">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Cosa', 'storegentic' ); ?></th>
					<th style="width:9rem"><?php esc_html_e( 'Usato', 'storegentic' ); ?></th>
					<th style="width:9rem"><?php esc_html_e( 'Restano', 'storegentic' ); ?></th>
					<th style="width:12rem"><?php esc_html_e( 'Quanto ne resta', 'storegentic' ); ?></th>
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
							 * La barra dice quanto ne resta, non quanto se n'e'
							 * usato: e' la domanda che si fa chi guarda. Il
							 * `title` ripete il numero perche' una barra da sola
							 * non e' leggibile da chi usa uno screen reader.
							 */
							$sg_resta = (int) round( ( 1 - (float) $sg_c['quota'] ) * 100 );
							?>
							<span class="sg-barra<?php echo $sg_c['esaurito'] ? ' sg-barra--finita' : ( $sg_c['stretto'] ? ' sg-barra--stretta' : '' ); ?>">
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
	<?php endif; ?>

	<?php require __DIR__ . '/vista-statistiche.php'; ?>
</div>
