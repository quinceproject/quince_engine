# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
