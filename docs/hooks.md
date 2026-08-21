# Hooks

The plugin gives these filters. Each one has its documentation block in the
source file.

## Connection

### `storegentic_endpoint`

Gives an address that the contract does not declare.

```php
apply_filters( 'storegentic_endpoint', string $trovato, string $nome, array $contratto ): string
```

`$trovato` is the address from the contract, or an empty string. The contract
always wins: the filter gets an empty string only when the address is not
there. See [contract.md](contract.md).

**File**: `src/Api/Contratto.php`

### `storegentic_permesso`

Sets the capability that a user needs for the panel.

```php
apply_filters( 'storegentic_permesso', string $permesso ): string
```

The default is `manage_woocommerce` on a shop, and `manage_options` on a site
with no shop.

**File**: `src/Negozio.php`

## Privacy

### `storegentic_consenso_statistiche`

Stops the cookie of the funnel.

```php
apply_filters( 'storegentic_consenso_statistiche', bool $consentito ): bool
```

Return `false` to stop the plugin from writing the cookie. If the site has the
WP Consent API, the plugin asks that API and does not use this filter.

**File**: `src/Analitica/Sessione.php`

### `storegentic_indirizzo_client`

Gives the true IP address behind a trusted proxy.

```php
apply_filters( 'storegentic_indirizzo_client', string $indirizzo ): string
```

The plugin uses `REMOTE_ADDR`, because a client cannot select that value.
Forwarded headers come from the client. If you use this filter, you must make
sure that the request comes from your proxy.

**File**: `src/Frontend/Ponte.php`

## Interface

### `storegentic_esempi_ricerca`

Sets the examples in the search panel.

```php
apply_filters( 'storegentic_esempi_ricerca', array $esempi ): array
```

### `storegentic_esempi_assistente`

Sets the suggested questions for the assistant.

```php
apply_filters( 'storegentic_esempi_assistente', array $esempi ): array
```

The two lists are different on purpose. The search suggests things to search.
The assistant suggests things to ask. A question in the search box gives zero
results.

**File**: `src/Frontend/Interfaccia.php`

### `storegentic_inneschi_ricerca`

Sets the CSS selectors of the theme search controls to intercept.

```php
apply_filters( 'storegentic_inneschi_ricerca', array $selettori ): array
```

The defaults are the common shapes: `form[role="search"]`, `form.search-form`,
`form.woocommerce-product-search`. A universal plugin cannot know the markup
of each theme.

**File**: `src/Frontend/Interfaccia.php`

### `storegentic_fetta_ricerca`

Sets the word in the address of the results page. The default is `deepsearch`.

```php
apply_filters( 'storegentic_fetta_ricerca', string $fetta ): string
```

**File**: `src/Frontend/Pagina.php`

## Colours

### `storegentic_palette_preparate`

Adds a ready palette.

```php
apply_filters( 'storegentic_palette_preparate', array $preparate ): array
```

### `storegentic_colori`

Changes the seven colours in the code.

```php
apply_filters( 'storegentic_colori', array $colori, string $scelta ): array
```

Each palette must keep a contrast ratio of 4.5:1 or more for the text. The
test `collaudo/palette.php` measures the ready palettes at each run.

**File**: `src/Frontend/Palette.php`

## Catalogue

### `storegentic_prodotto`

Adds fields to a product before the plugin sends it.

```php
apply_filters( 'storegentic_prodotto', array $voce, WC_Product $prodotto ): array
```

**File**: `src/Catalogo/Mappatore.php`

### `storegentic_contenuto`

The same, for a page or a post.

```php
apply_filters( 'storegentic_contenuto', array $voce, WP_Post $post ): array
```

**File**: `src/Catalogo/Contenuti.php`

### `storegentic_prodotti_da_sincronizzare`

Removes products from the sync.

```php
apply_filters( 'storegentic_prodotti_da_sincronizzare', array $ids ): array
```

**File**: `src/Catalogo/Sincronizzazione.php`

### `storegentic_contenuti_da_sincronizzare`

The same, for content.

```php
apply_filters( 'storegentic_contenuti_da_sincronizzare', array $ids ): array
```

**File**: `src/Catalogo/Contenuti.php`

## Shortcode

### `[storegentic]`

Puts a button in the content. The button opens the same window as the
floating button.

| Attribute | Values | Default |
|---|---|---|
| `etichetta` | Any text | The label of the launcher |
| `modo` | `cerca`, `foto`, `chat` | The first available mode |

You can also put the attribute `data-storegentic` on any element of the
theme. The plugin makes that element open the window.

## CSS variables

A theme can set the variables again on `:root`. Each variable has a fallback
in `assets/css/storegentic.css`. The plugin writes no CSS rule in the page:
it writes only variables.

| Variable | What it sets |
|---|---|
| `--sg-fondo`, `--sg-carta` | The background, and the surface of the cards |
| `--sg-inchiostro`, `--sg-inchiostro-2` | The text, and the quiet text |
| `--sg-linea` | The thin lines |
| `--sg-colore`, `--sg-testo` | The accent, and the text on the accent |
| `--sg-raggio` | The corner radius of the controls |
| `--sg-finestra-larga`, `--sg-finestra-alta` | The size of the window |
| `--sg-scheda-minima` | The narrowest card. It sets the number of columns. |
| `--sg-spazio` | The density factor |
| `--sg-velo`, `--sg-velo-quanto` | The overlay, and how much of it the page shows |
| `--sg-movimento` | The animation time. `0s` stops the animation. |
| `--sg-appiccicato` | The top offset of the sticky filter column. Use it when the theme has a fixed header. |
