# P1-09 — DatabaseGuard: eliminate dead `catch (\Exception)` around `$wpdb`

## Objective
Replace every unreachable `try { $wpdb->… } catch (\Exception) { log }` block with a single
shared guard that detects real `$wpdb` failures (`false === $result || $wpdb->last_error`),
logs them, and propagates `false` so failures stop being silently swallowed.

## Problem
`$wpdb` methods never throw (verified against `wp-includes/class-wpdb.php`: zero `throw`
statements). They return `false`/`null`/`[]` and populate `$wpdb->last_error`. Therefore every
`catch (\Exception)` around a pure `$wpdb` call is unreachable: the log line never runs and the
fallback value never applies. The plugin's entire DB error-logging layer is decorative — this is
the stated root cause behind F-01 being invisible.

## Why now
improvementproposal.md priority-1 item P1-09 (§7 logging overhaul). Highest-value logging fix
in the plugin: it is the mechanism that made earlier real failures undetectable.

## Evidence (exploration map, 73 catch blocks in `inc/`)
- **49 dead** (try body = non-throwing `$wpdb` only) → in scope.
- **1 partial** (`CoversApiDbLoggerManager::coversLogger`) → in scope, but also references an
  undefined static property (pre-existing fatal, `catch(\Exception)` cannot catch `Error`).
- **23 other** (no `$wpdb` in try, or genuinely throwing) → **out of scope, must NOT be swept**.
  Includes `CoversApi::fetch_cover` (Guzzle really throws `ConnectException`/`RequestException`),
  all WooCommerce CRUD / term / filesystem catches, `wp_delete_term`, `deleteTerm`.

### Dead-catch census by file
| File | Dead |
|---|---|
| `inc/Geslib/Api/GeslibApiDbLogManager.php` | 17 |
| `inc/Geslib/Api/GeslibApiDbQueueManager.php` | 9 |
| `inc/Geslib/Api/GeslibApiDbLinesManager.php` | 7 |
| `inc/Api/DbOperationsTrait.php` | 5 (trait never `use`d — zero risk) |
| `inc/Covers/Api/CoversApiDbLinesManager.php` | 4 |
| `inc/Covers/Api/CoversApiDbLogManager.php` | 3 |
| `inc/Geslib/Api/GeslibLogListTable.php` | 2 |
| `inc/Geslib/Api/GeslibApiDbLoggerManager.php` | 1 (broken: undefined constant) |
| `inc/Covers/Api/CoversApiDbLoggerManager.php` | 1 (broken: undefined static prop) |
| `inc/Covers/Api/CoversApiDbManager.php` | 1 (`countAllProducts`) |
| **Total** | **49** |

The four methods named in the proposal: `insertData()` and `updateGeslibLines()`
(`GeslibApiDbLinesManager`), `deleteItemFromQueue()` (`GeslibApiDbQueueManager`) — all Geslib;
`attachFile()` is **Covers** (`CoversApiDbManager`) and its try body contains no `$wpdb` at all
(only `file_put_contents`), so it belongs to bucket `other` and is handled as a documented
exception, not swept into a `$wpdb` guard.

## Decisions (user-approved)
- **Helper placement**: `Inc\Biblio\Api\DatabaseGuard` in `inc/Api/` — the only shared layer all
  three modules already depend on. Proposal's `GeslibDatabase` name rejected because the sweep
  spans Geslib + Covers + Biblio and a Geslib-namespace helper would force reverse dependencies.
- **Scope**: sweep *every* `$wpdb` call site in the dead bucket (all 49), not only the four
  named methods.

## Constraints
- No `declare(strict_types=1)` anywhere in the plugin; return coercion stays coercive.
- Match each file's local indentation (2 spaces in `Geslib/`/`Covers/Api`; `inc/Api` uses 4).
- Keep each file's existing i18n text domain; English artifacts; conventional commits; no push.
- Never touch the 23 `other` catches (especially the Guzzle cluster).
- Do not alter the log-file routing convention: `FileLogger` writes `debug-{type}-{date}.log`,
  so the module type (`geslib` / `covers` / `biblio`) must survive the sweep.
