# Storegentic — note per chi ci lavora

Questo file parla a chi tocca il codice. Chi installa il plugin legge
`readme.txt`.

## Come è fatto, in tre frasi

Il plugin non conosce nessun indirizzo del servizio tranne quello
dell'handshake. Tutto il resto — quali funzioni esistono, dove stanno, quanto
si può chiedere — lo dichiara il server a ogni collegamento, e il plugin si
adegua senza essere aggiornato. È la scelta che gli permette di stare su un
parco di installazioni che nessuno aggiorna.

Vedi `src/Api/Contratto.php`: c'è scritto lì, con il perché.

## Le classi, e a che domanda rispondono

| dove | domanda |
|---|---|
| `Api/Contratto` | che cosa dichiara il servizio? |
| `Api/Client` | come si parla con la rete, e cosa si fa quando non risponde |
| `Api/Consumi` | quanto consente il piano, quanto è già andato |
| `Api/Parametri` | quali parametri di ricerca il contratto dichiara regolabili |
| `Catalogo/Sincronizzazione` | la macchina a stati del caricamento, in due fasi |
| `Catalogo/Mappatore` | un prodotto WooCommerce, nella forma che il servizio vuole |
| `Catalogo/Contenuti` | lo stesso, per una pagina o un articolo |
| `Frontend/Ponte` | l'unica porta fra il browser e il servizio |
| `Frontend/Ricerca` | l'unica implementazione della ricerca |
| `Frontend/Ripiego` | che cosa si mostra quando il servizio tace |
| `Frontend/Citazioni` | quali prodotti nomina davvero una risposta dell'assistente |
| `Frontend/Palette` | di che colore è |
| `Frontend/Forma` | com'è fatta |
| `Analitica/Registratore` | la coda degli eventi verso il servizio |
| `Analitica/Sessione` | il filo dalla ricerca all'ordine |
| `Analitica/Percorso` | il funnel: carrello, cassa, acquisto |
| `Analitica/Misure` | il conto tenuto in casa, per il pannello |
| `Negozio` | c'è WooCommerce, o è un sito qualunque? |

## I collaudi

Girano su un'installazione vera, con `wp eval-file`. Non toccano i dati: ognuno
mette da parte quello che cambia e lo rimette alla fine, anche se si interrompe
a metà.

    for f in collaudo/*.php; do wp eval-file "$f"; done

Sono 163 prove. Ognuna difende un difetto che è successo davvero: il commento
in cima a ogni file dice quale.

**Attenzione**: `collaudo/sincronizzazione.php` una volta ha spento il plugin in
produzione, e `collaudo/misure.php` una volta ha riscritto il diario del
negozio. Se ne scrivi uno nuovo, la prima cosa che scrivi è il ripristino.

## Le traduzioni

Vedi `languages/LEGGIMI.md`. In breve: si rigenera il `.pot`, si aggiornano i
`.po`, si ricompila in `.mo` **e** `.l10n.php`.

## Due indirizzi che il servizio non dichiara

Al 2026-08-20, `/v1/commerce/search/instant` e `/v1/commerce/catalog/reconcile`
esistono, funzionano e sono documentati, ma l'handshake non li nomina. Il
plugin per principio non scrive indirizzi nel proprio codice: chi li vuole
accendere usa il filtro `storegentic_endpoint`.

Senza `catalogReconcile` il catalogo non viene mai potato: un prodotto tolto
resta nell'indice e continua a comparire nei risultati.

    add_filter( 'storegentic_endpoint', function ( $trovato, $nome ) {
        $mancanti = array(
            'instantSearch'    => '/v1/commerce/search/instant',
            'catalogReconcile' => '/v1/commerce/catalog/reconcile',
        );
        return '' !== (string) $trovato ? $trovato : ( $mancanti[ $nome ] ?? $trovato );
    }, 10, 2 );

Il valore del contratto ha la precedenza: il giorno che il servizio li dichiara,
questo filtro smette di fare qualunque cosa e si può cancellare.

## I filtri che il plugin espone

| filtro | a che serve |
|---|---|
| `storegentic_endpoint` | fornire un indirizzo che il contratto non dichiara |
| `storegentic_permesso` | quale capacità serve per il pannello |
| `storegentic_consenso_statistiche` | negare il consenso al cookie del percorso |
| `storegentic_esempi_ricerca` | gli esempi nella ricerca |
| `storegentic_esempi_assistente` | le domande suggerite all'assistente |
| `storegentic_inneschi_ricerca` | i selettori della ricerca del tema da intercettare |
| `storegentic_palette_preparate` | aggiungere combinazioni di colori |
| `storegentic_colori` | ritoccare i colori a codice |
| `storegentic_prodotto` | aggiungere campi a un prodotto spedito |
| `storegentic_contenuto` | lo stesso, per un contenuto |
| `storegentic_prodotti_da_sincronizzare` | escludere prodotti |
| `storegentic_contenuti_da_sincronizzare` | escludere contenuti |
| `storegentic_indirizzo_client` | l'IP vero dietro un proxy fidato |

## Regole di casa

- **Niente indirizzi del servizio nel codice**, tranne l'handshake.
- **Si mostra solo ciò che il contratto dichiara.** Un comando che risponde
  «non autorizzato» è peggio di un comando assente.
- **Il commento dice il perché, non il cosa.** Se una riga è strana, sopra c'è
  scritto quale difetto ha causato quella stranezza.
- **Italiano tecnico semplificato**: un'idea per frase, verbo attivo, stessa
  parola per la stessa cosa.
- **Tutte le stringhe passano da una funzione di traduzione.** Nessuna scritta
  a mano nel JavaScript: i testi arrivano da PHP.
