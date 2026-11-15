# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.0] - 2026-11-15

### Added

- Console command `quince:install`: installs every Quince module found in `modules/`
  that the shop does not have yet, updates the ones with a newer version on disk, in
  the order of their dependencies, and refreshes the files of the Quince theme.
  `--enable-theme` also makes Quince the active theme in every shop. The Composer
  project `quinceproject/setup` runs it after every download, with `--enable-theme`
  on the first one only.

### Changed

- Installed from the console (`prestashop:module install quince_engine`), the engine
  is enabled and hooked in every shop, as when installed from the back office with
  all shops selected. The console otherwise works in the default shop only.

## [0.7.2] - 2026-11-15

### Added

- **Cart** has a second hook field: a spot in the cart drawer, under the suggested
  products, the same kind of spot as under the suggestions on the cart page. Needs
  Quince theme 0.1.3.
- The Quince modules screen lists Quince Block.

## [0.7.1] - 2026-11-13

### Fixed

- **Install** and **Update** on the Quince modules screen lock after the first click. A
  second click unpacked the same package at the same time and PrestaShop reported
  that it could not write a file.

## [0.7.0] - 2026-11-13

### Added

- **Logo after scrolling** has a fifth choice, **Separate file**: upload a PNG, JPG or WEBP
  version of the logo (up to 1 MB) and the stuck header shows it instead of the main
  logo. Needs Quince theme 0.1.2.

### Fixed

- The hint under **Logo after scrolling** says what inversion does: it reverses every
  colour, so yellow turns blue.

## [0.6.2] - 2026-11-13

### Fixed

- After **Install** or **Update** on the Quince modules screen, the screen shows the new
  version right away. On servers with OPcache it showed the old one for up to a minute,
  although the update had worked.

## [0.6.1] - 2026-11-13

### Fixed

- A fresh installation puts the **Quince project** branch in the back office menu.
  PrestaShop refused to register the branch because it has no screen of its own, so
  the entries of the engine and of the modules ended up outside it. Shops installed
  with 0.6.0 or earlier get the branch, and their entries are moved into it, when the
  engine is updated.

## [0.6.0] - 2026-11-13

### Added

- Quince modules screen: installs and updates every Quince module and the Quince theme
  from the Quince release list. The package is checked against its SHA-256 before
  PrestaShop installs it.
- The screen lists all modules on the release list, also the ones released after this
  version of the engine, with the installed and the latest version.
- **Check for updates** downloads the release list again instead of waiting a day.

- Each module shows its installed and latest version and one status: up to date,
  update available, installed but switched off, or not installed.

### Fixed

- Links to module repositories point to github.com/quinceproject.
- **Open settings** appears for every Quince module that has a screen, also the ones
  with a screen only in the PrestaShop menu.
- Module descriptions on the Quince modules screen are translated and match what the
  modules do.

## [0.5.10] - 2026-11-10

### Changed

- An empty value in a table shows a hyphen instead of a long dash.
- Subcategories in the category lists are indented with a hyphen instead of a long dash.

## [0.5.9] - 2026-11-10

### Changed

- Polish translation completed: the texts added since the previous translation are now translated.
- Texts no longer use long dashes.

### Fixed

- The Tools screen, where the icon pack is updated, opens again. Its controller was not
  registered, so the screen ended in an error.

## [0.5.8] - 2026-11-09

### Fixed

- The release package denies web access to `vendor/`. The 0.5.7 package
  shipped without `vendor/.htaccess`, so on Apache the Composer files in that folder
  could be opened from a browser. The module code is unchanged.

## [0.5.7] - 2026-11-09

First public release.

### Added

- Appearance screen: brand, ink, third and promotion colours, from which the engine
  derives the full palette, status and badge colours, surfaces, borders, shape, grid
  and typography.
- Screens for the top bar, header, main menu and footer of the Quince theme.
- Home page built from sections and columns, arranged by dragging.
- Product page, product card and product list arrangements: elements switched on and
  off, reordered, and module hooks placed where they should stand.
- Cart screen with a trust block and a hook below the suggestions.
- Checkout screen: step by step or one page, a hook under the order confirmation,
  company details fetched from the EU VIES register by VAT number, and a VAT number
  check before the address is saved.
- Second address (delivery or invoice) saved in the same step as the first one.
- Brand and Schema.org organisation data, default meta tags and an `/llms.txt` file.
- Bootstrap Icons bundled with the module, replaceable from the Tools screen.
- Form protection shared by all Quince forms: signed page marker, hidden field,
  minimum fill time and a request limit, with a hook for further checks.
- Product reviews from the PrestaShop `productcomments` module drawn in the theme,
  with "Show all reviews" loading further ones.
- Quince modules screen listing which Quince modules run, are switched off or are
  not installed.
- Back office shell shared by every Quince module: the "Quince project" menu entry,
  the sidebar and the panel components.
- Polish translation.
