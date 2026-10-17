---
name: geslib-export-format
description: "Trigger: geslib, geslibplus, export format, pipe-delimited import, line types, GP4, INTER file. Parse the Geslib export spec and this plugin's importer."
license: Apache-2.0
metadata:
  author: "xare"
  version: "1.0"
---

## Activation Contract

Activate for work on the Geslib import pipeline: parsing INTER* files, adding line types, diagnosing data loss.

## Hard Rules

- Trailing `|` required: `readLine()` pops the last element first; no pipe → field lost.
- Exact field count: `array_combine()` throws; only `process1L` slices.
- Op code at index 1; exception: stock `B|id|stock` has none.
- Whitelist AND `process{Token}()` required; either missing → silent loss.
- ISO-8859-1 source; `utf8_encode()` twice per field strips leading spaces/commas.
- Sequence incremental files by FTP mtime, never filename.
- `5`/`LA`/`6E` merges need the product row first or lose the relation.
- Never delete `{prefix}geslib_log` rows; recycling reads `cycle+1`.

## Decision Gates

| Need | Action |
|------|--------|
| Column list / field order | `references/geslib-export-spec.md` |
| Field consumed in code | `references/importer-map.md` |
| Add a line type | Checklist → `references/importer-map.md` |
| Spec conflicts with code | Trust code; note divergence |
| Data loss | Check trailing pipe, count, whitelist, handler |

## Execution Steps

1. Read the right reference: spec=format, importer-map=code.
2. Identify type (0) and op (1); mind both id-index lists.
3. Count fields after `array_pop`; must equal the key list.
4. Confirm `process{Token}()` exists before trusting the whitelist.
5. Unsure → verify in `LineTypes.php` / `GeslibApiLines.php`.

## Output Contract

State: types/fields touched, handler added, field-count impact, encoding consequences.

## References

- `references/geslib-export-spec.md` — vendor spec: GP4 columns, line-type index.
- `references/importer-map.md` — pipeline, dispatch, Safe extension checklist.
- Provenance: `Formato exportación GeslibPLUS a Internet.pdf` (repo root, 30-05-2016).
