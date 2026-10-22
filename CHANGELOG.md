# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

First standalone release, planned as 1.0.0, for PRADO 4.4. SqlMap moves out of the PRADO framework (`framework/Data/SqlMap`) into this package. Namespaces and class names are unchanged. The baseline is the framework copy in PRADO `master` at commit `2c7e90da` (2026-10-21), including every SqlMap fix made there through pradosoft/prado#1330. The entries below are differences from that baseline. See the [migration guide](docs/migration.md) for upgrade steps.

### Added

- An `<insert>`, `<update>`, or `<delete>` statement with a `cacheModel` attribute flushes that cache model when it executes, the same as a `<flushOnExecute>` entry. The attribute was previously accepted and ignored (#5).
- `composer.json` declares `extra.prado` for PRADO 4.4: `bootstrap` (`TSqlMapConfig`), `error-messages` (`src/Data/SqlMap/DataMapper/messages.txt`), and `class-map` (`config/classes.json`). The legacy top-level `extra.bootstrap` remains for PRADO 4.3.
- `config/classes.json` maps the 55 SqlMap short class names (`TSqlMapConfig`, `TSqlMapFifoCache`, ...) to their FQNs. It replaces the SqlMap entries of the framework's `classes.php`.
- Message text for five codes that were thrown without a definition: `sqlmap_can_not_extend_select_key`, `sqlmap_configfile_invalid`, `sqlmap_must_enable_custom_paging`, `sqlmap_property_must_be_list`, `sqlmap_use_set_to_store_cache`.
- `TSqlMapException` accepts the `TException` argument forms: an optional integer error code first, and a trailing `Throwable` as the previous exception.

### Changed

- `TSqlMapException::getErrorCode()` returns the message code (for example `sqlmap_contains_no_statement`). It previously returned the translated message text.

### Fixed

- Result mapping falls back to a case-insensitive column match when the exact column name is absent. `pdo_oci` returns upper-case names and `pdo_pgsql` returns lower-case names. This applies to `<result column>` lookups, implicit `resultClass` mapping, `groupBy` keys, and lazy-load (`select`) keys.
- IBM DB2: the `PRADO_ROWNUM` column added by the paging subquery is removed from each row before mapping.
- IBM DB2: a null parameter without a `dbType` binds as `PDO::PARAM_STR`. `pdo_ibm` rejects `PDO::PARAM_NULL`.
- Firebird: a boolean parameter binds as `0` or `1`. `pdo_firebird` binds a PHP boolean only to a `BOOLEAN` column.
- `TCachingStatement` builds cache keys from the SQL text. It previously serialized the prepared command, which threw "Serialization of 'PDOStatement' is not allowed" for any cached statement with bound parameters.
- `TCachingStatement::executeInsert()` delegates to the wrapped statement. It previously called itself until the stack overflowed.
- `TParameterProperty::__sleep()` and `TResultProperty::__sleep()` name private properties by their namespaced class, so properties at their default values are left out of the serialized form. `TResultProperty` also misspelled `_propertyName`.
- `TResultProperty::getPropertyValueType()` no longer calls a method on `null` when no reflection class is found for the type.
- A `groupBy` result map no longer adds an element to a nested collection when every column of the nested result map is null, as in a LEFT JOIN row with no match. The collection stays empty. A parent row with no nested elements is still returned, in query order (#4).
- `TPropertyAccess::get()` and `has()` read `ArrayAccess` objects through `offsetExists()` and `offsetGet()`. `array_key_exists()` threw a `TypeError` for these objects on PHP 8 (#6).
