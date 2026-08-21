
## Install

```
wp plugin install storegentic-@VERSIONE@.zip --activate
```

Or open **Plugins > Add New > Upload Plugin** in WordPress.

Check the package first:

```
shasum -a 256 -c storegentic-@VERSIONE@.zip.sha256
```

Then open **Storegentic > Connection**, put the site key, and sync the
catalogue. See [docs/installation.md](docs/installation.md).

## Needs

WordPress 6.4 or later, PHP 8.0 or later. WooCommerce 8.0 or later is
optional.

## Reproduce this package

```
bin/build-zip.sh v@VERSIONE@
```
