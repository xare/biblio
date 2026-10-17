---
name: dilve-covers
description: "Trigger: dilve, covers, portadas, cubiertas, cover image, DAPI, getRecordsX, getResourceX, getResourceStatusX, cegal. Download book covers from Dilve/CEGAL and attach them as WooCommerce featured images."
license: Apache-2.0
metadata:
  author: "xare"
  version: "1.0"
---

## Activation Contract

Activate for work on the Covers module: Dilve/CEGAL cover downloads, `covers_log`/`covers_lines` flow, featured-image attachment, DAPI endpoint changes.

## Hard Rules

- Credentials live in WP option `covers_settings` (`dilve_user`/`dilve_pass`/`cegal_user`/`cegal_pass`); sent as plaintext query params.
- Cover name comes from ONIX MediaFile (type 03/04/05/06, prefer 06) → download via getResourceX; getRecordsX never returns binaries.
- Local file always `uploads/portadas/<ean>.jpg` (flat dir); mime hardcoded `image/jpeg`.
- Scan targets published products WITHOUT `_thumbnail_id`; product↔ISBN via meta `_ean`; EAN-13 checksum enforced before any API call.
- Featured image only via `set_post_thumbnail()` (`_thumbnail_id`); no gallery; never overwrite an existing thumbnail.
- One ISBN per getRecordsX call in this module (API allows 128); no rate limiter exists — never raise call volume blindly.
- Process HTTP headers before XML body; getResourceX errors have NO XML body.
- Dedup by `_wp_attached_file = portadas/<filename>`; re-encounters bump `attempts`, never create duplicate attachments.
- Dead paths — never trust without fixing first: admin tab-2 buttons (`covers_`/`dilve_` nonce+action mismatch), `wp covers scanProducts` (calls missing `search()`), `covers_logger` table (never created).
- Trust code over docs; note divergence in Output Contract.

## Decision Gates

| Need | Action |
|------|--------|
| DAPI params, limits, errors | `references/dilve-dapi.md` |
| Flow, files, tables, gotchas | `references/covers-pipeline.md` |
| Cover missing / blank product | Check covers_lines isError+attempts; hasAttachment path compare |
| Periodic cover updates | getResourceStatusX (not wired yet — extension point) |
| Cegal fallback path | TIPOFICHA=C → base64 IMAGEN_PORTADA |
| Docs conflict with code | Trust code; note divergence |

## Execution Steps

1. Read `references/covers-pipeline.md` before touching `inc/Covers/`.
2. Identify the entry path: cron (real), admin AJAX (broken), WP-CLI (broken).
3. DAPI questions → `references/dilve-dapi.md`; respect rate limits on any volume change.
4. Verify with real data: product `_ean`, covers_lines row, `uploads/portadas/<ean>.jpg`, `_thumbnail_id`.
5. Unsure → trust code; note divergence.

## Output Contract

State: DAPI calls added/changed, rate-limit impact, covers_log/lines schema impact, attachment/dedup consequences, known-broken paths touched.

## References

- `references/dilve-dapi.md` — DAPI v2.0 essentials: endpoints, params, limits, errors (provenance: dilvehelp.com/guias/dapi/, julio 2026).
- `references/covers-pipeline.md` — module flow, files/methods, tables, known-broken paths.
- Code anchor: `inc/Covers/Api/CoversApi.php` (search_dilve, create_cover, scanProducts).
