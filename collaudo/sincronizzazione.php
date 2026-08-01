<?php
/**
 * Collaudo della macchina a stati della sincronizzazione.
 *
 * COME SI ESEGUE
 *   wp eval-file wp-content/plugins/storegentic/collaudo/sincronizzazione.php
 *
 * La rete e' simulata con il filtro `pre_http_request`: nessuna chiamata
 * esce davvero, e nessun catalogo viene toccato. Il contratto e' finto.
 *
 * COSA VERIFICA, e perche' proprio questo
 *
 *   1. percorso felice — tutte le pagine passano, la riconciliazione viene
 *      chiamata due volte: una a vuoto per sapere cosa cancellerebbe, una
 *      vera.
 *
 *   2. una pagina non passa — lo stato diventa `fallita` e la
 *      riconciliazione NON parte. E' il caso che conta: la riconciliazione
 *      cancella tutto cio' che non ha visto, quindi eseguirla dopo una
 *      sincronizzazione monca svuoterebbe il catalogo del cliente.
 *
 *   3. la potatura e' troppo ampia — la prova a vuoto dice che verrebbero
 *      tolti piu' di un terzo dei prodotti: si sospende e si chiede
 *      conferma, invece di procedere.
 *
 * Il caso 2 usa un guasto legato al contenuto della pagina e non al numero
 * di chiamata: il client riprova sui 5xx, quindi un guasto legato al
 * conteggio verrebbe superato dal secondo tentativo e il collaudo
 * passerebbe senza aver provato nulla.
 *
 * @package Storegentic
 */

use Storegentic\Catalogo\Sincronizzazione;
use Storegentic\Impostazioni;

/**
 * Rete simulata: si intercetta pre_http_request e si risponde secondo un
 * copione. Cosi' la macchina a stati viene provata davvero, senza toccare
 * il servizio e senza il rischio di caricare o cancellare nulla.
 */
$GLOBALS['copione'] = array();
$GLOBALS['chiamate'] = array();

add_filter( 'pre_http_request', function( $corto, $args, $url ) {
    $GLOBALS['chiamate'][] = $url;
    foreach ( $GLOBALS['copione'] as $frammento => $risposta ) {
        if ( str_contains( $url, $frammento ) ) {
            if ( is_callable( $risposta ) ) { $risposta = $risposta( $args, $url ); }
            return array(
                'headers'  => array(),
                'body'     => wp_json_encode( $risposta['corpo'] ?? array() ),
                'response' => array( 'code' => $risposta['codice'] ?? 200, 'message' => 'ok' ),
                'cookies'  => array(), 'filename' => null,
            );
        }
    }
    return $corto;
}, 10, 3 );

function prova( string $nome, bool $atteso, string $dettaglio = '' ): void {
    printf( "  [%s] %s%s\n", $atteso ? ' OK ' : 'FALLITO', $nome, $dettaglio ? " — $dettaglio" : '' );
}

Impostazioni::salva( array( 'chiave' => 'collaudo', 'lotto' => 50, 'pota_mancanti' => true, 'invia_categorie' => true ) );

// Contratto simulato: senza, avvia() rifiuta — ed e' il comportamento giusto.
function contratto_finto(): void {
    set_transient( 'storegentic_contratto', array(
        'capabilities' => array( 'search' => true, 'catalogIngest' => true, 'analytics' => true ),
        'endpoints'    => array(
            'search'           => '/v1/commerce/search',
            'catalogUpsert'    => '/v1/commerce/catalog/upsert',
            'catalogReconcile' => '/v1/commerce/catalog/reconcile',
            'analyticsEvents'  => '/v1/commerce/analytics/events',
        ),
    ), 3600 );
    update_option( 'storegentic_contratto_impronta',
        hash( 'sha256', Impostazioni::leggi( 'base' ) . '|' . Impostazioni::leggi( 'chiave' ) ), false );
}
contratto_finto();

/* --------------------------------------------------- 1. percorso felice */
echo "\n=== 1. tutte le pagine passano, potatura piccola ===\n";
Sincronizzazione::azzera();
contratto_finto();
$GLOBALS['copione'] = array(
    '/catalog/upsert'    => array( 'codice' => 202, 'corpo' => array( 'accepted' => true ) ),
    '/catalog/reconcile' => function( $args ) {
        $c = json_decode( $args['body'], true );
        return array( 'codice' => 200, 'corpo' => array(
            'seenSkus' => 191, 'catalogSkus' => 195, 'prunedSkus' => 4,
            'dryRun' => (bool) ( $c['dryRun'] ?? false ),
        ) );
    },
);
$avvio = Sincronizzazione::avvia();
prova( 'avvio riuscito', ! is_wp_error( $avvio ) );
$s = Sincronizzazione::stato();
prova( 'fase in_corso', Sincronizzazione::IN_CORSO === $s['fase'], $s['fase'] );
prova( 'pagine calcolate', $s['pagine'] === (int) ceil( $s['totale'] / 50 ), "{$s['pagine']} pagine per {$s['totale']} prodotti" );

