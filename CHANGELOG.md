# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.10.9] - 2026-11-24

### Added

- **Quince modules**: Quince Notifuse in the module list, with its name, description
  and icon, so it can be installed and updated from the engine.

## [0.10.8] - 2026-11-23

### Added

- **Product lists → Filters**: new setting "Values in a filter before "Show more"",
  10 by default, 0 shows all values. A longer filter group shows its first values and
  a "Show more" button (Quince theme 0.1.17).
- Smarty modifier `qfacetlimit` that gives the theme this setting. The filter panel is
  built before PrestaShop passes the module variables to templates, so the theme asks
  for the value itself.

## [0.10.7] - 2026-11-22

### Added

- **Product lists → Subcategories**: new switch "Without a thumbnail, show the category
  image", on by default. A subcategory without its own thumbnail shows its category
  image in the tile instead of an empty tile. Needs the Quince theme 0.1.14.

### Changed

- **Tools → Image sizes and formats** covers every image type of the theme, with what
  each one is used for: products, categories, brands, suppliers and stores (new:
  `stores_default`, 287 × 160). A type missing one of these shows it, and **Apply
  recommended settings** adds it. Before, an existing type got only its size, so brand
  and supplier logos could have no generated sizes. Uses the shop already added
  outside the set are kept.

## [0.10.6] - 2026-11-21

### Changed

- **Tools → Image sizes and formats**: the recommended sizes of product cards
  (`home_default`) and of the photo on the product page (`medium_default`) follow the
  page width and the number of listing columns set in Appearance. For example a page
  of 1520 px with 4 columns gives 400 and 1000 px, the default 1200 px with 4 columns
  300 and 800 px. The zoomed photo (`large_default`, 1600 px) and the small types stay
  fixed. The screen shows which width and columns the sizes come from.

## [0.10.5] - 2026-11-17

### Fixed

- The category picker (for example on **Feature rank**) opens over the save bar.
  Before, the bottom of the category list hid under it.

## [0.10.4] - 2026-11-17

### Added

- **Tools → Image sizes and formats**: the image types and file formats the Quince
  theme needs (small 200, cart 250, home 450, medium 800, large 1600, category 300,
  JPEG with WebP), next to what the shop has, and a button that sets them. The
  thumbnails are then regenerated in **Design → Image settings**.
- Installed in a shop without product photos, the engine sets these image sizes and
  formats itself.

- **Product card → Badges on the photo** has a list of badge kinds under ☰, separately
  for the tile and the row: new, on sale, discount, pack, out of stock, online only
  and condition. All are on until the shop switches one off.

### Changed

- The number of features a product card shows in the list view is set only in
  **Product card** (row, "Features"). The field on **Product lists** is gone; the
  update moves a number set there to **Product card** unless that screen already
  has one.

### Fixed

- The settings of the **Product card** screen reach the shop. The theme now reads
  them (Quince theme 0.1.12); before, the screen saved them and the cards ignored
  them.

## [0.10.3] - 2026-11-17

### Added

- The theme gets the page width (`listing.container`) next to the number of
  columns, so product cards can ask the browser for a picture as wide as the card.

## [0.10.2] - 2026-11-16

### Fixed

