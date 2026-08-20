<?php
/**
 * Collaudo delle impostazioni e del salvataggio per pagine.
 *
 * COME SI ESEGUE
 *   wp eval-file wp-content/plugins/storegentic/collaudo/impostazioni.php
 *
 * IL DIFETTO CHE QUESTO FILE ESISTE PER IMPEDIRE.
 *
 * Le impostazioni stanno tutte in un'opzione sola, ma il pannello e' diviso in
 * pagine, e ogni pagina ne stampa un pezzo. Una casella non spuntata e una
 * casella che non era in pagina arrivano identiche al server: assenti. Se il
 * salvataggio le trattasse allo stesso modo, salvare la pagina dei colori
 * spegnerebbe la sincronizzazione automatica, le analisi e il ripiego — tutte
 * cose che stanno su un'altra pagina, e che nessuno ha toccato.
 *
 * E' gia' successo quando le pagine erano sezioni della stessa schermata. Ora
 * che sono pagine separate il rischio e' quintuplicato, perche' ogni
 * salvataggio ne vede una su cinque.
 *
 * La difesa e' il campo `gruppi[]`, che dice quali caselle erano davvero
 * stampate. Questo collaudo prova che funziona, gruppo per gruppo.
 *
 * NON TOCCA IL NEGOZIO: si conserva l'opzione intera all'inizio e la si rimette
 * alla fine, anche in caso di errore fatale.
 *
 * @package Storegentic
 */

use Storegentic\Api\Parametri;
use Storegentic\Impostazioni;
use Storegentic\Admin\Menu;
use Storegentic\Negozio;

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

echo "\nCollaudo delle impostazioni\n===========================\n\n";

$GLOBALS['sg_vere'] = get_option( Impostazioni::CHIAVE );

register_shutdown_function(
	static function (): void {
		if ( is_array( $GLOBALS['sg_vere'] ?? null ) ) {
			update_option( Impostazioni::CHIAVE, $GLOBALS['sg_vere'], false );
		}

		echo "impostazioni del negozio ripristinate.\n";
	}
);

/* ------------------------------------------------------- il perimetro */

echo "Cosa esiste\n";

$sg_predefinite = Impostazioni::predefinite();

sg_prova( 'ogni pagina del menu ha un titolo e una vista', ! empty( Menu::pagine() ) );

foreach ( Menu::pagine() as $sg_slug => $sg_p ) {
	$sg_file = \Storegentic\PERCORSO . '/src/Admin/viste/' . $sg_p['vista'] . '.php';

	sg_prova( 'la vista di "' . $sg_p['titolo'] . '" esiste', is_readable( $sg_file ), $sg_p['vista'] . '.php' );
}

/*
 * Ogni gruppo dichiarato dal menu deve essere gestito da salva(). Un gruppo
 * scritto nel menu e dimenticato nel salvataggio darebbe una pagina che
 * accetta il modulo e non salva niente, senza dire nulla.
 */
$sg_sorgente = (string) file_get_contents( \Storegentic\PERCORSO . '/src/Admin/Pagina.php' );

foreach ( Menu::pagine() as $sg_p ) {
	if ( '' === $sg_p['gruppo'] ) {
		continue;
	}

	sg_prova(
		'il gruppo "' . $sg_p['gruppo'] . '" viene salvato',
		str_contains( $sg_sorgente, "'" . $sg_p['gruppo'] . "', \$gruppi, true" )
	);
}

echo "\nLe chiavi morte non tornano\n";

/*
 * LE CHIAVI FINTE SI AGGIUNGONO A QUELLE VERE, NON LE SOSTITUISCONO.
 *
 * La prima versione di questa riga partiva da `predefinite()`, dove la chiave
 * del servizio e' vuota. Risultato: per tutto il resto del collaudo il negozio
 * era scollegato, il contratto non arrivava, e due prove piu' sotto fallivano
 * senza che ci fosse niente di rotto nel codice. E' lo stesso incidente gia'
 * pagato con collaudo/sincronizzazione.php, in una forma piu' mite: li' il
 * negozio restava scollegato anche dopo.
 */
update_option(
	Impostazioni::CHIAVE,
	array_merge(
		is_array( $GLOBALS['sg_vere'] ) ? $GLOBALS['sg_vere'] : $sg_predefinite,
		array( 'modalita' => 'finestra', 'assistente' => true, 'colore' => '#123456', 'inventata' => 'x' )
	),
	false
);

