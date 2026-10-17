# CEGAL usage in this plugin

The Covers module falls back to CEGAL when Dilve has no cover. Only petition #1 (fichalibro, TIPOFICHA=C) is implemented. The shared pipeline (scan, dedup, `portadas/<ean>.jpg`, `_thumbnail_id`) is documented in the sibling skill `dilve-covers` -> `references/covers-pipeline.md`; do not duplicate it here.

## Call-site map

| What | Where |
|------|-------|
| URL build (cegal branch) | `inc/Covers/Api/CoversApi.php:41-48` — `get_query('cegal', $isbn)` (see Request shape below) |
| Credentials load | `CoversApi.php:32-36` — `covers_settings` keys `cegal_user`/`cegal_pass` |
| Credentials sanitize | `inc/Covers/Api/Callbacks/AdminCallbacks.php:28-29` — `sanitize_text_field` |
| Settings fields | `inc/Covers/Pages/Dashboard.php:89` (defaults), `:135-158` (form fields) |
| Fetch | `CoversApi.php:293-306` — `wp_remote_get($query, ['timeout' => 140])`, control-char strip, `simplexml_load_string` |
| Parse | `CoversApi.php:308-317` — `LIBRO->PORTADA->IMAGEN_PORTADA` (`base64_decode`) + `FORMATO_PORTADA`; fails only when PORTADA or IMAGEN_PORTADA is empty (`:308`) |
| Consume | `CoversApi.php:339` — `create_cover()` takes `$data['imagedata']` when type is cegal; saved as `uploads/portadas/<ean>.jpg`, mime hardcoded `image/jpeg` (cegal branch calls with `$ean.'.jpg'`, `'image/jpeg'` at `:442`) |
| Cron trigger | `inc/Covers/Base/Cron.php:71` — second pass `scanProducts(..., 'cegal')` after the dilve pass |
| Admin AJAX | `inc/Covers/Base/CoversScanProductsFormController.php:65` — cegal pass after dilve (see the dead-paths warning in the `dilve-covers` skill) |
| Lines type | `inc/Covers/Api/CoversApiDbLinesManager.php:38` — `covers_lines.type` stores `dilve\|cegal` |
| Product meta field | `inc/Geslib/Base/WooCommerce_Customizations.php:33-42,52-54` — free-text `cegal_url` on the product edit screen, saved as post meta, never read by Covers |

## Request shape

`get_query('cegal', $isbn)` builds exactly one petition: fichalibro, TIPOFICHA=C, single ISBN, no FORMATO, no VERSION_SINLI.

```
https://www.cegalenred.com/peticiones/fichalibro.xml.php?USUARIO={u}&CLAVE={p}&ISBN={isbn}&TIPOFICHA=C
```

Consequences: the response is the SINLI shape (v03 default); DETALLE is never read, only PORTADA. The `$url` argument passed down to `fetch_cover()` is ignored on this path — the query is rebuilt from `$type`+`$isbn` (`CoversApi.php:255`).

## Response handling notes

- Control characters stripped before `simplexml_load_string` (`CoversApi.php:305`) — base64 blobs in CDATA can carry them.
- Parse failure is silent: no `setError()` on the cegal path, only `debug_log` + `return false`.
- Large payloads expected (base64 covers, no size limit per spec) — hence the 140s `wp_remote_get` timeout.

## Divergences from the vendor spec

- **1 ISBN per call** — spec allows up to 32 (`|`-separated); module sends one.
- **No FORMATO / VERSION_SINLI** — response is the SINLI shape (v03 default); DETALLE is never read, only PORTADA.
- **FORMATO_PORTADA ignored** — spec allows gif/bmp/jpg/jpeg/tiff; code always writes `<ean>.jpg` as `image/jpeg`.
- **ERRORES never parsed** — errors are in-band (HTTP 200); bad credentials (CODIGO 2), exhausted credit (3), or unknown ISBN (5) surface only as an empty/failed image. `setError()` runs only on the Dilve/Guzzle path, never on the cegal path.
- **Timeout contrast** — cegal: `wp_remote_get` 140s (`CoversApi.php:295`); Dilve cover download: Guzzle 10s (`CoversApi.php:259`).

## Credential hazard

`inc/Covers/Api/temp.txt` contains a sample request URL with LIVE credentials sitting in the plugin directory (untracked by git as of this writing — never commit or deploy it). Never copy those values into code, docs, or skills; rotate if exposed.

## Not wired (extension points only)

- Petitions 2-6: disponibilidad, rankingventas, ventasrel, disponibilidad_global, estadisticaventas.
- ERRORES node parsing / CODIGO handling on the cegal path.
- ISBN batching (up to 32 per call).
- FORMATO=XML (plain-XML ficha) and VERSION_SINLI 05/07.
