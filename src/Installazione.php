<?php
/**
 * Attivazione e disattivazione.
 *
 * All'attivazione non si contatta il servizio e non si crea nulla nel
 * database oltre alle impostazioni: un plugin appena attivato deve poter
 * essere disattivato senza lasciare tracce, e senza aver spedito il
 * catalogo a un servizio che l'utente non ha ancora configurato.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic;

use Storegentic\Catalogo\Pianificatore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installazione {

	public static function attiva(): void {
		if ( false === get_option( Impostazioni::CHIAVE, false ) ) {
			add_option( Impostazioni::CHIAVE, Impostazioni::predefinite(), '', false );
		}

		/*
		 * Il cron periodico si accende solo quando il plugin e' configurato
		 * e attivo: senza chiave non ha nulla da sincronizzare, e un evento
		 * programmato che fallisce a ogni giro sporca il log e basta.
		 */
		if ( Impostazioni::configurato() && Impostazioni::leggi( 'attivo' ) ) {
			require_once __DIR__ . '/Catalogo/Pianificatore.php';
			require_once __DIR__ . '/Catalogo/Sincronizzazione.php';
			Pianificatore::accendi_periodica();
		}
	}

	public static function disattiva(): void {
		require_once __DIR__ . '/Catalogo/Pianificatore.php';
		require_once __DIR__ . '/Catalogo/Sincronizzazione.php';
		Pianificatore::spegni_periodica();
	}
}
