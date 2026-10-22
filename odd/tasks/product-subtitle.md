# Feature: WooCommerce product subtitle (`product-subtitle`)

## Objective

Give every WooCommerce product in biblio a subtitle, sourced from the book feeds the shop already imports (Geslib) and queries (Dilve), exposed as a first-class plugin-managed WooCommerce field.

## Problem / Why

WooCommerce has no subtitle field. The plugin already receives subtitle data from two sources but neither is complete:

- Geslib `GP4` wire column 26 `Subtitulo` is parsed and written to meta `_subtitle` (`GeslibApiDbProductsManager.php:162` single, `:755` batch), but only when non-empty, so an emptied subtitle never clears the stored value, and there is no admin UI to see or edit it.
- Dilve ONIX 2.1 `<Subtitle>` (inside `<Title><TitleType>01`) is parsed by `CoversApi::search_dilve()` (`CoversApi.php:109-111`) but only written inside the cover-scan branch (`:443-447`), whose candidates come from `getProductsWithoutCover()` — products that already have a thumbnail are structurally unreachable.

## Decisions (user-owned, locked)

1. **Manual admin edit** — authoritative until the next Geslib `M` touches the product.
2. **Geslib `M` overwrites** `_subtitle`; an empty `subtitulo` on `A`/`M` **clears** the meta (currently stale values freeze forever).
3. **Dilve scan is fill-empty-only** — never overwrites an existing value (Geslib-imported or manual); keeps the scan order-independent against Geslib.
4. **Dilve gets its own scan**, decoupled from covers: candidates are published products with a valid `_ean` and no `_subtitle`, with **no** `_thumbnail_id` / `hasAttachment` condition.
5. **CEGAL is out of scope** — its fichalibro XML has no `<Subtitle>` element (value sits at fixed offsets inside the CDATA `<DETALLE>` block; fragile positional parse).

## Scope

**In**

- `T1` — custom `_subtitle` field in the WooCommerce product admin (input, save, list column, `register_post_meta`).
- `T2` — Geslib overwrite semantics (mirror the feed, clear on empty) in both writers.
- `T3` — Dilve subtitle scan (`getProductsWithoutSubtitle()` + `scanSubtitles()` + WP-CLI / admin AJAX / cron triggers), fill-empty-only.
- `T4` — tests: ONIX parse seam + GP4 fixture → meta + clear-on-empty + fill-empty guard.

**Out**

