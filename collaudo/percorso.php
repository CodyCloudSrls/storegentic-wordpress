<?php
/**
 * Collaudo del funnel: dalla ricerca all'ordine.
 *
 * COME SI ESEGUE
 *   wp eval-file wp-content/plugins/storegentic/collaudo/percorso.php
 *
 * NON TOCCA I DATI DEL NEGOZIO. La coda degli eventi si mette da parte e si
 * rimette; l'ordine di prova nasce "in attesa" — stato che i rapporti di
 * vendita non contano — e viene cancellato per sempre alla fine, anche se il
 * collaudo si interrompe a meta'.
 *
 * COSA DIFENDE
 *
 *   1. Che gli eventi partano. Per mesi il funnel si e' fermato al clic:
 *      `add_to_cart` era nel vocabolario e non lo emetteva nessuno,
 *      `checkout_started` e `purchase_completed` non esistevano proprio. Chi
 *      pagava il servizio non poteva sapere se la ricerca faceva vendere.
 *   2. Che l'attribuzione sia onesta. Un ordine conta come "venuto dalla
 *      ricerca" solo per gli articoli che erano stati davvero aperti da un
 *      nostro risultato, e il totale non attribuito si manda lo stesso: senza
 *      denominatore la conversione varrebbe sempre cento per cento.
 *   3. Che un ordine non si conti due volte. La pagina di ringraziamento si
 *      ricarica, si condivide, si riapre dalla cronologia.
 *   4. Che senza consenso non si scriva niente.
 *
 * @package Storegentic
 */

use Storegentic\Analitica\Percorso;
use Storegentic\Analitica\Sessione;
use Storegentic\Frontend\Risolutore;
use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['sg_falliti'] = 0;
$GLOBALS['sg_ordini']  = array();

/** Una prova, con il suo esito a schermo. */
function sg_prova( string $nome, bool $riuscita, string $dettaglio = '' ): void {
	if ( ! $riuscita ) {
		++$GLOBALS['sg_falliti'];
	}

	printf( "  %s  %s%s\n", $riuscita ? 'ok  ' : 'KO  ', $nome, '' !== $dettaglio ? "  ($dettaglio)" : '' );
}

/** Svuota la coda e restituisce quello che c'era dentro. */
function sg_coda(): array {
	$coda = get_option( 'storegentic_coda_eventi', array() );
	update_option( 'storegentic_coda_eventi', array(), false );

	return is_array( $coda ) ? $coda : array();
}

/** Il primo evento di un certo tipo, se c'e'. */
function sg_evento( array $coda, string $tipo ): ?array {
	foreach ( $coda as $e ) {
		if ( ( $e['eventType'] ?? '' ) === $tipo ) {
			return $e;
		}
	}

	return null;
}

echo "\nCollaudo del funnel\n===================\n\n";

if ( ! Storegentic\Negozio::c_e() ) {
	echo "  Senza WooCommerce non c'e' funnel da provare.\n\n";
	return;
}

$GLOBALS['sg_coda_vera'] = get_option( 'storegentic_coda_eventi', array() );
$GLOBALS['sg_analitica'] = (bool) Impostazioni::leggi( 'analitica' );

register_shutdown_function(
	static function (): void {
		foreach ( $GLOBALS['sg_ordini'] as $id ) {
			$o = wc_get_order( $id );

			if ( $o ) {
				$o->delete( true );
			}
		}

		update_option( 'storegentic_coda_eventi', $GLOBALS['sg_coda_vera'], false );
		Impostazioni::salva( array( 'analitica' => $GLOBALS['sg_analitica'] ) );
		Sessione::dimentica();

		echo "ordine di prova cancellato; coda e impostazioni ripristinate.\n";
	}
);

Impostazioni::salva( array( 'analitica' => true ) );
update_option( 'storegentic_coda_eventi', array(), false );

echo "Il filo della sessione\n";

Sessione::dimentica();
sg_prova( 'senza cookie non c e sessione', '' === Sessione::id() );

// In una richiesta vera il cookie lo scrive apri(); qui si simula.
$_COOKIE[ Sessione::COOKIE ] = bin2hex( random_bytes( 16 ) );
sg_prova( 'un identificativo valido si legge', 32 === strlen( Sessione::id() ) );

$_COOKIE[ Sessione::COOKIE ] = 'non-e-un-identificativo';
sg_prova( 'un identificativo inventato si ignora', '' === Sessione::id() );

$_COOKIE[ Sessione::COOKIE ] = bin2hex( random_bytes( 16 ) );

$sg_ids = wc_get_products( array( 'limit' => 3, 'return' => 'ids', 'status' => 'publish' ) );

if ( count( $sg_ids ) < 3 ) {
	echo "  Servono almeno tre prodotti pubblicati.\n";
	return;
}

$sg_p = array_map( 'wc_get_product', $sg_ids );
$sg_s = array_map( array( Risolutore::class, 'sku' ), $sg_p );

