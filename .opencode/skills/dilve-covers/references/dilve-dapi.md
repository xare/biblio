# Dilve DAPI Essentials (as used by the Covers module)

DAPI v2.0 reference distilled to what `inc/Covers/Api/CoversApi.php` actually calls, plus the one extension point worth knowing. Parameter names are case-sensitive.

General call structure: `https://www.dilve.es/dilve/dilve/accion.do?param=value&...`

The module uses the legacy action paths `getRecordsX.do` and `getResourceX.do` (equivalents of the DAPI `accion.do` actions).

## Endpoints Used by the Module

### getRecordsX — ONIX/CEGAL metadata

Built in `CoversApi::get_query()` + `search_dilve()`. ONE ISBN per call in this module (the API accepts up to 128).

Base URL (from `get_query()`):

```
http://www.dilve.es/dilve/dilve/getRecordsX.do?user={u}&password={p}&identifier={ean}
```

`search_dilve()` appends `&metadataformat=ONIX&version=2.1&encoding=UTF-8`. A commented alternative exists for CEGAL format: `&metadataformat=CEGAL&version=3&formatdetail=C`.

| Param | Obligatory | Values | Module use |
|-------|-----------|--------|------------|
| `user` | yes | Dilve username | `covers_settings['dilve_user']` |
| `password` | yes | Dilve password | `covers_settings['dilve_pass']` |
| `identifier` | yes | ISBN13/ISBN10, up to 128, `\|`-separated | one ISBN |
| `metadataformat` | no | ONIX (default), ONIX_ST, CEGAL, CSV | `ONIX` |
| `version` | no | ONIX: 2.1 (default), 3.0, 3.1; CEGAL: 3–9 | `2.1` |
| `formatdetail` | no | CEGAL only: B (default), C | unused (commented) |
| `encoding` | no | ONIX: UTF-8 (default) | forced `UTF-8` |

Response is ONIX XML (or CEGAL XML). It never embeds binaries: cover filenames come from `MediaFile` blocks; download them with getResourceX.

### getResourceX — binary download

Built in `CoversApi::get_dilve_file_url()` — only when `MediaFileLink` is not already an absolute URL (absolute links are passed through untouched).

```
http://www.dilve.es/dilve/dilve/getResourceX.do?user={u}&password={p}&identifier={isbn}&resource={filename}
```

| Param | Obligatory | Notes |
|-------|-----------|-------|
| `user` / `password` | yes | plaintext query params |
| `identifier` | yes | ISBN of the record that references the resource |
| `resource` | yes | filename WITH extension, urlencoded |
| `lastupdate` | no | timestamp; download only if resource modified on/after it. NOT used by the module. |

Response is the raw file. On failure, an error is reported in the HTTP status headers — there is NO XML error body. Always process headers before the body.

### getResourceStatusX — extension point (NOT wired yet)

Lists ISBNs whose referenced resource changed between `fromDate` and `toDate`. Useful to refresh covers periodically without re-scanning the catalog.

| Param | Notes |
|-------|-------|
| `fromDate` / `toDate` | timestamp window (see Timestamps) |
| `onixtype` | resource list 158; default `01` = cover |
| `fallback` | `Y` uses record touch date when the resource lacks one |

Available for future periodic cover updates; the module currently has no such scheduler.

## Limits

- Recommended: ≤6 getRecordsX requests/second.
- HARD limit: 20 requests/second per IP; the IP is blocked for 1 second after exceeding.
- ≤50,000 records/hour/user.
- Errors count against limits EVEN for nonexistent ISBNs — never query ISBNs not known to exist.

## Error Handling

Two distinct kinds:

1. **DAPI error** — HTTP 200 with an XML body: `<error><code>…</code><text>…</text></error>`.
2. **HTTP error** — error status in the response headers; the body may be empty (typical for getResourceX).

MUY IMPORTANTE per the official guide: process HTTP headers before the body, or you will try to parse empty/error bodies as XML.

## Timestamps

- Valid formats only: `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SSZ`.
- Interpreted in DILVE server time.
- Chain sync windows: use the returned `toDate` as the next `fromDate`.
- No dates before 2009 for getRecordStatusX.

## Compression

gzip/deflate supported via `Accept-Encoding`; recommended for large responses. The module does not currently send it (Guzzle default for cover downloads; `wp_remote_get` for the ONIX query).

## Periodic Sync Pattern

1. Initial copy via extractions or getRecordListX.
2. Then, in a loop: getRecordStatusX (metadata changes) + getResourceStatusX (cover changes), chaining `toDate` → next `fromDate`.
3. Download changed binaries via getResourceX (`lastupdate` can skip unchanged resources).

This is the intended extension point if covers must be refreshed after the initial download.

Provenance: https://dilvehelp.com/guias/dapi/ — DAPI v2.0 guide, julio 2026.
