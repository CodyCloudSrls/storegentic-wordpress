# Architecture

This document tells how the plugin is built, and why.

## The plugin in three sentences

The plugin knows one address of the service: the handshake address. The
server declares all the other things at each connection: which functions
exist, where they are, and how much a site can ask. The plugin adapts to the
declaration, and nobody has to update it.

This is a decision, not a shortcut. A plugin that holds its addresses in its
own code needs an update each time that the service changes. On a set of
sites that nobody updates, that update never arrives at all of them.

See `src/Api/Contratto.php`. The reason is in the file.

## The start sequence

`storegentic.php` does four things and no more:

1. It declares the metadata of the plugin.
2. It registers an autoloader. The class `Storegentic\Sub\Name` is in
   `src/Sub/Name.php`. There is no Composer, because the plugin must install
   from a package with no build step.
3. It tells WooCommerce that the plugin is compatible with the high
   performance order tables.
4. It attaches `Plugin::avvia()` to `plugins_loaded`, at priority 20.

The priority 20 is necessary. Before `plugins_loaded`, the plugin cannot know
if WooCommerce is on the site.

`src/Plugin.php` says which class attaches to which hook. It holds no other
logic. You can read it in ten seconds and know what the plugin does at the
start of WordPress.

## The classes, and the question that each one answers

| Class | Question |
|---|---|
| `Api\Contratto` | What does the service declare? |
| `Api\Client` | How does the plugin speak to the network, and what does it do when the network fails? |
| `Api\Consumi` | How much does the plan let, and how much is gone? |
| `Api\Parametri` | Which search parameters does the contract declare as adjustable? |
| `Catalogo\Sincronizzazione` | The state machine of the upload, in two phases. |
| `Catalogo\Mappatore` | A WooCommerce product, in the shape that the service wants. |
| `Catalogo\Contenuti` | The same, for a page or a post. |
| `Catalogo\Pianificatore` | When does the sync run? |
| `Frontend\Ponte` | The only door between the browser and the service. |
| `Frontend\Ricerca` | The only implementation of the search. |
| `Frontend\Ripiego` | What does the site show when the service is silent? |
| `Frontend\Citazioni` | Which products does an answer of the assistant name? |
| `Frontend\Scheda` | The markup of one result. |
| `Frontend\Palette` | Which colour is it? |
| `Frontend\Forma` | Which shape does it have? |
| `Analitica\Registratore` | The queue of the events to the service. |
| `Analitica\Sessione` | The thread from the search to the order. |
| `Analitica\Percorso` | The funnel: cart, checkout, purchase. |
| `Analitica\Misure` | The counts that stay on the site, for the panel. |
| `Negozio` | Is there a shop, or is this a normal site? |

## Six decisions, and the reason for each one

### 1. The key stays on the server

The site key can read and write the catalogue. If the JavaScript called the
service, the key would be in the source of each public page.

The browser speaks to WordPress. WordPress speaks to the service. The REST
routes are in `Frontend\Ponte`. The bridge shows the minimum: text search,
image search, a question to the assistant, one event. It does not show the
sync, because a sync is an operation of an administrator.

Each route is public, because a customer is not a logged-in user. For this
reason, each route has a rate limit for each IP address. Without the limit,
the bridge becomes a free way to consume the plan of the site.

### 2. One question, one place

The question "is WooCommerce here?" was in eleven files, in three shapes. Each
shape was true at a different moment of the load sequence. `Negozio::c_e()`
gives one answer. The callers do not have to know how it gets the answer.

The same rule applies to the search. Three doors lead to the same room: the
window, the results page, and the assistant when it names a product. All
three call `Frontend\Ricerca`. If each door called the service in its own
way, the three lists would show different prices.

### 3. The markup of a card exists one time

The results are in three places. Two of them come from the browser. The easy
way is to write the markup two times: one time in PHP, one time in
JavaScript. The two copies become different at the first change.

`Frontend\Scheda` makes the markup in PHP. The bridge sends the ready HTML
with the data. The JavaScript puts the HTML in the page and does no more. The
escape of the output is in one place.

### 4. The sync is a state machine, not a loop

The upload has two phases:

| Phase | Endpoint | What it does |
|---|---|---|
| A | `catalogUpsert` | It sends the catalogue, one page at a time. |
| B | `catalogReconcile` | It tells the server to prune what it did not see. |

Phase B removes data by design. If it starts after half of the catalogue, the
server removes the other half. The catalogue of the customer becomes empty,
and nobody sees the problem until the search gives zero results.

For this reason, phase B is a state. The plugin gets to that state only if
each page was successful. The state is in the database, not in the memory. It
stays after a timeout, a restart, and a new cron run.

| State | Meaning |
|---|---|
| `inattiva` | No sync is in progress. |
| `in_corso` | The plugin sends pages. |
| `da_chiudere` | All the pages passed. Only the reconciliation is left. |
| `fallita` | One page did not pass. The reconciliation does not start. |

The plugin does not go from `fallita` to `da_chiudere`. It starts again.

Before a true reconciliation, the plugin asks for a dry run. If the prune is
more than 30 percent of the index, the plugin stops and asks for a
confirmation. Half of a catalogue that disappears is almost always a defect
of the sync, not a decision of the shop.

### 5. The service decides which product, the shop decides what it is

Storegentic knows which products answer a question, and in which order. The
shop knows the price, the stock, the image and the name. The shop has that
data, correct to the second.

`Frontend\Risolutore` joins the two with the SKU. For this reason, the cache
holds only the SKU list and the order. It does not hold a price. A list of
SKUs does not become old in fifteen minutes. A price does.

### 6. A silent service is not an error page

The plan can end. The service can go down. The network of the host can fail.
In all three cases, the catalogue is in the database of the site.

`Frontend\Ripiego` searches the words in the titles and in the short
descriptions. It is less than a semantic search, and it does not pretend to
be one. The interface says that the results come from the shop. A word search
that looks like a semantic search makes the good function look broken.

The plugin records the error each time, also when the fallback is successful.
A fallback that operates well would hide the fault for weeks.

## The house rules

- **No address of the service in the code**, except the handshake.
- **Show only what the contract declares.** A command that answers "not
  authorized" is worse than a command that is not there.
- **A comment gives the reason, not the action.** If a line looks strange,
  the comment above it says which defect caused the strangeness.
- **Simplified Italian in the comments**: one idea for each sentence, an
  active verb, the same word for the same thing.
- **Each string goes through a translation function.** The JavaScript holds
  no text. The text comes from PHP.
