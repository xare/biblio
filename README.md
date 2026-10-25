# Biblio

WordPress plugin for library management. It combines a configurable book catalogue (custom post types + taxonomies), an automated importer for the GeslibPLUS book-trade format, and a cover-image pipeline that pulls artwork from Dilve (DAPI) and CEGAL en Red — all backed by WooCommerce products.

- **Plugin header:** `Biblio a plugin for libraries` — version `1.0`
- **Author:** Juan Etxenike Almeida
- **License:** GPL-3.0
- **Repository:** <https://github.com/xare/biblio>

---

## Table of contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Project structure](#project-structure)
- [Modules](#modules)
  - [Biblio (core)](#biblio-core)
  - [Geslib](#geslib)
  - [Covers](#covers)
- [Admin pages](#admin-pages)
- [WP-CLI commands](#wp-cli-commands)
- [Cron jobs](#cron-jobs)
- [Database](#database)
- [Front-end build](#front-end-build)
- [Testing](#testing)
- [Documentation](#documentation)
- [License](#license)

---

## Overview

`biblio.php` is the single entry point. It loads the Composer autoloader, registers the activation/deactivation hooks, and then boots three independent modules:

```php
Inc\Biblio\Init::register_services();   // core: CPTs, taxonomies, settings, assets
Inc\Geslib\Init::register_services();   // catalogue import pipeline
Inc\Covers\Init::register_services();   // cover image download pipeline
```

Each module owns an `Init` class exposing `get_services()` (a list of class names) and `register_services()`, which instantiates every service and calls `register()` if the method exists. That is the only wiring convention in the codebase — **new admin pages, cron hooks and CLI commands are added by listing the class in the relevant `Init::get_services()` array**.

The core module additionally exposes a small DI container (`Inc\Biblio\Base\ServiceContainer`, accessible via `Inc\Biblio\Init::getContainer()`), used to resolve shared services such as the logger and API helpers.

---

## Requirements

| Dependency | Notes |
| --- | --- |
| PHP 8.1+ | PHPUnit 10 (dev) requires it; runtime code targets PHP 8 |
| WordPress 6.x | Classic plugin layout (`wp-content/plugins/biblio`) |
| WooCommerce | Products are the storage target for imported books |
| Composer | Guzzle ^7.0 (runtime), PHPUnit ^10 + Mockery ^1.5 (dev) |
| Node.js + npm | Gulp 4 toolchain for SCSS/JS compilation |
| WP-CLI (optional) | Required only for the `wp geslib …` / `wp covers …` commands |

---

## Installation

```bash
git clone git@github.com:xare/biblio.git wp-content/plugins/biblio
cd wp-content/plugins/biblio

composer install    # PHP dependencies + PSR-4 autoloading
npm install         # Gulp/SCSS/JS toolchain
npx gulp            # compile assets to dist/ (build once) — see Front-end build

wp plugin activate biblio
```

> `vendor/`, `node_modules/`, `dist/`, `logs/` and lock files are gitignored, so **both `composer install` and `npm install` are mandatory after a fresh clone** — without `vendor/autoload.php` the plugin refuses to load.

On activation the plugin creates its custom tables, seeds default options (`biblio_settings`, `dilve_settings`, `cegal_settings`, `geslib_settings`, `biblio_cpt`, `biblio_taxonomy`), creates `wp-content/uploads/geslib/`, and flushes rewrite rules.

---

## Project structure

```
biblio/
├── biblio.php              # plugin bootstrap: autoloader, hooks, module boot
├── composer.json           # PSR-4: Inc\Biblio → inc/, Inc\Geslib, Inc\Covers
├── gulpfile.js             # SCSS/JS build + browser-sync watch
├── phpunit.xml             # Unit + Integration suites
│
├── inc/
│   ├── Api/                # settings API, callbacks, logger, DB helpers (core)
│   ├── Base/               # Activate/Deactivate, CPT & taxonomy controllers,
│   │                       # ServiceContainer, Enqueue, BaseController
│   ├── Pages/              # core admin dashboard
│   ├── Geslib/             # import pipeline: Api/, Base/, Commands/, Pages/
│   └── Covers/             # cover pipeline: Api/, Base/, Commands/, Pages/
│
├── templates/              # admin views (dashboard, tabs, list tables)
├── assets/                 # sources: scss/ and js/
├── dist/                   # compiled assets (generated, gitignored)
├── tests/                  # PHPUnit (Unit/ + Integration/)
├── plantuml/               # ER diagram of the custom tables
└── logs/                   # runtime logs (gitignored)
```

---

## Modules

### Biblio (core)

Namespace `Inc\Biblio\` → `inc/`.

- **Configurable catalogue schema.** Custom post types and taxonomies are *not* hardcoded: they are defined by the administrator through the **CPT** and **Taxonomy** settings pages and persisted in the `biblio_cpt` / `biblio_taxonomy` options. `CustomPostTypeController` and `CustomTaxonomyController` read those options and call `register_post_type()` / `register_taxonomy()` accordingly.
- **Settings API wrapper** (`inc/Api/SettingsApi.php`) builds menu pages, sections and fields declaratively; `inc/Api/Callbacks/*` render them.
- **Shared infrastructure:** `ServiceContainer` (DI), `LoggerInterface` + `FileLogger`, `DatabaseGuard`, `TableConstants`, `PluginPathProviderInterface`.
- **Assets:** `Enqueue` registers the compiled `dist/` CSS/JS.

### Geslib

Namespace `Inc\Geslib\` → `inc/Geslib/`.

Imports book-trade catalogue data from GeslibPLUS export files into WooCommerce products, custom taxonomies (authors, editorials, collections, categories) and their relations.

Pipeline:

1. The **two-hourly cron** (`geslib_cron_event`) picks up export files from `wp-content/uploads/<geslib_folder_index>/` — the folder name comes from the `geslib_folder_index` setting, defaulting to `geslib` (`GeslibApiReadFiles`).
2. Parsed lines are recorded in `wp_geslib_log` and pushed into `wp_geslib_queues`.
3. Queued work is drained in a fixed order defined by `Cron::ALL_QUEUES`:

   ```
   store_lines → build_content → store_products → store_autors
              → store_categories → store_editorials → store_colecciones
   ```

4. Each type has a dedicated manager (`inc/Geslib/Api/GeslibApiDb*Manager.php`) that writes products/terms through `GeslibApiDbProductsManager`, `GeslibApiDbTaxonomyManager`, etc.

Notable pieces: `Encoding` (character-set handling for legacy exports), `WooCommerce_Customizations`, list-table screens for files/log/queues/debug, and the largest CLI command group in the plugin.

### Covers

Namespace `Inc\Covers\` → `inc/Covers/`.

Downloads book-cover images and attaches them as WooCommerce featured images.

- `CoversApi` talks to the **Dilve DAPI** service (Guzzle) and to **CEGAL en Red** (WP HTTP API).
- Every attempt is audited in `wp_covers_log` (run summary) and `wp_covers_lines` (one row per ISBN: origin/target URL, status, error, attempts).
- Images are stored under `wp-content/uploads/portadas/` and linked with `set_post_thumbnail()`.
- Three **twice-daily** cron hooks cover the full sweep: general scan, missing covers, and subtitles.
- Admin screens expose the logs and line tables through `CoversLogListTable` / `CoversApiLinesListTable`.

---

## Admin pages

| Menu slug | Page | Module |
| --- | --- | --- |
| `biblio` | Biblio dashboard | core |
| `biblio_cpt` | Custom Post Types | core |
| `biblio_taxonomy` | Taxonomies | core |
| `geslib` | Geslib dashboard | Geslib |
| `geslib_files` | Geslib Files | Geslib |
| `geslib_log` | Geslib Log | Geslib |
| `geslib_queues` | Geslib Queues | Geslib |
| `covers` | Covers dashboard | Covers |
| `covers_logs` | Covers log | Covers |
| `covers_lines` | Covers lines | Covers |

Screens are rendered from `templates/` (`admin*.php`, `tabscontent/`) and backed by WP_List_Table subclasses in the module's `Api/` folder.

---

## WP-CLI commands

All commands are registered from each module's `Init::get_services()`.

**Geslib**

| Command | Purpose |
| --- | --- |
| `wp geslib hello` | Smoke test — verifies the command layer loads |
| `wp geslib showTables` | List the plugin's custom tables |
| `wp geslib log` | Show import log entries |
| `wp geslib lines` | Inspect parsed export lines |
| `wp geslib processAll` | Run the whole import pipeline |
| `wp geslib processAllAuthors` | Import authors only |
| `wp geslib storeProducts` | Build WooCommerce products from the queue |
| `wp geslib storeAuthors` | Import authors |
| `wp geslib storeEditorials` | Import editorials |
| `wp geslib storeProductCategories` | Import product categories |
| `wp geslib truncateTable` | Empty a plugin table |
| `wp geslib clear_queue` | Clear pending queue entries |
| `wp geslib deleteAllProducts` | **Destructive** — remove all imported products |
| `wp geslib deleteAllTerms` | **Destructive** — remove imported terms |
| `wp geslib deleteEditorials` | **Destructive** — remove editorial terms |
| `wp geslib deleteProductCategories` | **Destructive** — remove category terms |

**Covers**

| Command | Purpose |
| --- | --- |
| `wp covers scanProducts` | Scan products missing a cover and fetch images |
| `wp covers scanSubtitles` | Scan/fetch subtitle data |

> Destructive commands are listed for completeness — always confirm what they will delete before running them on production.

---

## Cron jobs

| Hook | Schedule | Owner |
| --- | --- | --- |
| `geslib_cron_event` | every 2 hours | Geslib import pipeline |
| `geslib_removeUncategorized_cron_event` | every 2 hours | Geslib cleanup |
| `covers_cron_event` | twice daily | Cover download scan |
| `covers_missing_cron_event` | twice daily | Retry missing covers |
| `covers_subtitles_cron_event` | twice daily | Subtitle scan |

Schedules are self-healing: `Cron::ensure_twohourly()` re-registers the event if it was removed (for example by a plugin reactivation), and the Geslib run is time-boxed so a large file resumes on the next cycle instead of hitting the PHP timeout.

---

## Database

Four custom tables are created on activation with `dbDelta` (see `plantuml/database.plantuml` for the diagram):

| Table | Purpose |
| --- | --- |
| `wp_geslib_log` | One row per imported file: status, cycles, line count, start/end |
| `wp_geslib_queues` | Pending work items (`type` + `data` JSON payload) |
| `wp_covers_log` | One row per cover run: scanned/processed counters, status |
| `wp_covers_lines` | One row per cover attempt: ISBN, URLs, attempts, error |

Schema migrations (missing columns such as `booktitle`, `book_id`, `cycle`) run idempotently on activation and on module boot.

Options written by the plugin: `biblio_settings`, `dilve_settings`, `cegal_settings`, `geslib_settings`, `biblio_cpt`, `biblio_taxonomy`.

---

## Front-end build

Sources live in `assets/` and compile into `dist/`:

| Source | Output |
| --- | --- |
| `assets/scss/{biblio,covers,coversAdmin,geslibAdmin}.scss` | `dist/css/*.css` |
| `assets/js/*.js` (Browserify + Babel) | `dist/js/*.js` |

```bash
npx gulp          # default task: build styles + JS, then browser-sync watch
```

The default task compiles, serves `http://larepartidora`, and rebuilds on SCSS/JS changes while reloading on PHP changes. The proxy host is set by `projectURL` in `gulpfile.js`.

---

## Testing

```bash
composer install                       # pulls PHPUnit 10 + Mockery
./vendor/bin/phpunit                   # full suite
./vendor/bin/phpunit tests/Unit        # unit suite only
```

- PHPUnit 10 + Mockery; tests live in `tests/Unit/` (`tests/Integration/` is configured in `phpunit.xml`).
- Bootstrap is `tests/bootstrap.php`; shared fixtures extend `tests\UnitTestCase`.
- Coverage is configured to include `inc/`.

Run the **vendor-local** binary (`./vendor/bin/phpunit`): the `npm test*` scripts expect a globally installed `phpunit`.

---

## Documentation

| File | Contents |
| --- | --- |
| [`AGENTS.md`](AGENTS.md) | Canonical development guide: architecture, commands, pitfalls, conventions |
| [`TESTING.md`](TESTING.md) | Testing conventions and coverage notes |
| [`GEMINI.md`](GEMINI.md) | Earlier project notes (partially stale — prefer `AGENTS.md`) |
| [`REFACTOR.md`](REFACTOR.md), [`REFACTORING.md`](REFACTORING.md) | Refactoring history and plans |
| `Formato exportación GeslibPLUS a Internet.pdf` | GeslibPLUS export format specification |
| `ESPECIFICACIONES_PETICIONES_SEPTIEMBRE_2019_V4.00.pdf` | CEGAL en Red web-service specification |
| `plantuml/database.plantuml` | ER diagram of the custom tables |

---

## License

GPL-3.0. See `composer.json` and `package.json`.