- Pre-existing defects are **flagged, not fixed**, unless they block this change:
  `DbOperationsTrait` unused; undefined constant/property in the two logger managers;
  `storeProductCategories()` has no definition; stale `$wpdb->insert_id` returned on failure.

## Behavioral hazards the sweep must neutralize (from map §G)
1. `get_var()` returns `null` on failure, `get_results(ARRAY_A)` returns `[]` — only
   `last_error` distinguishes them; hence `guard()` checks both conditions.
2. `false` into an `: array` return (`fetchLoggedFilesFromDb` → `listFilesInFolder(): array`)
   = TypeError. Today that path returns `[]`. Must not introduce a fatal.
3. `false` into `: int` coerces to `0` (no strict types) — no fatal, but value changes.
4. Stale `$wpdb->insert_id` returned on failure (`CoversApiDbLinesManager::insertLinesData`,
   `CoversApiDbLogManager::insertLogData`) feeds `set_url_origin(int)` → `WHERE id = 0`.
5. Several methods return `true` unconditionally; making them truthful flips existing
   `if (!…) → ERROR notice` and mid-pipeline abort paths — this is the intended effect of P1-09.
6. `processBatchBuildContent` catch does `continue`; a guard that returns without skipping can
   turn a DB failure into a loop bounded only by `$maxBatches`.
7. Display paths (`countGeslibLogStatus`, `getQueuedLogId`) change `echo "0"` → `echo ""`.
8. **Callers still ignore returns**: `insertData` (6 sites), `deleteItemFromQueue` (9 sites),
   Geslib `insertLogData` (2 sites) discard the result — propagation at the wrapper is necessary
   but not sufficient. Flagged as explicit follow-up, not silently ignored.

## Scope
**In**: `DatabaseGuard` class, its unit tests, replacing all 49 dead catches (plus the 1 partial
logger-manager catch), characterization tests for the highest-risk sites.
**Out**: the 23 `other` catches; fixing the pre-existing landmines listed above; changing
caller-side return handling beyond what the guard's return type requires to avoid new fatals;
log retention (W7).

## Checklist
- [x] T0 — Explore and map all 73 catch blocks; classify dead/partial/other; user picks placement.
- [x] T1 — Add `inc/Api/DatabaseGuard.php` + `tests/Unit/Api/DatabaseGuardTest.php`
       (success passthrough, `false` propagation, `last_error` detection, `null`/`[]` cases,
       logger injection so tests never touch the real logs dir). 10 tests.
- [x] T2 — Sweep Geslib: `GeslibApiDbLogManager` (17), `GeslibApiDbQueueManager` (9),
       `GeslibApiDbLinesManager` (7). Includes `insertData`, `updateGeslibLines`,
       `deleteItemFromQueue`. Neutralize hazards 2, 5, 6 where they apply. +5 tests.
- [x] T2b — CORRECTION: six `: bool` methods wrapping `delete`/`update`/`query` coerced a
       legitimate `0` row-count to `false`, reporting failure on a healthy database
       (`truncateGeslibLines` produced a false admin ERROR notice on an empty queue).
       Converted all six to `$result = guard(...); return false !== $result;`.
       +5 regression tests.
- [x] T3 — Sweep remaining: `GeslibLogListTable` (2), `DbOperationsTrait` (5),
       `CoversApiDbLinesManager` (4), `CoversApiDbLogManager` (3),
       `CoversApiDbManager::countAllProducts` (1) + the `attachFile` special case
       (its try wraps `file_put_contents`, not `$wpdb` — proposal premise was wrong there).
       Neutralize hazard 4 (stale `insert_id` → `false`).
       **Left in place deliberately**: `GeslibApiDbLoggerManager` and
       `CoversApiDbLoggerManager` — undefined constant/static property ⇒ PHP `Error`,
       uncatchable by `catch (\Exception)`, broken methods have zero callers; removing
       their dead catch changes nothing while churning broken code. +5 tests.
