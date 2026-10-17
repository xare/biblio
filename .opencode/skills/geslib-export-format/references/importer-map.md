# Importer Map — how this plugin interprets the GeslibPLUS export

Code map of the biblio plugin's Geslib import pipeline. Line numbers refer to the current working tree; re-verify when the code has moved. Complements `geslib-export-spec.md` (vendor format) with actual parser behavior.

## Pipeline

1. **File ingest** — `GeslibApiReadFiles::readFolder()`: globs `INTER*` files, unzips them, and registers them via `GeslibApiDbLogManager::insertLogData()`.
2. **Cron** — `Cron::geslib_cron_function()` (twohourly): drives per-file statuses `logged` → `queued` → `processed` with a heartbeat lock and per-file atomicity.
3. **Line staging** — `GeslibApiLines::storeToLines()` (`inc/Geslib/Api/GeslibApiLines.php:45`): reads the file with `file()`, runs `sanitizeLine()` per line, splits with `explode('|')`, applies the whitelist/exclusion filter (lines 90–102), and inserts batches of 300 rows into `{prefix}geslib_queues` with `type = 'store_lines'`.
4. **Queue processing** — `GeslibApiDbQueueManager::processFromQueue()` (`inc/Geslib/Api/GeslibApiDbQueueManager.php:412`) → `GeslibApiLines::readLine()` + `process{Type}()` → `processBatchBuildContent()` (line 452; entity match with **no `default` arm → `UnhandledMatchError` risk**) → `processBatchStoreProducts()` (:506), `processBatchStoreAutors()` (:612), `processBatchStoreEditorials()` (:638), `processBatchStoreCategories()` (:659), `processBatchStoreColecciones()` (:680) → WooCommerce products / `wp_insert_term()`.

## Dispatch

- **Whitelist** `LineTypes::LINE_TYPES` (`inc/Geslib/Config/LineTypes.php:131–147`) = 15 tokens: `['1L','1A','3','GP4','EB','IEB','5','6','6E','6I','6T','6IT','LA','B','AUT']`.
- **Dynamic dispatch** `readLine()` (`GeslibApiLines.php:192–199`): builds `process{token}`, guarded by `method_exists()` — a missing handler is skipped **silently** (the queue row is still deleted).
- **Existing handlers** (all in `GeslibApiLines.php`): `processGP4` (:213), `process6E` (:236), `process1L` (:263), `process2` (:289), `process3` (:310), `process5` (:332), `processAUT` (:353), `processLA` (:376), `processB` (:394).

### Whitelist vs. handler discrepancies (verified)

| Set | Tokens |
|-----|--------|
| Whitelisted (15) | `1L`, `1A`, `3`, `GP4`, `EB`, `IEB`, `5`, `6`, `6E`, `6I`, `6T`, `6IT`, `LA`, `B`, `AUT` |
| Handlers (9) | `GP4`, `6E`, `1L`, `2`, `3`, `5`, `AUT`, `LA`, `B` |
| Whitelisted **without** handler → dropped at dispatch | `1A`, `EB`, `IEB`, `6`, `6I`, `6T`, `6IT` (7) |
| Handler **without** whitelist entry → never dispatched | `2` (`process2` exists but `2` is not in `LINE_TYPES`; also excluded at ingest) |

Note: the ingest entity match (`GeslibApiLines.php:104–114`) maps `'2' => 'colecciones'`, but that arm is unreachable while `2` is not whitelisted. The whitelist token for translated index is `6IT` (summary-list spelling), while the vendor detail section uses `6TI`.

## Key maps (`inc/Geslib/Config/LineTypes.php`)

| Constant | Line | Count | Used for |
|----------|------|-------|----------|
| `PRODUCT_KEYS` | :31 | 62 names | `GP4`/`EB` product rows |
| `EDITORIAL_KEYS` | :96 | 8 names | `1L` editorials |
| `COLECCION_KEYS` | :107 | 5 names | `2` colecciones |
| `CATEGORIA_KEYS` | :115 | 6 names | `3` materias |
| `AUTHOR_KEYS` | :124 | 4 names | `AUT` authors |
| `*_DELETE_KEYS` | :7–29 | 3 each (`type`,`action`,`geslib_id`) | delete rows |

`PRODUCT_KEYS` = the 58 spec-era `GP4` fields (`type` … `titulo_original`) + 4 appended vendor columns: `pack`, `importe_canon`, `unidades_compra`, `descuento_maximo` — so a current `GP4` line must carry exactly 62 fields.

## Field positions and array alignment

- Wire layout: `[0]` = type token, `[1]` = operation code (it **stays in the array**; keys start `type, action, geslib_id`, so `array_combine()` aligns), `[2]` = `geslib_id` for most types.
- **Ingest id index** (`GeslibApiLines.php:103`): `index = in_array(type, ['6E','6TE','AUTBIO','B','LA']) ? 1 : 2` — for these types the id sits at position 1 (no op code).
- **Dispatch id index** (`GeslibApiLines.php:197`): `['6E','6TE','BIC','B','LA']` — a **different list** (`BIC` instead of `AUTBIO`). Keep both in sync when adding types.
- `readLine()` runs `array_pop($data)` first (`:186`): **every line must end with a trailing `|`** or the last real field is silently lost.
- `array_combine()` requires the **exact** field count (PHP 8 `ValueError` on mismatch). Only `process1L` slices the key list; no handler pads missing fields.

