# Tests

The tests are in `collaudo/`. They run on a true WordPress installation with
WP-CLI. There are 163 tests.

```
bin/run-tests.sh
```

The script runs each file and stops with a non-zero exit code if a test
fails. You can also run one file:

```
wp eval-file collaudo/misure.php
```

## What each file defends

| File | Subject |
|---|---|
| `impostazioni.php` | The settings: the sanitization of each field, the keys that must not come back, the permissions. |
| `misure.php` | The counts on the site: the month arithmetic, the caps, the personal data. |
| `palette.php` | The contrast ratio of each ready palette. |
| `percorso.php` | The funnel: cart, checkout, purchase, and the attribution. |
| `sincronizzazione.php` | The state machine of the sync, and the guard on the prune. |
| `citazioni.php` | Which products an answer of the assistant names. |

Each test defends a defect that happened. The comment block at the top of
each file says which defect.

## The rules for a new test

**Write the restore code first.** `collaudo/sincronizzazione.php` one time
stopped the plugin on a live site. `collaudo/misure.php` one time wrote over
the log of a shop.

A test must keep the data that it changes and put it back at the end. It must
do this also if it stops in the middle. Use `register_shutdown_function`:

```php
$sg_prima = get_option( 'storegentic_qualcosa' );

register_shutdown_function(
    static function () use ( $sg_prima ) {
        update_option( 'storegentic_qualcosa', $sg_prima );
    }
);
```

Put the counter of the failures in `$GLOBALS`, not in a variable at the top
of the file. WP-CLI runs the file inside a function, so a variable at the top
level is not global. A test that uses `global $sg_falliti` points to a
different thing. It then says "all tests passed" with a failure on the
screen.

## Two things to correct

- `collaudo/citazioni.php` has no `register_shutdown_function`. It deletes
  its test products at the end of the file. If it stops in the middle, the
  products stay in the shop with the prefix `COLLAUDO-`.
- `collaudo/citazioni.php` needs WooCommerce and does not check for it. On a
  site with no shop it stops with a fatal error.

## What the tests do not do

The tests do not call the service. They test the code of the plugin only.
`collaudo/citazioni.php` makes its own products, uses them, and deletes them.
