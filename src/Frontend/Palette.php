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
 * ritocca.
 *
 * I preparati rispettano tutti il contrasto richiesto fra testo e fondo, e non
 * e' una promessa scritta a parole: la difende `collaudo/palette.php`, che li
 * misura tutti a ogni giro. La promessa da sola non basta — era scritta anche
 * quando "Inchiostro e oro" stava a 3,78:1, sotto la soglia, per settimane.
 *
 * Chi sceglie i propri sette colori riceve lo stesso controllo: il rapporto si
 * vede accanto all'anteprima mentre si sceglie, e al salvataggio il pannello lo
 * dice. Non si impedisce di salvare — i colori di un negozio sono una scelta
 * sua — ma nessuno li sbaglia senza saperlo.
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
					/*
					 * L'ORO ERA #A57C3E, ED ERA TROPPO CHIARO.
					 *
					 * Misurato: bianco su quell'oro dava 3,78:1, sotto il 4,5:1
					 * che serve per il testo normale. Non e' un dettaglio da
					 * pignoli: l'accento tinge i pulsanti, i cartellini del
					 * prezzo e le bolle di chi scrive nella conversazione — cioe'
					 * testo piccolo, quello che si legge peggio. E questa e'
					 * proprio la combinazione consigliata a chi vende oggetti,
					 * quindi il difetto sarebbe finito addosso ai negozi che
					 * seguono il consiglio.
					 *
					 * Sceso a #8A6A2F l'oro resta oro — un bronzo caldo, non un
					 * marrone — e il bianco sopra arriva a 5,02:1.
					 */
					'accento'     => '#8A6A2F',
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
	 * Le coppie di colori che devono potersi leggere una sull'altra.
	 *
	 * Non sono tutte le combinazioni possibili: sono quelle che l'interfaccia
	 * accosta davvero. Il testo tenue non compare mai sull'accento, per dire, e
	 * pretenderlo restringerebbe le palette possibili senza motivo.
	 *
	 * @var array<int,array{0:string,1:string,2:string}>
	 */
	private const ACCOSTAMENTI = array(
		array( 'testo', 'superficie', 'Il testo sulle schede e sui fogli' ),
		array( 'testo', 'sfondo', 'Il testo sul fondo della finestra' ),
		array( 'testo_tenue', 'superficie', 'Le note e le categorie sulle schede' ),
		array( 'su_accento', 'accento', 'Il testo dentro i pulsanti e i cartellini del prezzo' ),
	);

	/**
	 * Il rapporto di contrasto fra due colori, come lo definisce il WCAG.
	 *
	 * QUESTA E' LA DEFINIZIONE BUONA, e sta qui perche' serve in tre posti: il
	 * collaudo che difende i preparati, il controllo al salvataggio e
	 * l'anteprima. Lo script dell'amministrazione ne tiene una copia in
	 * JavaScript — l'anteprima cambia senza ricaricare la pagina, e non puo'
	 * chiedere al server a ogni tocco del selettore — ma quella copia segue
	 * questa, non il contrario.
	 *
	 * Serve 4,5:1 per il testo normale (WCAG 1.4.3). Sotto quella soglia il
	 * testo non e' leggibile da chi ha una vista anche solo un po' ridotta, e
	 * su un telefono al sole non lo legge nessuno.
	 */
	public static function contrasto( string $primo, string $secondo ): float {
		$luce = static function ( string $esadecimale ): float {
			$pulito = ltrim( trim( $esadecimale ), '#' );

			if ( 3 === strlen( $pulito ) ) {
				$pulito = $pulito[0] . $pulito[0] . $pulito[1] . $pulito[1] . $pulito[2] . $pulito[2];
			}

			if ( ! preg_match( '/^[0-9a-f]{6}$/i', $pulito ) ) {
				return 0.0;
			}

			$canali = array();

			foreach ( array( 0, 2, 4 ) as $posto ) {
				$v = hexdec( substr( $pulito, $posto, 2 ) ) / 255;

				$canali[] = $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
			}

			return 0.2126 * $canali[0] + 0.7152 * $canali[1] + 0.0722 * $canali[2];
		};

		$a = $luce( $primo );
		$b = $luce( $secondo );

		return round( ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 ), 2 );
	}

	/**
	 * Gli accostamenti di una combinazione che non si leggono.
	 *
	 * Torna un elenco vuoto quando va tutto bene, cosi' chi chiama scrive
	 * `if ( empty( ... ) )` e non deve interpretare un booleano.
	 *
	 * @param array<string,string> $colori
	 * @return array<int,array{cosa:string,rapporto:float}>
	 */
	public static function verifica( array $colori ): array {
		$guai = array();

		foreach ( self::ACCOSTAMENTI as $coppia ) {
			list( $primo, $secondo, $cosa ) = $coppia;

			if ( empty( $colori[ $primo ] ) || empty( $colori[ $secondo ] ) ) {
				continue;
			}

			$rapporto = self::contrasto( (string) $colori[ $primo ], (string) $colori[ $secondo ] );

			if ( $rapporto < 4.5 ) {
				$guai[] = array( 'cosa' => $cosa, 'rapporto' => $rapporto );
			}
		}

		return $guai;
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
