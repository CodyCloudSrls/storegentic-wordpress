# Translations

The user interface is in Italian, English, German, French and Spanish. The
source language is Italian: the strings in the PHP code are Italian, and
WordPress uses the original when it finds no translation.

## The files

| File | What it is |
|---|---|
| `storegentic.pot` | The template. It holds each string of the plugin, with no translation. Start here for a new language. |
| `storegentic-XX_XX.po` | The catalogue of one language. A person can read it and change it. |
| `storegentic-XX_XX.mo` | The compiled catalogue, in the old format. |
| `storegentic-XX_XX.l10n.php` | The same catalogue as a PHP array. |
| `glossario.md` | The fixed terms, for each language. |
| `stile.md` | How to write an interface string. |

From WordPress 6.5, WordPress reads the `.l10n.php` file first. That file is
faster: there is no binary file to parse, and the opcache keeps it. The `.mo`
file stays for older sites.

**Compile both formats each time.** A catalogue that is half compiled is a
defect. An empty string in a catalogue is not a defect: gettext uses the
Italian original.

## Why Italian has a `.po` file but no `.mo` file

An Italian catalogue in which each translation is the same as the original
changes no word on the screen. It also makes WordPress read 41 kilobytes at
each request for nothing.

The `.po` file stays because it is useful. It lets a person change an Italian
sentence without a change to the code. After a change, compile it:

```
wp i18n make-mo  languages/storegentic-it_IT.po languages/
wp i18n make-php languages/storegentic-it_IT.po languages/
```

## After a change to the code

Make the template again:

```
wp i18n make-pot . languages/storegentic.pot --domain=storegentic --exclude=collaudo,bin,docs
```

Update the catalogues:

```
wp i18n update-po languages/storegentic.pot languages/
```

New strings stay empty. Changed strings get the mark "fuzzy". Translate the
first group and examine the second group. Then compile both formats, for each
language:

```
for l in it_IT en_US de_DE fr_FR es_ES; do
  wp i18n make-mo  languages/storegentic-$l.po languages/
  wp i18n make-php languages/storegentic-$l.po languages/
done
```

`bin/build-zip.sh` stops if a language has no compiled catalogue. It cannot
see a catalogue that is there but out of date, so compile both formats each
time.

## The rules that you cannot break

- The placeholders stay the same: `%s`, `%d`, `%1$s`, `%2$d`, `%%`. Same
  number, and same order if they have numbers.
- The spaces at the start and at the end of a string stay the same. Many of
  them separate two words that the code joins.
- The HTML tags and the HTML entities stay the same.
- The quotation marks follow the language: Italian and Spanish use
  `«...»`, English uses `"..."`, German uses `„..."`, French uses
  `« ... »` with no-break spaces.
- A `msgid` that is an address or a proper name stays the same.

## Add a language

1. Copy `storegentic.pot` to `storegentic-XX_XX.po`.
2. Fill the header: `Language`, `Plural-Forms`.
3. Translate. Follow `glossario.md` and `stile.md`.
4. Compile both formats.
5. Add the language to the list in `readme.txt` and in `README.md`.
