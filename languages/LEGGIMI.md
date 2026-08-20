# Le traduzioni di Storegentic

## Cosa c'è qui

| file | a cosa serve |
|---|---|
| `storegentic.pot` | il modello: l'elenco di tutte le stringhe del plugin, senza traduzioni. È il punto di partenza per una lingua nuova. |
| `storegentic-XX_XX.po` | il catalogo di una lingua, leggibile e modificabile. |
| `storegentic-XX_XX.mo` | lo stesso catalogo compilato. **È questo che WordPress legge.** Un `.po` modificato non ha effetto finché non lo si ricompila. |
| `glossario.md` | i termini fissi, lingua per lingua. |
| `stile.md` | come si scrive un'interfaccia: linguaggio controllato, ispirato ad ASD-STE100. |

## Perché l'italiano ha il `.po` e non il `.mo`

L'italiano è la lingua **sorgente**: le stringhe nel codice PHP sono già in
italiano, e WordPress usa l'originale quando non trova una traduzione. Un
catalogo italiano in cui ogni traduzione è identica all'originale non
cambierebbe una sola parola a schermo, e farebbe leggere e interpretare
quarantun kilobyte a ogni richiesta per niente.

Il `.po` resta perché è utile: permette di ritoccare una frase italiana senza
toccare il codice. Chi lo fa, poi lo compila, e da quel momento il catalogo
entra in funzione:

    wp i18n make-mo languages/storegentic-it_IT.po languages/

## Rifare il modello dopo aver cambiato il codice

    wp i18n make-pot . languages/storegentic.pot --domain=storegentic --exclude=collaudo

Poi si aggiornano i cataloghi esistenti sul modello nuovo:

    wp i18n update-po languages/storegentic.pot languages/

Le stringhe nuove restano vuote e vanno tradotte; quelle cambiate finiscono
segnate come "fuzzy" e vanno riviste.

## Le regole che non si negoziano

I segnaposto — `%s`, `%d`, `%1$s` — devono restare identici per numero e forma.
Uno perso fa comparire un buco nella frase; uno in più fa un errore di PHP.
Gli spazi all'inizio e alla fine di una stringa si conservano: molte servono a
staccare due parole che il codice unisce.
