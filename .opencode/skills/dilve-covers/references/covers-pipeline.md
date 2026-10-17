# Covers Module Code Map

Code map of `inc/Covers/` — verified against the source. File:line references are exact.

## File Index

| File | Role |
|------|------|
| `biblio.php:44-46` | calls `Init::register_services()` on every load |
| `inc/Covers/Init.php` | service registry + lazy ALTER of covers_lines |
| `inc/Covers/Api/CoversApi.php` | DAPI calls, ONIX parse, download, attach (the core) |
| `inc/Covers/Api/CoversApiDbManager.php` | product query, attachment dedup/insert, featured image, covers_url |
| `inc/Covers/Api/CoversApiDbLogManager.php` | covers_log rows (status lifecycle) |
| `inc/Covers/Api/CoversApiDbLinesManager.php` | covers_lines rows (per-ISBN attempts/errors) |
| `inc/Covers/Base/Cron.php` | the two twicedaily crons — the only real entry path |
| `inc/Covers/Pages/Dashboard.php` | admin menu + settings fields (these work) |
| `inc/Base/Activate.php:35-61` | creates covers_log and covers_lines |
| `inc/Covers/Base/DilveScanProductsFormController.php` | dead duplicate FQCN — never loaded |

## Bootstrap

- `biblio.php:44-46` — `Inc\Covers\Init::register_services()` on every load.
- Services (`inc/Covers/Init.php:21-31`): `Dashboard`, `CoversLogController` (empty shell, `Base/CoversLogController.php:7-11`), `CoversScanProductsCommand`, `CoversScanProductsFormController`, `Cron`. `Enqueue` is commented out; `CoversHelloCommand`/`CoversMediaCleanup` commented out.
- `Init::ensure_covers_tables_schema()` (`Init.php:56-75`) ALTERs `covers_lines` to add `booktitle`/`book_id` if missing.

## Canonical Flow (cron — the only real path)

1. `Cron::register()` (`Base/Cron.php:15-28`) hooks `covers_cron_event` (twicedaily) and `covers_missing_cron_event` (twicedaily).
2. `Cron::coversCron()` (`Base/Cron.php:49-79`): batch_size 100; one `covers_log` row via `CoversApiDbLogManager::insertLogData('logged')` (`Api/CoversApiDbLogManager.php:22-42`); loops `CoversApi::scanProducts($log_id, 100, $offset)` first dilve (`:60`) then cegal (`:71`); offset persisted in option `last_processed_offset` (`:58-65`, `:69-76`), reset to 0 when `hasMore == false`.
3. `CoversApi::scanProducts()` (`Api/CoversApi.php:370-472`):
   - `CoversApiDbManager::getProductsWithoutCover()` (`Api/CoversApiDbManager.php:56-78`) — raw SQL: published `product` posts WITHOUT `_thumbnail_id`; LIMIT comes from `$_POST` (see Gotchas), then `array_slice` by cron batch (`CoversApi.php:379-381`).
   - Per product: `get_post_meta(ID, '_ean')` (`:396`) → `hasAttachment()` (`:108-138`, skips if the featured image path already equals `uploads/portadas/<ean>.jpg`, strict `===`) → `validateEAN()` (`CoversApi.php:474-500`, EAN-13 checksum) → `CoversApiDbLinesManager::insertLinesData()` (`Api/CoversApiDbLinesManager.php:14-65`, dedup by ISBN: existing line ⇒ `attempts+1`).
   - Dilve branch (`:413-440`): `search_dilve($ean)` → if `cover_url` set: `create_cover($url, '<ean>.jpg', 'image/jpeg')` → `set_featured_image_for_product()` → `set_url_target` → `save_cover_source_by_product_id` → `set_covers_url`; also copies `_subtitle` from ONIX (`:435-438`).
   - Cegal branch (`:441-452`): `create_cover($url, …, 'cegal')` directly — the URL is the XML query itself.
   - `setLogStatus('processing')` per product (`:454`); when `hasMore == false`: `setLogStatus('processed')` + `set_processed_products` (`:467-469`).