- Product page carousels "Products from the same category" and "Recently viewed"
  show as many products as set in **Product page → Carousels** ("How many
  products"). The core modules behind them fetch their own number of products
  first, so a lower number there (for example 4) capped the carousel whatever the
  engine said. Saving the screen now writes the number to those modules as well,
  and the update does it once for the current settings.
- **Check for updates** on the Quince modules screen downloads a fresh release list.
  The project page keeps the file in a CDN for up to 10 minutes, so a check right
  after a release still saw the old list. The message now says how many updates
  are available instead of "The release list is up to date".

### Changed

- Product lists, zone "Below the list": the element "Pagination" is called
  "Further products". The same element shows page numbers or the load-more button,
  depending on "How the customer reaches further products", so it is needed in
  both modes.

## [0.10.1] - 2026-11-16

### Fixed

- Bands with a background picked from the palette (header, top bar, menu, footer)
  follow the ink colour. On a light band the text and the hover background of the
  actions (for example "Wishlist" in the header) use the ink when it is readable
  there (contrast 4.5:1 or more); before, they stayed a fixed black whatever the
  ink. Dark bands are unchanged.

## [0.10.0] - 2026-11-16

### Added

- **Sign-in and registration** screen (Pages group): account benefits shown next to
  the sign-in form and next to the registration form, a separate list for each page.
  A benefit has an icon and a title and description in every language; rows can be
  dragged, added and removed. Until the shop saves the screen, both pages show four
  default benefits in the customer's language. Needs the Quince theme 0.1.7.
- Benefit texts are plain text: tags are removed when saving and when reading, and
  the length is limited. The icon is a name from the engine's icon library.
- New palette colour `surface-page-sunken`: the page background a shade darker (or
  lighter on a dark page). For blocks that lie directly on the page next to a card.

## [0.9.0] - 2026-11-16

### Added

- **Product page → Carousels**: four new carousels built from the Quince Cross-selling
  product lists: spare parts, spare parts for, set items and in sets. They need Quince
  Cross-selling 0.3.1 and the Quince theme 0.1.5.
- Every product page carousel (related products, cross-selling, category products,
  recently viewed and the four new ones) has its own title for each language. An empty
  field keeps the default title, which is shown in the field.
- **Quince modules** screen: every module has its own icon. 13 icons come from Lucide
  (ISC licence, listed in `THIRD-PARTY-NOTICES.md`).

### Changed

- New module logo: the letter Q in the project gradient.
- Files in `src/` stop when opened directly instead of through PrestaShop.

## [0.8.4] - 2026-11-15

### Fixed

- The homepage no longer writes `Undefined array key "bleed"` warnings to the server
  log while the shop has no saved homepage sections. The built-in sections now get the
  same complete set of settings as saved ones. The page looks the same.

## [0.8.3] - 2026-11-15

### Security

- **Links typed in the back office accept only safe addresses**: `http`, `https`,
  `mailto`, `tel` and addresses within the shop. This covers the main menu, the footer,
  social icons, home page section links and the promo bar. A `javascript:` address
  is no longer saved, so it cannot run a script for a customer who clicks it.
- **The VAT number lookup (VIES) has a limit** of 20 requests in 10 minutes per
  visitor, and the country code is checked. Before, anyone could use it without limit
  and keep PHP processes busy, and VIES could block the shop.
- **Packages are downloaded only from Quince releases on GitHub**: the address is
  parsed and checked (host, path, no `..`), redirects may go only to GitHub's download
  hosts, and a package may not exceed 50 MB. The SHA-256 check stays.
- **Icons from an uploaded icon pack are checked**: an SVG with a script, event
  attributes or external links is skipped, and the back office says how many.
- Loading more reviews returns nothing for a product that is inactive or hidden, and
  the page number is capped.
- Form limits count each visitor behind a proxy or CDN separately
  (`Tools::getRemoteAddr()`), instead of all visitors together.

## [0.8.2] - 2026-11-15

### Security

- Every back-office screen of the engine checks the employee's permissions. Before,
  any logged-in employee, whatever their profile, could open and save every Quince
  Engine screen and install or update Quince modules. The screens now belong to the
  engine's menu tab, **Quince Engine** (`AdminQuinceEngine`): grant it to a profile under
  **Advanced Parameters → Team → Permissions**. SuperAdmin needs nothing. A profile that
  had access through the hidden `AdminQuince` tab needs access to this tab now.

## [0.8.1] - 2026-11-15

### Security

- The form guard can no longer be skipped by leaving its fields out. The offer,
  specialist, product question, review, registration and checkout forms are accepted
  only as a POST that carries the guard's token; a request without it, or with the
  data in the address, is rejected before the module or PrestaShop handles it. Before,
  a script that did not send the token bypassed the honeypot, the time check, the
  rate limit and reCAPTCHA. Review, registration and checkout forms are checked this
  way only while the Quince theme is active, as only the Quince theme adds the fields.
- The rate limit counts per form name chosen by the shop, not the name sent with the
  request, so renaming the form on every request no longer resets the counter.
- Adding a product to the quote list, removing it and refreshing the list no longer
  count against the offer form's limit.

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