$sg_lette = Impostazioni::tutte();
$sg_morte = array_intersect( array( 'modalita', 'assistente', 'colore', 'inventata' ), array_keys( $sg_lette ) );

sg_prova( 'una chiave che il plugin non conosce piu non si legge', empty( $sg_morte ), implode( ', ', $sg_morte ) );

Impostazioni::salva( array( 'raggio' => 9 ) );
$sg_dopo = (array) get_option( Impostazioni::CHIAVE );

sg_prova( 'e sparisce davvero dal database al primo salvataggio', ! isset( $sg_dopo['modalita'] ) && ! isset( $sg_dopo['colore'] ) );

echo "\nSalvare una pagina non spegne le altre\n";

/**
 * Simula l'invio di un modulo: solo i campi che quella pagina stampa davvero.
 *
 * Riproduce quello che fa Admin\Pagina::salva() senza passare da admin-post,
 * che vorrebbe dire nonce, redirect e uscita dal processo.
 *
 * @param array<int,string>   $gruppi
 * @param array<string,mixed> $campi
 */
function sg_invia( array $gruppi, array $campi ): void {
	$nuove = array();

	if ( in_array( 'aspetto', $gruppi, true ) ) {
		$nuove += array(
			'posizione'       => $campi['posizione'] ?? 'destra',
			'palette'         => $campi['palette'] ?? 'tema',
			'raggio'          => $campi['raggio'] ?? 10,
			'segnaposto'      => $campi['segnaposto'] ?? '',
			'saluto'          => $campi['saluto'] ?? '',
			'modi'            => (array) ( $campi['modi'] ?? array() ),
			'etichetta_avvio' => $campi['etichetta_avvio'] ?? '',
		);
	}

	if ( in_array( 'ricerca', $gruppi, true ) ) {
		$nuove += array(
			'risultati'           => $campi['risultati'] ?? 'pagina',
			'solo_su'             => $campi['solo_su'] ?? array(),
			'sostituisci_ricerca' => isset( $campi['sostituisci_ricerca'] ),
			'ripiego'             => isset( $campi['ripiego'] ),
			'quanti'              => $campi['quanti'] ?? 0,
			'soglia'              => $campi['soglia'] ?? '',
			'istantanea'          => isset( $campi['istantanea'] ),
		);
	}

	if ( in_array( 'contenuti', $gruppi, true ) ) {
		$nuove += array(
			'sincro_automatica' => isset( $campi['sincro_automatica'] ),
			'invia_categorie'   => isset( $campi['invia_categorie'] ),
			'pota_mancanti'     => isset( $campi['pota_mancanti'] ),
			'analitica'         => isset( $campi['analitica'] ),
			'statistiche'       => isset( $campi['statistiche'] ),
		);
	}

	Impostazioni::salva( $nuove );
}

// Si parte da tutto acceso, che e' la condizione in cui uno spegnimento si vede.
Impostazioni::salva(
	array(
		'sincro_automatica' => true,
		'invia_categorie'   => true,
		'pota_mancanti'     => true,
		'analitica'         => true,
		'statistiche'       => true,
		'ripiego'           => true,
		'istantanea'        => true,
		'sostituisci_ricerca' => true,
		'raggio'            => 8,
	)
);

// Si salva la sola pagina Aspetto, senza nessuna casella delle altre.
sg_invia( array( 'aspetto' ), array( 'palette' => 'notte', 'raggio' => 12, 'modi' => array( 'cerca', 'chat' ) ) );

$sg_i = Impostazioni::tutte();

sg_prova( 'la pagina Aspetto salva se stessa', 'notte' === $sg_i['palette'] && 12 === $sg_i['raggio'] );
sg_prova( 'la sincronizzazione automatica resta accesa', true === $sg_i['sincro_automatica'] );
sg_prova( 'le analisi restano accese', true === $sg_i['analitica'] );
sg_prova( 'le statistiche restano accese', true === $sg_i['statistiche'] );
sg_prova( 'il ripiego resta acceso', true === $sg_i['ripiego'] );
sg_prova( 'la ricerca istantanea resta accesa', true === $sg_i['istantanea'] );
sg_prova( 'la sostituzione della ricerca resta accesa', true === $sg_i['sostituisci_ricerca'] );

// Ora la sola pagina Ricerca, spegnendo una sua casella.
sg_invia( array( 'ricerca' ), array( 'risultati' => 'finestra', 'quanti' => 25, 'soglia' => '0,3', 'ripiego' => '1' ) );

