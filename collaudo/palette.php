<?php
/**
 * Collaudo dei colori.
 *
 * COME SI ESEGUE
 *   wp eval-file wp-content/plugins/storegentic/collaudo/palette.php
 *
 * PERCHE' ESISTE. Il file dei colori dichiarava, in cima, che tutte le
 * combinazioni preparate rispettano il contrasto richiesto. Era una promessa
 * scritta a parole, e a parole e' rimasta anche quando ha smesso di essere
 * vera: "Inchiostro e oro" — proprio quella consigliata a chi vende oggetti —
 * aveva il bianco sull'oro a 3,78:1, sotto il 4,5:1 del WCAG 1.4.3, e l'accento
 * tinge i pulsanti e i cartellini del prezzo. Nessuno se n'era accorto perche'
 * un contrasto non si vede a occhio: si misura.
 *
 * Da qui in avanti la promessa la difende questo file. Chi tocca un colore, o
 * ne aggiunge uno nuovo, lo scopre subito invece che in produzione.
 *
 * COSA SI MISURA. Solo gli accostamenti che l'interfaccia mette davvero uno
 * sull'altro: l'elenco sta in Palette::ACCOSTAMENTI, accanto ai colori, e non
 * qui, perche' e' una proprieta' del disegno e non della prova.
 *
 * @package Storegentic
 */

use Storegentic\Frontend\Palette;
use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['sg_falliti'] = 0;

/** Una prova, con il suo esito a schermo. */
function sg_prova( string $nome, bool $riuscita, string $dettaglio = '' ): void {
	if ( ! $riuscita ) {
		++$GLOBALS['sg_falliti'];
	}

	printf( "  %s  %s%s\n", $riuscita ? 'ok  ' : 'KO  ', $nome, '' !== $dettaglio ? "  ($dettaglio)" : '' );
}

echo "\nCollaudo dei colori\n===================\n\n";

echo "Contrasto delle combinazioni preparate (serve 4,5:1)\n";

foreach ( Palette::preparate() as $sg_nome => $sg_p ) {
	$sg_guai = Palette::verifica( (array) $sg_p['colori'] );

	$sg_dettaglio = '';

	foreach ( $sg_guai as $sg_g ) {
		$sg_dettaglio .= sprintf( '%s: %.2f:1  ', $sg_g['cosa'], $sg_g['rapporto'] );
	}

	sg_prova( (string) $sg_p['nome'], empty( $sg_guai ), trim( $sg_dettaglio ) );
}

echo "\nLa misura del contrasto\n";

/*
 * I due estremi e un valore noto. Bianco su nero e' 21:1 per definizione, un
 * colore su se' stesso e' 1:1, e #767676 su bianco e' l'esempio classico del
 * WCAG per il grigio piu' chiaro ancora ammesso sul testo normale.
 */
sg_prova( 'bianco su nero fa 21:1', 21.0 === Palette::contrasto( '#FFFFFF', '#000000' ) );
sg_prova( 'un colore su se stesso fa 1:1', 1.0 === Palette::contrasto( '#A57C3E', '#A57C3E' ) );
sg_prova( 'il grigio limite del WCAG passa appena', Palette::contrasto( '#767676', '#FFFFFF' ) >= 4.5 );
sg_prova( 'l ordine dei due colori non cambia il risultato', Palette::contrasto( '#1A1815', '#FFF' ) === Palette::contrasto( '#FFF', '#1A1815' ) );
sg_prova( 'la forma a tre cifre vale come quella a sei', Palette::contrasto( '#FFF', '#000' ) === Palette::contrasto( '#FFFFFF', '#000000' ) );

echo "\nLe variabili CSS\n";

$sg_prima = Impostazioni::tutte();

/*
 * Le impostazioni si rimettono come stavano anche se la prova si interrompe a
 * meta': un collaudo che lascia il negozio con altri colori e' un danno, non
 * una prova. Lezione gia' imparata con collaudo/sincronizzazione.php, che una
 * volta ha spento il widget in produzione.
 */
