<?php
/**
 * I parametri di ricerca che il contratto dichiara configurabili.
 *
 * COSA SI PUO' DAVVERO CONFIGURARE. Il servizio non espone nessun indirizzo per
 * leggere o scrivere impostazioni: provati il 2026-08-20 tutti quelli
 * plausibili — plugin/settings, workspace, agent/config, search/config — e
 * rispondono 404. Le uniche cose regolabili sono i parametri che si mandano
 * dentro ogni richiesta, e il contratto dichiara quali sono e quanto valgono
 * di base:
 *
 *   search.text.defaults.topK        quanti risultati chiedere
 *   search.text.defaults.threshold   quanto devono somigliare
 *   search.text.maxTopK              il tetto
 *   search.image.*                   gli stessi, per la ricerca con la foto
 *
 * QUINDI IL PANNELLO NON INVENTA NIENTE. Mostra questi e basta. Se un domani il
 * contratto ne dichiara un altro, si aggiunge qui; se ne toglie uno, il campo
 * sparisce da solo, perche' i valori di riferimento vengono letti di li' e non
 * scritti nel codice.
 *
 * PERCHE' LA SOGLIA CONTA. E' il parametro che decide quanto il servizio e'
 * disposto a includere. Il plugin non l'ha mai mandata, quindi il servizio ha
 * sempre usato la sua: chi si e' lamentato che una domanda a parole tornava
 * vuota non aveva modo di allargare la rete. Adesso ce l'ha.
 *
 * ZERO E VUOTO VOGLIONO DIRE "DECIDI TU". Un campo lasciato com'e' non manda
 * niente, e il servizio applica il suo valore di base: e' diverso da mandare
 * quel valore, perche' se il servizio lo cambia il negozio lo segue senza che
 * nessuno tocchi nulla.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parametri {

	/**
	 * Il tetto oltre il quale non si chiede, se il contratto non lo dice.
	 *
	 * Serve solo come rete: il valore buono e' quello dichiarato.
	 */
	private const TETTO_DI_SCORTA = 50;

	/**
	 * Il ramo del contratto per un modo di cercare.
	 *
	 * @return array<string,mixed>
	 */
	private static function ramo( string $modo ): array {
		$search = Contratto::sezione( 'search' );

		return is_array( $search[ $modo ] ?? null ) ? $search[ $modo ] : array();
	}

	/** Quanti risultati chiede il servizio di sua iniziativa. */
	public static function quanti_di_base( string $modo ): int {
		$ramo = self::ramo( $modo );

		return (int) ( $ramo['defaults']['topK'] ?? 0 );
	}

	/** Il massimo che il servizio accetta. */
	public static function quanti_al_massimo( string $modo ): int {
		$ramo   = self::ramo( $modo );
		$tetto  = (int) ( $ramo['maxTopK'] ?? 0 );

		return $tetto > 0 ? $tetto : self::TETTO_DI_SCORTA;
	}

	/** La soglia di somiglianza che il servizio usa di sua iniziativa. */
	public static function soglia_di_base( string $modo ): float {
		$ramo = self::ramo( $modo );

		return (float) ( $ramo['defaults']['threshold'] ?? 0 );
	}

	/** Il contratto dichiara la soglia per questo modo? */
	public static function soglia_regolabile( string $modo ): bool {
		$ramo = self::ramo( $modo );

		return isset( $ramo['defaults']['threshold'] );
	}

	/**
	 * I parametri da aggiungere al carico di una richiesta.
	 *
	 * Si mandano solo quelli scelti davvero. Un campo lasciato vuoto non
	 * compare nel carico, e il servizio applica il proprio.
	 *
	 * @param string $modo    'text' oppure 'image'.
	 * @param int    $quanti  Quanti risultati vuole chi chiama; 0 lascia decidere.
	 * @param mixed  $soglia  La soglia scelta; vuoto lascia decidere.
	 * @return array<string,mixed>
	 */
	public static function per( string $modo, int $quanti, $soglia ): array {
		$carico = array();

		if ( $quanti > 0 ) {
			$carico['topK'] = min( $quanti, self::quanti_al_massimo( $modo ) );
		}

		if ( '' !== (string) $soglia && self::soglia_regolabile( $modo ) ) {
			$carico['threshold'] = max( 0.0, min( 1.0, (float) $soglia ) );
		}

		return $carico;
	}
}
