[prado-sqlmap](../README.md) -> SqlMap Docs

# SqlMap Documentation

SqlMap is a data-mapper layer that maps SQL statements defined in XML files to PHP objects. It is a PHP port of iBATIS.NET DataMapper, the C# edition of the Java iBATIS project, and provides a clean separation between SQL and application code by keeping all queries in XML mapping files rather than embedded in PHP. [MyBATIS](https://mybatis.org/mybatis-3/) is the current actively maintained successor to iBATIS. This library covers the PRADO PHP framework implementation of the SqlMap pattern.

## Contents

| Section | Description |
|---|---|
| [Migration](migration.md) | Moving from the framework-bundled SqlMap to this package |
| [Prado Integration](integration.md) | Registering `TSqlMapConfig` as a PRADO application module, connection configuration, and caching the parsed manager |
| [Quick Start](quickstart.md) | Minimal working example: config file, mapping file, and PHP calls |
| [Configuration](configuration/README.md) | `sqlMapConfig` root file reference — properties, type aliases, type handlers, transaction manager, data source, and `sqlMaps` includes |
| [Statements](statements/README.md) | `select`, `insert`, `update`, `delete`, `statement`, and `procedure` tags; `selectKey`; `TSqlMapGateway` PHP API |
| [Mappings](mappings/README.md) | `resultMap`, `parameterMap`, inline parameter syntax, discriminators, and sub-maps |
| [Dynamic SQL](dynamic-sql/README.md) | `dynamic`, conditional tags (`isNull`, `isEqual`, `iterate`, etc.), and inline substitution tokens |

## Tag Quick Reference

