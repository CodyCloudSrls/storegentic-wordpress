# Changelog

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] — 2026-08-20

### Added

- A first level menu, with one page for each subject: Overview, Connection,
  Appearance, Search, Catalogue, Statistics. Before, one page held seven
  sections, and a person went past the site key to change the colour of a
  button.
- The plugin operates without WooCommerce. On a site with no shop, it sends
  the pages and the posts that you select, and it becomes a knowledge base.
- The funnel goes to the order: cart, checkout and purchase, with the
  attribution. Before, the plugin sent three events of six, and it stopped
  where the data starts to have a value.
- Four shapes for the window: centre, side, bottom, full screen. Also
  density, columns, overlay, animation and title font.
- Settings for the parameters that the contract declares: how many results,
  and the similarity threshold.
- A box on the dashboard, with the alarms first and the numbers after.
- German, French and Spanish. The interface now has five languages.

### Changed

- The licence is AGPL-3.0-or-later. Before, it was GPL-2.0-or-later. Section
  13 covers the network case. A person can change the plugin and let other
  people use it over a network. That person must give those people the source
  of the change.
- The plugin asks for the maximum number of results from the contract. Before,
  the number 50 was in the code.
- The settings drop a key that the plugin does not use. Before, four settings
  of two versions back stayed in the database for ever.
- The palette "Ink and gold" changed its accent from `#A57C3E` to `#8A6A2F`.
  White on the old colour gave 3.78:1, below the 4.5:1 that text needs. The
  new colour gives 5.02:1.

### Fixed

- The month arithmetic of the statistics. "One month before" from 31 May
  gives 1 May in PHP, because 31 April does not exist. The list of the months
  gave 05, 05, 03, 03. The calculation now starts from the first day of the
  month.
- The window does not disappear when the contract has no fingerprint. Before,
  a missing option stopped the search and the assistant on the whole site.

## [0.2.0] — 2026-08-03

### Added

- The plan and the consumption in the panel, with an alarm when a counter is
  at zero. Before, the panel said "connected" because the handshake was
  successful, and a handshake is successful also with no quota.
- The fallback to the content of the site when the service does not answer.
- Statistics on the site: what people search, what they do not find, and how
  long the service takes. The service has no address to read the events back.
- Instant search in the suggestions, while a person writes.
- One window, with the text search, the image search and the assistant. Before
  there were two separate parts, and one of them needed an element of the
  theme.

## [0.1.0] — 2026-08-01

### Added

- The first version: the handshake, the sync in two phases, the search and
  the analytics.
