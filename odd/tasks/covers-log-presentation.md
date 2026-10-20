# ODD Feature: Covers / Missing-Covers Log Presentation Rework

## Objective
Make `logs/debug-covers-*.log` and `logs/debug-missingcovers-*.log` small, single-line, greppable
and structured (geslib-style phases + summary), by fixing the shared log format, the content of
every call site feeding those two channels, and the run structure of both covers crons.

## Problem / Why
User review (2026-10-19): geslib logs are good; covers/missingcovers files are huge and hard to
parse. Verified causes: ~18-21 log entries per product (covers) and 5+ per product
(missingcovers) with unbounded backlog queries; raw dumps (`var_export` product row,
CoversApi.php:391; full CEGAL XML incl. base64, :303); plaintext Dilve/Cegal credentials in
logged queries (:256-257, :399 from `get_query()` :40-57); FileLogger renders 2-3 physical
lines per entry with the timestamp on line 1, empty titles, and every entry forced to DEBUG
(`BiblioApi::debug_log`); no phase markers/durations/summary; channel contamination
(`CoversApiDbManager::set_featured_image_for_product` :157-172 logs to `missingcovers` but is
called from the covers cron); dead/duplicate noise (:410/:415 double FILEPATH, duplicate
`case "17"` :193/:198, `:465` logs batch_size as "processed", wrong progress math :457,
unreachable logs CoversApiDbManager:226-241).

## Authorized scope (user: "Yes do the full rework (A+B+C)")
- **A — FileLogger format**: one physical line per entry,
  `Y-m-d H:i:s [LEVEL] [type] title: message` (title omitted when empty); sanitize newlines
  to ` | `, strip control chars, trim, defensive cap at 2000 chars
  (  `... [truncated, N bytes]`), `LOCK_EX`. Shared with geslib: geslib log files get the new
  line format too (their phase/summary CONTENT conventions untouched).
- **B — Call-site content** across `inc/Covers/Api/CoversApi.php`, `CoversApiDbManager.php`,
  `CoversApiDbLinesManager.php`, `CoversApiDbLogManager.php`: no bodies/queries/var_export/
  credentials; one line per product outcome or anomaly; real levels via
  `getLogger()->info/error/debug` (no `INFO `/`ERROR ` prefixes in titles); empty titles get
  `__CLASS__:__LINE__ __FUNCTION__` like siblings; remove dead/duplicate/wrong lines; fix
  channel contamination after verifying callers.
- **C — Run structure** in `inc/Covers/Base/Cron.php`: `=== COVERS CRON START ===`,
  `--- Phase: DILVE/CEGAL ---` + per-phase duration, per-batch single line, final
  `=== COVERS CRON SUMMARY === Duration | Scanned | Attached | Invalid EAN | Failed | Memory peak ===`;
  same for missingcovers (`Candidates | Found | Attached | Already had | Missing | Failed`).
  Stats plumbed from `scanProducts` / `attach_local_missing_covers` while PRESERVING their
  current return contracts and all SQL/batch behavior.

