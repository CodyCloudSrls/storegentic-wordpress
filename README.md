# Storegentic for WordPress

Storegentic gives a WordPress site three ways to find things: text search,
image search, and an assistant. The plugin operates with WooCommerce and
without it.

| | |
|---|---|
| Version | 0.3.0 |
| Needs | WordPress 6.4 or later, PHP 8.0 or later |
| Operates with | WooCommerce 8.0 or later (optional) |
| Licence | AGPL-3.0-or-later |
| Author | CodyCloud Srls |
| Service | [storegentic.eu](https://storegentic.eu) |

## What the plugin does

With WooCommerce, the plugin sends the products to the service. Each result
is a card with a price and a stock status.

Without WooCommerce, the plugin sends the pages and the posts that you select.
The site becomes a knowledge base. The assistant answers questions about the
content of the site.

The plugin does not change the theme. Each CSS class starts with `sg-`. Each
style value comes from a CSS variable with a fallback. A theme can set the
variables again. The theme does not have to fight the CSS of the plugin.

## The rule of the design

The plugin knows one address: the handshake address. At each handshake, the
server declares a contract. The contract has these parts:

- `endpoints` — the addresses to use
- `capabilities` — the functions that are on for this site
- `plan` and `usage` — the limits and the consumption
- `search`, `agentChat` and `ingestion` — the parameters

The plugin reads the contract and adapts to it. The server can move an
address, stop a function, or change a limit. The sites follow at the next
handshake. You do not have to update the plugin on each site.

There is one result of this rule. **If the contract does not declare a
capability, the plugin does not show the command.** A button that answers
"not authorized" is worse than a button that is not there.

[docs/contract.md](docs/contract.md) tells more about the contract.

## Install

### From the release package

1. Get `storegentic-<version>.zip` from the
   [releases page](../../releases).
2. Make sure that the package is complete:

   ```
   shasum -a 256 -c storegentic-<version>.zip.sha256
   ```

3. In WordPress, open **Plugins > Add New > Upload Plugin**. Select the file.
4. Activate the plugin.

With WP-CLI:

```
wp plugin install storegentic-<version>.zip --activate
```

### From this repository

Clone the repository into the plugin directory of the site:

```
git clone git@github.com:CodyCloudSrls/storegentic-wordpress.git \
  wp-content/plugins/storegentic
```

The plugin needs no build step. There is no Composer dependency and no npm
dependency.

## Configure

1. Open **Storegentic > Connection** in the WordPress menu.
2. Put the site key in the **Key** field. Save.
3. Select **Check the connection**. The panel shows the contract of the
   service.
4. Open **Storegentic > Catalogue** and start a sync.

[docs/installation.md](docs/installation.md) gives the full procedure. It also
tells what to do when the service does not declare all the addresses.

## Repository layout

```
storegentic.php        The plugin file: the metadata and the autoloader
uninstall.php          What the plugin removes when you delete it
src/                   The classes, one directory for each area
  Api/                 The contract, the transport, the plan, the parameters
  Catalogo/            The sync, and the map from a product to a document
  Frontend/            The window, the search, the results page, the cards
  Admin/               The menu, the panel pages, the dashboard box
  Analitica/           The event queue, the session, the funnel, the counts
assets/                One CSS file and one JavaScript file for each context
languages/             The .pot template, and one catalogue for each language
collaudo/              The tests. See docs/testing.md
bin/                   The build script and the test script
docs/                  This documentation
```

The plugin files are at the root of the repository. You can clone the
repository directly into `wp-content/plugins/`. The build script keeps the
development files out of the release package.

## Documentation

| Document | Question that it answers |
|---|---|
| [docs/architecture.md](docs/architecture.md) | How is the plugin built, and why? |
| [docs/contract.md](docs/contract.md) | What does the service declare? |
| [docs/hooks.md](docs/hooks.md) | Which filters can I use? |
| [docs/installation.md](docs/installation.md) | How do I put it on a site? |
| [docs/testing.md](docs/testing.md) | How do I run the tests? |
| [docs/translations.md](docs/translations.md) | How do I change or add a language? |
| [docs/repository-standard.md](docs/repository-standard.md) | How is a connector repository built? |
| [CONTRIBUTING.md](CONTRIBUTING.md) | What are the rules for the code? |
| [SECURITY.md](SECURITY.md) | How do I report a security problem? |
| [CHANGELOG.md](CHANGELOG.md) | What changed in each version? |

## Tests

The tests run on a true WordPress installation with WP-CLI:

```
bin/run-tests.sh
```

Each test keeps the data that it changes. Each test puts the data back at the
end. See [docs/testing.md](docs/testing.md).

## Build a release package

```
bin/build-zip.sh
```

The script makes `dist/storegentic-<version>.zip` and a `.sha256` file. It
reads the version from `storegentic.php`. It keeps out the tests, the
translation tools, and the documentation.

## The language of the source code

The identifiers and the comments in `src/` are in Italian. This is on
purpose, and it does not change. The comments hold the reason for each
decision: which defect caused which line. A translation of 14000 lines would
lose that value and would add no function.

The documentation in `docs/` is in English. The user interface is in Italian,
English, German, French and Spanish.

## Licence

AGPL-3.0-or-later. See [LICENSE](LICENSE).

Section 13 of the licence covers the network case. A person can change this
plugin and let other people use it over a network. That person must give
those people the source of the change.
