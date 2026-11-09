# Quince Engine

**Theme engine for the Quince theme on PrestaShop 9.2.**

Quince Engine is where the Quince theme is set up from the back office: colours and
typography, the header, menu and footer, the home page, the product page, product
lists, the cart and the checkout. The shop owner arranges each page by switching
elements on and off and dragging them into order, without editing templates. The
engine is also the base every other Quince module builds on: it hosts their settings
screens and gives them shared services.

> Quince Engine is a free, open-source module (OSL-3.0). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Appearance from four colours.** The shop gives its brand, ink, third and
  promotion colours. The engine derives the shades, backgrounds, borders and text
  so that everything stays legible, and keeps status colours (confirmation, warning,
  error) and product badges (new, bestseller, discount) apart from the brand colour.
  Corners, lines, shadows, the grid and typography are set once for the whole shop.
- **Top bar, header, menu and footer.** Contact details and language and currency
  selectors above the header, an optional message bar, the logo and how the header
  behaves on scroll, the search field, the customer actions and their icons. The menu
  shows the chosen main categories with icons, opens them in the chosen shape and
  depth, and takes links of the shop's own. The footer has company details, link
  columns, social links, a bottom bar with payment methods and a back to top button.
- **Home page from sections.** The home page is a list of sections, each split into
  columns, each column with its own content. Sections are reordered by dragging.
- **Product page, product card and product lists.** Each screen mirrors the page it
  sets up. Elements are switched on and off, dragged into order, and module hooks are
  added where they should stand. Elements that belong to the theme can be switched
  off, not removed. The card comes in two kinds, a tile and a full-width row. Product
  lists take promotion slots in the grid and a choice of how customers reach further
  products.
- **Cart and checkout.** A trust block in the cart, checkout step by step or on one
  page, a hook under the order confirmation details, company details fetched from the
  EU VIES register by VAT number, and a VAT number check before an address is saved
  (the NIP check digit for Poland, the length of the number for other countries).
  A second address, for delivery or for the invoice, is saved in the same step as
  the first one.
- **Brand and Schema.** Schema.org organisation data, default meta tags for pages that
  set none of their own, and a plain-text `/llms.txt` file. Empty fields keep the
  PrestaShop behaviour.
- **Icons with no outside requests.** Bootstrap Icons are bundled with the module, so
  nothing is loaded from another server while a visitor browses. A newer release can
  be uploaded from the Tools screen.
- **Form protection.** Quince forms in the shop share one gate: a signed page marker,
  a hidden field, a minimum fill time and a request limit per address. Other modules,
  such as Quince reCAPTCHA, add their own check through a hook.
- **Product reviews in the theme.** Reviews from the PrestaShop `productcomments`
  module are drawn by the theme and appear in the page code, so search engines see
  them. "Show all reviews" loads the rest.
- **One home for Quince modules.** The "Quince project" back office menu entry, the
  sidebar, the settings screens of the other Quince modules, and a screen showing
  which of them run, are switched off or are not installed yet.
- **Multistore aware.** Settings are saved for the shop or the shop group selected in
  the back office. The product page and product list screens say which shops show
  an arrangement different from the one on the screen.
- **Multilingual.** English base with a full Polish translation, on PrestaShop's
  standard translation system.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince theme | required for the shop pages |

The engine stores the settings; the Quince theme is what draws them in the shop. With
another theme active, the settings screens still work, but the shop pages do not
follow them. The Quince theme is published separately.

**Override.** The engine installs one override,
`override/classes/checkout/CheckoutAddressesStep.php`, which saves the second address
in the same checkout step. PrestaShop refuses to install it if another module already
overrides the same method; the Module Manager then names the conflict.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_engine.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_engine/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_engine` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince Engine and click **Install**.

**With Composer** *(coming soon)*

Installing with `composer require quinceproject/engine` will work once the package is
published on Packagist.

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_engine/releases).

Install the engine before the other Quince modules. Most of them declare it as a
dependency, and PrestaShop refuses to install those without it.

## Configuration

Open **Quince project → Quince Engine** in the back office menu, or click
**Configure** next to the module in the Module Manager. The sidebar groups the
screens:

1. **Design.** Global settings: colours, shades, surfaces, borders, shape, grid and
   typography.
2. **Sections.** Top bar, header, main menu and footer.
3. **Pages.** Home page, product page, product card, product lists, cart and
   checkout.
4. **Modules.** The settings screens of the Quince modules installed in the shop.
5. **System.** The Quince modules list, Brand & Schema, and Tools with the icon pack.

## Architecture

- **Symfony controllers and Twig** for every settings screen, on routes under
  `quince/`, with the shared `qc-*` panel components in `views/css/`.
- **`ConfigStore`** writes settings for the shop or shop group in context, so a value
  saved for all shops reaches a shop that has its own row.
- **`FeatureRegistry`** and the `actionQuinceRegisterFeature` hook let other Quince
  modules announce themselves; an installed module gets its entry in the sidebar.
- **`FormGuard`** checks Quince form submissions on
  `actionFrontControllerInitAfter`; modules add checks through
  `actionQuinceFormVerify`.
- **Front controllers**: `vies` (company details by VAT number), `reviews` (further
  product reviews) and `llmstxt` (the `/llms.txt` file).
- **Settings** are stored in PrestaShop configuration under the `QUINCE_` prefix.
  The checkout variant is the core `PS_ONE_PAGE_CHECKOUT_ENABLED` setting, so it
  stays in step with the PrestaShop one.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_engine.git
cd quince_engine
composer install
```

The folder must be called `quince_engine`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_engine/` folder **with its `vendor/`
directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

Bootstrap Icons, Alpine.js and SortableJS ship with the module under their own MIT
licences, listed in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
