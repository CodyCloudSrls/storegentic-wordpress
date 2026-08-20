<?php
/**
 * La forma dell'interfaccia: com'e' fatta la finestra, non di che colore e'.
 *
 * PERCHE' UNA SECONDA CLASSE E NON PIU' VOCI IN Palette. Sono due domande
 * diverse, e chi le risponde ragiona in due modi diversi. I colori sono
 * un'identita': si scelgono guardando. La forma e' una scelta di prodotto: una
 * finestra al centro dice "fermati e guarda", un cassetto laterale dice
 * "continua pure a leggere". Mescolarle in un elenco solo di venti voci
 * darebbe una pagina che non compila nessuno — lo stesso motivo per cui i
 * colori sono sette e non venti.
 *
 * COSA SI PUO' CAMBIARE, E PERCHE' PROPRIO QUESTE COSE
 *
 *   forma       dove si apre la finestra. E' la leva che cambia di piu' la
 *               sensazione del negozio, e l'unica che non si puo' ottenere con
 *               il CSS del tema senza riscrivere il markup.
 *   larghezza   quanto e' larga. Un catalogo di venti prodotti sta stretto in
 *               una finestra da mille pixel; uno di diecimila ci sta male.
 *   pulsante    che aspetto ha il comando che apre: pillola con la scritta,
 *               tondo con la sola lente, o barra larga in fondo.
 *   distanza    quanto sta staccato dai bordi. Su un sito che ha gia' un
 *               comando in basso a destra, questo lo scavalca.
 *   densita     quanto respira il contenuto. Comoda si legge meglio, compatta
 *               ne fa stare di piu' sullo stesso schermo.
 *   colonne     quanto e' larga la scheda piu' stretta: e' cosi' che si decide
 *               quante colonne di prodotti stanno in riga, a qualunque
 *               larghezza di schermo, senza scrivere numeri di rottura.
 *   velo        quanto si oscura la pagina dietro.
 *   movimento   se la finestra entra con un'animazione.
 *   caratteri   il carattere dei titoli.
 *
 * COME ARRIVA A SCHERMO. Come i colori: variabili CSS stampate in pagina. Il
 * foglio di stile usa `var( --sg-qualcosa, ripiego )` dappertutto, quindi un
 * valore non impostato lascia in piedi quello di prima e un tema puo'
 * continuare a ridefinire tutto. Qui non si stampa una riga di CSS che non sia
 * una variabile: nessuna regola, nessun selettore, nessuna sorpresa.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

use Storegentic\Impostazioni;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Forma {

	/**
	 * Dove si apre la finestra.
	 *
	 * `centro` e' quella di sempre. Le altre non sono varianti estetiche:
	 * rispondono a bisogni diversi di chi vende.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function forme(): array {
		return array(
			'centro'   => array(
				'nome'   => __( 'Al centro', 'storegentic' ),
				'spiega' => __( 'Un foglio in mezzo allo schermo, sopra la pagina. Chiede attenzione: chi lo apre sta cercando, non leggendo.', 'storegentic' ),
			),
			'laterale' => array(
				'nome'   => __( 'A lato', 'storegentic' ),
				'spiega' => __( 'Un cassetto che entra dal bordo e lascia vedere la pagina. Adatto a chi vuole che si continui a leggere mentre si cerca.', 'storegentic' ),
			),
			'basso'    => array(
				'nome'   => __( 'Dal basso', 'storegentic' ),
				'spiega' => __( 'Sale dal fondo, come nelle applicazioni. Sul telefono è il gesto più naturale, e il pollice arriva ai comandi.', 'storegentic' ),
			),
			'pieno'    => array(
				'nome'   => __( 'A tutto schermo', 'storegentic' ),
				'spiega' => __( 'Occupa tutto, su ogni dispositivo. Massimo spazio per le fotografie, nessuna distrazione intorno.', 'storegentic' ),
			),
		);
	}

	/**
	 * Che aspetto ha il comando che apre la finestra.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function pulsanti(): array {
		return array(
			'pillola' => array(
				'nome'   => __( 'Pillola con scritta', 'storegentic' ),
				'spiega' => __( 'Dice a parole cosa fa. Chi non ha mai visto un assistente su un sito capisce che può parlarci.', 'storegentic' ),
			),
			'tondo'   => array(
				'nome'   => __( 'Tondo, solo icona', 'storegentic' ),
				'spiega' => __( 'Occupa pochissimo. Si sceglie quando la pagina ha già altri comandi in basso.', 'storegentic' ),
			),
			'barra'   => array(
				'nome'   => __( 'Barra in fondo', 'storegentic' ),
				'spiega' => __( 'Una fascia larga quanto lo schermo. Difficile non vederla, e sul telefono si tocca senza mirare.', 'storegentic' ),
			),
		);
	}

	/**
	 * Quanto respira il contenuto.
	 *
	 * Il valore e' un fattore, non una misura: moltiplica gli spazi gia'
	 * scelti invece di sostituirli. Cosi' il rapporto fra le distanze resta
	 * quello disegnato, e cambia solo la scala.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function densita(): array {
		return array(
			'comoda'   => array(
				'nome'    => __( 'Comoda', 'storegentic' ),
				'spiega'  => __( 'Più aria intorno a ogni cosa. Si legge meglio, e su uno schermo grande non sembra vuoto.', 'storegentic' ),
				'fattore' => 1.0,
			),
			'compatta' => array(
				'nome'    => __( 'Compatta', 'storegentic' ),
				'spiega'  => __( 'Meno spazi. Sullo stesso schermo ci stanno più prodotti, e si scorre di meno.', 'storegentic' ),
				'fattore' => 0.78,
			),
		);
	}

	/**
	 * I caratteri fra cui si puo' scegliere per i titoli.
	 *
	 * NESSUN CARATTERE SI SCARICA DA FUORI. Sono pile di caratteri di sistema:
	 * arrivano a costo zero, non fanno una richiesta di rete, non spostano il
	 * testo quando finiscono di caricare, e non mandano l'indirizzo IP di chi
	 * visita a un servizio di terze parti — cosa che con i caratteri di Google
	 * e' un problema di privacy prima ancora che di prestazioni.
	 *
	 * Chi vuole il proprio carattere lascia "dal tema": il tema lo ha gia'
	 * caricato, e il plugin lo eredita senza saperne niente.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function caratteri(): array {
		return array(
			'tema'      => array(
				'nome'  => __( 'Dal tema del sito', 'storegentic' ),
				'pila'  => 'inherit',
			),
			'sistema'   => array(
				'nome'  => __( 'Senza grazie', 'storegentic' ),
				'pila'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			),
			'grazie'    => array(
				'nome'  => __( 'Con grazie', 'storegentic' ),
				'pila'  => 'Georgia, "Times New Roman", "Nimbus Roman", serif',
			),
			'stretto'   => array(
				'nome'  => __( 'Stretto', 'storegentic' ),
				'pila'  => '"Helvetica Neue", "Arial Narrow", "Liberation Sans Narrow", sans-serif',
			),
		);
	}

	/**
	 * Le variabili CSS della forma.
	 *
	 * Solo variabili. Le regole che le usano stanno nel foglio di stile, dove
	 * si possono leggere tutte insieme: un plugin che stampa selettori in
	 * pagina costringe a cercare lo stile in due posti.
	 */
	public static function css(): string {
		$i = Impostazioni::tutte();

		$densita = self::densita();
		$fattore = (float) ( $densita[ (string) $i['densita'] ]['fattore'] ?? 1.0 );

		$caratteri = self::caratteri();
		$pila      = (string) ( $caratteri[ (string) $i['caratteri'] ]['pila'] ?? 'inherit' );

		$righe = array(
			// Quanto e' larga la finestra, e quanto e' alta al massimo.
			'--sg-finestra-larga:' . self::rem( (int) $i['larghezza'] ),
			'--sg-finestra-alta:' . self::rem( (int) $i['altezza'] ),

			/*
			 * Il fattore di densita' moltiplica gli spazi. Le distanze restano
			 * in proporzione fra loro: cambia la scala, non il disegno.
			 */
			'--sg-spazio:' . self::numero( $fattore ),

			// La scheda piu' stretta ammessa: da qui il numero di colonne.
			'--sg-scheda-minima:' . self::rem( (int) $i['colonna'] ),

			// Quanto sta staccato dal bordo il comando che apre.
			'--sg-basso:' . self::rem( (int) $i['distanza'] ),

			'--sg-titolo-font:' . $pila,
		);

		/*
		 * IL VELO SI RICAVA, NON SI SCRIVE. Il colore lo decide la palette —
		 * su un fondo scuro un velo nero non si vedrebbe — e qui si regola
		 * solo quanto copre. Si moltiplica l'opacita' scelta dentro la stessa
		 * funzione che la palette usa gia', cosi' i due comandi non si
		 * contraddicono.
		 */
		$righe[] = '--sg-velo-quanto:' . self::numero( (int) $i['velo'] / 100 );
		$righe[] = '--sg-velo-sfocatura:' . ( (bool) $i['sfocatura'] ? '3px' : '0px' );

		/*
		 * Il movimento si spegne mettendo la durata a zero, invece di togliere
		 * la transizione: cosi' resta una sola regola nel foglio di stile, e
		 * `prefers-reduced-motion` puo' azzerarla comunque per chi lo chiede al
		 * proprio dispositivo, qualunque cosa dica questa impostazione.
		 */
		$righe[] = '--sg-movimento:' . ( (bool) $i['movimento'] ? '.22s' : '0s' );

		return ':root{' . implode( ';', $righe ) . '}';
	}

	/**
	 * La classe che dice al foglio di stile che forma dare alla finestra.
	 *
	 * Si mette sull'elemento della finestra e non sul `body`: la finestra e'
	 * l'unica cosa che cambia, e sporcare il `body` di un sito altrui con le
	 * nostre classi e' il modo piu' rapido per collidere con il tema.
	 */
	public static function classe(): string {
		$forma = (string) Impostazioni::leggi( 'forma' );

		return 'sg-finestra--' . ( isset( self::forme()[ $forma ] ) ? $forma : 'centro' );
	}

	/** La classe del comando che apre. */
	public static function classe_pulsante(): string {
		$scelto = (string) Impostazioni::leggi( 'pulsante' );

		return 'sg-lancia--' . ( isset( self::pulsanti()[ $scelto ] ) ? $scelto : 'pillola' );
	}

	/** Un numero intero in rem, con il punto decimale che vuole il CSS. */
	private static function rem( int $decimi ): string {
		return self::numero( $decimi / 10 ) . 'rem';
	}

	/** Un numero come lo vuole il CSS: punto decimale, niente zeri inutili. */
	private static function numero( float $valore ): string {
		return rtrim( rtrim( number_format( $valore, 2, '.', '' ), '0' ), '.' ) ?: '0';
	}
}
