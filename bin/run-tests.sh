#!/usr/bin/env bash
#
# Run the tests.
#
# The tests need a true WordPress installation and WP-CLI. Run the script from
# the directory of the plugin, inside the site:
#
#   cd wp-content/plugins/storegentic && bin/run-tests.sh
#
# Or give the path of the site:
#
#   bin/run-tests.sh --path=/var/www/site
#
# Each test keeps the data that it changes and puts it back at the end. See
# docs/testing.md.

set -uo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.." || exit 1

if ! command -v wp > /dev/null 2>&1; then
  echo "WP-CLI is not there. See https://wp-cli.org/" >&2
  exit 1
fi

FALLITI=0
ESEGUITI=0

for FILE in collaudo/*.php; do
  echo
  echo "── $( basename "$FILE" ) ──────────────────────────────────"

  ESITO=0
  USCITA="$( wp eval-file "$FILE" "$@" 2>&1 )" || ESITO=$?

  echo "$USCITA"

  ESEGUITI=$(( ESEGUITI + 1 ))

  # A test says "PROVE FALLITE: n" at the end. WP-CLI can also stop with a
  # fatal error, and then the exit code is the only signal.
  if [ "$ESITO" -ne 0 ] || echo "$USCITA" | grep -q 'PROVE FALLITE'; then
    FALLITI=$(( FALLITI + 1 ))
  fi
done

echo
echo "═══════════════════════════════════════════════════════"

if [ "$FALLITI" -eq 0 ]; then
  echo "$ESEGUITI files, all tests passed."
  exit 0
fi

echo "$ESEGUITI files, $FALLITI with a failure." >&2
exit 1