4. `create_cover()` (`:331-368`): `fetch_cover` → dedup `isAttachment($filename)` (matches `_wp_attached_file = 'portadas/<filename>'`, `CoversApiDbManager.php:287-297`) + `file_exists` → reuse and touch the post (guid regex fixup, see Gotchas); else `attachFile()` (`file_put_contents`, `CoversApiDbManager.php:87-101`) → `insertAttachment()` (`CoversApiDbManager.php:259-279`, `wp_insert_attachment` + `wp_generate_attachment_metadata`, parent 0).
5. `set_featured_image_for_product()` (`CoversApiDbManager.php:147-176`): `get_posts` by `_ean` meta → `set_post_thumbnail()` (`_thumbnail_id`) unless one already exists.
6. `Cron::missingCoversCron()` (`Base/Cron.php:81-87`) → `attach_local_missing_covers()` (`CoversApi.php:502-576`): pre-staged local files at `uploads/img/<substr(ean,0,7)>/<ean>.jpg|.JPG` (note: lookup EAN is `substr($originalean, 0, -1)` — last digit stripped, `:525`).

## Dilve/CEGAL Endpoints

- getRecordsX (`get_query()` `CoversApi.php:40-57` + `search_dilve()` `:67-74`): `http://www.dilve.es/dilve/dilve/getRecordsX.do?user={u}&password={p}&identifier={ean}&metadataformat=ONIX&version=2.1&encoding=UTF-8` — ONE ISBN per call. `get_query()` builds only user/password/identifier; format params appended in `search_dilve`. Commented alternative: `metadataformat=CEGAL&version=3&formatdetail=C` (`:72`).
- getResourceX (`get_dilve_file_url()` `:232-244`): built only when `MediaFileLink` is not already an absolute URL; `resource` is urlencoded with extension.
- Cegal (`get_query()` `:41-48`): `https://www.cegalenred.com/peticiones/fichalibro.xml.php?USUARIO={u}&CLAVE={p}&ISBN={isbn}&TIPOFICHA=C`.
- Credentials: WP option `covers_settings` array keys `dilve_user`, `dilve_pass`, `cegal_user`, `cegal_pass` (`Pages/Dashboard.php:73-160`; sanitized `Api/Callbacks/AdminCallbacks.php:24-31`). Plaintext query params.
- `fetch_cover()` (`:254-319`): Dilve via Guzzle, `verify=false`, timeout 10.0s (`:259`); Cegal via `wp_remote_get` timeout 140s (`:295`), parses `LIBRO->PORTADA->IMAGEN_PORTADA` (base64) + `FORMATO_PORTADA` (`:308-317`).

## ONIX Cover Extraction

`MediaFile` loop (`:171-214`): `MediaFileTypeCode` 03/04/05/06 = cover; prefer code 06 when `MediaFileLinkTypeCode == "06"` (`:181`). Type 08 = author photo; 17 = publisher logo (duplicated case, see Gotchas); 51 = preview. Local filename always `<ean>.jpg` regardless of remote name.

## Storage & Attachment

- Path: `wp_upload_dir()['basedir'] . '/portadas/<ean>.jpg'` — FLAT dir, no YYYY/MM (`:409`, `:348`).
- Attachment: `post_mime_type=image/jpeg`, `post_title='PORTADA: <filename>'`, status `inherit`, parent 0 (`CoversApiDbManager.php:259-271`). Featured image ONLY via `_thumbnail_id`; no product gallery.
- Side meta: `covers_url` (source URL), registered REST-visible (`register_cover_meta()` `CoversApiDbManager.php:301-316`), written via `save_cover_source_by_product_id()` (`:325-331`) and `set_covers_url()` (`:185-218`).

## DB Tables & Options

