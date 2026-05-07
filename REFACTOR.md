# Biblio Plugin Refactoring Plan

## Executive Summary

The codebase has significant tight coupling (151+ `new` instantiations), duplicated patterns, and inconsistent architecture. The proposed refactoring simplifies without losing functionality.

---

## Priority 1: Service Container & Dependency Injection

### Current Issue
151 instances of manual `new SomethingApi()` throughout the codebase, making testing impossible and changes difficult.

### Proposed Solution
Use the existing `ServiceContainer` class with proper registration.

| File | Change |
|------|--------|
| `inc/Base/ServiceContainer.php` | Extend to register all managers as singletons |
| `inc/Geslib/Init.php` | Register all Geslib services |
| `inc/Covers/Init.php` | Register all Covers services |

### Justification
- Existing `ServiceContainer` class is underutilized
- Already implements factory/singleton patterns
- Enables unit testing by enabling mock injection
- Reduces code by ~100 lines of instantiations

### Example After
```php
// Before (tight coupling)
$geslibApiDbQueueManager = new GeslibApiDbQueueManager;

// After (dependency injection)
$geslibApiDbQueueManager = $container->get(GeslibApiDbQueueManager::class);
```

---

## Priority 2: Extract Shared Database Operations

### Current Issue
Each manager (`GeslibApiDbManager`, `GeslibApiDbLinesManager`, `GeslibApiDbQueueManager`, etc.) has duplicate methods: `count()`, `insert()`, `update()`, `delete()`, `truncate()`.

### Proposed Solution
Create a base trait or abstract class with common CRUD operations.

| File | Change |
|------|--------|
| `inc/Api/DbOperationsTrait.php` | New trait with shared DB methods |
| `inc/Geslib/Api/GeslibApiDbManager.php` | Use trait |

### Justification
- DRY principle violation
- Each manager repeats `wpdb->insert()`, `wpdb->update()`, etc.
- ~200 lines of duplicate code can be reduced to ~30

### Proposed Trait Methods
```php
trait DbOperationsTrait {
    protected function count(string $table): int;
    protected function insert(array $data): int;
    protected function update(int $id, array $data): bool;
    protected function delete(int $id): bool;
    protected function truncate(string $table): bool;
}
```

---

## Priority 3: Centralize Table Constants

### Current Issue
Table names defined in multiple places with inconsistent constants.

### Proposed Solution
Single constants class.

| File | Change |
|------|--------|
| `inc/Api/TableConstants.php` | New class with all table constants |

### Justification
- `GESLIB_LINES_TABLE`, `GESLIB_LOG_TABLE`, `GESLIB_QUEUES_TABLE` scattered
- Easy to misspell, hard to maintain
- Single point of truth

---

## Priority 4: Extract Line Type Configuration

### Current Issue
Static arrays with 60+ keys in `GeslibApiLines.php` (lines 11-125) scattered and hard to maintain.

### Proposed Solution
Configuration class/file.

| File | Change |
|------|--------|
| `inc/Geslib/Config/LineTypes.php` | New config class |
| `inc/Geslib/Api/GeslibApiLines.php` | Import from config |

### Current
```php
static $productKeys = ["type", "action", "geslib_id", "description", "author", ...]; // 60+ keys
static $editorialKeys = ["type", "action", "geslib_id", "name", "name_short", ...];
```

### After
```php
// LineTypes.php
return [
    'product' => ['type', 'action', 'geslib_id', 'description', ...],
    'editorial' => ['type', 'action', 'geslib_id', 'name', ...],
];

// Usage
$keys = LineTypes::get('product');
```

### Justification
- External configuration easier to maintain
- Single source of truth for Geslib format
- Enables easy addition of new line types
- Documentation improvement

---

## Priority 5: Remove Dead/Redundant Code

### Current Issue
Several unused methods and code blocks.

| Location | Issue |
|----------|-------|
| `GeslibApiLines::process6TE()` | Empty method (line 367) |
| `GeslibApiLines::processAUTBIO()` | Not called anywhere (line 491) |
| `BaseController::managers` | Set but never used |

### Justification
- Cleaner codebase
- Fewer confusion points
- Easier debugging

---

## Priority 6: Standardize Logger Injection

### Current Issue
20 instances of `new BiblioApi()` just for logging.

### Proposed Solution
Logger as separate injectable service.

| File | Change |
|------|--------|
| `inc/Api/LoggerInterface.php` | Already exists, use consistently |
| Remove | `$this->biblioApi = new BiblioApi()` boilerplate |

### Justification
- Follows existing interface pattern
- Easier to swap loggers (file, DB, remote)
- Cleaner code

---

## Priority 7: WP-CLI Command Consolidation

### Current Issue
17 separate command files with duplicated patterns.

### Proposed Solution
Consolidate to 3-4 commands with subcommands.

| File | Change |
|------|--------|
| `inc/Geslib/Commands/` | Consolidate to 3-4 commands with subcommands |

### Proposed Structure
```
wp geslib process all      -> combines process-all, process-all-authors
wp geslib store products  -> combines store-products, store-authors, store-editorials
wp geslib delete all     -> combines delete-products, delete-all-terms
wp geslib queue          -> queue subcommands
```

### Justification
- 17 files → 4 reduces complexity
- Consistent command pattern
- Easier to maintain

---

## Priority 8: Add Unit Tests for Core Classes

### Current Issue
29 tests exist but don't cover key business logic.

| Class | Test Coverage Needed |
|-------|-------------------|
| `GeslibApiLines` | 0% |
| `GeslibApiDbQueueManager` | 0% |
| `CoversApi` | 0% |

### Justification
- Ensure refactoring doesn't break functionality
- Document expected behavior
- Enable future changes safely

---

## Risk Assessment

| Priority | Risk | Mitigation |
|----------|------|------------|
| Priority 1 | Breaking changes | Run existing tests first |
| Priority 2 | Query customizations | Use protected methods, override per manager |
| Priority 4 | Format changes | Version the line types |

---

## Estimated Impact

| Area | Current | After | Reduction |
|------|---------|-------|-----------|
| Files | 89 | 75 | -14% |
| Lines of code | ~10,000 | ~7,500 | -25% |
| Test coverage | 29 tests | 60+ tests | +100% |

---

## Implementation Order

1. **Run existing tests** - Ensure baseline passes
2. **Priority 5** - Remove dead code first (low risk)
3. **Priority 3** - Centralize constants
4. **Priority 4** - Extract line types configuration
5. **Priority 1** - Service container and DI
6. **Priority 2** - Shared DB operations trait
7. **Priority 6** - Standardize logger
8. **Priority 7** - WP-CLI consolidation
9. **Priority 8** - Add tests
10. **Run tests** - Verify all pass