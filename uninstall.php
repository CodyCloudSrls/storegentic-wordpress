<?php
/**
 * Disinstallazione.
 *
 * Un plugin che si disinstalla deve andarsene davvero: opzioni, cache,
 * eventi programmati. Restano solo i dati che non sono suoi — il catalogo
 * di WooCommerce non si tocca.
 *
 * Il catalogo caricato su Storegentic non viene cancellato di qui: e' un
 * dato del cliente sul suo servizio, e cancellarlo dalla disinstallazione di
 * un plugin sarebbe una sorpresa sgradita. Si cancella dalla console.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$prefisso = 'storegentic_';

$opzioni = array(
	$prefisso . 'impostazioni',
	$prefisso . 'contratto_ultimo',
	$prefisso . 'contratto_impronta',
	$prefisso . 'installazione',
	$prefisso . 'sincro_stato',
	$prefisso . 'sincro_diario',
	$prefisso . 'coda_eventi',
	$prefisso . 'ultima_sincro',
	$prefisso . 'ultimo_errore',
);

foreach ( $opzioni as $opzione ) {
	delete_option( $opzione );
}

/*
 * Le statistiche stanno in un'opzione per mese — storegentic_misure_2026_08 —
 * e i mesi non sono un elenco fisso. Si va indietro di due anni: molto piu' di
 * quanto Analitica\Misure ne conservi (tre), quel tanto che basta a ripulire
 * anche i mesi rimasti da una versione precedente che ne teneva di piu'.
 */
$adesso = time();

for ( $indietro = 0; $indietro < 24; $indietro++ ) {
	delete_option( $prefisso . 'misure_' . gmdate( 'Y_m', (int) strtotime( "-$indietro months", $adesso ) ) );
}

delete_transient( $prefisso . 'contratto' );

wp_clear_scheduled_hook( 'storegentic_sincro_periodica' );
wp_clear_scheduled_hook( 'storegentic_sincro_passo' );
wp_clear_scheduled_hook( 'storegentic_svuota_coda' );