$giri = 0;
while ( Sincronizzazione::in_corso() && $giri < 30 ) {
    $r = Sincronizzazione::passo();
    if ( is_wp_error( $r ) ) { echo "    passo fallito: " . $r->get_error_message() . "\n"; break; }
    $giri++;
}
$s = Sincronizzazione::stato();
prova( 'conclusa', Sincronizzazione::INATTIVA === $s['fase'], $s['fase'] );
prova( 'inviati tutti i prodotti', (int) $s['inviati'] === (int) $s['totale'], "{$s['inviati']}/{$s['totale']}" );
$dry = count( array_filter( $GLOBALS['chiamate'], fn( $u ) => str_contains( $u, 'reconcile' ) ) );
prova( 'riconciliazione chiamata due volte (prova a vuoto + vera)', 2 === $dry, "$dry chiamate" );

/* ------------------------------------------- 2. una pagina non passa */
echo "\n=== 2. una pagina fallisce ===\n";
Sincronizzazione::azzera();
contratto_finto();
$GLOBALS['chiamate'] = array();
$pagine_viste = array();
$GLOBALS['copione'] = array(
    /*
     * Il guasto si lega al CONTENUTO della pagina, non al numero di
     * chiamata: il client riprova sui 5xx, quindi un guasto legato al
     * conteggio verrebbe superato dal secondo tentativo. Qui la seconda
     * pagina fallisce sempre, anche ai rientri — che e' come si comporta
     * un server davvero in difficolta' su quel carico.
     */
    '/catalog/upsert' => function( $args ) use ( &$pagine_viste ) {
        $corpo = json_decode( $args['body'], true );
        $primo = $corpo['products'][0]['sku'] ?? '';
        $pagine_viste[ $primo ] = ( $pagine_viste[ $primo ] ?? 0 ) + 1;
        // La seconda pagina distinta incontrata fallisce, sempre.
        $distinte = array_keys( $pagine_viste );
        if ( count( $distinte ) >= 2 && $primo === $distinte[1] ) {
            return array( 'codice' => 500, 'corpo' => array( 'message' => 'errore interno simulato' ) );
        }
        return array( 'codice' => 202, 'corpo' => array() );
    },
    '/catalog/reconcile' => array( 'codice' => 200, 'corpo' => array( 'prunedSkus' => 0 ) ),
);
Sincronizzazione::avvia();
$giri = 0; $errore = null;
while ( Sincronizzazione::in_corso() && $giri < 30 ) {
    $r = Sincronizzazione::passo();
    if ( is_wp_error( $r ) ) { $errore = $r; break; }
    $giri++;
}
$s = Sincronizzazione::stato();
prova( 'stato fallita', Sincronizzazione::FALLITA === $s['fase'], $s['fase'] );
prova( 'errore riportato', null !== $errore, $errore ? $errore->get_error_message() : '' );
$rec = count( array_filter( $GLOBALS['chiamate'], fn( $u ) => str_contains( $u, 'reconcile' ) ) );
prova( 'NESSUNA riconciliazione dopo un fallimento', 0 === $rec, "$rec chiamate" );
$forza = Sincronizzazione::riconcilia();
prova( 'riconciliazione rifiutata da stato fallita', is_wp_error( $forza ), is_wp_error( $forza ) ? $forza->get_error_code() : 'ESEGUITA' );

/* ----------------------------------------- 3. potatura troppo ampia */
echo "\n=== 3. la potatura cancellerebbe mezzo catalogo ===\n";
Sincronizzazione::azzera();
contratto_finto();
$GLOBALS['chiamate'] = array();
$GLOBALS['copione'] = array(
    '/catalog/upsert'    => array( 'codice' => 202, 'corpo' => array() ),
    '/catalog/reconcile' => array( 'codice' => 200, 'corpo' => array(
        'seenSkus' => 90, 'catalogSkus' => 200, 'prunedSkus' => 110, 'dryRun' => true,
    ) ),
);
Sincronizzazione::avvia();
$giri = 0; $ultimo = null;
while ( Sincronizzazione::in_corso() && $giri < 30 ) {
    $ultimo = Sincronizzazione::passo();
    if ( is_wp_error( $ultimo ) ) { break; }
    $giri++;
}
prova( 'riconciliazione sospesa', is_wp_error( $ultimo ) && 'storegentic_potatura_ampia' === $ultimo->get_error_code(),
    is_wp_error( $ultimo ) ? $ultimo->get_error_code() : 'nessun errore' );
$s = Sincronizzazione::stato();
prova( 'resta da_chiudere, non fallita', Sincronizzazione::DA_CHIUDERE === $s['fase'], $s['fase'] );
prova( 'potatura registrata per la conferma', ! empty( $s['potatura'] ),
    ! empty( $s['potatura'] ) ? "{$s['potatura']['da_potare']} su {$s['potatura']['in_catalogo']}" : '' );
$vere = count( array_filter( $GLOBALS['chiamate'], fn( $u ) => str_contains( $u, 'reconcile' ) ) );
prova( 'una sola chiamata: la prova a vuoto', 1 === $vere, "$vere chiamate" );

Sincronizzazione::azzera();
Impostazioni::salva( array( 'chiave' => '', 'attivo' => false ) );
delete_transient( 'storegentic_contratto' );
echo "\nstato ripulito.\n";
