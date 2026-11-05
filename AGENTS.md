# AGENTS.md

Agent instructions for working with prado-sqlmap. Supplements `CLAUDE.md`.

## Build, Lint, and Test Commands

```bash
# Lint (dry-run, no changes)
vendor/bin/php-cs-fixer fix --dry-run

# Lint (apply fixes)
vendor/bin/php-cs-fixer fix

# Static analysis
vendor/bin/phpstan analyse --memory-limit=512M

# Run pure unit tests (no database required)
vendor/bin/phpunit --testsuite unit

# Run main database-backed test suites
vendor/bin/phpunit --testsuite db-sqlite
vendor/bin/phpunit --testsuite db-mysql
vendor/bin/phpunit --testsuite db-pgsql
vendor/bin/phpunit --testsuite db-firebird
vendor/bin/phpunit --testsuite db-sqlsrv
vendor/bin/phpunit --testsuite db-oracle
vendor/bin/phpunit --testsuite db-ibm

# Filter within a suite
vendor/bin/phpunit --testsuite unit --filter <File, ClassName, or methodName>
```

### Composer shortcuts

```bash
composer fix       # apply cs-fixer style fixes to src/
composer stan      # phpstan analyse --memory-limit=512M
composer test      # = vendor/bin/phpunit --testsuite unit
composer unittest  # = vendor/bin/phpunit --testsuite unit
composer dbtest    # = vendor/bin/phpunit --testsuite db-mysql,db-pgsql,db-sqlite,db-firebird
composer fulltest  # full pre-commit check: php -l, cs-fixer dry-run, phpstan, phpunit unit
```

Run `composer fix` before `composer fulltest` to auto-apply style fixes; `fulltest` runs the cs-fixer in dry-run (check-only) mode.

> **Never** add `--verbose` or other extra flags to phpunit commands not listed above — it is not a valid flag.

## PHP Coding Standards

- Indentation: **tabs** (not spaces)
- Line endings: Unix `\n`
- PHP 8.2+ syntax; CI tests 8.2, 8.3, 8.4, 8.5
- PSR-12 enforced via php-cs-fixer
- Uniform Access / Self Encapsulation: access own fields only through getters/setters, never `$this->_prop` directly in subclasses

## Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Classes | `TPascalCase` | `TSqlMapManager`, `TResultMap` |
| Methods | `camelCase` | `queryForObject`, `getResultMap` |
| Variables | `camelCase` | `$parameterMap`, `$resultClass` |
| Constants | `SCREAMING_SNAKE_CASE` | `QUERY_FOR_LIST` |
| Enumeration values | `PascalCase` | `LRU`, `FIFO` |
| Private / protected properties | `_camelCase` | `_sqlmapConfigFile` |
| Namespace | `Prado\Data\SqlMap\{Sub}` | `Prado\Data\SqlMap\Configuration\TParameterMap` |

## Documentation Standards

All docblocks must pass the documentation style enforced by prado.data. The same rules apply here.

Language and National Variety: English — American  
Qualities of the writing: clear, thorough, easy to comprehend, not verbose (brevity), timeless, integrated, wholistic  
Tense: Present

**Banned constructions:**

- **Antithesis** — "does not just X, it Ys"; "rather than X, it Ys". State once what it does.
- **Em-dash dramatic asides** — no "— and that's the point", "— never stronger". Use a period.
- **Editorializing / filler** — "importantly", "of course", "it is worth noting". Omit.
- **Rule-of-three rhetorical build-ups**. One fact per sentence.

Prefer: subject–verb–object declaratives and `condition → result` bullet/table pairs.

### New API elements

- **`@since` tag** — use the next release version when adding new public methods or classes; omit the method tag when it matches the class tag. The authoritative next-release version number is in prado's `AGENTS.md` (see `vendor/pradosoft/prado/AGENTS.md`).
- `@author` names: Wei Zhuo, Brad Anderson, Fabio Bas — only these three.

## Error Handling

- Extension exceptions inherit from `TSqlMapException` or a subclass in `src/Data/SqlMap/DataMapper/`.
- Error message strings reference `messages.txt` in the same directory via `Prado::localize()`.
- Do not silently swallow exceptions; rethrow or convert to `TSqlMapException` at the SqlMap boundary.

## Framework-Specific Guidelines

### TComponent and TApplication

This extension does not define standalone TComponent classes — it participates in the host PRADO application via `TSqlMapConfig`, a `TDataSourceConfig` subclass. When the host application loads the extension, it calls `TSqlMapConfig::init()`. All Prado event, property, and module APIs are available through the inherited TComponent/TModule chain.

