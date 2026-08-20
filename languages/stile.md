# Come si scrive un'interfaccia, in qualunque lingua

Regole di linguaggio controllato, ispirate a ASD-STE100.

1. **Un'idea per frase.** Se ci sono due idee, sono due frasi.
2. **Verbo attivo, tempo presente.** "Il servizio non risponde", non "non è
   stata ricevuta risposta dal servizio".
3. **Stessa parola per la stessa cosa.** Vedi il glossario. Mai variare per
   eleganza.
4. **Niente aggettivi promozionali.** Non "potente", "avanzato", "intelligente"
   dove non è un nome tecnico.
5. **Corto.** Un'etichetta di pannello sta in due o tre parole. Una
   descrizione sta in una riga e mezza.
6. **Si dice cosa succede, non cosa è successo di male.** "Riprova fra poco",
   non "si è verificato un errore imprevisto".
7. **Si dà del tu** dove la lingua lo consente in un'interfaccia
   (inglese, spagnolo, francese: "vous" di cortesia; tedesco: "Sie").

## Regole tecniche, non negoziabili

- I segnaposto restano **identici**: `%s`, `%d`, `%1$s`, `%2$d`, `%%`. Stesso
  numero, stesso ordine se numerati. Se non sono numerati, l'ordine nella frase
  tradotta deve corrispondere all'originale.
- Gli spazi iniziali e finali si conservano esattamente.
- Le entità e i tag HTML si conservano.
- Le virgolette si adattano alla lingua: italiano «...», inglese "...",
  tedesco „...“, francese « ... » (con spazi unificatori), spagnolo «...».
- L'apostrofo tipografico ’ si usa dove la lingua lo prevede.
- Un `msgid` che è un indirizzo web o un nome proprio si restituisce **identico**.