## Explicitly out of scope
- No SQL/query behavior changes, no batch-size changes, no changes to which products are picked up.
- No PR/push (delivery is the human's decision). No changes to geslib log CONTENT.
- Do not touch the pre-existing uncommitted deletion `assets/js/larepartidora.code-workspace`
  or untracked `.codegraph/`.

## Constraints
- Match file indentation (2 spaces in Geslib/Covers; verify per file), keep file's text domain.
- Generated artifacts (code/comments/UI copy) in English; conventional commits, no AI attribution.
- Never write credentials into code, docs, tests, or logs.
- Existing behaviors observable in admin/CLI must keep working.

## Checklist
- [ ] T1 — Branch + this doc (route: inline; trigger: ODD protocol step 5)
- [ ] T2 — Tier A: FileLogger single-line format + unit tests updated/added
      (route: delegated writer; trigger: writer rule — 6+ non-trivial files)
- [ ] T3 — Tier B: CoversApi + 3 Covers DB managers call-site content rework
      (route: delegated writer, same batch as T2/T4)
- [ ] T4 — Tier C: Covers cron phase/summary structure + stats plumbing + channel fix
      (route: delegated writer, same batch)
- [ ] T5 — Verification: `./vendor/bin/phpunit` green, `php -l` on every edited file,
      independent verifier against this spec, parent spot-check of one reported command
- [ ] T6 — Work-unit commit(s) with conventional messages; record commit ids here
- [ ] T7 — Close: report outcome, checks run, any failures

## TDD
Mode: **off** (no strict_tdd config found in session/engram; project has no TDD mandate in
AGENTS.md). Runner: `./vendor/bin/phpunit` (vendor binary, never global).
Ordinary functional checks apply: suite green + lint clean. Tests still move WITH the change
(FileLoggerTest asserts the old format → must be updated in the same commit).

## Route declaration
Delegated direct (writer): mapping already delegated (explore handoff), write touches 6+
non-trivial files → mandatory writer trigger. Verification: writer self-verification + fresh
independent verifier (native risk assess unavailable on this runtime → treated as high) +
parent spot-check.

## Delivery strategy
Chosen: `ask-on-risk`, resolved 2026-10-19 by user decision → **`stacked-to-main`**.
Forecast was ~350-500 authored lines; actual 506 → split into two stacked PR slices:
PR #1 = `848b3eb6` (254 lines, logger format + tests), PR #2 = `934c0e6c` + docs commit
(351 + docs lines, covers call sites + cron summaries). Both merge to main in order.
No PR is created unless the user asks.

## Progress
- [x] T1 done — branch `refactor/covers-log-presentation`, doc created.
- [x] T2-T4 done — writer implemented A+B+C (7 files, 506 authored changed lines:
      365 insertions + 141 deletions across FileLogger, CoversApi, 3 Covers DB managers,
      Covers Cron, FileLoggerTest; pre-existing ` D assets/js/larepartidora.code-workspace`
      intentionally left unstaged).
- [x] T5 done — writer self-verification + independent verifier (PASS, 0 critical/major;
      noted MINOR: commented-out debug_log strings remain inside a pre-existing block
      comment CoversApiDbManager.php:229-238; per-product helper lines still ~6-8 for a
      successful Dilve attach, down from ~18-21) + parent spot-check. `./vendor/bin/phpunit`:
      58 tests / 98 assertions green, exit 0 (1 pre-existing schema-9.5 deprecation);
      `php -l` clean on all 7 files.
- [x] T6 done — work-unit commits on `refactor/covers-log-presentation` (stacked-to-main,
      user-chosen 2026-10-19):
      - `848b3eb6` refactor(biblio): write one sanitized single line per log entry
        (254 authored lines — FileLogger + FileLoggerTest + this doc) = **PR slice 1**
      - `934c0e6c` refactor(covers): shrink covers/missingcovers logs to outcome lines and
        cron summaries (351 authored lines — 5 covers files) = **PR slice 2 (code)**
      - docs commit recording these ids = PR slice 2 (docs)
      Pre-existing deletion `assets/js/larepartidora.code-workspace` and untracked
      `.codegraph/` remain unstaged/uncommitted by design.
- [x] T7 done — closed 2026-10-19: both units verified (suite green, lint clean,
      independent verifier PASS, parent spot-check green); nothing pushed, no PR created
      (delivery remains the human's decision).

## Evidence
Verification evidence recorded in Progress (T5/T6).

- Focused test command: `./vendor/bin/phpunit tests/Unit/Api/FileLoggerTest.php` → 10 tests,
  18 assertions, OK. Full suite `./vendor/bin/phpunit` → 58 tests, 98 assertions, OK
  (1 pre-existing phpunit.xml schema-9.5 deprecation). `php -l` clean on all 7 files.
- Runtime harness: N/A — cron scheduling needs a live WP install; behavior preservation
  instead verified by an independent HEAD-vs-worktree diff review (SQL, batch size,
  hasMore logic, schedules and public return contracts identical).
- Rollback boundaries: revert `848b3eb6` alone removes the format change (FileLogger +
  test + this doc); revert `934c0e6c` alone removes the covers content/cron rework; the
  two units do not depend on each other at runtime (call sites tolerate either format).
