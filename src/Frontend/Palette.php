<?php
/**
 * I colori dell'interfaccia.
 *
 * PERCHE' UNA CLASSE E NON DUE CAMPI. Prima c'erano due colori: uno di fondo e
 * uno di testo. Con due colori si tinge un pulsante, non si veste
 * un'interfaccia: la conversazione, i risultati, i bordi, i testi tenui e le
 * superfici finivano tutti su valori scritti nel foglio di stile, e cambiare
 * il colore del negozio lasciava tutto il resto grigio. Non era una palette:
 * era una tinta.
 *
 * Qui i colori sono sette, e sono quelli che servono per davvero:
 *
 *   sfondo      il fondo della pagina dietro i pannelli
 *   superficie  il fondo dei fogli, delle bolle e delle schede
 *   testo       il colore della parola scritta
 *   testo_tenue le informazioni di contorno: categoria, conteggi, note
 *   bordo       le linee sottili che separano senza pesare
 *   accento     il colore che chiama all'azione: pulsanti, prezzi, bolle
 *   su_accento  cosa si legge sopra l'accento
 *
 * Sono pochi di proposito. Una palette con venti voci non la compila nessuno,
 * e chi la compila la sbaglia.
 *
 * PERCHE' I PREPARATI. Scegliere sette colori che stiano insieme e' un lavoro
 * da progettista. Chi gestisce un negozio sceglie da un elenco e, se vuole,
 * ritocca. I preparati qui sotto rispettano tutti il contrasto richiesto fra
 * testo e fondo.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Palette {

	/** I nomi dei colori, nell'ordine in cui si mostrano. */
	public const VOCI = array( 'sfondo', 'superficie', 'testo', 'testo_tenue', 'bordo', 'accento', 'su_accento' );

	/**
	 * Le combinazioni gia' pronte.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function preparate(): array {
		$preparate = array(
			'tema'      => array(
				'nome'     => __( 'Dal tema del sito', 'storegentic' ),
				'spiega'   => __( 'Prende i colori dal tema, se il tema li dichiara. Altrimenti usa il neutro.', 'storegentic' ),
				'eredita'  => true,
				'colori'   => self::neutro(),
			),
			'neutro'    => array(
				'nome'   => __( 'Neutro', 'storegentic' ),
				'spiega' => __( 'Grigi caldi e un nero pieno. Sta bene su quasi tutto.', 'storegentic' ),
				'colori' => self::neutro(),
			),
			'inchiostro' => array(
				'nome'   => __( 'Inchiostro e oro', 'storegentic' ),
				'spiega' => __( 'Fondo chiaro, testo quasi nero, accento dorato. Per chi vende oggetti.', 'storegentic' ),
				'colori' => array(
					'sfondo'      => '#F7F4EF',
					'superficie'  => '#FFFFFF',
					'testo'       => '#1A1815',
					'testo_tenue' => '#6A635B',
					'bordo'       => '#E5DED4',
					'accento'     => '#A57C3E',
					'su_accento'  => '#FFFFFF',
				),
			),
			'notte'     => array(
				'nome'   => __( 'Notte', 'storegentic' ),
				'spiega' => __( 'Fondo scuro e accento chiaro. Per i negozi con vetrina scura.', 'storegentic' ),
				'colori' => array(
					'sfondo'      => '#14161A',
					'superficie'  => '#1D2026',
					'testo'       => '#F2F3F5',
					'testo_tenue' => '#9AA0AA',
					'bordo'       => '#2C313A',
					'accento'     => '#7A9CFF',
					'su_accento'  => '#12151A',
				),
			),
			'bosco'     => array(
				'nome'   => __( 'Bosco', 'storegentic' ),
				'spiega' => __( 'Verdi profondi su carta chiara.', 'storegentic' ),
				'colori' => array(
					'sfondo'      => '#F4F6F3',
					'superficie'  => '#FFFFFF',
					'testo'       => '#16211A',
					'testo_tenue' => '#5E6B62',
					'bordo'       => '#DCE3DC',
					'accento'     => '#2F6B4F',
					'su_accento'  => '#FFFFFF',
				),
			),
			'elettrico' => array(
				'nome'   => __( 'Elettrico', 'storegentic' ),
				'spiega' => __( 'Grigi freddi e un blu deciso.', 'storegentic' ),
				'colori' => array(
					'sfondo'      => '#FAFAFB',
					'superficie'  => '#FFFFFF',
					'testo'       => '#0A0A0A',
					'testo_tenue' => '#6B6B6B',
					'bordo'       => '#E2E2E2',
					'accento'     => '#2540E8',
					'su_accento'  => '#FFFFFF',
				),
			),
			'propria'   => array(
				'nome'   => __( 'Personalizzata', 'storegentic' ),
				'spiega' => __( 'Scegli tu i sette colori.', 'storegentic' ),
				'colori' => self::neutro(),
			),
		);

		/**
		 * Permette a un tema di aggiungere le proprie combinazioni.
		 *
		 * @param array<string,array<string,mixed>> $preparate
		 */
		return (array) apply_filters( 'storegentic_palette_preparate', $preparate );
	}

	/**
	 * @return array<string,string>
	 */
	private static function neutro(): array {
		return array(
			'sfondo'      => '#F7F5F2',
			'superficie'  => '#FFFFFF',
			'testo'       => '#1A1815',
			'testo_tenue' => '#6A635B',
			'bordo'       => '#E3E0DC',
			'accento'     => '#1A1815',
			'su_accento'  => '#FFFFFF',
		);
	}

	/**
	 * I colori in uso adesso.
	 *
	 * @return array<string,string>
	 */
	public static function colori(): array {
		$scelta    = (string) Impostazioni::leggi( 'palette' );
		$preparate = self::preparate();
		$base      = $preparate[ $scelta ]['colori'] ?? self::neutro();

		if ( 'propria' === $scelta ) {
			$proprie = (array) Impostazioni::leggi( 'colori' );

			foreach ( self::VOCI as $voce ) {
				if ( ! empty( $proprie[ $voce ] ) ) {
					$base[ $voce ] = (string) $proprie[ $voce ];
				}
			}
		}

		/**
		 * Ultimo ritocco a codice, per chi ha bisogni che un pannello non copre.
		 *
		 * @param array<string,string> $base
		 * @param string               $scelta Nome della combinazione scelta.
		 */
		return (array) apply_filters( 'storegentic_colori', $base, $scelta );
	}

	/**
	 * Le variabili CSS da stampare in pagina.
	 *
	 * Quando la combinazione e' "dal tema", i colori non si scrivono: si
	 * lasciano ai valori di ripiego del foglio di stile, che un tema puo'
	 * ridefinire dichiarando le stesse variabili. Scriverli qui vorrebbe dire
	 * vincere sempre sul tema, e togliergli la possibilita' di adattarci.
	 */
	public static function css(): string {
		$scelta = (string) Impostazioni::leggi( 'palette' );
		$raggio = (int) Impostazioni::leggi( 'raggio' );

		$righe = array();

		if ( 'tema' !== $scelta ) {
			$c = self::colori();

			$righe[] = '--sg-fondo:' . $c['sfondo'];
			$righe[] = '--sg-carta:' . $c['superficie'];
			$righe[] = '--sg-inchiostro:' . $c['testo'];
			$righe[] = '--sg-inchiostro-2:' . $c['testo_tenue'];
			$righe[] = '--sg-linea:' . $c['bordo'];
			$righe[] = '--sg-colore:' . $c['accento'];
			$righe[] = '--sg-testo:' . $c['su_accento'];

			/*
			 * Il velo e l'ombra si ricavano dal testo, non da un grigio fisso:
			 * su una palette scura un velo nero non si vede, e su una chiara un
			 * velo chiaro non separa. Si usa la sfumatura del colore del testo,
			 * che per costruzione fa contrasto con lo sfondo.
			 */
			$righe[] = '--sg-velo:' . self::trasparente( $c['testo'], 0.55 );
			$righe[] = '--sg-ombra:0 18px 50px -12px ' . self::trasparente( $c['testo'], 0.22 );
			$righe[] = '--sg-accento-tenue:' . self::trasparente( $c['accento'], 0.10 );
		}

		/*
		 * Una scala, non un numero solo. Il raggio scelto vale per i comandi;
		 * i fogli e le bolle ne vogliono uno piu' largo e le pastiglie uno piu'
		 * stretto, altrimenti un'interfaccia con angoli tutti uguali sembra
		 * disegnata da una macchina.
		 */
		$righe[] = '--sg-raggio:' . $raggio . 'px';
		$righe[] = '--sg-raggio-s:' . max( 2, (int) round( $raggio * 0.6 ) ) . 'px';
		$righe[] = '--sg-raggio-l:' . ( $raggio + 6 ) . 'px';

		return ':root{' . implode( ';', $righe ) . '}';
	}

	/**
	 * Lo stesso colore, con un velo di trasparenza.
	 *
	 * Accetta le forme che escono dal selettore di colore di WordPress: tre o
	 * sei cifre esadecimali. Su qualunque altra cosa si torna a un nero
	 * appena visibile, che e' il comportamento meno dannoso.
	 */
	private static function trasparente( string $esadecimale, float $quanto ): string {
		$pulito = ltrim( trim( $esadecimale ), '#' );

		if ( 3 === strlen( $pulito ) ) {
			$pulito = $pulito[0] . $pulito[0] . $pulito[1] . $pulito[1] . $pulito[2] . $pulito[2];
		}

		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $pulito ) ) {
			return 'rgba(0,0,0,' . $quanto . ')';
		}

		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $pulito, 0, 2 ) ),
			hexdec( substr( $pulito, 2, 2 ) ),
			hexdec( substr( $pulito, 4, 2 ) ),
			rtrim( rtrim( number_format( $quanto, 2, '.', '' ), '0' ), '.' )
		);
	}
}
