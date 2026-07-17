# Couplands Listing System

WordPress plugin (v2.8.7) for Couplands — a caravan/motorhome/campervan dealership. Provides custom post types, AJAX filtering with faceted search, image galleries, shortcodes, and an admin settings UI.

**Single file:** `couplands-listing-system.php` (all 4350 lines — no build step, no package.json, no tests).

## External dependencies (not bundled)

| Dependency | Purpose | How used |
|---|---|---|
| ACF (Advanced Custom Fields) | Field data | `get_field()`, `acf_get_fields()` — plugin falls back to `get_post_meta` if not active |
| Elementor | Listing card templates | `do_shortcode('[elementor-template id="..."]')` |
| jQuery | All JS | Loaded via WordPress core (`wp_enqueue_scripts`) |
| Swiper.js | Image slider | Loaded from CDN inside `render_listing_gallery()` |
| FancyBox v5 | Lightbox | Loaded from CDN inside `render_listing_gallery()` |
| WooCommerce | Product listings | Optional — checked before use |

## Architecture

Two classes instantiated at the bottom:

```
new Listing_System();
  └── new Listing_Registrar()  (constructed inside Listing_System::__construct)
```

**`Listing_Registrar`** — registers CPTs and taxonomies on `init`.

**`Listing_System`** — everything else: shortcodes, AJAX, admin menu, settings, filter logic.

### Custom post types
`caravan`, `motorhome`, `campervan` — all have archive disabled (`has_archive: false`). Archive pages are standard WP pages mapped in settings.

### Taxonomies
- `listing-make-model` — hierarchical; top-level = Make, children = Models
- `listing-location` — locations with ACF fields (phone, email, etc.)
- `listing-category` — used to gate filter results to posts with the "Listing" term (auto-inserted on activation)

## Shortcodes

| Shortcode | Method | Notes |
|---|---|---|
| `[caravan_filter]` | `render_caravan_filter()` | Main filter sidebar/modal. Detects post type from page mapping. Outputs JS. |
| `[listing_selection]` | `render_listing_selection()` | Vehicle type + New/Used radio form |
| `[listing_sorting]` | `render_listing_sorting()` | Sort dropdown (price asc/desc, title asc/desc) |
| `[listing_gallery is_archive="1"]` | `render_listing_gallery()` | Swiper + FancyBox gallery; archive mode shows 4 images |
| `[listing_feature key="interior_features"]` | `render_listing_feature()` | Comma-separated meta → `<ul>` |
| `[listing_meta_fields]` | `render_listing_meta_fields()` | 3-col grid of ACF fields (config from admin) |
| `[listing_filter_mobile]` | `render_listing_filter_mobile()` | Trigger button for mobile filter drawer |
| `[pricing]` | `render_pricing()` | Price + monthly finance (8.5% APR, 20% deposit, 120 months) |
| `[is_sale]` | `render_is_sale()` | "Sale!" badge when `price < rrp` |
| `[location_details taxonomy="..." meta_keys="..." type="tel\|email\|term_name"]` | `render_location_details()` | ACF fields from taxonomy terms |
| `[listing_model_grid]` | `render_listing_model_grid()` | Grid of models filtered by page filter builder conditions |
| `[manufacturer_search year="2026"]` | `render_manufacturer_search()` | Manufacturer cards with per-type listing counts |
| `[view_model_url]` | `render_view_model_url()` | URL to parent archive page with model + page filters appended |
| `[current_term_image field="..."]` | `render_current_term_image_shortcode()` | ACF image from current taxonomy archive term |

## AJAX

Action: `filter_caravans` (both logged-in and public).  
Handler: `ajax_filter_caravans()`.  
Response JSON: `{ html, facets, max_pages, total_posts }`.

The JS result container is `#my-loop-grid-container` (must exist in the Elementor template on the archive page).

### Filter query flow

