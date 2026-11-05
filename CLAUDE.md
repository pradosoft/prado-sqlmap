# CLAUDE.md

This file provides guidance to Agents when working with code in this repository.

## What This Is

**prado-sqlmap** is a PRADO 4 composer extension (`prado4-extension`) that provides the SqlMap data-mapper layer (iBATIS-style SQL mapping) for the PRADO PHP framework. All source lives under `src/Data/SqlMap/` (PSR-4 namespace `Prado\Data\SqlMap\`). Tests live under `tests/unit/Data/SqlMap/` (shared base classes) and `tests/unit/Data/DbSpecific/` (database-specific wrappers).

## Commands

```bash
# Code style check (dry-run)
vendor/bin/php-cs-fixer fix --dry-run src/

# Code style fix (apply)
vendor/bin/php-cs-fixer fix src/

# Static analysis
vendor/bin/phpstan analyse --memory-limit=512M

# Run pure unit tests (no database required)
composer unittest
# or:
vendor/bin/phpunit --testsuite unit

# Run unit + SQLite, MySQL, PostgreSQL, and Firebird together in one run
composer dbtest
# or:
vendor/bin/phpunit --testsuite unit,db-sqlite,db-mysql,db-pgsql,db-firebird

# Run a single database suite
composer db-sqlite
composer db-mysql
composer db-pgsql
composer db-firebird
composer db-sqlsrv
composer db-oracle
composer db-ibm
# or:
vendor/bin/phpunit --testsuite db-sqlite
vendor/bin/phpunit --testsuite db-mysql
vendor/bin/phpunit --testsuite db-pgsql
vendor/bin/phpunit --testsuite db-firebird
vendor/bin/phpunit --testsuite db-sqlsrv
vendor/bin/phpunit --testsuite db-oracle
vendor/bin/phpunit --testsuite db-ibm

# Filter to a specific test class or method
vendor/bin/phpunit --testsuite unit --filter <File, ClassName, or methodName>
```

### Full Check (required before git commit)

```bash
composer fulltest
```

Runs in order — all must pass:

1. PHP compile check: `find src -name '*.php' | xargs php -l`
2. `vendor/bin/php-cs-fixer fix --dry-run src/`
3. `vendor/bin/phpstan analyse --memory-limit=512M`
4. `vendor/bin/phpunit --testsuite unit`

Run `composer fix` first to auto-apply style fixes before the dry-run check.

> **Never add or change phpunit command options** when unit testing — only run project unit tests as specified above. phpunit does NOT have a `--verbose` CLI option.

## Architecture

### Extension Entry Point

**`TSqlMapConfig`** (`src/Data/SqlMap/TSqlMapConfig.php`) — the `TDataSourceConfig` subclass that bootstraps the extension. Registered as `extra.bootstrap` in `composer.json`. Manages connection (via `ConnectionID` reference or inline `<database>` child element), XML config file parsing, `TSqlMapManager` lifecycle, and optional caching of the parsed manager into the PRADO application cache.

### Source Layout

```
src/Data/SqlMap/
├── TSqlMapConfig.php       — extension entry point / application module
├── TSqlMapGateway.php      — public API façade for executing mapped statements
├── TSqlMapManager.php      — manager: holds all parsed config, connection, type handlers
├── Configuration/          — XML config parsing and in-memory model
│   ├── TSqlMapXmlConfiguration.php     — top-level sqlMap XML parser
│   ├── TSqlMapXmlConfigBuilder.php     — builds manager from parsed XML
│   ├── TSqlMapXmlMappingConfiguration.php  — statement/resultMap/paramMap XML
│   ├── TSqlMapStatement.php            — base statement descriptor
│   ├── TSqlMapSelect/Insert/Update/Delete.php  — per-DML statement types
│   ├── TSqlMapSelectKey.php            — generated-key descriptor
│   ├── TParameterMap.php / TParameterProperty.php  — parameter mapping
│   ├── TResultMap.php / TResultProperty.php        — result mapping
│   ├── TDiscriminator.php / TSubMap.php            — polymorphic result maps
│   ├── TSqlMapCacheModel.php / TSqlMapCacheKey.php / TSqlMapCacheTypes.php
│   ├── TInlineParameterMapParser.php   — parses #prop# inline params in SQL
│   └── TSimpleDynamicParser.php        — parses $prop$ dynamic SQL tokens
├── DataMapper/             — runtime data-access helpers and type system
│   ├── TSqlMapTypeHandler.php / TSqlMapTypeHandlerRegistry.php
│   ├── TSqlMapFifoCache.php / TSqlMapLruCache.php / TSqlMapApplicationCache.php
│   ├── TSqlMapCache.php / TSqlMapPagedList.php
│   ├── TPropertyAccess.php / TObjectProxy.php / TLazyLoadList.php
│   └── T*Exception.php     — domain-specific exceptions
└── Statements/             — statement execution engine
    ├── TMappedStatement.php            — base mapped statement executor
    ├── TSelectMappedStatement.php / TInsertMappedStatement.php / …
    ├── TCachingStatement.php           — cache-aware select wrapper
    ├── TPreparedStatement.php / TPreparedCommand.php
    ├── TPreparedStatementFactory.php
    ├── TSimpleDynamicSql.php / TStaticSql.php
    ├── TSqlMapObjectCollectionTree.php — groupBy result tree builder
    └── TPostSelectBinding.php / TResultSet*.php
```

### Test Layout

```
tests/
├── test_tools/
│   ├── phpunit_bootstrap.php   — loads autoloader, Prado.php, boots TApplication
│   └── phpstan-bootstrap.php   — loads autoloader and Prado.php for phpstan
├── unit/
│   ├── PradoUnit.php           — single require that loads full harness from vendor
│   ├── app/                    — minimal TApplication config for bootstrapped tests
│   └── Data/
│       ├── SqlMap/             — shared base test classes (abstract; no DB required for pure-unit)
│       │   ├── BaseCase.php                — abstract base for all DB-dependent tests
│       │   ├── common.php                  — DB config classes and script runners
│       │   ├── Configuration/              — pure unit tests (no DB)
│       │   ├── DataMapper/                 — pure unit tests (no DB)
│       │   ├── Statements/                 — pure unit tests (no DB)
│       │   ├── SqlMapSleepTest.php         — pure unit: serialization tests
│       │   ├── TSqlMapInsertOrIgnoreConfigTest.php  — pure unit: config class tests
│       │   ├── CacheTest.php / StatementTest.php / … — abstract DB test base classes
│       │   ├── maps/                       — SqlMap XML mapping files for tests
│       │   ├── scripts/                    — per-driver SQL schema init scripts
│       │   └── sqlite/                     — backup.db and tests.db for SQLite
│       └── DbSpecific/         — concrete wrappers per database driver
│               ├── Mysql/          — MySQLBaseTestConfig
│               ├── Pgsql/          — PgsqlBaseTestConfig
│               ├── sqlite/         — SQLiteBaseTestConfig
│               ├── Firebird/       — FirebirdBaseTestConfig
│               ├── SqlSrv/         — SqlSrvBaseTestConfig
│               ├── Oracle/         — OracleBaseTestConfig
│               └── Ibm/            — IbmBaseTestConfig
```

### SqlMap Execution Flow

`TSqlMapGateway::queryForObject($id, $param)` → `TSqlMapManager::getMappedStatement($id)` → `TMappedStatement::executeQueryForObject($conn, $param)` → `TPreparedCommand::create($conn, $statement, $param)` → PDO execute → result row → `TResultMap` property mapping → returned object.

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Classes | `TPascalCase` | `TSqlMapManager`, `TParameterProperty` |
| Methods | `camelCase` | `queryForObject`, `getResultMap` |
| Variables | `camelCase` | `$parameterMap` |
| Class Constants | `SCREAMING_SNAKE_CASE` | `QUERY_FOR_LIST` |
| Enumerated Constants | `PascalCase` | `LRU`, `FIFO` |
| Class properties | `_camelCase` | `_sqlmapConfigFile`, `_connection` |
| Namespace | `Prado\Data\SqlMap\{Sub}` | `Prado\Data\SqlMap\Configuration\TResultMap` |

## Important Rules

- **No `classes.php`** — this is a standalone extension; the PSR-4 autoloader handles class discovery. Do not create a `classes.php`.
- **`composer.json` `extra.prado`** (Prado 4.4) declares `bootstrap`, `error-messages` (`src/Data/SqlMap/DataMapper/messages.txt`), and `class-map` (`config/classes.json`, short name → FQCN for XML `class="TSqlMapConfig"` style references). Keep the legacy top-level `extra.bootstrap` for Prado 4.3. Add new classes to `config/classes.json`.
- **`if` statements** always use a block (`{}`), never a single-line body.
- **Backward compatibility** — all changes must be backward compatible for point releases.
- **`@since` tag** — use the next release version when adding new methods or classes; omit the method tag when it matches the class tag. Check prado's `CLAUDE.md` for the current next-release version number.
- **Package version** — `TSqlMapManager::VERSION` is the package version. Bump it with each release; `TSqlMapConfig` discards cached managers built by another version.
- **Uniform Access Principal / Self Encapsulation** required — access own properties via getters/setters, not raw `$this->_prop`.
- **Error codes** for PRADO exceptions are defined in `src/Data/SqlMap/DataMapper/messages.txt`.
- **Messages are ICU MessageFormat patterns** (PRADO 4.4 formats them with `\MessageFormatter`): an apostrophe beside a brace is doubled, `''{0}''`; a literal brace is quoted.
- **`@author` tags** — only Wei Zhuo, Brad Anderson, and Fabio Bas are listed as extension authors.

## Test Bootstrap

The bootstrap (`tests/test_tools/phpunit_bootstrap.php`) loads the Composer autoloader, `Prado.php`, and boots a `TTestApplication` (from the prado vendor harness). This is required because `TSqlMapApplicationCache` calls `Prado::getApplication()`. `TTestApplication` uses `sys_get_temp_dir()` as its base path and creates its own `runtime/` directory — no `app/` directory is needed.

The phpunit bootstrap (`tests/test_tools/phpunit_bootstrap.php`) loads `PradoUnitRequires.php` from the prado vendor harness, making `PradoUnit`, `PradoUnitDataConnectionTrait`, `TTestApplication`, and `TarTestHelper` available to all test files without any per-file require.

## Testing

### Test Suite Boundaries

- **`unit` suite** — pure unit tests only (no database). Covers `Configuration/`, `DataMapper/`, and `Statements/` subdirectories plus `SqlMapSleepTest.php`. The abstract `BaseCase`-extending classes in the root `SqlMap/` directory are excluded automatically because they are `abstract`.
- **`db-sqlite`, `db-mysql`, `db-pgsql`, `db-firebird`** — run in the main CI job together with the unit suite.
- **`db-sqlsrv`, `db-oracle`, `db-ibm`** — dedicated separate CI jobs.

### Adding a New DB-Dependent Test

1. Add the abstract test method to the relevant base class in `tests/unit/Data/SqlMap/` (e.g., `StatementTest.php`).
2. No changes needed in `DbSpecific/` — each driver's concrete wrapper automatically inherits the new test.

### PradoUnit Infrastructure

- `PradoUnit::snapshot($obj)` / `PradoUnit::restore($obj, $snap)` — save/restore all properties including private ancestors.
- `PradoUnit::getProp($obj, 'name')` / `PradoUnit::setProp($obj, 'name', $val)` — reflection access to private properties.
- `PradoUnit::snapshotStatic(Cls::class)` / `PradoUnit::restoreStatic(Cls::class, $snap)` — same for static state.

## Code Style

- Indentation: **tabs** (not spaces)
- Line endings: Unix (`\n`)
- PHP minimum: **8.2**, as PRADO 4.4 requires (CI tests 8.2, 8.3, 8.4, 8.5)
- PSR-12 enforced via php-cs-fixer
- Use `?` for single nullable types and in doc blocks

### Documentation Style (enforced)

Docblocks are technical documentation written with direct technical statements.
Language and National Variety: English — American
Qualities of the writing: clear, thorough, easy to comprehend, not verbose (brevity), timeless/always was, integrated, wholistic
Tense: Present — tune for ease of comprehension

_Banned constructions_:
- **Antithesis / "not merely X — it Ys"**: no "does not just X, it Ys", "is not a Y, it's a Z", "rather than X, it Ys". State what it does, once.
- **Em-dash dramatic asides** used for emphasis or reveal ("— and that's the point", "— never stronger"). Use a period or plain clause.
- **Editorializing / filler** is unnecessary.
- **Rule-of-three rhetorical lists** and build-up sentences. One fact per sentence.

Prefer: subject–verb–object declaratives, tables and bullet lists of `condition → result` where appropriate.
Docblocks inform and describe; persuasive writing is not the goal.

## Anti-Patterns (Required Safeguards)

- **Never** run `git clone/mv/restore/rm/add/commit/merge/rebase/reset/pull/push/fetch` without developer approval first.
- **Never** run `rm` on any path without developer approval first.
- **Never** remove `composer --dev` dependencies.
- **Never** erase or overwrite files during unit testing — the file changes being tested must be preserved.
