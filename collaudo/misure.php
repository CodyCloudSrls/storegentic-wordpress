<?php
/**
 * Collaudo delle statistiche tenute in casa.
 *
 * COME SI ESEGUE
 *   wp eval-file wp-content/plugins/storegentic/collaudo/misure.php
 *
 * Il collaudo scrive nel mese in corso, quindi PRIMA si mette da parte quello
 * che c'e' e alla fine lo si rimette. Un collaudo che cancella le statistiche
 * vere del negozio e' un danno, non una prova: lezione gia' pagata con
 * collaudo/sincronizzazione.php, che una volta ha spento il widget in
 * produzione.
 *
 * COSA DIFENDE, IN ORDINE DI SUBDOLITA'
 *
 *   1. Il conto dei mesi. "Un mese fa" a partire dal 31 maggio, in PHP, fa
 *      1 maggio: il 31 aprile non esiste e la data trabocca in avanti. Il
 *      difetto compare solo negli ultimi giorni di certi mesi, cioe' quasi mai
 *      quando si prova. Qui si provano apposta i giorni scomodi.
 *   2. La differenza fra "il cliente non ha visto niente" e "il servizio non ha
 *      risposto". Sono due colonne diverse del pannello e con il ripiego acceso
 *      non coincidono.
 *   3. Il tetto: l'opzione non deve crescere senza fine, e cio' che si butta
 *      va dichiarato invece che sparire in silenzio.
 *   4. Le ricerche che contengono un dato personale: si contano, non si
 *      trascrivono.
 *
 * @package Storegentic
 */

use Storegentic\Analitica\Misure;
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

echo "\nCollaudo delle statistiche\n==========================\n\n";

/* --------------------------------------------------------- il magazzino */

$sg_opzione = 'storegentic_misure_' . wp_date( 'Y_m' );
$sg_prima   = get_option( $sg_opzione, null );
$sg_acceso  = (bool) Impostazioni::leggi( 'statistiche' );

register_shutdown_function(
	static function () use ( $sg_opzione, $sg_prima, $sg_acceso ): void {
		if ( null === $sg_prima ) {
			delete_option( $sg_opzione );
		} else {
			update_option( $sg_opzione, $sg_prima, false );
		}

		Impostazioni::salva( array( 'statistiche' => $sg_acceso ) );

		echo "statistiche del negozio ripristinate.\n";
	}
);

Impostazioni::salva( array( 'statistiche' => true ) );
delete_option( $sg_opzione );

/* ------------------------------------------------------------- i mesi */

echo "Il conto dei mesi\n";

/**
 * I tre mesi attesi a ritroso da una data, calcolati in un altro modo.
 *
 * Si parte dall'anno e dal mese e si scende di uno per volta a mano: e' il
 * conto che farebbe una persona su un calendario, e non passa da strtotime,
 * quindi non puo' sbagliare nello stesso modo del codice che sta provando.
 *
 * @return array<int,string>
 */
function sg_mesi_attesi( int $anno, int $mese ): array {
	$fuori = array();

	for ( $i = 0; $i < 3; $i++ ) {
		$fuori[] = sprintf( '%04d_%02d', $anno, $mese );

		--$mese;

		if ( $mese < 1 ) {
			$mese = 12;
			--$anno;
		}
	}

	return $fuori;
}

$sg_giorni = array(
	'31 maggio (aprile ha 30 giorni)'  => array( 2026, 5, 31 ),
	'30 marzo (febbraio ne ha 28)'     => array( 2026, 3, 30 ),
	'31 dicembre (si cambia anno)'     => array( 2026, 12, 31 ),
	'29 febbraio (anno bisestile)'     => array( 2028, 2, 29 ),
	'1 gennaio (si torna indietro)'    => array( 2027, 1, 1 ),
	'15 giugno (un giorno qualunque)'  => array( 2026, 6, 15 ),
);

foreach ( $sg_giorni as $sg_nome => $sg_g ) {
	list( $sg_a, $sg_m, $sg_d ) = $sg_g;

	$sg_avuti  = Misure::finestra( mktime( 12, 0, 0, $sg_m, $sg_d, $sg_a ) );
	$sg_attesi = sg_mesi_attesi( $sg_a, $sg_m );

	sg_prova( $sg_nome, $sg_avuti === $sg_attesi, implode( ', ', $sg_avuti ) );
}

sg_prova( 'i mesi conservati sono tre', 3 === Misure::MESI );

/* ------------------------------------------------------------ i conti */

echo "\nCosa ha visto il cliente, e cosa ha fatto il servizio\n";

Misure::segna( 'ricerca', 'collana di perle', 5, 900 );
Misure::segna( 'ricerca', 'collana di perle', 5, 1100 );
Misure::segna( 'ricerca', 'cavigliera', 0, 800 );
// Il servizio e' caduto, ma il ripiego ha trovato lo stesso tre prodotti.
Misure::segna( 'ricerca', 'bracciale rigido', 3, 120, 'Search quota exceeded', 429 );
// Il servizio e' caduto e non ha rimediato nessuno.
Misure::segna( 'ricerca', 'spilla vintage', 0, 130, 'Search quota exceeded', 429 );

$sg_r = Misure::riepilogo();
$sg_f = $sg_r['funzioni']['ricerca'];