### TSqlMapConfig Connection Patterns

Two supported patterns for specifying the database connection:

**Pattern 1 — ConnectionID reference** (production, recommended):
```xml
<modules>
  <module id="db" class="System.Data.TDataSourceConfig">
    <database ConnectionString="sqlite:path/to/db.sq3"/>
  </module>
  <module id="sqlmap" class="Prado\Data\SqlMap\TSqlMapConfig"
          ConnectionID="db" ConfigFile="sqlmap.xml"/>
</modules>
```

**Pattern 2 — Inline `<database>` child element** (self-contained tests):
```xml
<module class="Prado\Data\SqlMap\TSqlMapConfig" ConfigFile="sqlmap.xml">
  <database ConnectionString="sqlite:path/to/db.sq3"/>
</module>
```

### SqlMap XML mapping files

Statement files loaded by `TSqlMapXmlMappingConfiguration`. Key tag names:

- `<select>` / `<insert>` / `<update>` / `<delete>` — mapped statements
- `<resultMap>` — result-row-to-object mapping
- `<parameterMap>` — explicit parameter-to-column mapping (superseded by inline `#prop#` syntax)
- `<cacheModel>` — result caching; types: `MEMORY`, `LRU`, `FIFO`

**Inline parameter syntax** — `#propertyName,dbType=VARCHAR#` in SQL strings.  
**Dynamic SQL token** — `$propertyName$` (simple substitution, use with caution — no escaping).  
**`##` is not an escaped `#`** — the second `#` of `##` opens the next inline span; do not use `##` in SQL templates.

### TInlineParameterMapParser

Parses `#([^#]+)#` — the interior must contain at least one non-`#` character. Two adjacent `#` characters (`##`) do not form an escaped literal; the second `#` begins the next match span.

## Testing

### Test Suite Boundaries

| Suite | Description | DB needed |
|---|---|---|
| `unit` | Pure unit: Configuration/, DataMapper/, Statements/, SqlMapSleepTest | No |
| `db-sqlite` | SQLite driver: all abstract DbSpecific base classes via Sqlite/ wrappers | Yes — SQLite file |
| `db-mysql` | MySQL driver | Yes |
| `db-pgsql` | PostgreSQL driver | Yes |
| `db-firebird` | Firebird driver | Yes |
| `db-sqlsrv` | SQL Server driver | Yes |
| `db-oracle` | Oracle driver | Yes |
| `db-ibm` | IBM DB2 driver | Yes |

### Adding a Test

**Pure unit test** — place in `tests/unit/Data/SqlMap/Configuration/`, `DataMapper/`, or `Statements/`. Extend `PHPUnit\Framework\TestCase` directly.

**DB-dependent test** — add the `abstract` test method to the relevant base class in `tests/unit/Data/SqlMap/` (e.g., `StatementTest.php`). All driver wrappers under `DbSpecific/*/SqlMap/` inherit it automatically; no changes needed in those files.

**New driver wrapper** — create `tests/unit/Data/SqlMap/DbSpecific/<Driver>/<Driver><TestName>Test.php`:
```php
<?php
require_once(__DIR__ . '/../../StatementTest.php');

class <Driver>StatementTest extends StatementTest
{
    protected static string $configClass = '<Driver>BaseTestConfig';
}
```
Add the matching `<Driver>BaseTestConfig` class to `tests/unit/Data/SqlMap/common.php`.

### BaseCase Pattern

All DB-dependent base classes in `tests/unit/Data/SqlMap/` are `abstract class`. PHPUnit skips abstract classes without needing `@codeCoverageIgnore`. Concrete driver wrappers in `DbSpecific/` set:

```php
protected static string $configClass = 'SQLiteBaseTestConfig'; // or MySQL, Pgsql, …
```

Empty string (`''`) falls through to `BaseTestConfig::createConfigInstance()`.

### Test Bootstrap

`tests/test_tools/phpunit_bootstrap.php` loads the Composer autoloader, `Prado.php`, `PradoUnitRequires.php` (the full PRADO test harness: `PradoUnit`, `PradoUnitDataConnectionTrait`, `TTestApplication`, `TarTestHelper`), and boots a `TTestApplication`. No `app/` directory required — `TTestApplication` uses `sys_get_temp_dir()` and creates its own `runtime/`. The bootstrap is required because `TSqlMapApplicationCache` calls `Prado::getApplication()`.

