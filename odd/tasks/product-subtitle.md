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

### T2 — Geslib overwrite semantics (W2)
- [ ] `GeslibApiDbProductsManager.php:162` (single) and `:755` (batch): mirror the feed — non-empty value replaces, empty value clears (`delete_post_meta` / remove from `$metaUpdates` + explicit delete).
- [ ] Confirm `subtitulo` (wire col 26 == `LineTypes::PRODUCT_KEYS` index 26) survives `processGP4` → `build_content` JSON → both writers.
- Checks: covered by T4 unit tests.

### T3 — Dilve subtitle scan (W3)
- [ ] `CoversApiDbManager::getProductsWithoutSubtitle()`: `post_type=product`, `post_status=publish`, valid `_ean` (reuse `CoversApi::validateEAN()`), **no** `_subtitle` meta, no `_thumbnail_id`/`hasAttachment` conditions. Batching via `offset`/`limit`.
- [ ] `CoversApi::scanSubtitles($log_id, $batch_size, $offset, $stats)`: `search_dilve($ean)` → write `_subtitle` **only when empty**. Metadata-only: no `getResourceX`, no `create_cover`, no image side effects. Respect DAPI rate limits (batch + pause).
- [ ] Align the incidental write at `CoversApi.php:443-447` to the same fill-empty-only rule.
- [ ] Triggers: new WP-CLI `covers scanSubtitles`; one admin AJAX handler with correct nonce/action pair; optional `subtitles_cron_event` mirroring `covers_cron_event` (batch 100, twice daily). Register via `Init::get_services()`.
- Checks: `php -l`, suite green, manual run pending user.

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
- 2026-10-22: **T1 done** — `_subtitle` field/save/column/REST registration in `inc/Geslib/Base/GeslibProductController.php` (+55 lines). Verification: writer `php -l` + `./vendor/bin/phpunit` (58/58) reproduced by parent spot check. RDD on (global). Chain strategy: `stacked-to-main`. Commit: see next entry.
