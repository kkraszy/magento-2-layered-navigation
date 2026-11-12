# Kkkonrad_LayeredNavigation

Improved layered navigation for Hyvä. Category and search result pages, every theme, no template override
needed in the theme.

- **Several options per attribute:** `?color=49,50` = black **or** blue; different
  attributes still combine with AND. Option links toggle their value. Each active
  option is listed in "Now shopping by" with its own remove button.
- **Counts that stay useful:** an attribute in use counts its options as if it
  were not, so picking "Black" still offers the other colours. This costs one
  extra OpenSearch query per active attribute (`Model/Facets.php`).
- **Price slider:** two handles plus number inputs, bounded by the listing's
  real min/max price ignoring the price filter. Sends core's `price=from-to`.
- **No reloads:** filter, swatch and pager links in the sidebar fetch the page
  (full page cache applies) and swap `#maincontent .columns`. Back/forward work,
  open accordions stay open. Sorting still reloads. With JS off it is plain links.
- **Long lists:** more than 8 options get a search box and "Show more".
- **Swatches:** Hyvä's swatch template is kept; selected swatches get
  `aria-current="true"` and a ring.

No configuration. Enable/disable with `bin/magento module:enable|disable`.
Which attributes appear is core's: Stores > Attributes > Product >
"Use in Layered Navigation" / "Use in Search Results Layered Navigation".

Styles: `view/frontend/web/css/layerednav.css` (unlayered, `kk-ln-*` classes).
Themes can override `Kkkonrad_LayeredNavigation::filter.phtml` as usual.

Not included: SEO-friendly filter URLs (`/bags/black-blue.html`), robots/canonical
rules for filtered pages, per-attribute settings (single-select, display mode),
category tree, stock and rating filters.