$sg_i = Impostazioni::tutte();

sg_prova( 'la pagina Ricerca salva se stessa', 'finestra' === $sg_i['risultati'] && 25 === $sg_i['quanti'] );
sg_prova( 'la virgola decimale si accetta come il punto', '0.3' === (string) $sg_i['soglia'], (string) $sg_i['soglia'] );
sg_prova( 'una sua casella non spuntata si spegne davvero', false === $sg_i['istantanea'] );
sg_prova( 'ma i colori dell altra pagina non si toccano', 'notte' === $sg_i['palette'] );
sg_prova( 'e le analisi restano accese', true === $sg_i['analitica'] );

echo "\nI parametri del servizio\n";

Impostazioni::salva( array( 'quanti' => 9999, 'soglia' => '7' ) );
$sg_i = Impostazioni::tutte();

sg_prova(
	'un numero oltre il tetto viene riportato al tetto',
	$sg_i['quanti'] === Parametri::quanti_al_massimo( 'text' ),
	(string) $sg_i['quanti']
);

sg_prova( 'una soglia oltre 1 viene riportata a 1', '1' === (string) $sg_i['soglia'], (string) $sg_i['soglia'] );

Impostazioni::salva( array( 'soglia' => '' ) );

sg_prova( 'una soglia vuota resta vuota e non diventa zero', '' === (string) Impostazioni::leggi( 'soglia' ) );
sg_prova( 'una soglia vuota non entra nel carico', ! isset( Parametri::per( 'text', 0, '' )['threshold'] ) );

Impostazioni::salva( array( 'soglia' => '0' ) );

sg_prova( 'una soglia scritta a zero invece si manda', 0.0 === ( Parametri::per( 'text', 0, Impostazioni::leggi( 'soglia' ) )['threshold'] ?? null ) );

echo "\nLe modalita e i tipi di contenuto\n";

Impostazioni::salva( array( 'modi' => array() ) );
sg_prova( 'spegnere tutte le modalita lascia almeno la ricerca', array( 'cerca' ) === Impostazioni::leggi( 'modi' ) );

Impostazioni::salva( array( 'modi' => array( 'chat', 'inventata', 'foto' ) ) );
sg_prova( 'una modalita inventata non entra', array( 'foto', 'chat' ) === Impostazioni::leggi( 'modi' ), implode( ',', (array) Impostazioni::leggi( 'modi' ) ) );

Impostazioni::salva( array( 'tipi' => array( 'page', 'tipo_che_non_esiste', 'post' ) ) );

/*
 * Si confrontano gli insiemi e non le sequenze: l'ordine che esce e' quello in
 * cui WordPress dichiara i tipi, non quello in cui sono arrivati, e per un
 * elenco di caselle spuntate l'ordine non significa niente.
 */
$sg_tipi = (array) Impostazioni::leggi( 'tipi' );
sort( $sg_tipi );

sg_prova( 'un tipo di contenuto inesistente non entra', array( 'page', 'post' ) === $sg_tipi, implode( ',', $sg_tipi ) );

echo "\nL indirizzo del servizio\n";

$sg_buono = (string) Impostazioni::leggi( 'base' );

foreach ( array( 'http://api.storegentic.eu', 'https://localhost', 'https://127.0.0.1', 'non-un-indirizzo', '' ) as $sg_cattivo ) {
	Impostazioni::salva( array( 'base' => $sg_cattivo ) );

	sg_prova(
		'"' . ( '' === $sg_cattivo ? '(vuoto)' : $sg_cattivo ) . '" non sostituisce l indirizzo buono',
		$sg_buono === (string) Impostazioni::leggi( 'base' ),
		(string) Impostazioni::leggi( 'base' )
	);
}

echo "\nIl permesso\n";

sg_prova(
	'con WooCommerce serve il permesso di WooCommerce',
	Negozio::c_e() ? 'manage_woocommerce' === Negozio::permesso() : 'manage_options' === Negozio::permesso(),
	Negozio::permesso()
);

add_filter( 'storegentic_permesso', static fn() => 'edit_posts' );
sg_prova( 'il permesso si puo cambiare con un filtro', 'edit_posts' === Negozio::permesso() );
remove_all_filters( 'storegentic_permesso' );

printf(
	"\n%s\n",
	0 === $GLOBALS['sg_falliti']
		? 'Tutte le prove superate.'
		: sprintf( '%d prove fallite.', $GLOBALS['sg_falliti'] )
);
