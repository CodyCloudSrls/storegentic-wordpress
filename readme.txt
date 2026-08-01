=== Storegentic per WooCommerce ===
Contributors: codycloud
Tags: woocommerce, ricerca, ricerca semantica, ecommerce
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later

Collega un negozio WooCommerce a Storegentic: ricerca semantica, agente conversazionale e analisi di cosa cercano i clienti.

== Description ==

Il plugin manda il catalogo a Storegentic e mette una ricerca semantica sul
negozio. Funziona su qualsiasi tema, senza modificarlo.

**Come e' fatto**

Il plugin non conosce gli indirizzi del servizio. Ne conosce uno solo, quello
dell'handshake: da li' il server dichiara quali funzioni sono attive per quel
negozio, quali indirizzi usare e quali limiti valgono. Se il servizio cambia,
le installazioni si adeguano da sole.

La chiave del negozio resta sul server. Il browser parla con WordPress,
WordPress parla con Storegentic: la chiave non compare mai nel sorgente di
una pagina pubblica.

Il caricamento del catalogo avviene in due fasi. Prima si spediscono le
pagine, poi — e solo se ogni pagina e' passata — si chiude la sessione e il
servizio toglie dall'indice cio' che non ha visto. La chiusura non parte mai
su una sincronizzazione incompleta, perche' toglierebbe dall'indice i
prodotti che non hanno fatto in tempo a partire.

== Installation ==

1. Attiva il plugin.
2. WooCommerce > Storegentic.
3. Incolla la chiave del negozio e salva.
4. Verifica il collegamento, poi sincronizza il catalogo.

== Frequently Asked Questions ==

= La chiave e' visibile ai visitatori? =
No. Le chiamate passano dal server.

= Che succede se la sincronizzazione si interrompe? =
Riprende dalla pagina dove si era fermata. La chiusura, che e' l'unica fase
che toglie prodotti dall'indice, parte solo se tutte le pagine sono passate.

= Posso decidere dove compare la ricerca? =
Si': barra con lo shortcode [storegentic], pulsante fluttuante, oppure solo
finestra aperta da un tuo elemento con l'attributo data-storegentic.

== Changelog ==

= 0.1.0 =
* Prima versione: handshake, sincronizzazione a due fasi, ricerca, analisi.