Sessione::ricorda( $sg_s[0], 'agent_search' );
Sessione::ricorda( $sg_s[1], 'agent_chat' );
// Il terzo non passa da Storegentic: e' il controllo negativo.

sg_prova( 'un prodotto aperto dai risultati si ricorda', 'agent_search' === Sessione::modo_di( $sg_s[0] ) );
sg_prova( 'con il modo da cui e stato scoperto', 'agent_chat' === Sessione::modo_di( $sg_s[1] ) );
sg_prova( 'un prodotto trovato per conto proprio no', '' === Sessione::modo_di( $sg_s[2] ) );

Sessione::ricorda( $sg_s[0], 'image_search' );
sg_prova( 'vince il PRIMO modo, non l ultimo', 'agent_search' === Sessione::modo_di( $sg_s[0] ) );

echo "\nIl carrello\n";

sg_coda();
Percorso::nel_carrello( 'k1', $sg_ids[0], 2, 0 );
Percorso::nel_carrello( 'k3', $sg_ids[2], 1, 0 );

$sg_c = sg_coda();

sg_prova( 'ogni aggiunta manda un evento', 2 === count( $sg_c ), count( $sg_c ) . ' eventi' );

$sg_a = $sg_c[0] ?? array();
$sg_b = $sg_c[1] ?? array();

sg_prova( 'il tipo e quello che il servizio accetta', 'add_to_cart' === ( $sg_a['eventType'] ?? '' ) );
sg_prova( 'l aggiunta attribuita porta il modo', 'agent_search' === ( $sg_a['mode'] ?? '' ), (string) ( $sg_a['mode'] ?? '-' ) );
sg_prova( 'e si dichiara attribuita', true === ( $sg_a['data']['attributed'] ?? null ) );
sg_prova( 'la quantita e quella vera', 2 === (int) ( $sg_a['data']['quantity'] ?? 0 ) );
sg_prova( 'l aggiunta NON attribuita si manda lo stesso', 'add_to_cart' === ( $sg_b['eventType'] ?? '' ) );
sg_prova( 'ma senza modo, e dichiarata non attribuita', ! isset( $sg_b['mode'] ) && false === ( $sg_b['data']['attributed'] ?? null ) );

echo "\nL ordine\n";

$sg_o = wc_create_order( array( 'status' => 'pending' ) );
$GLOBALS['sg_ordini'][] = $sg_o->get_id();

$sg_o->add_product( $sg_p[0], 1 );  // attribuito
$sg_o->add_product( $sg_p[2], 1 );  // no
$sg_o->calculate_totals();
$sg_o->save();

sg_coda();
Percorso::ordinato( $sg_o->get_id() );

$sg_acquisto = sg_evento( sg_coda(), 'purchase_completed' );

sg_prova( 'l ordine manda purchase_completed', null !== $sg_acquisto );
sg_prova( 'con il totale intero', (float) $sg_o->get_total() === (float) ( $sg_acquisto['data']['value'] ?? -1 ), (string) ( $sg_acquisto['data']['value'] ?? '-' ) );
sg_prova( 'e con la parte attribuita, che e minore', (float) ( $sg_acquisto['data']['attributedValue'] ?? 0 ) < (float) $sg_o->get_total() );
sg_prova( 'un solo articolo attribuito su due', 1 === (int) ( $sg_acquisto['data']['attributedItems'] ?? 0 ) );
sg_prova( 'lo sku attribuito e quello giusto', array( $sg_s[0] ) === (array) ( $sg_acquisto['data']['attributedSkus'] ?? array() ) );
sg_prova( 'la valuta e quella dell ordine', $sg_o->get_currency() === ( $sg_acquisto['data']['currency'] ?? '' ) );

/*
 * Nessun dato di persone. E' la prova che rende il funnel spedibile senza
 * chiedersi ogni volta cosa contiene.
 */
$sg_json = strtolower( (string) wp_json_encode( $sg_acquisto ) );

foreach ( array( 'email', 'billing', 'address', 'phone', 'first_name', 'last_name' ) as $sg_vietato ) {
	sg_prova( 'niente "' . $sg_vietato . '" nell evento', ! str_contains( $sg_json, $sg_vietato ) );
}

sg_coda();
Percorso::ordinato( $sg_o->get_id() );
sg_prova( 'lo stesso ordine non si conta due volte', 0 === count( sg_coda() ) );

echo "\nIl consenso\n";

Impostazioni::salva( array( 'analitica' => false ) );
sg_prova( 'ad analisi spente non si apre nessuna sessione', ! Sessione::si_puo() );

Impostazioni::salva( array( 'analitica' => true ) );
add_filter( 'storegentic_consenso_statistiche', '__return_false' );
sg_prova( 'un filtro puo negare il consenso', ! Sessione::si_puo() );

remove_all_filters( 'storegentic_consenso_statistiche' );
sg_prova( 'e riconcederlo', Sessione::si_puo() );

printf(
	"\n%s\n",
	0 === $GLOBALS['sg_falliti']
		? 'Tutte le prove superate.'
		: sprintf( '%d prove fallite.', $GLOBALS['sg_falliti'] )
);