- CEGAL subtitle parsing.
- Frontend/theme display of `_subtitle` (theme's concern; meta is stored and REST-exposed).
- Delivery (push/PR/merge) — human decision.

## Constraints

- Generated artifacts (code, comments, UI copy, commits, tests) in **English**; conventional commits, no AI attribution.
- Match the indentation of each file you edit (mixed across the repo; `GeslibProductController.php` = 4 spaces).
- Security for any new endpoint/form: nonce + capability check + `sanitize_text_field()` (repo rule; do not copy the inconsistent old ones).
- DAPI limits: 1 ISBN per `getRecordsX` in this module, ≤6 req/s recommended, ≤50k records/hour/user, errors count against limits; batch and pause. Never log query URLs (they embed credentials).
- Do not reuse broken paths: WP-CLI `covers scanProducts` (calls missing `search()`), admin tab-2 buttons (nonce/action prefix mismatch).
- Tests: `./vendor/bin/phpunit` (vendor binary, Unit suite only). Unit tests never execute WP functions — no WP-dependent code is directly testable; extract pure seams instead.
- ~400 authored changed lines per work unit is a planning heuristic, not a cap.

## Authorized edit surfaces (planned — exact paths this feature may touch)

- `inc/Geslib/Base/GeslibProductController.php`
- `inc/Geslib/Api/GeslibApiDbProductsManager.php`
- `inc/Covers/Api/CoversApi.php`
- `inc/Covers/Api/CoversApiDbManager.php`
- `inc/Covers/Commands/` (new command file allowed)
- `inc/Covers/Base/` (new/updated cron or AJAX controller allowed)
- `tests/Unit/` (new test files allowed)

## Tasks

### T1 — Custom subtitle field (W1) — DONE
- [x] Add `_subtitle` input to `GeslibProductController` (hook `woocommerce_product_options_general_product_data`; subtitle is descriptive content, belongs near the title, not in the SKU panel).
- [x] Save via `woocommerce_admin_process_product_object` + `sanitize_text_field(wp_unslash(...))`; empty submitted string → `delete_meta_data('_subtitle')` (keeps T3's fill-empty candidate query consistent); absent POST key → early return, meta untouched.
- [x] Product list column via `manage_edit-product_columns` / `manage_product_posts_custom_column`, output escaped with `esc_html()` (header via `esc_html__`).
- [x] `register_post_meta('product', '_subtitle', ['show_in_rest' => true, 'type' => 'string', 'single' => true, 'sanitize_callback' => 'sanitize_text_field'])` inside the controller's `register()`.
- Checks: `php -l` clean, `./vendor/bin/phpunit` = 58/58 green (parent spot check reproduced), no new test (WP hooks are never executed in Unit tests — documented exception).
- Route: delegated (1 writer). Note: `show_in_rest` without `auth_callback` makes `_subtitle` REST-readable but not REST-writable — intentional (admin screen authoritative).
- Risk noted by writer: hook-name typo hazard (`manage_edit-product_columns` underscore) — byte-verified correct at line 27.

### T2 — Geslib overwrite semantics (W2) — DONE
- [x] `GeslibApiDbProductsManager.php:164-166` (single): empty/null `subtitulo` → `delete_post_meta($product_id, '_subtitle')` (no-op on new products, cache-aware).
- [x] `GeslibApiDbProductsManager.php:759-765` (batch): empty → `$wpdb->delete` of the `_subtitle` row before the upsert block (the `$metaUpdates` upsert has no delete capability; `_subtitle` is absent from `$meta_keys` when empty, so the upsert cannot re-insert). Note: raw `$wpdb` skips object-cache invalidation — pre-existing trait of this method, inherited.
- [x] JSON path confirmed end-to-end: wire col 26 == `PRODUCT_KEYS` index 26 (count 62) → `processGP4` `array_combine` → `build_content` JSON → `$content['subtitulo']` at `storeProduct:99` / `batchStoreProducts:711`. `GeslibApiSanitize` maps `''` → JSON `null` → `isset()` false → `''` → clear path.
- Checks: `php -l` clean, `./vendor/bin/phpunit` 58/58 (parent spot check reproduced), clear-path fixture covered by T4.
- Route: delegated (1 writer). File is **tab-indented** (AGENTS.md's "2 spaces across Geslib/" is wrong here — matched region bytes).

### T3 — Dilve subtitle scan (W3) — DONE
- [x] `CoversApiDbManager::getProductsWithoutSubtitle(int $batch_size, int $offset)` (`:92`): published products, non-empty `_ean`, NO `_subtitle` row; explicit params (no `$_POST` like its sibling); no `_thumbnail_id`/attachment conditions. Avoided the sibling's latent `$wpdb->prepare()`-with-no-placeholders `doing_it_wrong`.
- [x] `CoversApi::scanSubtitles($batch_size, $offset, &$stats)` (`:538`): fill-empty-only (re-checks `get_post_meta(...) === ''` at write time), `update_post_meta` on fill, metadata-only (no `getResourceX`/`fetch_cover`/`create_cover` — grep-verified), rate-limited via `biblio_subtitles_scan_interval_ms` filter (default 1000 ms), logs to `'subtitles'` channel, never logs credential URLs. Stats: `scanned, filled, skipped_has_subtitle, skipped_no_subtitle_in_onix, invalid_ean, failed`.
- [x] Incidental write in `scanProducts()` (`:465-472`) aligned to fill-empty-only (`subtitle=updated` / `subtitle=kept_existing`).
- [x] Pure seam `parseOnixTitles(\SimpleXMLElement): array` (`:226`) extracted from `search_dilve()` (`:105-108`), byte-identical logic, zero WP functions → T4 test target.
- [x] Triggers: WP-CLI `covers scanSubtitles` (new `CoversScanSubtitlesCommand.php`, registered in `Init.php`); admin AJAX `wp_ajax_covers_scan_subtitles` added to the `$actions` loop in `CoversScanProductsFormController` with the **matched** pair (`covers_scan_products_form`/`covers_nonce`) + `manage_options` check (broken tab-2 buttons untouched); cron event `covers_subtitles_cron_event` in `Cron.php` — core `twicedaily` (covers' own mechanism, not Geslib's custom `twohourly`), batch 100, no `covers_log` rows.
- **Divergence from the original checklist (intentional, locked at implementation):** `scanSubtitles()` has NO `$log_id` parameter — metadata-only scan writes no `covers_log`/`covers_lines` rows; cron event named `covers_subtitles_cron_event`, no longer "optional".
- Checks: `php -l` on all 6 touched files clean, `./vendor/bin/phpunit` 58/58 (parent spot check reproduced).
- Route: delegated (1 writer). Risks: candidates whose ONIX lacks a subtitle re-enter every batch until data/EAN fixed (bounded 100/run); cron may interleave with `covers_cron_event` (still ≤~2 req/s vs DAPI 6/s).

### T4 — Tests (W4)
- [ ] Extract ONIX subtitle parse into a pure method (`parseOnixSubtitle(string $xml)` or equivalent) — Unit tests never run `wp_remote_get()`.
- [ ] Fixture = the real ONIX response recorded in `urls.md` (trimmed to title/subtitle block if useful).
- [ ] Test: ONIX fixture → subtitle extracted; GP4 fixture line (62 fields + trailing `|`) → `subtitulo` → meta write path; empty-`subtitulo` `M` → cleared; Dilve scan leaves non-empty `_subtitle` untouched.
- Checks: `./vendor/bin/phpunit` all green.

## Route declaration

| Task | Route | Trigger evidence |
|---|---|---|
| T1 | delegated (1 writer) | feature spans multiple files; keeps parent context thin |
| T2 | delegated (1 writer) | 2 writers in one file + JSON path verification |
| T3 | delegated (1 writer) | multi-file (query + scan + triggers) |
| T4 | delegated (1 writer) | test authoring across seams |

## Acceptance criteria

1. `_subtitle` visible/editable in the WooCommerce product admin and listed as a column; value survives save/reload.
2. `_subtitle` exposed via REST (`show_in_rest`).
3. Geslib `M` with a subtitle overwrites any existing value; with empty `subtitulo` clears it.
4. Dilve scan fills only empty `_subtitle`, works on products that already have a thumbnail, and never downloads images.
5. Full unit suite green; `php -l` clean on every touched file.

## Delivery strategy

- Branch: `feat/product-subtitle` (branched from `main`).
- Strategy: `ask-on-risk` (default). Forecast ≈ 550 authored changed lines > 400 → asked once; **user chose `stacked-to-main`** (each task = own PR targeting `main`, independently mergeable). Do not switch strategies later.

## Progress

- 2026-10-22: feature document created; branch `feat/product-subtitle` created.
- 2026-10-22: **T1 done** — `_subtitle` field/save/column/REST registration in `inc/Geslib/Base/GeslibProductController.php` (+55 lines). Verification: writer `php -l` + `./vendor/bin/phpunit` (58/58) reproduced by parent spot check. RDD on (global). Chain strategy: `stacked-to-main`.
  - Commit: `7003c27f` `feat(geslib): add product subtitle admin field and REST meta` (2 files, +165/−1, includes this doc).
  - RDD assessment (base `main`, committed-only, untracked excluded): **risk `medium`, `review_due=false`, reason `under_budget`** — review pending in this slice until the ~400-line budget; boundary stays at `main`.
- 2026-10-22: **T2 done** — both `_subtitle` writers mirror the feed (clear-on-empty: single `delete_post_meta`, batch raw `$wpdb->delete` before the upsert). JSON path `subtitulo` (wire col 26) verified end-to-end to both writers. +9 lines. Verification: writer + parent spot check `php -l` clean, `./vendor/bin/phpunit` 58/58.
  - Commit: `86310ddd` `fix(geslib): mirror subtitle feed with clear-on-empty in both writers` (2 files, +19/−5). RDD: risk `medium`, `review_due=false`, `under_budget` (180 lines vs base `main`).
- 2026-10-22: **T3 done** — Dilve subtitle scan: `getProductsWithoutSubtitle()` (`CoversApiDbManager:92`), `scanSubtitles()` fill-empty-only + rate limit (`CoversApi:538`), `parseOnixTitles()` seam (`CoversApi:226`), `scanProducts()` write aligned, triggers = WP-CLI `covers scanSubtitles` + AJAX `wp_ajax_covers_scan_subtitles` (matched nonce) + cron `covers_subtitles_cron_event` (twicedaily, batch 100). 6 files, +225/−10. Verification: writer `php -l` ×6 + suite 58/58, parent spot check reproduced. Commit: see next entry.
