<?php
/**
 * Il disegno di un risultato.
 *
 * UN SOLO POSTO. I risultati compaiono in tre punti — il pannello rapido, la
 * pagina dei risultati, l'elenco sotto una risposta dell'assistente — e due di
 * questi arrivano dal browser, non dal server. La via facile sarebbe scrivere
 * il markup due volte: una in PHP per la pagina, una in JavaScript per il
 * resto. Le due copie divergono al primo ritocco, e si scopre mesi dopo che
 * lo sconto si vede solo in meta' del sito.
 *
 * Qui il markup esiste una volta sola, in PHP. Quando serve al browser, il
 * ponte manda l'HTML gia' fatto insieme ai dati: il JavaScript lo inserisce e
 * basta. Ne guadagna anche la sicurezza, perche' l'escape avviene in un punto
 * solo e non in ogni concatenazione di stringhe lato client.
 *
 * DUE FORME, NON DUE SCHEDE. `griglia` e' la vetrina della pagina dei
 * risultati; `riga` e' la voce compatta del pannello e dell'assistente, dove
 * lo spazio verticale e' poco. Cambiano proporzioni e quantita' di testo, non
 * i dati ne' il comportamento.
 *
 * @package Storegentic
 */

declare( strict_types = 1 );

namespace Storegentic\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scheda {

	/**
	 * @param array<string,mixed> $s
	 */
	public static function html( array $s, string $forma = 'griglia' ): string {
		return 'riga' === $forma ? self::riga( $s ) : self::griglia( $s );
	}

