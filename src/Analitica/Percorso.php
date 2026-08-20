<?php
/**
 * Il funnel: dalla ricerca all'ordine pagato.
 *
 * IL BUCO CHE QUESTA CLASSE CHIUDE. Il plugin mandava a Storegentic tre eventi
 * su sei: la domanda, i risultati, il clic su un risultato. Poi silenzio. Il
 * servizio dichiara di accettare anche `add_to_cart`, `checkout_started` e
 * `purchase_completed`, e nessuno glieli mandava: `add_to_cart` era addirittura
 * nel vocabolario del plugin senza che una sola riga lo emettesse.
 *
 * Il risultato era un funnel che si interrompe proprio dove comincia a valere
 * qualcosa. Si vedeva che le persone cercavano e cliccavano; non si vedeva se
 * compravano. E quindi non si poteva rispondere all'unica domanda che conta per
 * chi paga il servizio: **la ricerca intelligente fa vendere di piu'?**
 *
 * COSA SI AGGANCIA, E PERCHE' PROPRIO QUESTI GANCI
 *
 *   woocommerce_add_to_cart          il carrello vero, non solo il pulsante
 *                                    dentro la nostra finestra. Chi cerca,
 *                                    apre la scheda e aggiunge da li' e' il
 *                                    percorso piu' comune di tutti, ed era
 *                                    invisibile.
 *   woocommerce_before_checkout_form la cassa aperta. Si aggancia alla vista
 *                                    del modulo e non a `is_checkout()`,
 *                                    perche' quello e' vero anche sulla pagina
 *                                    di ringraziamento.
 *   woocommerce_thankyou             l'ordine esiste. Non si aspetta il
 *                                    pagamento incassato: un bonifico si
 *                                    conferma dopo giorni, e un funnel che
 *                                    arriva con tre giorni di ritardo non
 *                                    serve a leggere una campagna.
 *
 * L'ATTRIBUZIONE. Un acquisto conta come "venuto da Storegentic" solo se in
 * quella sessione il prodotto era stato aperto da un nostro risultato. Il filo
 * lo tiene Analitica\Sessione. Si manda comunque anche l'ordine non attribuito,
 * con il totale: senza il denominatore, la percentuale di conversione della
 * ricerca non si puo' calcolare.
 *
 * NIENTE DATI PERSONALI. Non partono nome, email, indirizzo ne' il numero
 * d'ordine leggibile. Partono: quanti articoli, quanto vale, in che valuta,
 * quali SKU erano attribuiti e da quale modo. E' quello che serve a misurare
 * un funnel, e nulla di piu'.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Analitica;

use Storegentic\Impostazioni;
use Storegentic\Negozio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Percorso {

	/**
	 * Il segno che dice "quest'ordine e' gia' stato contato".
	 *
	 * Sta sull'ordine e non in una sessione: la pagina di ringraziamento si
	 * ricarica, si condivide, si riapre dalla cronologia, e ogni volta
	 * `woocommerce_thankyou` scatta di nuovo. Senza questo segno un ordine
	 * finirebbe nel funnel tre volte e il fatturato attribuito sarebbe gonfio.
	 */
	private const CONTATO = '_storegentic_percorso';

	public static function avvia(): void {
		if ( ! Negozio::c_e() ) {
			return; // Senza negozio non c'e' carrello, cassa ne' ordine.
		}

		add_action( 'woocommerce_add_to_cart', array( self::class, 'nel_carrello' ), 10, 6 );
		add_action( 'woocommerce_before_checkout_form', array( self::class, 'alla_cassa' ) );
		add_action( 'woocommerce_thankyou', array( self::class, 'ordinato' ) );
	}

	/**
	 * Qualcuno ha messo qualcosa nel carrello.
	 *
	 * @param string $chiave     Chiave della riga nel carrello.
	 * @param int    $id         Prodotto.
	 * @param int    $quantita   Quanti.
	 * @param int    $variazione Variante scelta, se c'e'.
	 */
	public static function nel_carrello( $chiave, $id, $quantita, $variazione = 0 ): void {
		$prodotto = wc_get_product( $variazione ? (int) $variazione : (int) $id );

		if ( ! $prodotto ) {
			return;
		}

		$sku  = \Storegentic\Frontend\Risolutore::sku( $prodotto );
		$modo = Sessione::modo_di( $sku );

		/*
		 * Anche l'aggiunta NON attribuita si manda. Il servizio deve poter
		 * calcolare quanta parte del carrello passa dalla ricerca: senza le
		 * aggiunte che non ci passano, quella frazione varrebbe sempre 100%.
		 */
		Registratore::accoda(
			'add_to_cart',
			array(
				'sessionId' => Sessione::id(),
				'mode'      => $modo,
				'data'      => array(
					'sku'         => $sku,
					'quantity'    => (int) $quantita,
					'value'       => (float) $prodotto->get_price(),
					'currency'    => get_woocommerce_currency(),
					'attributed'  => '' !== $modo,
				),
			)
		);
	}

	/** Il modulo della cassa e' comparso a schermo. */
	public static function alla_cassa(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$carrello = WC()->cart;

		if ( $carrello->is_empty() ) {
			return;
		}

		/*
		 * Una volta per visita alla cassa, non a ogni ricaricamento. Chi
		 * sbaglia la carta e riprova non deve contare come due partenze: la
		 * percentuale di abbandono verrebbe fuori migliore di com'e'.
		 */
		if ( false !== get_transient( self::segno_cassa() ) ) {
			return;
		}

		set_transient( self::segno_cassa(), 1, HOUR_IN_SECONDS );

		$attribuiti = self::attribuiti_nel_carrello( $carrello );

		Registratore::accoda(
			'checkout_started',
			array(
				'sessionId' => Sessione::id(),
				'mode'      => $attribuiti['modo'],
				'data'      => array(
					'items'           => (int) $carrello->get_cart_contents_count(),
					'value'           => (float) $carrello->get_cart_contents_total(),
					'currency'        => get_woocommerce_currency(),
					'attributedItems' => (int) $attribuiti['quanti'],
					'attributedValue' => (float) $attribuiti['valore'],
				),
			)
		);
	}

	/**
	 * L'ordine e' stato registrato.
	 *
	 * @param int $ordine
	 */
	public static function ordinato( $ordine ): void {
		$ordine = wc_get_order( (int) $ordine );

		if ( ! $ordine || $ordine->get_meta( self::CONTATO ) ) {
			return;
		}

		// Si segna PRIMA di spedire: un errore di rete non deve far contare due volte.
		$ordine->update_meta_data( self::CONTATO, time() );
		$ordine->save();

		$quanti = 0;
		$valore = 0.0;
		$modo   = '';
		$skus   = array();

		foreach ( $ordine->get_items() as $riga ) {
			$prodotto = $riga->get_product();

			if ( ! $prodotto ) {
				continue;
			}

			$sku      = \Storegentic\Frontend\Risolutore::sku( $prodotto );
			$suo_modo = Sessione::modo_di( $sku );

			if ( '' === $suo_modo ) {
				continue;
			}

			$quanti += (int) $riga->get_quantity();
			$valore += (float) $riga->get_total();
			$skus[]  = $sku;

			// Il modo dell'ordine e' quello del primo articolo attribuito.
			if ( '' === $modo ) {
				$modo = $suo_modo;
			}
		}

		Registratore::accoda(
			'purchase_completed',
			array(
				'sessionId' => Sessione::id(),
				'mode'      => $modo,
				'data'      => array(
					'items'           => (int) $ordine->get_item_count(),
					'value'           => (float) $ordine->get_total(),
					'currency'        => $ordine->get_currency(),
					'attributedItems' => $quanti,
					'attributedValue' => round( $valore, 2 ),
					// Solo gli SKU, che sono codici di catalogo e non dati di persone.
					'attributedSkus'  => array_slice( $skus, 0, 20 ),
				),
			)
		);

		/*
		 * Il filo ha finito il suo lavoro. Lasciarlo in piedi vorrebbe dire
		 * attribuire alla ricerca di oggi anche l'ordine che la stessa persona
		 * fara' fra tre settimane.
		 */
		Sessione::dimentica();
	}

	/**
	 * Quanta parte del carrello viene da Storegentic.
	 *
	 * @param \WC_Cart $carrello
	 * @return array{quanti:int,valore:float,modo:string}
	 */
	private static function attribuiti_nel_carrello( $carrello ): array {
		$quanti = 0;
		$valore = 0.0;
		$modo   = '';

		foreach ( $carrello->get_cart() as $riga ) {
			$prodotto = $riga['data'] ?? null;

			if ( ! $prodotto instanceof \WC_Product ) {
				continue;
			}

			$suo_modo = Sessione::modo_di( \Storegentic\Frontend\Risolutore::sku( $prodotto ) );

			if ( '' === $suo_modo ) {
				continue;
			}

			$quanti += (int) ( $riga['quantity'] ?? 0 );
			$valore += (float) ( $riga['line_total'] ?? 0 );

			if ( '' === $modo ) {
				$modo = $suo_modo;
			}
		}

		return array( 'quanti' => $quanti, 'valore' => round( $valore, 2 ), 'modo' => $modo );
	}

	/** Il segno "gia' arrivato alla cassa", legato a questa sessione. */
	private static function segno_cassa(): string {
		$id = Sessione::id();

		return 'sg_cassa_' . hash( 'sha256', '' !== $id ? $id : (string) wp_get_session_token() );
	}
}
