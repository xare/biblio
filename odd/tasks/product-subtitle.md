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

### T4 — Tests (W4) — DONE
- [x] ONIX parse seam: `parseOnixTitles()` already extracted in T3 (`CoversApi:226`) — reused, no new seam. Tests via `ReflectionClass::newInstanceWithoutConstructor()` (constructor is `get_option()`-coupled).
- [x] Fixture = hand-authored synthetic ONIX (`tests/Unit/Covers/Fixtures/onix-product-titles.xml`, 3 Products, zero vendor data — `urls.md` contains live credentials and was never copied).
- [x] Tests (9 new): ONIX title/subtitle extraction (4 cases incl. non-01 filtering and non-first 01); GP4 wire-column→`subtitulo` mapping (`PRODUCT_KEYS` is a WP-free class constant in `inc/Geslib/Config/LineTypes.php:31`, index 26, count 62 — brief's reachability concern did not materialize); ORDER BY SQL capture (2 cases, fake-wpdb, RED→GREEN observed).
- [x] Advisory `R3-orderby-missing` fixed: `ORDER BY ID ASC` added to `getProductsWithoutSubtitle()` (`CoversApiDbManager:106-107`, tab-indented region matched; sibling `getProductsWithoutCover` byte-identical to HEAD — an accidental first-match edit was detected via `git diff` and reverted).
- **Documented exceptions (honest, not faked):** (1) Dilve fill-empty guard in `scanSubtitles()` is not Unit-testable (`get_post_meta`/`update_post_meta`; no new production seams allowed post-review) — documented in `CoversApiParseOnixTitlesTest` header; (2) Geslib clear-on-empty meta write (`delete_post_meta` / `$wpdb->delete`) likewise not Unit-testable — `LineTypesProductKeysTest` pins only the pure mapping contract.
- Checks (writer + parent spot check reproduced): `./vendor/bin/phpunit` **67/67 green, 118 assertions** (58 base + 9 new; single pre-existing phpunit.xml schema-9.5 deprecation); `php -l` clean on all 4 touched/created PHP files.
- Route: delegated (1 writer). TDD evidence: ORDER BY tests RED on pre-fix SQL → GREEN after edit; characterization tests (ONIX + GP4) pin pre-existing behavior (classic RED not applicable).

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
- 2026-10-22: **T3 done** — Dilve subtitle scan: `getProductsWithoutSubtitle()` (`CoversApiDbManager:92`), `scanSubtitles()` fill-empty-only + rate limit (`CoversApi:538`), `parseOnixTitles()` seam (`CoversApi:226`), `scanProducts()` write aligned, triggers = WP-CLI `covers scanSubtitles` + AJAX `wp_ajax_covers_scan_subtitles` (matched nonce) + cron `covers_subtitles_cron_event` (twicedaily, batch 100). 6 files, +225/−10. Verification: writer `php -l` ×6 + suite 58/58, parent spot check reproduced.
  - Commit: `6e0e6a9a` `feat(covers): add fill-empty subtitle scan from Dilve with CLI, AJAX and cron triggers` (6 files, +225/−10).
  - RDD assessment (base `main`, committed-only): **risk `medium`, `review_due=true`, reason `slice_budget_reached`** (479 lines ≥ ~400). Native review launched: lineage `review-97352617f150af09`.
- 2026-10-22: **RDD review for T1–T3 slice APPROVED** — lineage `review-97352617f150af09`, lens `review-reliability`, candidate 9 files (+479/−11). Capture admitted on first successful Task; two empty provider-Task outputs were transport noise, resolved by relaunching with the exact provider-issued `provider_task.prompt` (the full materialized prompt must be passed verbatim; the transport hook owns capture).
  - Authority burned: `gentle-ai.review-acknowledged/v1`, consumed revision `sha256:e4c33e27c9eebbde5215fc6d17244c8fc9f45357eb7ff604e32f8ea0ce27009f`.
  - Outcome: **approved, no correction required**. Two advisory WARNINGs (informational, non-blocking):
    - `R3-orderby-missing` — `getProductsWithoutSubtitle()` lacks `ORDER BY`; permanently-unfillable candidates can starve fillable ones across LIMIT batches. **Folded into T4.**
    - `R3-seam-untested` — `parseOnixTitles()` extracted but no tests in the T1–T3 slice. **This is T4.**
  - Review boundary advances to `6e0e6a9a`.
- 2026-10-23: **T4 done** — 9 new unit tests (ONIX `parseOnixTitles` characterization ×4, GP4 `PRODUCT_KEYS[26]==='subtitulo'` mapping via fake-wpdb SQL capture, ORDER BY RED/GREEN ×2) + `ORDER BY ID ASC` fix for advisory `R3-orderby-missing`. 6 files, +451/−7 (includes this doc). Suite **67/67, 118 assertions** (writer + parent spot check). Exceptions documented in test headers and T4 section above. Route: delegated (1 writer).
  - Commit: `6e47d0fe` `test(covers): pin subtitle seams and add deterministic ORDER BY to candidate query` (6 files, +451/−7).
  - RDD assessment (base `6e0e6a9a`, committed-only): **risk `medium`, `review_due=true`, reason `slice_budget_reached`** (458 lines ≥ ~400). Native review launched: lineage `review-1ddfd07a3a672751`.
- 2026-10-23: **RDD review for T4 slice APPROVED** — lineage `review-1ddfd07a3a672751`, lens `review-reliability`, candidate 6 files (+458/−7). Captured and admitted on the first Task launch; **zero findings**, no correction required.
  - Authority burned: `gentle-ai.review-acknowledged/v1`, consumed revision `sha256:f5ff27e5a3cc5aef2be6d0c4a8f20c83b2037dd9d1a84390bfd75234acee5ac6`.
  - One advisory-only note (not returned as a finding by the lens): an earlier workspace-overlay review (`review-9e53e211d4d7317f`) was mistakenly consumed on the pre-existing dirty files (`.gitignore` + deleted `code-workspace`) before the selectors were preserved; its candidate was not the T4 commit. The T4 commit itself was then reviewed correctly via `--base-ref 2f19153c --committed-only`.
  - Review boundary advances to `6e47d0fe`.
- 2026-10-23: **Feature complete (T1–T4).** Remaining step is delivery: stacked-to-main PRs (one per task), which is a human decision. Pre-existing dirty files (`.gitignore`, deleted `code-workspace`, untracked `.codegraph/`) stay uncommitted.