- [x] T4 — Live verification: forced a genuine MySQL failure (1048 NULL-into-NOT-NULL, and a
       nonexistent table) through the real wrapper `insertLinesIntoQueue()` and through a
       direct `guard()` call ⇒ `[ERROR - geslib - ...]` lines in
       `logs/debug-geslib-2026-10-18.log`; `guard()` returned `false`. Rule A+ proven live:
       `deleteItemFromQueue('probe_nonexistent_type', -999999, -999999)` → `true`, 0 rows,
       log file md5 unchanged. No collateral damage (row counts stable on every touched
       table; probes verified zero-match). phpunit 54/93, `php -l` 9/9, dead-catch census 0.
- [ ] T5 — Work-unit commits, RDD assess, memory, report.

## Decision learned during implementation (supersedes a naive reading of the fix)
**Rule A+**: never write `return DatabaseGuard::guard($wpdb->delete|update|query(...))` inside
a `: bool` method. `$wpdb` returns an int row-count that is legitimately `0`; `guard()` correctly
passes `0` through as success, but `: bool` coercion turns it into `false`. Use
`$result = guard(...); return false !== $result;`. `insert`-guarded one-liners are fine
(`insert` cannot legitimately return 0). This bit us once and was corrected before commit.

## Sizing / delivery status
- Authored lines (additions + deletions, generated files excluded):
  source 594 (238 add / 356 del across 8 modified files) + `DatabaseGuard` 71 + tests 993
  (251 + 434 + 308) + this document 127 = **1785 authored lines**.
- Planned work-unit commits (tests kept with the code they verify; docs with the change):
  1. `feat(biblio): add DatabaseGuard to detect real $wpdb failures` — class + its tests (~322).
  2. `refactor(geslib): replace dead $wpdb try/catch with DatabaseGuard` — 4 Geslib files + sweep tests (~856).
  3. `refactor(covers): replace dead $wpdb try/catch with DatabaseGuard` — Covers x3 + trait + tests + this doc (~607).
- **Over the ~400 authored-line delivery budget.** One honest slicing pass done; units 2 and 3
  cannot shrink further without splitting a single test file away from the code it verifies.
  Delivery strategy (`size:exception` vs chained PRs) is an open **user decision** — nothing
  has been pushed, so commits can be regrouped before any PR.

## Authorized scope
Repository `/var/www/html/larepartidora/wp-content/plugins/biblio`, branch
`fix/f-01-failure-propagation` (4 commits already present, base `a6101533`). Delivery (push/PR)
remains the user's decision. The unrelated working-tree deletion
`assets/js/larepartidora.code-workspace` must never be staged.

## Acceptance criteria
- Zero `catch (\Exception)` blocks whose try body is exclusively non-throwing `$wpdb` calls.
- The 23 `other` catches are byte-identical after the change (verified by diff).
- A forced DB failure produces an `ERROR` line in the module's `debug-{type}-{date}.log`.
- `guard()` returns `false` on failure and the original value on success; no new `TypeError` in
  any `: array`/`: int` path.
- `./vendor/bin/phpunit` green (baseline 29 tests / 68 assertions + 1 pre-existing deprecation).
- `php -l` clean on every touched file.

## Applicable checks
`./vendor/bin/phpunit` · `php -l` per touched file · targeted diff review that bucket `other`
is untouched · live `wp eval` probe forcing a failure and reading back the log line.

## Progress
- T0–T4 done and verified (see checklist for evidence per task).
- Final state: **dead `$wpdb` catches remaining: 0** plugin-wide. The 22 legitimately-throwing
  (`other`) catches are byte-identical, verified by empty `git diff --stat` on those files.
- Verification of record: `./vendor/bin/phpunit` → `Tests: 54, Assertions: 93,
  PHPUnit Deprecations: 1` (baseline 29/68 + 25 new tests; the single deprecation is the
  pre-existing phpunit.xml schema 9.5 one); `php -l` clean on all 9 changed source files;
  live forced-failure probe produced real `ERROR` log lines.
- Next step: T5 — three work-unit commits, RDD assess, memory, report.
