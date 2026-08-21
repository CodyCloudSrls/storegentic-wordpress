=== Storegentic ===
Contributors: codycloud
Tags: ricerca, ricerca semantica, ricerca per immagine, assistente, woocommerce
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.3.0
License: AGPLv3 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Ricerca semantica, ricerca con una foto e un assistente conversazionale, su un
negozio WooCommerce o su un sito qualunque.

== Description ==

Il plugin manda i contenuti a Storegentic e mette sul sito tre modi di cercare:
a parole, con una fotografia, e chiedendo a un assistente. Funziona con
qualsiasi tema, senza modificarlo.

**Con WooCommerce, e senza**

Con WooCommerce indicizza i prodotti, e i risultati sono schede con prezzo e
disponibilità. Senza, indicizza le pagine e gli articoli che scegli e diventa
la base di conoscenza del sito: l'assistente risponde su quello che il sito
racconta. Il plugin si adatta da solo, non c'è niente da configurare.

**Il servizio dichiara, il plugin obbedisce**

Il plugin non conosce gli indirizzi del servizio. Ne conosce uno solo, quello
dell'handshake: da lì il server dichiara quali funzioni sono attive per quel
sito, quali indirizzi usare e quali limiti valgono. Se il servizio cambia, le
installazioni si adeguano da sole, senza aggiornare il plugin su ogni sito.

Una modalità compare solo se la vuoi tu **e** se il servizio la dichiara. Un
comando che risponde «non disponibile» è peggio di un comando assente.

**La chiave resta sul server**

Il browser parla con WordPress, WordPress parla con Storegentic. La chiave non
compare mai nel sorgente di una pagina pubblica.

**Se il servizio non risponde, il sito continua a far trovare le cose**

Succede: la quota del piano finisce, il servizio va in manutenzione, la rete
cade. In quei casi la ricerca cerca nei contenuti del sito e lo dichiara a
schermo, invece di mostrare un errore a chi stava comprando. Si può spegnere.

**Il caricamento dei contenuti avviene in due fasi**

Prima si spediscono le pagine, poi — e solo se ogni pagina è passata — si
chiude la sessione e il servizio toglie dall'indice ciò che non ha visto. La
chiusura non parte mai su una sincronizzazione incompleta, perché toglierebbe
dall'indice ciò che non ha fatto in tempo a partire. Se la potatura supera una
soglia, si ferma e chiede conferma.

**Misura, e lo dice**

Il pannello mostra il piano e i consumi dichiarati dal servizio, e accanto
quello che è successo davvero: quante domande sono arrivate, quante sono
rimaste senza risultati, quante volte il servizio non ha risposto, e — la
tabella che vale il pannello intero — che cosa cercano le persone e non
trovano.

Il funnel arriva fino all'ordine: ricerca, risultati, apertura, carrello,
cassa, acquisto. Un acquisto conta come «venuto dalla ricerca» solo per gli
articoli che erano stati davvero aperti da un risultato. Non partono dati
personali: quanti articoli, quanto valgono, in che valuta, quali SKU.

**Personalizzabile**

Sette colori con combinazioni già pronte, e il rapporto di contrasto misurato
mentre si sceglie. Quattro modi di aprire la finestra — al centro, a lato, dal
basso, a tutto schermo — più larghezza, densità, colonne, velo, movimento e
carattere dei titoli. Tutto passa da variabili CSS: un tema può ridefinirle
invece di combattere con la specificità dei selettori.

== Installation ==

1. Carica la cartella `storegentic` in `wp-content/plugins/` e attiva il plugin.
2. Menu **Storegentic > Collegamento**.
3. Incolla la chiave del servizio e salva.
4. Verifica il collegamento, poi vai su **Catalogo** e sincronizza.

Il plugin non richiede WooCommerce. Se WooCommerce c'è, indicizza i prodotti;
altrimenti la pagina si chiama **Contenuti** e scegli tu che cosa indicizzare.

== Frequently Asked Questions ==

= La chiave è visibile ai visitatori? =
No. Le chiamate passano dal server.

= Che succede se la sincronizzazione si interrompe? =
Riprende dalla pagina dove si era fermata. La chiusura, che è l'unica fase che
toglie contenuti dall'indice, parte solo se tutte le pagine sono passate.

= Che succede se il servizio non risponde? =
La ricerca cerca nei contenuti del sito e lo scrive sopra i risultati. È una
ricerca per parole, non per concetti: trova meno cose, ma chi sta comprando
vede dei prodotti invece di un errore.

= Serve il consenso ai cookie? =
Il plugin scrive un cookie solo quando qualcuno usa davvero la ricerca, e solo
se c'è il consenso: usa la WP Consent API se il sito ce l'ha, altrimenti il
filtro `storegentic_consenso_statistiche`. Chi non tocca la ricerca non riceve
niente.

= In quali lingue è tradotto? =
Italiano, inglese, tedesco, francese e spagnolo.

= Posso decidere dove compare la ricerca? =
Sì: lo shortcode `[storegentic]`, il pulsante fluttuante, oppure un elemento
tuo con l'attributo `data-storegentic`. Si può anche sostituire la ricerca del
tema.

== Changelog ==

= 0.3.0 =
* Menu di primo livello, diviso per ambito: panoramica, collegamento, aspetto, ricerca, catalogo, statistiche.
* Il plugin funziona anche senza WooCommerce, come base di conoscenza del sito.
* Il funnel arriva fino all'ordine: carrello, cassa e acquisto, con attribuzione.
* Quattro forme della finestra, più densità, colonne, velo, movimento e caratteri.
* Impostazioni per i parametri che il contratto dichiara: quanti risultati e soglia di somiglianza.
* Riquadro in bacheca con il riassunto e gli allarmi.
* Tradotto in inglese, tedesco, francese e spagnolo.

= 0.2.0 =
* Piano e consumi nel pannello, con l'avviso quando un contatore è finito.
* Ripiego sui contenuti del sito quando il servizio non risponde.
* Statistiche tenute in casa: che cosa si cerca, che cosa non si trova, quanto ci mette il servizio.
* Ricerca istantanea nei suggerimenti mentre si scrive.
* Una finestra sola, autosufficiente, con ricerca, foto e assistente.

= 0.1.0 =
* Prima versione: handshake, sincronizzazione a due fasi, ricerca, analisi.
