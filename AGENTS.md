# AGENTS.md — Biblio WordPress Plugin

Canonical AI development guide for this repository. Human-readable docs live in `GEMINI.md` (older, partially stale), `TESTING.md`, and `REFACTOR*.md`; when they conflict with this file or the code, trust the code and note the divergence.

## What this is

WordPress + WooCommerce plugin for a bookshop (La Repartidora). Three modules:

| Module | Namespace root | Purpose |
|--------|----------------|---------|
| Biblio (core) | `Inc\Biblio\` → `inc/` | CPTs (books/authors/editorials), settings, shared infra (`BiblioApi`, `FileLogger`, `ServiceContainer`) |
| Geslib | `Inc\Geslib\` → `inc/Geslib/` | GeslibPLUS import pipeline (INTER* files → WooCommerce products), 16 WP-CLI commands, cron every 2h |
| Covers | `Inc\Covers\` → `inc/Covers/` | Book-cover downloads from Dilve (DAPI) + CEGAL en Red → product featured images |

Bootstrap: `biblio.php` → Composer autoload → `register_activation_hook` → each module's `Init::register_services()` (guarded by `class_exists`).

## Commands

```bash
composer install                 # PHP deps (Guzzle 7; dev: PHPUnit 10.5, Mockery)
npm install                      # JS/SCSS toolchain
npx gulp                         # default: build SCSS+JS to dist/ + browser-sync watch (projectURL http://larepartidora)
./vendor/bin/phpunit             # tests — ALWAYS use vendor binary, not global phpunit
./vendor/bin/phpunit tests/Unit  # only suite that exists
```

- No Composer scripts. `package.json` `npm test*` scripts call a **global** `phpunit` — do not rely on them; use `./vendor/bin/phpunit`.
- Frontend sources: `assets/scss/{biblio,covers,coversAdmin,geslibAdmin}.scss`, `assets/js/*` → `dist/` (gitignored).

## Testing

- PHPUnit 10 + Mockery only. No Brain Monkey / WP_Mock (TESTING.md mentions Brain Monkey — stale).
- `tests/bootstrap.php` is empty; autoload works via Composer's phpunit integration. WP functions are never executed in Unit tests: inject `PluginPathProviderInterface` instead of calling `plugin_dir_path()`.
- Conventions (from `TESTING.md`): constructor DI, code to interfaces (`LoggerInterface` + `FileLogger`), `ServiceContainer` for services. Pattern: mock collaborators, assert on injected seams.
- Known gaps: `tests/Integration/` referenced in `phpunit.xml` but missing; phpunit.xml uses schema 9.5 (expect deprecation warnings under 10.5).

## Architecture patterns

- **Service registration**: every module has `Init::get_services()` returning class names; `register_services()` instantiates each and calls `register()` if present. New admin pages/commands/crons follow this.
- **DB**: raw `$wpdb` SQL + `dbDelta` on activation. Tables: `wp_covers_log`, `wp_covers_lines`, `wp_geslib_log`, `wp_geslib_queues` (schema diagram: `plantuml/database.plantuml`).
- **Options**: settings pages per module; Covers credentials in `covers_settings` (keys `dilve_user`, `dilve_pass`, `cegal_user`, `cegal_pass`).
- **Domain key**: WooCommerce products link to books via post meta `_ean` (EAN-13, checksum-validated in `CoversApi::validateEAN()`). Covers dedup via `_thumbnail_id` + `_wp_attached_file = portadas/<ean>.jpg`.
- **HTTP**: Guzzle (Dilve, `verify=false`, 10s), `wp_remote_get` (CEGAL, 140s).
- Indentation is **mixed** (tabs in `inc/Base/CustomPostTypeController.php`, 4 spaces in `inc/Base/Activate.php` + tests, 2 spaces across `Geslib/`/`Covers/`). No PHPCS: **match the file you edit**.
- Namespaces PSR-4; method names often snake_case — follow the local style, do not mass-refactor style.
- i18n text domains are mixed (`biblio`, `geslib`, `woocommerce`); keep the domain of the file you touch.
- Security: `sanitize_text_field` on inputs, `manage_options` on admin pages; nonce coverage is inconsistent — when adding new endpoints/forms, always add nonce + capability checks (do not copy the inconsistent old ones).

## Skills — load the matching SKILL.md BEFORE working in its area

Project skills (exact paths; pass them to sub-agents too):

| Skill | Path | Load when |
|-------|------|-----------|
| geslib-export-format | `.opencode/skills/geslib-export-format/SKILL.md` | Geslib import: INTER* parsing, line types, data loss, encoding |
| dilve-covers | `.opencode/skills/dilve-covers/SKILL.md` | Covers module: Dilve DAPI, scan pipeline, attachments, `covers_log`/`covers_lines` |
| cegal-en-red | `.opencode/skills/cegal-en-red/SKILL.md` | CEGAL en Red: fichalibro petitions, base64 covers, error codes, credentials |
| wp-plugin-development | `.agents/skills/wp-plugin-development/SKILL.md` | Any WP-specific work: lifecycle, Settings API, security, cron, debugging |

Registry index: `.atl/skill-registry.md` (auto-generated; refresh with `gentle-ai skill-registry refresh` after adding skills — refreshed after the Covers skills were added, all 4 project skills indexed).

User-level skills that frequently apply here: `wordpress-pro`, `branch-pr`, `work-unit-commits`, `chained-pr`, `issue-creation`, `judgment-day`.

## Domain cheat-sheet

- **Covers flow (real path = cron only)**: `covers_cron_event` (twice daily, batch 100) → products published without `_thumbnail_id` → Dilve `getRecordsX` (ONIX MediaFile) → cover via `getResourceX` or absolute URL → CEGAL fallback `fichalibro.xml.php` TIPOFICHA=C (base64) → `uploads/portadas/<ean>.jpg` → `set_post_thumbnail`. Dead paths: admin tab-2 scan buttons (nonce/action prefix mismatch), `wp covers scanProducts` (calls missing `search()`).
- **Geslib flow**: cron `geslib_cron_event` (2h) scans `geslibfiles/INTER*` zips → `geslib_log` → `geslib_queues` → line-type handlers → WooCommerce products. Sequence files by FTP mtime, never filename. Trailing `|` and exact field count are load-bearing — see geslib-export-format skill.
- **Vendor contracts**: Dilve DAPI v2.0 (dilvehelp.com/guias/dapi/), CEGAL en Red V4.00 (`ESPECIFICACIONES_PETICIONES_SEPTIEMBRE_2019_V4.00.pdf` at repo root), GeslibPLUS spec (`Formato exportación GeslibPLUS a Internet.pdf`).

## Known pitfalls (verified — fix, don't trust)

- Root `Init.php` duplicates `inc/Init.php` (same FQCN); PSR-4 loads only `inc/Init.php` — root file is dead code.
- `inc/Covers/Base/DilveScanProductsFormController.php` re-declares an existing FQCN referencing nonexistent classes; never autoloaded.
- `inc/Covers/Api/temp.txt` holds **live CEGAL credentials** (untracked by git). Never commit, deploy, or copy its values; rotate if exposed.
- `covers_logger` table constant exists but the table is never created; `CoversApiDbLoggerManager` is broken/dead.
- `.gitignore` excludes `composer.lock` — reproducibility risk; be explicit about dependency versions when reporting environment facts.
- `plantuml/*:Zone.Identifier` files are Windows cruft — ignore.
- No CI (`.github/` has no workflows). Verification is local: `./vendor/bin/phpunit` + manual admin/cron checks.

## Working rules for AI agents

1. Load the matching project skill (table above) before touching its area; pass exact SKILL.md paths when delegating.
2. Trust code over vendor docs, `GEMINI.md`, and `TESTING.md`; note divergences in your output.
3. Verify claims against source (file:line) before asserting behavior; the pitfalls list above is real.
4. Generated artifacts (code, comments, UI copy, commits) in **English**; conventional commits, no AI attribution.
5. Never write credentials, keys, or real vendor samples into code, docs, tests, or skills.
6. Substantial changes: work-unit commits with tests alongside behavior; ~400 authored lines per unit as a planning heuristic (not a cap).
7. Delivery (push/PR/merge) is a human decision — never auto-push.
8. Receipt-driven review is opt-in and user-owned; do not enable or invoke it unless the user asks.
