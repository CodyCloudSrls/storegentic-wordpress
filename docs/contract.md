# The contract

The plugin holds one address: `/v1/commerce/plugin/handshake`. It is in
`src/Api/Contratto.php`, and it is the only path in the code. The base
address is in `src/Impostazioni.php`.

At each handshake, the plugin sends what it is, and the server answers with a
contract.

## What the plugin sends

| Field | Value |
|---|---|
| `pluginName`, `pluginVersion` | The name and the version of the plugin. |
| `platform`, `platformVersion` | `woocommerce`, and the version of WordPress. |
| `ecommerceVersion` | The version of WooCommerce, or null. |
| `storeUrl`, `shopUrl` | The address of the site, and of the shop page. |
| `environment` | The result of `wp_get_environment_type()`. |
| `installationId` | A stable random identifier. |
| `metadata` | The locale, the currency, the PHP version, the multisite flag. |

The installation identifier does not come from the address of the site. A
site that changes its domain stays the same site.

## What the server declares

| Part | Content |
|---|---|
| `endpoints` | The address for each function. |
| `capabilities` | The functions that are on for this site. |
| `plan`, `usage` | The limits, and the consumption. |
| `rateLimits` | How often the site can call. |
| `search` | The default values and the maximum values for the search. |
| `agentChat` | The channel, the format and the parameters of the assistant. |
| `ingestion` | The batch size for the upload. |

### Names

The server can name the same thing in different ways between versions:
`catalogUpsert`, `catalog_upsert`, `catalog.upsert`. The plugin accepts all
the forms of one name.

Different names for the same thing are synonyms, not forms. The plugin holds
a short list of synonyms in `Contratto::SINONIMI`. The plugin asked for
`catalogIngest`, and the contract of one site declared `ingest`. The question
gave a "no" answer for a capability that was on.

## The cache of the contract

| Item | Time |
|---|---|
| The contract | 6 hours |
| A failed handshake | 5 minutes |

The plugin also holds a permanent copy of the last good contract. It uses the
copy only when the service is down.

There are two exceptions:

- On a `401` or a `403`, the plugin does **not** use the old copy. It deletes
  the contract. A revoked key must not keep a site in a "connected" state.
- A `2xx` answer with no `capabilities` and no `endpoints` is not a contract.
  The plugin does not put it in the cache. It would destroy the copy.

The cache holds a fingerprint of the key and of the base address. A contract
that comes from a different key does not describe this site.

The public pages read the cache only. They never start a handshake. A network
call inside the render of a page becomes a wait for the customer.

## Two addresses that the service does not declare

On 2026-08-20, `/v1/commerce/search/instant` and
`/v1/commerce/catalog/reconcile` exist, operate, and have documentation. But
the handshake does not name them.

The plugin uses only what the contract declares. Without a filter, the two
functions stay off.

This is not a small thing. Without `catalogReconcile`, **the plugin never
prunes the index**. A product that leaves the shop stays in the results, and
the link goes to a page that is not there.

Put this code in a must-use plugin, or in the `functions.php` file of the
theme:

```php
add_filter( 'storegentic_endpoint', function ( $trovato, $nome ) {
    $mancanti = array(
        'instantSearch'    => '/v1/commerce/search/instant',
        'catalogReconcile' => '/v1/commerce/catalog/reconcile',
    );

    return '' !== (string) $trovato ? $trovato : ( $mancanti[ $nome ] ?? $trovato );
}, 10, 2 );
```

The contract always wins. On the day that the service declares the two
addresses, this filter does nothing, and you can delete it.

## Do not trust the counters alone

Measurement of 2026-08-20 on one site:

- The `search` counter was at zero.
- The service answered `429` also for the assistant and also for the upload
  of the catalogue. The contract declared those two quotas as available.
- `rateLimits.resetsAt` gave a date in the past. The date did not move.

The panel shows the plan and the consumption for this reason. It also shows
what happened in reality, from `Analitica\Misure`. The two columns are not
the same thing. When you are in doubt, call a true address.