## Operation codes

- Ingest maps `$line_array[1]` (`GeslibApiLines.php:115–120`): `A → crear`, `M → actualizar`, `B → borrar`, anything else → `adjuntar`.
- Handlers switch on `$data[1]`; an unknown operation code makes them return `false`.
- Exception: line type `B` (stock) has **no** operation code — the wire is `B|id|stock`, so `$data[1]` is the id.
- Deletes are applied in `processBatchStore*`: products via `wc_get_product()->delete(true)`, terms via `GeslibApiDbManager::deleteTerm()`.

## Encoding

- `GeslibApiSanitize::utf8_encode()` = `ltrim($string, ', ')` + `Encoding::fixUTF8()`.
- `fixUTF8()` passes valid UTF-8 unchanged and converts ISO-8859-1 → UTF-8 otherwise → **expected source encoding is ISO-8859-1**.
- Applied **twice** per field (ingest `sanitizeLine()` + `sanitize_content_array()`): leading spaces/commas are destroyed, numeric strings containing `,` become floats, `''` becomes `null`, URLs are skipped.

## Mapping to WooCommerce

- `GP4` → product: `description` → `post_title`, `sinopsis` → `description`, `pvp` → `price`, `peso`/1000 → `weight`; meta `_ean`, `_num_paginas`, `_subtitle`, `_author`, plus `geslib_id`.
- Terms are linked through termmeta: `editorial_geslib_id`, `author_geslib_id`, `category_geslib_id`.
- `5` (categories) and `LA` (authors) merge into the product's `build_content` JSON — **both require the product row to already exist**, otherwise the relation is lost ("Content not found").
- Entity routing: `1L` → editorials, `AUT` → autors, `3` → product_cat, `2` → colecciones (unreachable), `GP4`/`B`/`LA` → product.
- Materia hierarchy `substr(category_geslib_id, 0, -2)` runs only from admin AJAX, not from cron.

## Ingest exclusions (`GeslibApiLines.php:90–102`)

Lines are dropped before queuing when any of these holds:

1. the line contains `< Genérica >`;
2. the type is not in `LineTypes::LINE_TYPES`;
3. a `1L|A` line whose editorial already exists in `editorials`;
4. an `AUT|A` line whose author already exists in `autors`;
5. a `B` line whose stock equals the product's current stock (compared via a pre-loaded stock map).

## Tables, batches, and known rough edges

- `{prefix}geslib_log` — file registry. **Never delete rows**: filename recycling computes `cycle+1` from existing entries.
- `{prefix}geslib_queues` — self-cleaning staging FIFO keyed by `type`. Schema drift: `Activate.php` creates only `id, log_id, geslib_id, type, data`, but writers also persist `entity` and `action`.
- `GESLIB_LINES_TABLE` is referenced in code but never created.
- Batch caps: `store_lines` 200, `build_content` 200, `store_products` 50, terms 200; multi-insert threshold 500, filterable via `biblio_geslib_multi_insert_threshold`.
- WP-CLI entry: `GeslibProcessAllCommand`.
- Legacy/broken paths: `GeslibApiStoreData` partial no-ops; `GeslibStoreProductCategoriesCommand` calls nonexistent methods; term-meta key mismatch `autor_geslib_id` vs `author_geslib_id`; `deleteTerm` colecciones prefix bug.
- Single open TODO: accented category names (`inc/Geslib/Api/GeslibApiDbTaxonomyManager.php:339`).

## Safe extension checklist (adding a line type)

1. Add the exact token to `LineTypes::LINE_TYPES` (`inc/Geslib/Config/LineTypes.php:131`) — without it ingest drops every line of that type.
2. Implement `process{Token}()` in `GeslibApiLines.php` with the exact token casing — without it `method_exists()` silently skips the line while the queue row is deleted.
3. Respect the exact field count: after `array_pop()` removes the trailing empty element, the field count must equal the key list count (`array_combine()` throws otherwise). Every source line must end with `|`.
4. If the type's id sits at position 1 (no op code), add it to **both** special-case lists: ingest (`GeslibApiLines.php:103`) and dispatch (`:197`) — they currently differ (`AUTBIO` vs `BIC`).
5. Handle operation codes `A`/`M`/`B` (or document their absence for relation lines); unknown codes make handlers return `false`.
6. Add or extend the matching key map in `LineTypes.php` if new fields are consumed; append keys at the end, mirroring the vendor's append-only rule.
7. Follow batch/entity conventions: add the entity to the ingest `match` (`:104`), and remember `processBatchBuildContent()` has no `default` arm — an unmapped entity throws `UnhandledMatchError`.
8. Re-check ingest exclusions (step list above) so duplicate filters or the `< Genérica >` filter do not swallow the new lines.