	/**
	 * @param array<string,mixed> $s
	 */
	private static function griglia( array $s ): string {
		ob_start();
		?>
		<article class="sg-scheda">
			<a class="sg-scheda__link" href="<?php echo esc_url( (string) $s['url'] ); ?>">
				<span class="sg-scheda__foto">
					<?php echo self::foto( $s, 300, 400 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo self::segno( $s ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</span>
				<span class="sg-scheda__corpo">
					<?php if ( ! empty( $s['categoria'] ) ) : ?>
						<span class="sg-scheda__categoria"><?php echo esc_html( (string) $s['categoria'] ); ?></span>
					<?php endif; ?>
					<span class="sg-scheda__nome"><?php echo esc_html( (string) $s['nome'] ); ?></span>
					<?php echo self::prezzo( $s ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</span>
			</a>
			<?php echo self::acquisto( $s ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</article>
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * La forma compatta: foto, nome, e il prezzo in fondo.
	 *
	 * Il prezzo sta in una pastiglia a destra e non sotto il nome. In una
	 * colonna stretta i prezzi incolonnati si confrontano con un colpo
	 * d'occhio, mentre annegati nel testo vanno cercati riga per riga — ed e'
	 * il dato che si guarda per primo.
	 *
	 * @param array<string,mixed> $s
	 */
	private static function riga( array $s ): string {
		ob_start();
		?>
		<a class="sg-riga" href="<?php echo esc_url( (string) $s['url'] ); ?>">
			<span class="sg-riga__foto"><?php echo self::foto( $s, 72, 72 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="sg-riga__corpo">
				<span class="sg-riga__nome"><?php echo esc_html( (string) $s['nome'] ); ?></span>
				<?php if ( ! empty( $s['categoria'] ) ) : ?>
					<span class="sg-riga__categoria"><?php echo esc_html( (string) $s['categoria'] ); ?></span>
				<?php endif; ?>
			</span>
			<?php echo self::pastiglia( $s ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Il prezzo come pastiglia.
	 *
	 * Esaurito e senza prezzo non sono prezzi: prendono la forma tenue, cosi'
	 * l'accento resta il segnale di "questo si compra".
	 *
	 * @param array<string,mixed> $s
	 */
	private static function pastiglia( array $s ): string {
		if ( empty( $s['disponibile'] ) ) {
			return '<span class="sg-cartellino sg-cartellino--tenue">' . esc_html__( 'Esaurito', 'storegentic' ) . '</span>';
		}

		$prezzo = (string) ( $s['prezzo'] ?? '' );

		if ( '' === $prezzo ) {
			return '<span class="sg-cartellino sg-cartellino--tenue">' . esc_html__( 'Su richiesta', 'storegentic' ) . '</span>';
		}

		return '<span class="sg-cartellino">' . esc_html( $prezzo ) . '</span>';
	}

	/**
	 * @param array<string,mixed> $s
	 */
	private static function foto( array $s, int $larghezza, int $altezza ): string {
		$url = (string) ( $s['immagine'] ?? '' );

		if ( '' === $url ) {
			return '<span class="sg-scheda__vuota" aria-hidden="true"></span>';
		}

		/*
		 * `loading` e `decoding` non sono decorazioni: una pagina di risultati
		 * mostra fino a quarantotto foto, e senza il caricamento pigro il
		 * telefono le scarica tutte prima di disegnare la prima riga.
		 */
		return sprintf(
			'<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" decoding="async">',
			esc_url( $url ),
			esc_attr( (string) ( $s['alt'] ?? $s['nome'] ?? '' ) ),
			$larghezza,
			$altezza
		);
	}

	/**
	 * @param array<string,mixed> $s
	 */
	private static function segno( array $s ): string {
		if ( empty( $s['disponibile'] ) ) {
			return '<span class="sg-segno sg-segno--fine">' . esc_html__( 'Esaurito', 'storegentic' ) . '</span>';
		}

		if ( ! empty( $s['prezzoPieno'] ) ) {
			return '<span class="sg-segno sg-segno--saldo">' . esc_html__( 'In saldo', 'storegentic' ) . '</span>';
		}

		if ( ! empty( $s['unico'] ) ) {
			return '<span class="sg-segno sg-segno--unico">' . esc_html__( 'Ultimo pezzo', 'storegentic' ) . '</span>';
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $s
	 */
	private static function prezzo( array $s ): string {
		$prezzo = (string) ( $s['prezzo'] ?? '' );

		/*
		 * Trentadue prodotti di questo catalogo non hanno prezzo. Lasciare lo
		 * spazio vuoto fa sembrare la scheda rotta: si dice cosa fare.
		 */
		if ( '' === $prezzo ) {
			return '<span class="sg-prezzo sg-prezzo--assente">' . esc_html__( 'Prezzo su richiesta', 'storegentic' ) . '</span>';
		}

		$pieno = (string) ( $s['prezzoPieno'] ?? '' );

		if ( '' !== $pieno ) {
			return sprintf(
				'<span class="sg-prezzo"><s class="sg-prezzo__pieno">%s</s> <span class="sg-prezzo__ora">%s</span></span>',
				esc_html( $pieno ),
				esc_html( $prezzo )
			);
		}

		return '<span class="sg-prezzo">' . esc_html( $prezzo ) . '</span>';
	}

	/**
	 * Il pulsante che aggiunge al carrello, con il markup di WooCommerce.
	 *
	 * Si usano le classi e gli attributi che WooCommerce si aspetta invece di
	 * inventarne di propri: cosi' se ne occupa il suo JavaScript, il carrello
	 * si aggiorna senza ricaricare, e l'evento `added_to_cart` arriva al tema,
	 * che lo usa gia' per il contatore in testata. Riscrivere quella catena
	 * avrebbe voluto dire riscriverla peggio.
	 *
	 * @param array<string,mixed> $s
	 */
	private static function acquisto( array $s ): string {
		if ( empty( $s['acquistabile'] ) || empty( $s['id'] ) ) {
			return '';
		}

		$prodotto = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $s['id'] ) : null;

		if ( ! $prodotto ) {
			return '';
		}

		return sprintf(
			'<a href="%1$s" class="sg-aggiungi button product_type_%2$s add_to_cart_button ajax_add_to_cart" data-product_id="%3$d" data-product_sku="%4$s" data-quantity="1" aria-label="%5$s" rel="nofollow">%6$s</a>',
			esc_url( $prodotto->add_to_cart_url() ),
			esc_attr( $prodotto->get_type() ),
			(int) $s['id'],
			esc_attr( (string) $prodotto->get_sku() ),
			/* translators: %s: nome del prodotto. */
			esc_attr( sprintf( __( 'Aggiungi %s al carrello', 'storegentic' ), (string) $s['nome'] ) ),
			esc_html__( 'Aggiungi', 'storegentic' )
		);
	}

	/**
	 * Le stesse schede, con l'HTML accanto ai dati.
	 *
	 * I dati servono al browser per filtrare e ordinare senza richiedere di
	 * nuovo; l'HTML gli evita di doverlo costruire.
	 *
	 * @param array<int,array<string,mixed>> $schede
	 * @return array<int,array<string,mixed>>
	 */
	public static function con_html( array $schede, string $forma ): array {
		foreach ( $schede as $i => $s ) {
			$schede[ $i ]['html'] = self::html( $s, $forma );
		}

		return $schede;
	}
}
