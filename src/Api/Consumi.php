<?php
/**
 * Quanto consente il piano, e quanto e' gia' stato consumato.
 *
 * PERCHE' QUESTA CLASSE ESISTE. Il contratto dichiara da sempre `plan`,
 * `usage` e `rateLimits`, e il plugin non li guardava. Il pannello diceva
 * "Collegamento: attivo" leggendo solo se l'handshake era riuscito — cosa che
 * riesce benissimo anche a quota finita, perche' l'handshake non consuma
 * nulla.
 *
 * Il risultato osservato su questo negozio: il contatore delle ricerche era a
 * zero da giorni, ogni ricerca del sito tornava "429 Search quota exceeded", e
 * il pannello mostrava tutto verde. Chi gestisce il negozio non aveva un solo
 * posto dove accorgersene.
 *
 * QUI NON SI DECIDE NIENTE, SI RIFERISCE. La classe non spegne funzioni e non
 * blocca chiamate in anticipo: legge i numeri che il servizio dichiara e li
 * mette in una forma leggibile. Chi decide se un comando compare resta il
 * contratto (vedi Api\Contratto); chi dice com'e' andata davvero l'ultima
 * chiamata e' Analitica\Misure, che guarda gli esiti invece delle promesse.
 *
 * PERCHE' NON BASTANO I NUMERI DEL CONTRATTO. Misurato il 2026-08-20: il
 * contratto dichiarava `chatRemaining: 359`, e l'assistente rispondeva
 * comunque `429 search_limit_exceeded`. Il contatore delle ricerche vale anche
 * per la chat, ma il contratto non lo dice. Per questo il pannello mostra le
 * due cose accanto: cosa promette il piano, e cosa e' successo davvero.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Consumi {

	/**
	 * Come si chiamano, in italiano, i contatori del servizio.
	 *
	 * La chiave e' quella che usa il contratto in `plan.limits`. Il terzo
	 * elemento dice cosa smette di funzionare quando quel contatore finisce:
	 * un numero rosso senza conseguenza non aiuta nessuno a decidere.
	 *
	 * Un contatore che il servizio aggiunge e qui non c'e' viene mostrato lo
	 * stesso, con il suo nome tecnico: nascondere un limite perche' il plugin
	 * non lo conosce e' il modo migliore per farci sbattere contro.
	 *
	 * @var array<string,array{0:string,1:string,2:string}>
	 */
	private const NOMI = array(
		'search'     => array(
			'Ricerche',
			'quante',
			'Quando finisce, la ricerca a parole e quella con la foto smettono di rispondere.',
		),
		'chat'       => array(
			'Conversazioni',
			'quante',
			'Quando finisce, l’assistente smette di rispondere.',
		),
		'sku'        => array(
			'Prodotti in indice',
			'quanti',
			'Quando finisce, i prodotti nuovi non entrano più nell’indice.',
		),
		'ingestJobs' => array(
			'Sincronizzazioni',
			'quante',
			'Quando finisce, il catalogo non si aggiorna più.',
		),
		'storageBytes' => array(
			'Spazio',
			'byte',
			'Quando finisce, l’indice non accetta altri contenuti.',
		),
	);

	/**
	 * I contatori del piano, uno per riga.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function contatori(): array {
		$contratto = Contratto::ottieni();

		if ( ! is_array( $contratto ) ) {
			return array();
		}

		$limiti  = self::ramo( $contratto, 'plan', 'limits' );
		$usati   = is_array( $contratto['usage'] ?? null ) ? $contratto['usage'] : array();
		$rimasti = is_array( $contratto['rateLimits'] ?? null ) ? $contratto['rateLimits'] : array();

		$righe = array();

		foreach ( $limiti as $chiave => $limite ) {
			$limite = (float) $limite;

			if ( $limite <= 0 ) {
				continue; // Un limite a zero o assente non e' un limite: e' un dato mancante.
			}

			$nome = self::NOMI[ $chiave ] ?? array( (string) $chiave, 'quanti', '' );

			/*
			 * Il "rimasto" lo dichiara il servizio; se non lo dichiara si
			 * ricava. Non si fa il contrario — ricavarlo sempre — perche' il
			 * servizio puo' contare in modo suo, e il suo numero e' quello che
			 * poi applica.
			 */
			$usato   = (float) ( $usati[ self::conteggio( (string) $chiave, $usati ) ] ?? 0 );
			$residuo = isset( $rimasti[ $chiave . 'Remaining' ] )
				? (float) $rimasti[ $chiave . 'Remaining' ]
				: max( 0, $limite - $usato );

			$righe[] = array(
				'chiave'   => (string) $chiave,
				'nome'     => $nome[0],
				'unita'    => $nome[1],
				'spiega'   => $nome[2],
				'usato'    => $usato,
				'limite'   => $limite,
				'rimasto'  => $residuo,
				'quota'    => (float) min( 1, max( 0, $usato / $limite ) ),
				'esaurito' => $residuo <= 0,
				/*
				 * "Quasi finito" a nove decimi: sotto quella soglia un avviso
				 * suonerebbe a ogni ricarica di pagina per settimane, e chi lo
				 * legge tutti i giorni smette di leggerlo.
				 */
				'stretto'  => $residuo > 0 && ( $usato / $limite ) >= 0.9,
			);
		}

		return $righe;
	}

	/**
	 * Come si chiama, in `usage`, il consumo di un limite.
	 *
	 * Il contratto non usa lo stesso nome nei due rami: il limite si chiama
	 * `search` e il consumo `searchCount`, ma lo spazio si chiama
	 * `storageBytes` in tutti e due. Si prova la forma con il suffisso e poi
	 * quella nuda.
	 *
	 * @param array<string,mixed> $usati
	 */
	private static function conteggio( string $chiave, array $usati ): string {
		foreach ( array( $chiave . 'Count', $chiave ) as $forma ) {
			if ( isset( $usati[ $forma ] ) ) {
				return $forma;
			}
		}

		return $chiave;
	}

	/** Il nome del piano, come lo chiama il servizio. */
	public static function piano(): string {
		$contratto = Contratto::ottieni();

		if ( ! is_array( $contratto ) || ! is_array( $contratto['plan'] ?? null ) ) {
			return '';
		}

		return (string) ( $contratto['plan']['name'] ?? $contratto['plan']['code'] ?? '' );
	}

	/**
	 * Quando i contatori tornano a zero, se il servizio lo dichiara.
	 *
	 * @return array{quando:int,passata:bool}|null
	 */
	public static function rinnovo(): ?array {
		$contratto = Contratto::ottieni();

		if ( ! is_array( $contratto ) || ! is_array( $contratto['rateLimits'] ?? null ) ) {
			return null;
		}

		$quando = strtotime( (string) ( $contratto['rateLimits']['resetsAt'] ?? '' ) );

		if ( ! $quando ) {
			return null;
		}

		/*
		 * UNA DATA GIA' PASSATA E' UN'INFORMAZIONE, NON UN DETTAGLIO.
		 *
		 * Misurato il 2026-08-20: il contratto dichiarava `resetsAt`
		 * 2026-08-01, cioe' venti giorni prima. Il contatore delle ricerche era
		 * a zero e la data del rinnovo non si spostava: aspettare non serviva a
		 * niente, e senza questo controllo il pannello avrebbe scritto "si
		 * rinnova il 1 agosto" a un negozio fermo da tre settimane.
		 */
		return array( 'quando' => $quando, 'passata' => $quando < time() );
	}

	/** C'e' almeno un contatore finito? */
	public static function qualcosa_esaurito(): bool {
		foreach ( self::contatori() as $c ) {
			if ( $c['esaurito'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Un numero come si scrive in italiano, con l'unita' giusta.
	 *
	 * @param float $valore
	 */
	public static function scrivi( $valore, string $unita ): string {
		if ( 'byte' === $unita ) {
			return size_format( (float) $valore, (float) $valore >= 1048576 ? 1 : 0 );
		}

		return number_format_i18n( (float) $valore );
	}

	/**
	 * Un ramo annidato del contratto.
	 *
	 * @param array<string,mixed> $contratto
	 * @return array<string,mixed>
	 */
	private static function ramo( array $contratto, string $primo, string $secondo ): array {
		$a = $contratto[ $primo ] ?? null;

		if ( ! is_array( $a ) || ! is_array( $a[ $secondo ] ?? null ) ) {
			return array();
		}

		return $a[ $secondo ];
	}
}