register_shutdown_function(
	static function () use ( $sg_prima ): void {
		Impostazioni::salva( $sg_prima );
	}
);

Impostazioni::salva( array( 'palette' => 'tema', 'raggio' => 10 ) );
$sg_css = Palette::css();

sg_prova( 'con "dal tema" non si scrive nessun colore', ! str_contains( $sg_css, '--sg-inchiostro:' ) );
sg_prova( 'con "dal tema" gli angoli si scrivono lo stesso', str_contains( $sg_css, '--sg-raggio:10px' ) );

Impostazioni::salva( array( 'palette' => 'notte' ) );
$sg_css = Palette::css();

/*
 * Si controllano i nomi, non quanti sono. Contarli richiedeva di ricordarsi che
 * oltre ai sette colori ci sono anche il velo, l'ombra e l'accento tenue, e
 * infatti la prima versione di questa riga contava male e falliva su un codice
 * giusto. Un elenco di nomi dice anche QUALE variabile manca.
 */
$sg_attese = array( '--sg-fondo:', '--sg-carta:', '--sg-inchiostro:', '--sg-inchiostro-2:', '--sg-linea:', '--sg-colore:', '--sg-testo:', '--sg-velo:', '--sg-ombra:', '--sg-accento-tenue:' );
$sg_manca  = array_values( array_filter( $sg_attese, static fn( $v ) => ! str_contains( $sg_css, $v ) ) );

sg_prova( 'una combinazione scelta scrive tutte le variabili', empty( $sg_manca ), implode( ' ', $sg_manca ) );
sg_prova( 'il velo si ricava dal testo, non da un nero fisso', str_contains( $sg_css, '--sg-velo:rgba(242,243,245' ) );

Impostazioni::salva( array( 'raggio' => 0 ) );
sg_prova( 'a raggio zero la scala non va sotto zero', str_contains( Palette::css(), '--sg-raggio-s:2px' ) );

Impostazioni::salva( array( 'raggio' => 999 ) );
sg_prova( 'un raggio assurdo viene riportato al massimo', str_contains( Palette::css(), '--sg-raggio:24px' ) );

Impostazioni::salva( array( 'palette' => 'propria', 'colori' => array( 'accento' => '#123456' ) ) );
$sg_css = Palette::css();

sg_prova( 'un colore proprio vince sul preparato', str_contains( $sg_css, '--sg-colore:#123456' ) );
sg_prova( 'gli altri colori restano quelli del neutro', str_contains( $sg_css, '--sg-carta:#FFFFFF' ) );

Impostazioni::salva( array( 'colori' => array( 'accento' => 'non-un-colore' ) ) );
sg_prova( 'un colore illeggibile non entra nelle impostazioni', array() === (array) Impostazioni::leggi( 'colori' ) );

echo "\nIl controllo dei colori propri\n";

sg_prova(
	'una combinazione leggibile non da segnalazioni',
	empty( Palette::verifica( array( 'testo' => '#000000', 'superficie' => '#FFFFFF', 'sfondo' => '#FFFFFF', 'testo_tenue' => '#595959', 'accento' => '#000000', 'su_accento' => '#FFFFFF' ) ) )
);

$sg_guai = Palette::verifica( array( 'testo' => '#CCCCCC', 'superficie' => '#FFFFFF', 'sfondo' => '#FFFFFF', 'testo_tenue' => '#DDDDDD', 'accento' => '#EEEEEE', 'su_accento' => '#FFFFFF' ) );

sg_prova( 'una combinazione illeggibile viene segnalata tutta', 4 === count( $sg_guai ), count( $sg_guai ) . ' accostamenti' );

sg_prova(
	'un colore mancante non fa fallire il controllo',
	empty( Palette::verifica( array( 'testo' => '#000000' ) ) )
);

printf(
	"\n%s\n\n",
	0 === $GLOBALS['sg_falliti']
		? 'Tutte le prove superate.'
		: sprintf( '%d prove fallite.', $GLOBALS['sg_falliti'] )
);
