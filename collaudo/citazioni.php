<?php
/**
 * Collaudo del riconoscimento dei prodotti citati.
 *
 * COME SI ESEGUE
 *   wp eval-file wp-content/plugins/storegentic/collaudo/citazioni.php
 *
 * Il collaudo crea i propri prodotti, li usa, e li cancella. Non tocca il
 * catalogo del negozio e non chiama il servizio: qui si prova soltanto la
 * lettura del testo di una risposta.
 *
 * PERCHE' QUESTI CASI, E NON ALTRI
 *
 * Ogni prova qui sotto e' un modo in cui un confronto ingenuo sbaglia, e ogni
 * modo e' stato osservato davvero su questo catalogo o costruito per rompere
 * una versione precedente di questo codice.
 *
 *   - un titolo che e' il principio di un altro titolo: chi nomina il lungo
 *     non vuole il corto;
 *   - un titolo che contiene tutte le parole di un altro: un confronto a
 *     insieme di parole li mostrerebbe entrambi;
 *   - due prodotti gemelli separati da un refuso nel catalogo;
 *   - un nome inventato che rimescola le parole di due prodotti veri: dentro
 *     ci sta per intero il titolo di uno dei due;
 *   - una frase che parla di categorie al plurale, dove la flessione italiana
 *     somiglia a un errore di battitura;
 *   - un titolo condiviso da piu' prodotti, che senza una chiave non si puo'
 *     sciogliere;
 *   - un indirizzo di prodotto dentro un collegamento, che e' una chiave
 *     esatta, e uno di un altro sito, che non lo e'.
 *
 * LA REGOLA CHE IL COLLAUDO DIFENDE: un falso positivo pesa piu' di un falso
 * negativo. Una scheda sbagliata sotto una risposta mette le parole e le
 * figure in contraddizione nella stessa schermata.
 *
 * @package Storegentic
 */

use Storegentic\Frontend\Citazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Il conto sta in $GLOBALS e non in una variabile in cima al file: wp eval-file
 * esegue il file dentro una funzione, quindi le variabili di primo livello non
 * sono globali, e `global $sg_falliti` puntava a un'altra cosa. Il collaudo
 * annunciava "tutte le prove superate" con una prova fallita sotto gli occhi —
 * cioe' il difetto peggiore che un collaudo possa avere.
 */
$GLOBALS['sg_falliti'] = 0;

/** Una prova, con il suo esito a schermo. */
function sg_prova( string $nome, bool $riuscita, string $dettaglio = '' ): void {
	if ( ! $riuscita ) {
		++$GLOBALS['sg_falliti'];
	}

	printf( "  %s  %s%s\n", $riuscita ? 'ok  ' : 'KO  ', $nome, '' !== $dettaglio ? "  ($dettaglio)" : '' );
}

/**
 * Crea un prodotto di prova e ne restituisce l'identificativo.
 */
function sg_crea( string $titolo, string $prezzo = '' ): int {
	$p = new WC_Product_Simple();
	$p->set_name( $titolo );
	$p->set_status( 'publish' );
	$p->set_catalog_visibility( 'visible' );
	$p->set_sku( 'COLLAUDO-' . wp_generate_password( 8, false, false ) );

	if ( '' !== $prezzo ) {
		$p->set_regular_price( $prezzo );
		$p->set_price( $prezzo );
	}

	return (int) $p->save();
}

echo "\nCollaudo delle citazioni\n========================\n\n";

/*
 * IL CATALOGO DI PROVA. I titoli sono costruiti in modo che ogni coppia
 * rappresenti una trappola diversa; i nomi sono inventati apposta per non
 * confondersi con quelli di nessun negozio vero.
 */
$sg_id = array(
	// coppia prefisso: il corto e' il principio del lungo
	'corto'      => sg_crea( 'Collana zaffiro in acciaio brunito', '59' ),
	'lungo'      => sg_crea( 'Collana zaffiro in acciaio brunito placcato oro', '79' ),
	// il lungo contiene tutte le parole del corto, ma non di seguito
	'inserito'   => sg_crea( 'Bracciale treccia sottile in acciaio brunito', '25' ),
	'contenente' => sg_crea( 'Bracciale treccia in acciaio brunito', '22' ),
	// gemelli separati da un refuso del catalogo
	'giusto'     => sg_crea( 'Anello elastico quarzo rosa antico', '20' ),
	'refuso'     => sg_crea( 'Anello elestico quarzo rosa antico', '20' ),
	// due prodotti che differiscono per la posizione di due parole
	'ordine_a'   => sg_crea( 'Spilla acciaio brunito placcato oro con cuori piccoli', '29' ),
	'ordine_b'   => sg_crea( 'Spilla acciaio brunito con cuori piccoli', '29' ),
	// omonimi: stesso titolo, due prodotti
	'omonimo_1'  => sg_crea( 'Parure zirconi verde bosco', '99' ),
	'omonimo_2'  => sg_crea( 'Parure zirconi verde bosco', '99' ),
	// titolo con una parola vuota in meno rispetto a come si scrive di solito
	'senza_con'  => sg_crea( 'Orecchini lunghi quarzo brunito antico', '45' ),
);

