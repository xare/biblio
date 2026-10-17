---
name: cegal-en-red
description: "Trigger: cegal, cegal en red, cegalenred, fichalibro, SINLI, portada cegal, disponibilidad, ranking ventas, ventasrel, estadistica ventas. CEGAL en Red HTTP petitions and this plugin's cover-download usage."
license: Apache-2.0
metadata:
  author: "xare"
  version: "1.0"
---

## Activation Contract

Activate for CEGAL en Red work: `fichalibro` petitions, SINLI fichas, base64 portada parsing, cegal credentials/fallback.

## Hard Rules

- Credentials live in WP option `covers_settings` (`cegal_user`/`cegal_pass`); sent as plaintext GET params USUARIO/CLAVE.
- Only `fichalibro.xml.php` with TIPOFICHA=C is wired; the other 5 spec petitions are reference-only.
- Cover arrives EMBEDDED as base64 in PORTADA/IMAGEN_PORTADA — no separate binary download like Dilve's getResourceX.
- FORMATO_PORTADA declares the real format (gif/bmp/jpg/jpeg/tiff); module ignores it, always saves `<ean>.jpg` image/jpeg — flag when touching image handling.
- Module sends ONE ISBN per call, omitting FORMATO/VERSION_SINLI (defaults SINLI v03); the spec allows up to 32.
- Errors are IN-BAND (`ERRORES/ERROR/CODIGO`, HTTP 200); the module never parses them — 2=bad credentials, 3=CREDITO FINALIZADO, 5=ISBN not found; a failed cover may be an unparsed error.
- Cegal fetch: `wp_remote_get`, 140s timeout (large base64 payloads); Dilve cover path: Guzzle, 10s.
- Never copy credentials into code/docs/skills; `inc/Covers/Api/temp.txt` holds a live-credential sample — do not replicate; rotate if exposed.
- Shared pipeline (covers_log/covers_lines, `portadas/<ean>.jpg`, `_thumbnail_id`) lives in sibling skill `dilve-covers`; read it before touching flow code.
- Trust code over spec; note divergence in Output Contract.

## Decision Gates

| Need | Action |
|------|--------|
| Vendor params, roots, schemas, error codes | `references/cegal-api-spec.md` |
| Plugin call sites, credentials, parse, gotchas | `references/cegal-in-plugin.md` |
| Shared scan/dedup/attachment pipeline | Sibling skill `dilve-covers` |
| Batching | Spec allows 32 ISBNs per call; module sends 1 |
| Availability / ranking / sales data | Petitions 2-6 in the spec; none wired |
| Docs conflict with code | Trust code; note divergence |

## Execution Steps

1. Read `references/cegal-in-plugin.md` before touching the cegal branch (`inc/Covers/Api/CoversApi.php`).
2. Identify the petition needed: only fichalibro TIPOFICHA=C exists; petitions 2-6 require new code.
3. Debugging an empty cover? Inspect the raw response for the ERRORES node — the module never parses it.
4. Verify with a real ISBN: LIBRO/PORTADA present? CODIGO errors in the body?
5. Respect the credit model: CODIGO 3 (CREDITO FINALIZADO) means the account is exhausted.
6. Unsure → trust code; note divergence.

## Output Contract

State: petitions added/changed, batching/credit impact, cover-format/mime consequences, ERRORES-parsing changes, pipeline skills touched.

## References

- `references/cegal-api-spec.md` — 6 petitions, params, roots, error table, schemas (provenance: `ESPECIFICACIONES_PETICIONES_SEPTIEMBRE_2019_V4.00.pdf`, repo root).
- `references/cegal-in-plugin.md` — call sites, credentials, parse path, gotchas.
- Sibling skill `dilve-covers` — shared covers pipeline (scan, dedup, attachment).
- Code anchor: `inc/Covers/Api/CoversApi.php` (get_query cegal branch, fetch_cover, create_cover).
