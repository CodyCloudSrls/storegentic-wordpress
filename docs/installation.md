# Installation

## Before you start

| Item | Minimum |
|---|---|
| WordPress | 6.4 |
| PHP | 8.0 |
| WooCommerce | 8.0, and it is optional |

You also need a site key from Storegentic.

## 1. Put the plugin on the site

### With the release package

Get `storegentic-<version>.zip` from the [releases page](../../releases).

Check that the package is complete:

```
shasum -a 256 -c storegentic-<version>.zip.sha256
```

Then open **Plugins > Add New > Upload Plugin** in WordPress. Select the file
and activate the plugin.

With WP-CLI:

```
wp plugin install storegentic-<version>.zip --activate
```

### With git

```
git clone git@github.com:CodyCloudSrls/storegentic-wordpress.git \
  wp-content/plugins/storegentic
wp plugin activate storegentic
```

The directory must have the name `storegentic`.

The plugin needs no build step. It has no Composer dependency and no npm
dependency. The compiled catalogues (`.mo` and `.l10n.php`) are in the
repository on purpose. A plugin without them installs in Italian, in each
language.

## 2. Connect the site

1. Open **Storegentic > Connection**.
2. Put the key in the **Key** field. The panel shows the key with a mask.
   Leave the field empty to keep the key that is there.
3. Save.
4. Select **Check the connection**.

The page then shows the functions and the addresses that the service
declares. If something is not there, it is not on the service.

The plugin tries a new base address before it saves it. If the address does
not answer, the plugin keeps the address that is there and tells you. That
field is the only field that can disconnect a live shop.

## 3. Send the catalogue

Open **Storegentic > Catalogue** and select **Sync now**.

The sync has two phases. First the plugin sends the catalogue, one page at a
time. Then, and only if each page passed, the plugin closes the session and
the service prunes the index. See [architecture.md](architecture.md).

The plugin sends one page for each cron run. For this reason, a large
catalogue needs more than one run. The cron of WordPress starts with a page
view. On a site with traffic, the pages follow each other quickly.

## 4. Turn it on

Open **Storegentic > Connection** and select **Show the Storegentic search to
the visitors**. Save.

## Two things to know

### The service does not declare all the addresses

Without `catalogReconcile`, the plugin never prunes the index. A product that
leaves the shop stays in the results. [contract.md](contract.md) gives the
filter that puts the address back.

### The counters of the plan stop more than they declare

The panel shows the plan and the consumption. Do not trust those numbers
alone. Measurement of 2026-08-20: the `search` counter was at zero. The
service then answered `429` also for the assistant and for the upload. The
contract declared those two quotas as available.

## After the installation

| Page | Question that it answers |
|---|---|
| Overview | Does everything operate? |
| Connection | Which service, and which key? |
| Appearance | Which shape and which colours? |
| Search | How does it search? |
| Catalogue, or Content | What does it know? |
| Statistics | What do the customers search? |

The dashboard also gets a box. The box shows the alarms first and the numbers
after. It makes no network call.

## Remove the plugin

Deactivation stops the scheduled tasks. It keeps the settings.

Deletion removes the options, the cache and the scheduled tasks. It does not
touch the catalogue of WooCommerce. It also does not delete the index on the
service: that data belongs to the customer. Delete it from the console of
Storegentic.