$sg_lumaca = get_post_field( 'post_name', $sg_id['corto'] );

$sg_casi = array(
	array(
		'nome'   => 'titolo esatto, una citazione',
		'testo'  => 'Ti consiglio la Collana zaffiro in acciaio brunito, molto elegante.',
		'attesi' => array( 'corto' ),
	),
	array(
		'nome'   => 'si nomina il titolo lungo: il corto non deve uscire',
		'testo'  => 'Ti consiglio la Collana zaffiro in acciaio brunito placcato oro a 79,00 €.',
		'attesi' => array( 'lungo' ),
	),
	array(
		'nome'   => 'parole tutte presenti ma non di seguito',
		'testo'  => 'Bracciale treccia sottile in acciaio brunito a 25,00 €.',
		'attesi' => array( 'inserito' ),
	),
	array(
		'nome'   => 'gemello con refuso: il testo scrive bene',
		'testo'  => 'Anello elastico quarzo rosa antico, 20,00 €.',
		'attesi' => array( 'giusto' ),
	),
	array(
		'nome'   => 'gemello con refuso: il refuso e nel catalogo',
		'testo'  => 'Anello elestico quarzo rosa antico, 20,00 €.',
		'attesi' => array( 'refuso' ),
	),
	array(
		'nome'   => 'nome inventato che rimescola due prodotti',
		'testo'  => 'Spilla acciaio brunito con cuori piccoli placcato oro, 29,00 €.',
		'attesi' => array(),
	),
	array(
		'nome'   => 'parola vuota aggiunta da chi scrive',
		'testo'  => 'Gli Orecchini lunghi CON quarzo brunito antico sono perfetti.',
		'attesi' => array( 'senza_con' ),
	),
	array(
		'nome'   => 'titolo condiviso da due prodotti: si tace',
		'testo'  => 'La Parure zirconi verde bosco e molto bella.',
		'attesi' => array(),
	),
	array(
		'nome'   => 'frase di categorie al plurale',
		'testo'  => 'Per un vestito nero: collane sottili, bracciali in acciaio e anelli minimal.',
		'attesi' => array(),
	),
	array(
		'nome'   => 'condizioni di vendita, nessun prodotto',
		'testo'  => 'La spedizione e gratuita da 60 euro e hai 30 giorni per il reso.',
		'attesi' => array(),
	),
	array(
		'nome'   => 'indirizzo del prodotto senza il titolo',
		'testo'  => 'Guarda qui: ' . home_url( '/prodotto/' . $sg_lumaca . '/' ) . ' ti piacera.',
		'attesi' => array( 'corto' ),
	),
	array(
		'nome'   => 'indirizzo di un altro sito',
		'testo'  => 'Guarda https://esempio.invalido/prodotto/' . $sg_lumaca . '/ e dimmi.',
		'attesi' => array(),
	),
	array( 'nome' => 'testo vuoto', 'testo' => '', 'attesi' => array() ),
	array( 'nome' => 'solo punteggiatura', 'testo' => '... !!! ???', 'attesi' => array() ),
);

foreach ( $sg_casi as $sg_c ) {
	$sg_attesi  = array_map( static fn( $k ) => $sg_id[ $k ], $sg_c['attesi'] );
	$sg_trovati = Citazioni::trova( $sg_c['testo'] );

	// Il collaudo guarda solo i propri prodotti: il catalogo vero non c'entra.
	$sg_trovati = array_values( array_intersect( $sg_trovati, array_values( $sg_id ) ) );

	$sg_manca  = array_diff( $sg_attesi, $sg_trovati );
	$sg_troppo = array_diff( $sg_trovati, $sg_attesi );

	$sg_dettaglio = '';

	foreach ( $sg_manca as $sg_x ) {
		$sg_dettaglio .= 'manca "' . get_the_title( $sg_x ) . '" ';
	}

	foreach ( $sg_troppo as $sg_x ) {
		$sg_dettaglio .= 'di piu "' . get_the_title( $sg_x ) . '" ';
	}

	sg_prova( $sg_c['nome'], empty( $sg_manca ) && empty( $sg_troppo ), trim( $sg_dettaglio ) );
}

/*
 * L'ordine delle schede segue l'ordine in cui la risposta nomina i prodotti:
 * un elenco che a schermo cambia ordine rispetto al testo sopra si legge come
 * un errore.
 */
$sg_ordine = Citazioni::trova(
	'Prima la Collana zaffiro in acciaio brunito placcato oro, poi il Bracciale treccia sottile in acciaio brunito.'
);
$sg_ordine = array_values( array_intersect( $sg_ordine, array_values( $sg_id ) ) );

sg_prova(
	'le schede seguono l ordine del testo',
	array( $sg_id['lungo'], $sg_id['inserito'] ) === $sg_ordine,
	implode( ',', $sg_ordine )
);

/* --------------------------------------------------------- pulizia */

foreach ( $sg_id as $sg_x ) {
	wp_delete_post( $sg_x, true );
}

printf( "\n%s\n", 0 === $GLOBALS['sg_falliti'] ? 'Tutte le prove superate.' : 'PROVE FALLITE: ' . $GLOBALS['sg_falliti'] );
echo "prodotti di collaudo cancellati.\n";