Individual test files do not need any harness `require_once` — the bootstrap covers all of them.

`tests/test_tools/phpstan-bootstrap.php` loads the autoloader and `Prado.php` for PHPStan.

## Static Analysis (PHPStan)

`phpstan.neon` at the project root registers all 7 PRADO PHPStan extensions from `vendor/pradosoft/prado`. These extensions handle:

- `DynamicMethodsClassReflectionExtension` — `getXxx`/`setXxx` method magic
- `TComponentPropertiesReflectionExtension` — TComponent property access
- `TComponentHasMethodTypeSpecifyingExtension`
- `TComponentCanGetPropertyTypeSpecifyingExtension`
- `TComponentCanSetPropertyTypeSpecifyingExtension`
- `TComponentIsaTypeSpecifyingExtension`
- `PradoMethodVisibleStaticMethodTypeSpecifyingExtension`

Level: 2. Paths: `src/`.

## Directory Structure

```
prado-sqlmap/
├── composer.json               — prado4-extension; bootstrap = TSqlMapConfig
├── phpstan.neon                — level 2; registers 7 Prado\PHPStan\* extensions
├── CLAUDE.md                   — concise guidance for agents
├── AGENTS.md                   — this file
├── src/
│   └── Data/
│       └── SqlMap/
│           ├── TSqlMapConfig.php
│           ├── TSqlMapGateway.php
│           ├── TSqlMapManager.php
│           ├── Configuration/
│           ├── DataMapper/
│           └── Statements/
└── tests/
    ├── test_tools/
    │   ├── phpunit_bootstrap.php
    │   └── phpstan-bootstrap.php
    └── unit/
        └── Data/
            ├── SqlMap/         — abstract base test classes + pure unit subdirs
            │   ├── common.php  — DB config classes (BaseTestConfig subclasses)
            │   ├── BaseCase.php
            │   ├── Configuration/
            │   ├── DataMapper/
            │   ├── Statements/
            │   ├── maps/       — SqlMap XML mapping files
            │   ├── scripts/    — per-driver schema SQL
            │   └── sqlite/     — SQLite .db files
            └── DbSpecific/
                ├── Mysql/
                ├── Pgsql/
                ├── sqlite/
                ├── Firebird/
                ├── SqlSrv/
                ├── Oracle/
                └── Ibm/
```

## Anti-Patterns and Required Safeguards

- **Never** run `git clone/mv/restore/rm/add/commit/merge/rebase/reset/pull/push/fetch` without developer approval first.
- **Never** run `rm` on any path without developer approval first.
- **Never** remove `composer --dev` dependencies.
- **Never** erase or overwrite files during unit testing — the file changes being tested must be preserved.
- **Never** delete any folders or files until the associated task is absolutely and totally complete.
- **Never** add `--no-verify` to git commands or `--no-strict` to phpunit.
- **Never** add `MSSQLBaseTestConfig` or similar broken config classes — verify property names against `BaseTestConfig` before creating a new subclass.
- **Never** use `##` as a literal `#` escape in SqlMap SQL templates — it is not an escape sequence and breaks `TInlineParameterMapParser`.
- **Never** create DB-specific test classes that are not `abstract` unless they are concrete driver wrappers under `DbSpecific/`.

## Working Knowledge Sub-Process

When asked to understand a subsystem before modifying it, follow this pattern:

1. Read the primary class file(s) for the subsystem.
2. Read the corresponding test files to understand expected behavior.
3. Check `common.php` for relevant config classes if the subsystem touches DB connection.
4. Run `vendor/bin/phpunit --testsuite unit` (no DB) to confirm the baseline.
5. Only then propose changes.

When asked to add support for a new database driver:

1. Add `<Driver>BaseTestConfig` to `tests/unit/Data/SqlMap/common.php` following the existing pattern (implement `getConnection()`, `getSqlMapConfigFile()`, `getScriptDir()`, `getScriptRunner()`, `hasFeature()`).
2. Create `tests/unit/Data/SqlMap/DbSpecific/<Driver>/` directory.
3. For each abstract base class in `tests/unit/Data/SqlMap/`, create a concrete wrapper with `require_once(__DIR__ . '/../../BaseClassName.php')`.
4. Add a `<testsuite name="db-<driver>">` entry to `phpunit.xml` covering `tests/unit/Data/SqlMap/DbSpecific/<Driver>/`. The `unit` suite already excludes the entire `DbSpecific/` directory.
5. Add SQL schema script(s) to `tests/unit/Data/SqlMap/scripts/<driver>/`.