1. Load per-type filter config from `couplands_filters_{post_type}` option.
2. Always AND-restrict to `listing-category = "Listing"` (index 0 of tax_query — never relaxed).
3. Apply `condition` hidden field (New/Used).
4. Apply sort (`sort_by` POST param; default: price ASC by `meta_value_num`).
5. Loop config and add taxonomy / meta clauses from POST data.
6. `get_args_with_guaranteed_results()` — if zero results, progressively drop meta then secondary taxonomy clauses. Never drops the "Listing" category or the selected Make/Model.
7. Compute facets via `build_facet_args()` — each field's availability is computed with ALL other active filters applied but that field's own selection removed. This lets users switch between values of the same filter without first resetting it.
8. Paginate: 12 per page, infinite scroll via `IntersectionObserver` on a sentinel below the grid (`paged` POST param). Facets only computed on page 1.

### Make/Model special handling
- Uses `slug` (not `term_id`) for both make and model in filter queries and URL params.
- Facet for makes: drop entire `listing-make-model` constraint, collect root-level slugs from matching posts.
- Facet for models: keep selected make, drop specific model — so all models of the chosen make stay visible.

### URL sync
`syncUrl()` uses `history.replaceState` to keep the filter state in the query string (shareable/reloadable). Called on filter change and sort change, but NOT on initial page load or infinite-scroll paging. `condition` (hidden field) and `post_type` are excluded from the URL. The default condition is not written to the URL on initial load.

### Filter option visibility
Unavailable options are **completely removed from the DOM** (not disabled):
- `<select>`: options are cached on the element (`data('cls-all-options')`) and the DOM is rebuilt to contain only valid + currently-selected values.
- Checkboxes/radios: the parent `<label>` is hidden via `.toggle(isValid)`.

## Elementor template IDs (hardcoded)

| ID | Usage |
|---|---|
| 549 | Listing card for caravan/motorhome/campervan |
| 1049 | Listing card for products |
| 662 | Ad slot injected after the 3rd listing card |
| 734 | Injected inside single listing gallery |

## Options / meta keys

| Key | Type | Purpose |
|---|---|---|
| `couplands_filters_{type}` | option | Filter config array per post type (caravan/motorhome/campervan/product) |
| `couplands_archive_page_{type}` | option | WP page ID used as archive for that post type |
| `couplands_child_template_{type}` | option | Elementor template ID for direct children of archive page |
| `couplands_model_grid_item_template` | option | Elementor template ID for model grid items |
| `couplands_placeholder_image_{type}` | option | Attachment ID of placeholder image per post type |
| `couplands_meta_fields_config` | option | Ordered array of ACF fields for `[listing_meta_fields]` display |
| `_listing_gallery_ids` | post meta | Comma-separated attachment IDs |
| `_couplands_page_filters` | post meta | Per-page filter conditions for `[listing_model_grid]` |

ACF field groups pulled for meta fields admin tab: IDs `541`, `2642`, `2443`.

## Admin UI

**Menu:** "Listings" (custom top-level menu, `dashicons-list-view`, position 6). Submenus: Caravans, Motorhomes, Campervans, Locations, Makes & Models, Categories, Settings. CPT default menu items are removed.

**Settings page** (`couplands-filter-settings`), tabs:
- **General** — archive page mapping, child page templates, model grid template, placeholder images
- **Caravan/Motorhome/Campervan/Product Filters** — drag-sortable rows configuring which meta/taxonomy fields appear as filter inputs, their label, input type (select/checkbox/radio), default label, and URL param name
- **Meta Fields Display** — drag-sortable ACF field list with visibility, custom label, and price-formatting toggles

**Page Filter Builder meta box** — on every Page; defines conditions (`_couplands_page_filters`) used by `[listing_model_grid]` to determine which model terms to show and what query args to apply.

## Finance calculation

`render_pricing()` uses UK EAR (Effective Annual Rate):
- `monthly_rate = (1 + APR)^(1/12) - 1` where APR = 0.085
- 20% deposit, 120-month term
- Standard amortized formula: `P * (r * (1+r)^n) / ((1+r)^n - 1)`

## Mobile filter modal

The `[caravan_filter]` container doubles as a slide-in drawer on ≤1024px viewports. It is shown/hidden by toggling `.is-active` on `#cls-filter-modal-wrapper`. The `[listing_filter_mobile]` shortcode renders the trigger button. Close triggers have class `cls-filter-modal-close-trigger`.
