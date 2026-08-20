<?php
/**
 * Aspetto: che faccia ha il widget, e cosa offre.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Frontend\Finestra;
use Storegentic\Frontend\Forma;
use Storegentic\Frontend\Palette;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array<string,mixed> $i */
/** @var bool $collegato */

if ( ! $collegato ) {
	printf(
		'<p class="description sg-largo">%s</p>',
		esc_html__( 'L’aspetto si configura a servizio collegato: quali modalità mostrare dipende anche da quali il servizio dichiara.', 'storegentic' )
	);

	return;
}

self::apri_modulo();
?>

<h2 class="title"><?php esc_html_e( 'Colori', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Combinazione', 'storegentic' ); ?></th>
		<td>
			<?php
			$sg_preparate = Palette::preparate();
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

				foreach ( Palette::VOCI as $sg_v ) :
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
					<input type="number" name="raggio" min="0" max="24" class="sg-numero"
					       value="<?php echo esc_attr( (string) $i['raggio'] ); ?>" data-sg-raggio> px</label>
			</p>

			<?php
			/*
			 * L'anteprima non e' un vezzo. Sette colori scelti a numeri non
			 * dicono niente finche' non si vedono uno accanto all'altro: e'
			 * guardando la bolla scura sopra la carta chiara che ci si accorge
			 * che il testo non si legge.
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
			 * Il rapporto misurato, accanto all'anteprima. Prima qui c'era solo
			 * la regola scritta — "sotto 4,5:1 non si legge" — che e'
			 * un'informazione inutile finche' non si sa a quanto si sta. Lo
			 * riempie il JavaScript a ogni tocco; senza JavaScript resta vuoto,
			 * e il controllo lo fa comunque il salvataggio.
			 */
			?>
			<p class="sg-contrasto" data-sg-contrasto aria-live="polite"></p>

			<p class="description"><?php esc_html_e( 'Il testo deve staccare dallo sfondo: sotto un rapporto di 4,5:1 non è leggibile da tutti. I numeri qui sopra misurano gli accostamenti che contano davvero.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<h2 class="title"><?php esc_html_e( 'Il pulsante e la finestra', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Che cosa offre la finestra', 'storegentic' ); ?></th>
		<td>
			<fieldset>
				<?php
				$sg_modi = array(
					'cerca' => array( __( 'Ricerca a parole', 'storegentic' ), __( 'Chi sa che cosa vuole lo descrive e lo trova.', 'storegentic' ) ),
					'foto'  => array( __( 'Ricerca con una foto', 'storegentic' ), __( 'Si carica una foto e si trova quello che le somiglia.', 'storegentic' ) ),
					'chat'  => array( __( 'Assistente', 'storegentic' ), __( 'Chi non sa che cosa vuole lo chiede a parole sue.', 'storegentic' ) ),
				);
				$sg_accesi      = (array) $i['modi'];
				$sg_disponibili = array_keys( Finestra::modi() );

				foreach ( $sg_modi as $sg_nome => $sg_voce ) :
					?>
					<label class="sg-riga-spunta">
						<input type="checkbox" name="modi[]" value="<?php echo esc_attr( $sg_nome ); ?>"
						       <?php checked( in_array( $sg_nome, $sg_accesi, true ) ); ?>>
						<strong><?php echo esc_html( $sg_voce[0] ); ?></strong>
						<span class="description"> — <?php echo esc_html( $sg_voce[1] ); ?></span>
						<?php if ( in_array( $sg_nome, $sg_accesi, true ) && ! in_array( $sg_nome, $sg_disponibili, true ) ) : ?>
							<em class="sg-allarme"><?php esc_html_e( '(il servizio non la dichiara: non compare)', 'storegentic' ); ?></em>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description"><?php esc_html_e( 'Una modalità compare solo se la vuoi tu e se il servizio la dichiara. Un comando che risponde «non disponibile» è peggio di un comando assente.', 'storegentic' ); ?></p>
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
		<th scope="row"><label for="sg-etichetta-avvio"><?php esc_html_e( 'Testo del pulsante', 'storegentic' ); ?></label></th>
		<td>
			<input type="text" id="sg-etichetta-avvio" name="etichetta_avvio" class="regular-text"
			       value="<?php echo esc_attr( (string) $i['etichetta_avvio'] ); ?>"
			       placeholder="<?php echo esc_attr( Finestra::etichetta() ); ?>">
			<p class="description"><?php esc_html_e( 'Lasciandolo vuoto il testo si adatta alle modalità accese.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sg-segnaposto"><?php esc_html_e( 'Testo nel campo', 'storegentic' ); ?></label></th>
		<td>
			<input type="text" id="sg-segnaposto" name="segnaposto" class="regular-text"
			       value="<?php echo esc_attr( (string) $i['segnaposto'] ); ?>"
			       placeholder="<?php esc_attr_e( 'Che cosa stai cercando?', 'storegentic' ); ?>">
			<p class="description"><?php esc_html_e( 'Un esempio concreto fa capire cosa si può chiedere meglio di una domanda generica.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sg-saluto"><?php esc_html_e( 'Prima frase dell’assistente', 'storegentic' ); ?></label></th>
		<td>
			<input type="text" id="sg-saluto" name="saluto" class="large-text"
			       value="<?php echo esc_attr( (string) $i['saluto'] ); ?>"
			       placeholder="<?php esc_attr_e( 'Dimmi che cosa cerchi o per chi è il regalo: ti propongo qualcosa.', 'storegentic' ); ?>">
			<p class="description"><?php esc_html_e( 'È la frase che si legge appena si apre l’assistente. Lasciala vuota per quella predefinita.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>


<h2 class="title"><?php esc_html_e( 'Come si apre', 'storegentic' ); ?></h2>

<p class="description sg-largo">
	<?php esc_html_e( 'È la scelta che cambia di più la sensazione del negozio, più dei colori. Una finestra al centro chiede attenzione; un cassetto lascia continuare a leggere.', 'storegentic' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Forma della finestra', 'storegentic' ); ?></th>
		<td>
			<fieldset class="sg-scelte">
				<?php foreach ( Forma::forme() as $sg_n => $sg_f ) : ?>
					<label class="sg-scelta">
						<input type="radio" name="forma" value="<?php echo esc_attr( $sg_n ); ?>" <?php checked( (string) $i['forma'], $sg_n ); ?>>
						<span class="sg-scelta__segno sg-scelta__segno--<?php echo esc_attr( $sg_n ); ?>" aria-hidden="true"><i></i></span>
						<span class="sg-scelta__nome"><?php echo esc_html( (string) $sg_f['nome'] ); ?></span>
						<span class="sg-scelta__spiega"><?php echo esc_html( (string) $sg_f['spiega'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description"><?php esc_html_e( 'Sul telefono «al centro» e «a lato» diventano schermo pieno: un cassetto largo quanto lo schermo è lo schermo. «Dal basso» invece resta dal basso, perché è lì che arriva il pollice.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Quanto è grande', 'storegentic' ); ?></th>
		<td>
			<p class="sg-misura">
				<label for="sg-larghezza"><?php esc_html_e( 'Larghezza', 'storegentic' ); ?></label>
				<input type="number" id="sg-larghezza" name="larghezza" class="sg-numero" min="320" max="1200" step="10"
				       value="<?php echo esc_attr( (string) $i['larghezza'] ); ?>">
				<span class="sg-tenue"><?php esc_html_e( 'decimi di rem — 680 fa 68rem, circa 1088 px', 'storegentic' ); ?></span>
			</p>
			<p class="sg-misura">
				<label for="sg-altezza"><?php esc_html_e( 'Altezza', 'storegentic' ); ?></label>
				<input type="number" id="sg-altezza" name="altezza" class="sg-numero" min="300" max="1200" step="10"
				       value="<?php echo esc_attr( (string) $i['altezza'] ); ?>">
			</p>
			<p class="description"><?php esc_html_e( 'Sono massimi: su uno schermo più stretto la finestra si adatta da sola e non esce mai dai bordi.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Il comando che apre', 'storegentic' ); ?></th>
		<td>
			<fieldset class="sg-scelte">
				<?php foreach ( Forma::pulsanti() as $sg_n => $sg_p ) : ?>
					<label class="sg-scelta">
						<input type="radio" name="pulsante" value="<?php echo esc_attr( $sg_n ); ?>" <?php checked( (string) $i['pulsante'], $sg_n ); ?>>
						<span class="sg-scelta__nome"><?php echo esc_html( (string) $sg_p['nome'] ); ?></span>
						<span class="sg-scelta__spiega"><?php echo esc_html( (string) $sg_p['spiega'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="sg-misura">
				<label for="sg-distanza"><?php esc_html_e( 'Distanza dal bordo', 'storegentic' ); ?></label>
				<input type="number" id="sg-distanza" name="distanza" class="sg-numero" min="0" max="80" step="5"
				       value="<?php echo esc_attr( (string) $i['distanza'] ); ?>">
				<span class="sg-tenue"><?php esc_html_e( 'alzalo se il sito ha già un comando in basso', 'storegentic' ); ?></span>
			</p>
		</td>
	</tr>
</table>

<h2 class="title"><?php esc_html_e( 'Come si legge', 'storegentic' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Densità', 'storegentic' ); ?></th>
		<td>
			<fieldset class="sg-scelte">
				<?php foreach ( Forma::densita() as $sg_n => $sg_d ) : ?>
					<label class="sg-scelta">
						<input type="radio" name="densita" value="<?php echo esc_attr( $sg_n ); ?>" <?php checked( (string) $i['densita'], $sg_n ); ?>>
						<span class="sg-scelta__nome"><?php echo esc_html( (string) $sg_d['nome'] ); ?></span>
						<span class="sg-scelta__spiega"><?php echo esc_html( (string) $sg_d['spiega'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sg-colonna"><?php esc_html_e( 'Larghezza minima di una scheda', 'storegentic' ); ?></label></th>
		<td>
			<input type="number" id="sg-colonna" name="colonna" class="sg-numero" min="90" max="280" step="5"
			       value="<?php echo esc_attr( (string) $i['colonna'] ); ?>">
			<p class="description"><?php esc_html_e( 'Da qui si decidono le colonne: più stretta, più prodotti in riga. Non serve indicare un numero di colonne, che sarebbe sbagliato a ogni altra larghezza di schermo.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sg-caratteri"><?php esc_html_e( 'Carattere dei titoli', 'storegentic' ); ?></label></th>
		<td>
			<select id="sg-caratteri" name="caratteri">
				<?php foreach ( Forma::caratteri() as $sg_n => $sg_c ) : ?>
					<option value="<?php echo esc_attr( $sg_n ); ?>" <?php selected( (string) $i['caratteri'], $sg_n ); ?>><?php echo esc_html( (string) $sg_c['nome'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Nessun carattere si scarica da fuori: sono quelli già presenti nei dispositivi. Non fanno attendere il testo e non mandano l’indirizzo di chi visita a servizi di terze parti.', 'storegentic' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Dietro la finestra', 'storegentic' ); ?></th>
		<td>
			<p class="sg-misura">
				<label for="sg-velo"><?php esc_html_e( 'Quanto si copre la pagina', 'storegentic' ); ?></label>
				<input type="number" id="sg-velo" name="velo" class="sg-numero" min="0" max="100" step="10"
				       value="<?php echo esc_attr( (string) $i['velo'] ); ?>">
				<span class="sg-tenue">%</span>
			</p>
			<label class="sg-riga-spunta"><input type="checkbox" name="sfocatura" <?php checked( (bool) $i['sfocatura'] ); ?>>
				<?php esc_html_e( 'Sfoca quello che sta dietro', 'storegentic' ); ?></label>
			<label class="sg-riga-spunta"><input type="checkbox" name="movimento" <?php checked( (bool) $i['movimento'] ); ?>>
				<?php esc_html_e( 'La finestra entra con un’animazione', 'storegentic' ); ?></label>
			<p class="description"><?php esc_html_e( 'Chi ha chiesto meno animazioni al proprio dispositivo non le vede comunque: è una richiesta di accessibilità, non una preferenza.', 'storegentic' ); ?></p>
		</td>
	</tr>
</table>

<?php self::chiudi_modulo(); ?>
