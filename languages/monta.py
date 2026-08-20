#!/usr/bin/env python3
"""Monta un catalogo .po dal modello .pot piu' un dizionario di traduzioni.

Si parte SEMPRE dal .pot e non dalle traduzioni: cosi' il .po conserva i
commenti per il traduttore, i riferimenti ai file e i contesti, e una stringa
che il traduttore non ha reso resta vuota — che in gettext vuol dire "usa
l'originale", non "stringa vuota a schermo".
"""
import json, re, sys

INTESTAZIONI = {
    'en_US': ('English (United States)', 'nplurals=2; plural=(n != 1);'),
    'de_DE': ('German',                  'nplurals=2; plural=(n != 1);'),
    'fr_FR': ('French (France)',          'nplurals=2; plural=(n > 1);'),
    'es_ES': ('Spanish (Spain)',          'nplurals=2; plural=(n != 1);'),
    'it_IT': ('Italian',                  'nplurals=2; plural=(n != 1);'),
}


def cita(s):
    return json.dumps(s, ensure_ascii=False)


def monta(pot, traduzioni, lingua, uscita):
    nome, plurali = INTESTAZIONI[lingua]
    fuori, corrente, chiave, dentro_intestazione = [], [], None, True
    msgid = plurale = None
    saltate = 0

    def scarica():
        nonlocal corrente, msgid, plurale
        if msgid is None:
            fuori.extend(corrente)
            corrente = []
            return
        t = traduzioni.get(msgid)
        if t and t.get('msgstr'):
            if plurale is not None:
                fuori.append('msgstr[0] ' + cita(t['msgstr']))
                fuori.append('msgstr[1] ' + cita(t.get('msgstr_plurale') or t['msgstr']))
            else:
                fuori.append('msgstr ' + cita(t['msgstr']))
        else:
            if plurale is not None:
                fuori.append('msgstr[0] ""')
                fuori.append('msgstr[1] ""')
            else:
                fuori.append('msgstr ""')
        corrente = []
        msgid = plurale = None

    righe = open(pot, encoding='utf-8').read().split('\n')
    i = 0
    while i < len(righe):
        r = righe[i]
        if r.startswith('msgstr') and not dentro_intestazione:
            # si salta il msgstr vuoto del modello, incluse le continuazioni
            i += 1
            while i < len(righe) and righe[i].startswith('"'):
                i += 1
            scarica()
            continue
        # L'intestazione e' il primo blocco, quello con msgid vuoto: il suo
        # msgstr contiene i metadati e va conservato intero. Si esce
        # dall'intestazione al primo msgid con del testo dentro, non al primo
        # msgid in assoluto — che e' proprio quello dell'intestazione.
        if r.startswith('msgid ') and dentro_intestazione and r != 'msgid ""':
            dentro_intestazione = False
        if r.startswith('msgid '):
            msgid = json.loads(r[6:])
            chiave = 'msgid'
        elif r.startswith('msgid_plural '):
            plurale = json.loads(r[13:])
            chiave = 'plurale'
        elif r.startswith('"') and chiave == 'msgid':
            msgid += json.loads(r)
        elif r.startswith('"') and chiave == 'plurale':
            plurale += json.loads(r)
        elif not r.startswith('"'):
            chiave = None
        fuori.append(r)
        i += 1
    scarica()

    testo = '\n'.join(fuori)
    testo = testo.replace('"Language: \\n"', '"Language: %s\\n"' % lingua)
    if '"Language:' not in testo:
        testo = testo.replace('"MIME-Version: 1.0\\n"', '"Language: %s\\n"\n"MIME-Version: 1.0\\n"' % lingua)
    testo = re.sub(r'"Plural-Forms:[^"]*"', '"Plural-Forms: %s\\\\n"' % plurali, testo)
    if 'Plural-Forms:' not in testo:
        testo = testo.replace('"Language: %s\\n"' % lingua,
                              '"Language: %s\\n"\n"Plural-Forms: %s\\n"' % (lingua, plurali))
    testo = testo.replace('"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"',
                          '"Last-Translator: CodyCloud Srls <it@codycloud.it>\\n"')
    testo = testo.replace('"Language-Team: LANGUAGE <LL@li.org>\\n"',
                          '"Language-Team: %s\\n"' % nome)
    open(uscita, 'w', encoding='utf-8').write(testo)
    return testo


if __name__ == '__main__':
    lingua, sorgente, uscita = sys.argv[1], sys.argv[2], sys.argv[3]
    if sorgente == 'IDENTICA':
        # L'italiano e' la lingua sorgente: la traduzione e' l'originale, e si
        # legge dal modello stesso. Nessun file intermedio da tenere in giro.
        trad = {}
        msgid = plurale = None
        chiave = None
        for r in open('storegentic.pot', encoding='utf-8'):
            r = r.rstrip('\n')
            if r.startswith('msgid '):
                msgid, chiave, plurale = json.loads(r[6:]), 'msgid', None
            elif r.startswith('msgid_plural '):
                plurale, chiave = json.loads(r[13:]), 'plurale'
            elif r.startswith('"') and chiave == 'msgid':
                msgid += json.loads(r)
            elif r.startswith('"') and chiave == 'plurale':
                plurale += json.loads(r)
            elif r.startswith('msgstr'):
                if msgid:
                    trad[msgid] = {'msgstr': msgid, 'msgstr_plurale': plurale or msgid}
                chiave = None
    else:
        trad = {t['msgid']: t for t in json.load(open(sorgente, encoding='utf-8'))}
    monta('storegentic.pot', trad, lingua, uscita)
    tot = sum(1 for r in open(uscita, encoding='utf-8') if r.startswith('msgstr') and not r.startswith('msgstr ""'))
    print('%s: %d stringhe tradotte' % (uscita, tot))