| Tag | Parent context | Purpose | Doc |
|---|---|---|---|
| `sqlMapConfig` | *(root)* | Root element of the SqlMap config file | [Configuration](configuration/README.md) |
| `properties` | `sqlMapConfig` | Loads an external `.properties` file for `${key}` substitution | [Configuration](configuration/README.md#properties) |
| `property` | `properties`, `cacheModel` | Declares a single key/value property or cache property | [Configuration](configuration/README.md#properties) |
| `typeAlias` | `sqlMapConfig`, `sqlMap` | Defines a short name for a fully-qualified class name | [Configuration](configuration/README.md#typealias) |
| `typeHandler` | `sqlMapConfig`, `typeHandlers` | Registers a custom `TSqlMapTypeHandler` for a PHP/DB type pair | [Configuration](configuration/README.md#typehandler) |
| `transactionManager` | `sqlMapConfig` | Selects the transaction strategy (`PDO`, `ADODB`, etc.) and wraps `dataSource` | [Configuration](configuration/README.md#transactionmanager) |
| `dataSource` | `transactionManager` | Supplies DSN, username, password, and driver attributes | [Configuration](configuration/README.md#datasource) |
| `sqlMaps` | `sqlMapConfig` | Container for one or more `sqlMap` resource includes | [Configuration](configuration/README.md#sqlmaps) |
| `sqlMap` | `sqlMaps` | References a single XML mapping file via `resource` attribute | [Configuration](configuration/README.md#sqlmaps) |
| `cacheModel` | `sqlMapConfig` | Declares a named cache model (type, read-only flag, serialize flag) | [Configuration](configuration/README.md#cachemodel) |
| `flushInterval` | `cacheModel` | Sets a time-based automatic cache flush interval | [Configuration](configuration/README.md#flushinterval) |
| `flushOnExecute` | `cacheModel` | Flushes the cache when a named statement executes | [Configuration](configuration/README.md#flushonexecute) |
| `sqlMap` | *(root, mapping file)* | Root element of a mapping file; holds all statement and map definitions | [Statements](statements/README.md) |
| `alias` / `typeAlias` | `sqlMap` | Short-name alias scoped to the mapping file | [Mappings](mappings/README.md#aliases) |
| `typeHandlers` / `typeHandler` | `sqlMap` | File-scoped type handler registration | [Mappings](mappings/README.md#typehandlers) |
| `resultMap` | `sqlMap` | Maps result-set columns to object properties | [Mappings](mappings/README.md#resultmap) |
| `result` | `resultMap` | Maps a single column to a single property | [Mappings](mappings/README.md#result) |
| `discriminator` | `resultMap` | Selects a concrete `subMap` based on a column value | [Mappings](mappings/README.md#discriminator) |
| `subMap` | `discriminator` | Associates a discriminator value with an alternate `resultMap` | [Mappings](mappings/README.md#submap) |
| `parameterMap` | `sqlMap` | Maps ordered or named parameters to statement placeholders | [Mappings](mappings/README.md#parametermap) |
| `parameter` | `parameterMap` | Declares one parameter: property, db type, null value, and mode | [Mappings](mappings/README.md#parameter) |
| `select` | `sqlMap` | Mapped `SELECT` statement; returns one object or a list | [Statements](statements/README.md#select) |
| `insert` | `sqlMap` | Mapped `INSERT` statement; optionally returns a generated key | [Statements](statements/README.md#insert) |
| `selectKey` | `insert` | Executes a query to retrieve the generated primary key | [Statements](statements/README.md#selectkey) |
| `update` | `sqlMap` | Mapped `UPDATE` statement; returns affected-row count | [Statements](statements/README.md#update) |
| `delete` | `sqlMap` | Mapped `DELETE` statement; returns affected-row count | [Statements](statements/README.md#delete) |
| `statement` | `sqlMap` | Generic mapped statement for any SQL that does not fit a specific DML tag | [Statements](statements/README.md#statement) |
| `procedure` | `sqlMap` | Mapped stored-procedure call with `IN`/`OUT`/`INOUT` parameter support | [Statements](statements/README.md#procedure) |
| `dynamic` | statement body | Wraps conditional child elements; strips its own `prepend` when all children are empty | [Dynamic SQL](dynamic-sql/README.md#dynamic) |
| `isParameterPresent` | `dynamic`, statement body | Includes content when the parameter object is not `null` | [Dynamic SQL](dynamic-sql/README.md#parameter-presence) |
| `isNotParameterPresent` | `dynamic`, statement body | Includes content when the parameter object is `null` | [Dynamic SQL](dynamic-sql/README.md#parameter-presence) |
| `isPropertyAvailable` | `dynamic`, statement body | Includes content when the named property exists on the parameter | [Dynamic SQL](dynamic-sql/README.md#property-availability) |
| `isNotPropertyAvailable` | `dynamic`, statement body | Includes content when the named property does not exist on the parameter | [Dynamic SQL](dynamic-sql/README.md#property-availability) |
| `isNull` | `dynamic`, statement body | Includes content when the property value is `null` | [Dynamic SQL](dynamic-sql/README.md#null-checks) |
| `isNotNull` | `dynamic`, statement body | Includes content when the property value is not `null` | [Dynamic SQL](dynamic-sql/README.md#null-checks) |
| `isEmpty` | `dynamic`, statement body | Includes content when the property value is empty (`""`, `0`, empty array) | [Dynamic SQL](dynamic-sql/README.md#empty-checks) |
| `isNotEmpty` | `dynamic`, statement body | Includes content when the property value is not empty | [Dynamic SQL](dynamic-sql/README.md#empty-checks) |
| `isEqual` | `dynamic`, statement body | Includes content when the property value equals `compareValue` | [Dynamic SQL](dynamic-sql/README.md#comparisons) |
| `isNotEqual` | `dynamic`, statement body | Includes content when the property value does not equal `compareValue` | [Dynamic SQL](dynamic-sql/README.md#comparisons) |
| `isGreaterThan` | `dynamic`, statement body | Includes content when the property value is greater than `compareValue` | [Dynamic SQL](dynamic-sql/README.md#comparisons) |
| `isGreaterEqual` | `dynamic`, statement body | Includes content when the property value is greater than or equal to `compareValue` | [Dynamic SQL](dynamic-sql/README.md#comparisons) |
| `isLessThan` | `dynamic`, statement body | Includes content when the property value is less than `compareValue` | [Dynamic SQL](dynamic-sql/README.md#comparisons) |
| `isLessEqual` | `dynamic`, statement body | Includes content when the property value is less than or equal to `compareValue` | [Dynamic SQL](dynamic-sql/README.md#comparisons) |
| `iterate` | `dynamic`, statement body | Iterates over a list or array property, repeating enclosed SQL for each element | [Dynamic SQL](dynamic-sql/README.md#iterate) |

---

[prado-sqlmap](../README.md) -> SqlMap Docs

## See Also

- [Prado Integration](integration.md) — module registration, `TSqlMapConfig`, connection wiring
- [Configuration Reference](configuration/README.md) — full `sqlMapConfig` XML reference
- [Statements Reference](statements/README.md) — `TSqlMapGateway` PHP API and all DML tags