sg_prova( 'le domande si contano tutte', 5 === $sg_f['chiamate'], (string) $sg_f['chiamate'] );
sg_prova( 'riuscite piu vuote fanno il totale', $sg_f['riuscite'] + $sg_f['vuote'] === $sg_f['chiamate'] );
sg_prova( 'senza risultati sono due', 2 === $sg_f['vuote'], (string) $sg_f['vuote'] );
sg_prova( 'il servizio e stato muto due volte', 2 === $sg_f['fallite'], (string) $sg_f['fallite'] );
sg_prova(
	'una domanda salvata dal ripiego non conta come vuota',
	0 === (int) ( $sg_r['cercate']['bracciale rigido']['senza'] ?? -1 )
);
sg_prova( 'l ultimo errore resta scritto', 429 === (int) ( $sg_f['ultimo_errore']['codice'] ?? 0 ) );

echo "\nLe domande\n";

sg_prova( 'la stessa domanda non fa due righe', 2 === (int) ( $sg_r['cercate']['collana di perle']['quante'] ?? 0 ) );
sg_prova( 'le domande si contano anche a servizio muto', isset( $sg_r['cercate']['spilla vintage'] ) );
sg_prova( 'chi non trova niente finisce nell elenco giusto', isset( $sg_r['senza']['cavigliera'] ) );
sg_prova( 'chi trova non ci finisce', ! isset( $sg_r['senza']['collana di perle'] ) );

Misure::segna( 'ricerca', '  COLLANA   di   Perle ', 5, 900 );
$sg_r = Misure::riepilogo();

sg_prova(
	'maiuscole e spazi doppi sono la stessa domanda',
	3 === (int) ( $sg_r['cercate']['collana di perle']['quante'] ?? 0 ),
	(string) ( $sg_r['cercate']['collana di perle']['quante'] ?? 0 )
);

echo "\nIl tempo\n";

/*
 * La media si rilegge adesso e non si riusa quella di prima: fra le due c'e'
 * stata un'altra ricerca, e la prima versione di questa prova confrontava un
 * numero vecchio con uno nuovo e falliva su un codice giusto.
 */
$sg_media = Misure::riepilogo()['funzioni']['ricerca']['ms_medio'];

sg_prova( 'il tempo medio e la media dei tempi veri', $sg_media > 0, $sg_media . ' ms' );

Misure::segna( 'ricerca', 'anello', 4, 0 );

sg_prova(
	'una risposta in cache non abbassa la media',
	Misure::riepilogo()['funzioni']['ricerca']['ms_medio'] === $sg_media,
	Misure::riepilogo()['funzioni']['ricerca']['ms_medio'] . ' ms'
);

echo "\nQuello che non si conserva\n";

Misure::segna( 'ricerca', 'scrivimi a mario.rossi@esempio.it', 2, 500 );
Misure::segna( 'ricerca', 'il mio ordine 3391827465', 2, 500 );

$sg_r = Misure::riepilogo();

sg_prova( 'un indirizzo di posta non si trascrive', ! isset( $sg_r['cercate']['scrivimi a mario.rossi@esempio.it'] ) );
sg_prova( 'un numero lungo non si trascrive', ! isset( $sg_r['cercate']['il mio ordine 3391827465'] ) );
sg_prova( 'ma si contano, e il pannello lo dice', 2 === $sg_r['riservate'], (string) $sg_r['riservate'] );

echo "\nIl tetto\n";

/*
 * Si riempie l'elenco oltre il tetto con domande tutte diverse, poi si
 * controlla che non sia cresciuto e che le scartate siano dichiarate.
 */
for ( $sg_i = 0; $sg_i < 460; $sg_i++ ) {
	Misure::segna( 'ricerca', 'domanda unica numero ' . $sg_i, 1, 100 );
}

$sg_r = Misure::riepilogo();

sg_prova( 'l elenco non supera il tetto', $sg_r['distinte'] <= 400, $sg_r['distinte'] . ' domande' );
sg_prova( 'le domande buttate sono dichiarate', $sg_r['scartate'] > 0, $sg_r['scartate'] . ' scartate' );
sg_prova(
	'una domanda ricorrente sopravvive allo sfoltimento',
	isset( $sg_r['cercate']['collana di perle'] )
);

echo "\nL interruttore\n";

Impostazioni::salva( array( 'statistiche' => false ) );

$sg_quante = Misure::riepilogo()['funzioni']['ricerca']['chiamate'];
Misure::segna( 'ricerca', 'a statistiche spente', 3, 400 );

sg_prova( 'a statistiche spente non si scrive niente', Misure::riepilogo()['funzioni']['ricerca']['chiamate'] === $sg_quante );

Impostazioni::salva( array( 'statistiche' => true ) );

echo "\nLe aperture\n";

Misure::segna_apertura( 'ONI-1' );
Misure::segna_apertura( 'ONI-1' );
Misure::segna_apertura( 'ONI-2' );

$sg_r = Misure::riepilogo();

sg_prova( 'le aperture si sommano per prodotto', 2 === (int) ( $sg_r['aperti']['ONI-1'] ?? 0 ) );
sg_prova( 'uno sku vuoto non entra', ( Misure::segna_apertura( '  ' ) ?? true ) && ! isset( Misure::riepilogo()['aperti'][''] ) );

echo "\nUn tipo che non esiste\n";

Misure::segna( 'inventata', 'qualcosa', 1, 100 );

sg_prova( 'una funzione sconosciuta non crea righe', ! isset( Misure::riepilogo()['funzioni']['inventata'] ) );

printf(
	"\n%s\n",
	0 === $GLOBALS['sg_falliti']
		? 'Tutte le prove superate.'
		: sprintf( '%d prove fallite.', $GLOBALS['sg_falliti'] )
);
