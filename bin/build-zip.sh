#!/usr/bin/env bash
#
# Make the release package.
#
# The script uses `git archive`, so the package holds only the files that git
# holds, and only those that `.gitattributes` does not mark `export-ignore`.
# A stray file in the working directory cannot get into a release.
#
# Usage:
#   bin/build-zip.sh            build from HEAD
#   bin/build-zip.sh v0.3.0     build from a tag
#
# Output:
#   dist/storegentic-<version>.zip
#   dist/storegentic-<version>.zip.sha256

set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

RIF="${1:-HEAD}"

# ---------------------------------------------------------------- version

VERSIONE="$( sed -n 's/^ \* Version: *\([0-9][^ ]*\) *$/\1/p' storegentic.php | head -1 )"

if [ -z "$VERSIONE" ]; then
  echo "The Version header is not in storegentic.php." >&2
  exit 1
fi

COSTANTE="$( sed -n "s/^const VERSIONE *= *'\([^']*\)'.*/\1/p" storegentic.php | head -1 )"
STABILE="$( sed -n 's/^Stable tag: *\(.*\)$/\1/p' readme.txt | tr -d '\r' | head -1 )"

if [ "$VERSIONE" != "$COSTANTE" ]; then
  echo "The header says $VERSIONE and the constant VERSIONE says $COSTANTE." >&2
  exit 1
fi

if [ "$VERSIONE" != "$STABILE" ]; then
  echo "The header says $VERSIONE and readme.txt Stable tag says $STABILE." >&2
  exit 1
fi

# ------------------------------------------------------------ catalogues
#
# WordPress reads the .mo file and the .l10n.php file, not the .po file. A
# language that ships without them installs in Italian, and nobody sees it
# before the live site.
#
# The check asks one question only: is the compiled catalogue there? A check
# on "is it up to date" needs the modification time or the history, and both
# lie. A clone gives every file the same time, in alphabetical order, so the
# .po file always looks the most recent. See docs/translations.md for the
# rule that a person must follow.

MANCANTI=0

for PO in languages/*.po; do
  LINGUA="$( basename "$PO" .po )"

  if [ "$LINGUA" = "storegentic-it_IT" ]; then
    continue  # Italian is the source language. See docs/translations.md.
  fi

  for FORMATO in "languages/${LINGUA}.mo" "languages/${LINGUA}.l10n.php"; do
    if [ ! -f "$FORMATO" ]; then
      echo "$FORMATO is not there. Compile $PO." >&2
      MANCANTI=1
    fi
  done
done

if [ "$MANCANTI" -ne 0 ]; then
  echo "See docs/translations.md." >&2
  exit 1
fi

# ----------------------------------------------------------------- build

rm -rf dist
mkdir -p dist

PACCHETTO="dist/storegentic-${VERSIONE}.zip"

git archive --format=zip --prefix=storegentic/ --output="$PACCHETTO" "$RIF"

# `shasum` is on macOS, `sha256sum` is on most Linux distributions.
if command -v shasum > /dev/null 2>&1; then
  IMPRONTA=( shasum -a 256 )
else
  IMPRONTA=( sha256sum )
fi

( cd dist && "${IMPRONTA[@]}" "storegentic-${VERSIONE}.zip" > "storegentic-${VERSIONE}.zip.sha256" )

# ---------------------------------------------------------------- report

# Only the files. `unzip -l` counts the directory entries as well.
QUANTI="$( unzip -Z1 "$PACCHETTO" | grep -vc '/$' )"
PESO="$( du -h "$PACCHETTO" | cut -f1 )"

echo
echo "storegentic ${VERSIONE}  ($RIF)"
echo "  $PACCHETTO  —  $QUANTI files, $PESO"
sed 's/^/  /' "${PACCHETTO}.sha256"
echo

if unzip -l "$PACCHETTO" | grep -qE 'storegentic/(collaudo|docs|bin)/'; then
  echo "A development directory is in the package. Check .gitattributes." >&2
  exit 1
fi

echo "To check the package:  ${IMPRONTA[*]} -c ${PACCHETTO}.sha256"
