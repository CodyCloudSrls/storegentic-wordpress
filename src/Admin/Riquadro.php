<?php
/**
 * Il riquadro nella bacheca di WordPress.
 *
 * PERCHE' IN BACHECA. La bacheca e' la pagina che si apre entrando: e' il posto
 * dove si guarda se qualcosa non va, non una pagina che si va a cercare. Un
 * servizio esterno che smette di rispondere, o un piano che finisce, va visto
 * li' — non da chi si ricorda di aprire il pannello di un plugin.
 *
 * COSA DICE, IN ORDINE. Prima cio' che chiede un intervento — piano finito,
 * servizio muto, collegamento caduto — e solo dopo i numeri. Un riquadro che
 * mette i numeri per primi si legge come una decorazione, e chi lo legge tutti
 * i giorni smette di guardarlo.
 *
 * COSA NON FA. Non chiama il servizio: legge il contratto gia' in cache e le
 * misure gia' in database. La bacheca si apre decine di volte al giorno, e una
 * chiamata di rete dentro un riquadro la rallenterebbe tutte le volte.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Admin;

use Storegentic\Analitica\Misure;
use Storegentic\Api\Consumi;
use Storegentic\Impostazioni;
use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Riquadro {

	private const ID = 'storegentic_riepilogo';

	public static function avvia(): void {
		add_action( 'wp_dashboard_setup', array( self::class, 'registra' ) );
	}

	public static function registra(): void {
		if ( ! current_user_can( Negozio::permesso() ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::ID,
			__( 'Storegentic', 'storegentic' ),
			array( self::class, 'disegna' )
		);
	}

	public static function disegna(): void {
		// Il foglio di stile serve anche qui: le classi sono le stesse del pannello.
		Pagina::stile();

		if ( ! Impostazioni::configurato() ) {
			self::non_configurato();
			return;
		}

		$riepilogo = Misure::riepilogo();
		$consumi   = Consumi::contatori();

		self::allarmi( $riepilogo, $consumi );
		self::numeri( $riepilogo );
		self::non_trovato( $riepilogo );

		printf(
			'<p class="sg-riquadro__piede"><a href="%s">%s</a> · <a href="%s">%s</a></p>',
			esc_url( Menu::url() ),
			esc_html__( 'Panoramica', 'storegentic' ),
			esc_url( Menu::url( 'storegentic-statistiche' ) ),
			esc_html__( 'Tutte le statistiche', 'storegentic' )
		);
	}

	private static function non_configurato(): void {
		printf(
			'<p>%s</p><p><a href="%s" class="button button-primary">%s</a></p>',
			esc_html__( 'Storegentic non è ancora collegato: manca la chiave del servizio.', 'storegentic' ),
			esc_url( Menu::url( 'storegentic-collegamento' ) ),
			esc_html__( 'Collega ora', 'storegentic' )
		);
	}

	/**
	 * Cio' che chiede un intervento, e niente altro.
	 *
	 * @param array<string,mixed>            $riepilogo
	 * @param array<int,array<string,mixed>> $consumi
	 */
	private static function allarmi( array $riepilogo, array $consumi ): void {
		$righe = array();

		foreach ( $consumi as $c ) {
			if ( $c['esaurito'] ) {
				$righe[] = array(
					'male' => true,
					'testo' => sprintf(
						/* translators: %s: il nome del contatore finito. */
						__( 'Il piano ha finito: %s. Il servizio rifiuta le richieste.', 'storegentic' ),
						mb_strtolower( (string) $c['nome'] )
					),
				);
			} elseif ( $c['stretto'] ) {
				$righe[] = array(
					'male' => false,
					'testo' => sprintf(
						/* translators: 1: nome del contatore, 2: quanti ne restano. */
						__( 'Quasi finito: %1$s, ne restano %2$s.', 'storegentic' ),
						mb_strtolower( (string) $c['nome'] ),
						Consumi::scrivi( $c['rimasto'], (string) $c['unita'] )
					),
				);
			}
		}

		/*
		 * L'errore del servizio si mostra solo se e' di questo mese e ce n'e'
		 * stato piu' di uno: un singolo errore isolato capita, ed e' rumore.
		 */
		foreach ( (array) $riepilogo['funzioni'] as $f ) {
			if ( (int) $f['fallite'] > 1 && ! empty( $f['ultimo_errore'] ) ) {
				$righe[] = array(
					'male' => true,
					'testo' => sprintf(
						/* translators: 1: nome della funzione, 2: quante volte, 3: il messaggio del servizio. */
						__( '%1$s: il servizio non ha risposto %2$s volte. Ultimo messaggio: «%3$s».', 'storegentic' ),
						$f['nome'],
						number_format_i18n( (int) $f['fallite'] ),
						(string) $f['ultimo_errore']['messaggio']
					),
				);
			}
		}

		if ( empty( $righe ) ) {
			printf(
				'<p class="sg-riquadro__bene">%s</p>',
				esc_html__( 'Tutto in ordine: il servizio risponde e il piano tiene.', 'storegentic' )
			);

			return;
		}

		echo '<ul class="sg-riquadro__allarmi">';

		foreach ( $righe as $riga ) {
			printf(
				'<li class="%s">%s</li>',
				$riga['male'] ? 'sg-allarme' : 'sg-attenzione',
				esc_html( (string) $riga['testo'] )
			);
		}

		echo '</ul>';
	}

	/**
	 * @param array<string,mixed> $riepilogo
	 */
	private static function numeri( array $riepilogo ): void {
		$totale = 0;
		$vuote  = 0;

		foreach ( (array) $riepilogo['funzioni'] as $f ) {
			$totale += (int) $f['chiamate'];
			$vuote  += (int) $f['vuote'];
		}

		if ( 0 === $totale ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Questo mese non è ancora arrivata nessuna domanda.', 'storegentic' )
			);

			return;
		}

		$quadri = array(
			array( __( 'Domande', 'storegentic' ), number_format_i18n( $totale ), '' ),
			array( __( 'Senza risultati', 'storegentic' ), number_format_i18n( $vuote ), $vuote > 0 ? 'sg-attenzione' : '' ),
			array( __( 'Domande diverse', 'storegentic' ), number_format_i18n( (int) $riepilogo['distinte'] ), '' ),
		);

		echo '<div class="sg-quadri sg-quadri--fitti">';

		foreach ( $quadri as $q ) {
			printf(
				'<div class="sg-quadro"><span class="sg-quadro__voce">%s</span><strong class="sg-quadro__valore %s">%s</strong></div>',
				esc_html( (string) $q[0] ),
				esc_attr( (string) $q[2] ),
				esc_html( (string) $q[1] )
			);
		}

		echo '</div>';
	}

	/**
	 * Le domande rimaste senza risposta: e' la riga che fa fare qualcosa.
	 *
	 * @param array<string,mixed> $riepilogo
	 */
	private static function non_trovato( array $riepilogo ): void {
		$senza = array_slice( (array) $riepilogo['senza'], 0, 5, true );

		if ( empty( $senza ) ) {
			return;
		}

		printf( '<h4 class="sg-riquadro__titolo">%s</h4>', esc_html__( 'Cercato e non trovato', 'storegentic' ) );

		echo '<ul class="sg-elenco sg-elenco--fitto">';

		foreach ( $senza as $testo => $voce ) {
			printf(
				'<li><strong>%s</strong> <span class="sg-tenue">%s</span></li>',
				esc_html( (string) $testo ),
				esc_html(
					sprintf(
						/* translators: %s: quante volte. */
						_n( '%s volta', '%s volte', (int) $voce['senza'], 'storegentic' ),
						number_format_i18n( (int) $voce['senza'] )
					)
				)
			);
		}

		echo '</ul>';
	}
}