- `{prefix}covers_log` (`inc/Base/Activate.php:35-43` — created by the GLOBAL activation, not a Covers file): id, start_date, end_date, status (waiting/enqueued/logged/processing/processed), scanned_items, processed_items. One row per scan run.
- `{prefix}covers_lines` (`inc/Base/Activate.php:46-61`): id, log_id, isbn, booktitle, book_id, path, url_origin, url_target, date, isError, error, attempts, type (dilve|cegal). One row per ISBN (deduped; re-encounters bump attempts).
- `{prefix}covers_logger`: NEVER created (dead); constant only (`inc/Api/TableConstants.php:13`); `CoversApiDbLoggerManager` references undefined `self::$coversLoggerKeys` (`Api/CoversApiDbLoggerManager.php:17`).
- Options: `covers_settings`, `last_processed_offset`, `covers_admin_notice`, `dilve_admin_notice`.

## Admin UI & CLI — KNOWN BROKEN (dead paths)

- Menu "Covers" (slug `covers`) + subpages `covers_logs`, `covers_lines` with WP_List_Table list tables (`Pages/Dashboard.php:38-71`); settings fields work (`Dashboard.php:73-160`).
- Tab-2 scan buttons BROKEN: JS `assets/js/coversAdmin.js` sends `action=dilve_scan_products` + `dilve_nonce` (`:26`, `:110`); server registers `wp_ajax_covers_scan_products` checking `covers_nonce` (`Base/CoversScanProductsFormController.php:31-34`, `:55-56`). Additionally: global `inc/Base/Enqueue.php:20` enqueues `dist/js/covers.min.js` but `dist/` is absent from the repo, source `assets/js/covers.js` is empty (0 lines), and `coversAdmin.js` itself is never enqueued. Net effect: the buttons cannot work.
- WP-CLI `wp covers scanProducts` BROKEN: calls nonexistent `$this->dilveApi->search()` (`Commands/CoversScanProductsCommand.php:53` — `CoversApi` only has `search_dilve`); `insertLogData` called with 2 args (`:49`) vs 1-arg signature (`CoversApiDbLogManager.php:22` — extra arg silently ignored by PHP, but the missing `search()` is a fatal).
- Cron works; that is the real path.

## Error Handling

- Fetch failures → `CoversApiDbLinesManager::setError()` (`Api/CoversApiDbLinesManager.php:74-90`, sets covers_lines isError/error); attempts incremented on re-encounter (`:49-53`); no automatic retry scheduler; no rate limiting; no image format/size validation (jpg assumed).

## Gotchas

- `getProductsWithoutCover()` reads `$_POST['batch_size']`/`$_POST['offset']` directly (`CoversApiDbManager.php:60-61`) — under cron they are absent, so the SQL is unbounded and slicing happens in `scanProducts` instead.
- `insertAttachment()` parameter order is `(filename, filepath)` — filename FIRST (`CoversApiDbManager.php:259`); `attachFile`'s docblock is mislabeled "insertFile".
- `insertLogData()` docblock advertises filename/status/linesCount but the signature is 1-arg (`CoversApiDbLogManager.php:22`).
- Duplicate `case "17":` in the MediaFile switch (`CoversApi.php:193` and `:198`); first case falls through into the second (no `break` before it).
- guid regex fixup at `create_cover` (`CoversApi.php:359`) reads `$existing_file[0]->guuid` — a typo for `guid`; the property is always null, so the dated-path cleanup is a no-op.
- `scanProducts` calls `insertLinesData()` TWICE for every dilve product (`CoversApi.php:411` and `:416`) — the second call finds the line and double-increments `attempts` per run.
- `Base/DilveScanProductsFormController.php:15` re-declares the FQCN `Inc\Covers\Base\CoversScanProductsFormController` (file name ≠ class name) — never PSR-4 autoloaded; including it would fatal with a class redeclaration. Never reference it.
- `hasAttachment()` compares `get_attached_file()` output with a strict `===` against `basedir/portadas/<ean>.jpg` (`CoversApiDbManager.php:133`) — any uploads-path filter breaks the match and re-downloads are attempted.
- `set_url_target()` stores the CURRENT featured-image URL at call time (`CoversApiDbLinesManager.php:142-150`), not a fixed target path.
- `countAllProducts()` counts ALL published products, not coverless ones (`CoversApiDbManager.php:239-241`); progress % in `scanProducts` uses it as denominator.
