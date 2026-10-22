# prado-sqlmap

SqlMap Data Mapper extension for the [PRADO PHP Framework](https://github.com/pradosoft/prado).

SqlMap maps SQL statements to PHP objects using XML configuration files. Queries, inserts, updates, and deletes are defined in XML; the gateway executes them and maps result rows to objects or arrays automatically. prado-sqlmap is a PHP port of the iBATIS DataMapper (SQL Maps 2), whose Java line continues today as [MyBatis](https://github.com/mybatis/mybatis-3). The MyBatis documentation is a useful reference for the shared concepts. See [History](#history).

## Requirements

- PHP 8.1 or later
- PRADO 4.3.3 or later (`pradosoft/prado`)
- PDO extension and a PDO driver for your database

## Installation

Within a PRADO Application:

```bash
composer require pradosoft/prado-sqlmap
```

Applications upgrading from the SqlMap bundled with the PRADO framework: see the [migration guide](docs/migration.md). Release notes are in the [changelog](CHANGELOG.md).

## Supported Databases

Any database with a PDO driver. The test suite covers:

| Driver | PDO DSN prefix |
|---|---|
| MySQL / MariaDB | `mysql:` |
| PostgreSQL | `pgsql:` |
| SQLite | `sqlite:` |
| Firebird | `firebird:` |
| SQL Server | `sqlsrv:` |
| Oracle | `oci:` |
| IBM DB2 | `ibm:` |

## Configuration

Register `pradosoft/prado-sqlmap` or `TSqlMapConfig` as an application module in your PRADO `application.xml` (or `application.php`).

### Pattern 1 — Shared connection (recommended)

Reference a `TDataSourceConfig` module by its `id`:

```xml
<modules>
    <module id="db" class="System.Data.TDataSourceConfig">
        <database ConnectionString="mysql:host=localhost;dbname=myapp"
                  Username="user" Password="secret"/>
    </module>

    <module id="sqlmap" class="Prado\Data\SqlMap\TSqlMapConfig"
            ConnectionID="db"
            ConfigFile="Application.SqlMap.sqlmap"/>
</modules>
```

### Pattern 2 — Inline connection

Embed the `<database>` element directly inside `TSqlMapConfig`:

```xml
<modules>
    <module id="sqlmap" class="Prado\Data\SqlMap\TSqlMapConfig"
            ConfigFile="Application.SqlMap.sqlmap">
        <database ConnectionString="sqlite:/path/to/app.db"/>
    </module>
</modules>
```

### TSqlMapConfig properties

| Property | Type | Description |
|---|---|---|
| `ConfigFile` | string | Dot-notation path to the SqlMap XML config file |
| `ConnectionID` | string | ID of a `TDataSourceConfig` module to share its connection |
| `EnableCache` | bool | Cache the parsed manager in the PRADO application cache (default `false`) |

## SqlMap XML Configuration

> For the upstream specification see the [MyBATIS 3 documentation](https://mybatis.org/mybatis-3/).

Two kinds of XML files drive SqlMap: a **config file** (`sqlmap.xml`) that wires everything together, and one or more **map files** that declare the SQL statements and their mappings.

---

### Config file (`sqlmap.xml`)

The root element is `<sqlMapConfig>`. It accepts three child sections in any order.

```xml
<?xml version="1.0" encoding="utf-8"?>
<sqlMapConfig>

    <!-- 1. Global properties — substituted as ${name} in map files -->
    <properties>
        <property name="selectKey" value="SELECT LAST_INSERT_ID()"/>
        <property name="schema"    value="myapp"/>
    </properties>

    <!-- 2. Custom PHP type handlers -->
    <typeHandlers>
        <typeHandler class="App\SqlMap\BoolHandler"      dbType="TINYINT"/>
        <typeHandler class="App\SqlMap\MoneyHandler"     dbType="DECIMAL" type="float"/>
    </typeHandlers>

    <!-- 3. Map files — paths resolved relative to this config file -->
    <sqlMaps>
        <sqlMap resource="Account.xml"/>
        <sqlMap resource="Order.xml"/>
    </sqlMaps>

</sqlMapConfig>
```

#### `<typeHandler>` attributes

| Attribute | Required | Description |
|---|---|---|
| `class` | Yes | Fully-qualified PHP class name implementing `TSqlMapTypeHandler` |
| `dbType` | No | Database column type this handler applies to (e.g. `TINYINT`, `VARCHAR`) |
| `type` | No | PHP type this handler applies to (e.g. `bool`, `float`) |

#### `<sqlMap>` attributes

| Attribute | Description |
|---|---|
| `resource` | Path to a map file, relative to the config file directory |

---

### Map file root: `<sqlMap>`

```xml
<sqlMap namespace="Account">
    <!-- cacheModel, alias, resultMap, parameterMap, and statement elements -->
</sqlMap>
```

| Attribute | Description |
|---|---|
| `namespace` | Optional prefix for all statement IDs in this file. Cross-file references use `Namespace.StatementId`. |

All top-level elements may appear in any order and any number of times. The `<statements>` element is an optional grouping wrapper with no semantic effect — its children are equivalent to top-level elements.

---

### `<alias>` — type aliases

Declares short names for PHP class names. Used mainly when porting from .NET iBATIS (which required fully-qualified assembly names). In PHP, this is rarely needed.

```xml
<alias>
    <typeAlias alias="Account" type="App\Model\Account"/>
</alias>
```

| Attribute | Description |
|---|---|
| `alias` | Short name usable in `class`, `resultClass`, `parameterClass`, and `type` attributes |
| `type` | Fully-qualified PHP class name |

---

### `<cacheModel>` — result caching

Declares a named cache that `<select>` statements can reference.

```xml
<cacheModel id="account-cache" implementation="LRU" readOnly="true" serialize="false">
    <flushInterval hours="1" minutes="30" seconds="0"/>
    <flushOnExecute statement="UpdateAccount"/>
    <flushOnExecute statement="DeleteAccount"/>
    <property name="size" value="100"/>
</cacheModel>
```

#### `<cacheModel>` attributes

| Attribute | Default | Description |
|---|---|---|
| `id` | — | Unique name for this cache model within the file |
| `implementation` | — | Cache type: `LRU`, `FIFO`, `MEMORY`, or a fully-qualified class name |
| `readOnly` | `true` | When `true`, all callers receive the same cached object (no defensive copy). Set `false` to let callers mutate their copy safely. |
| `serialize` | `false` | Serialize objects before storing. Useful when `readOnly="false"` to guarantee isolation. |

#### Cache model child elements

| Element | Attributes | Description |
|---|---|---|
| `<flushInterval>` | `hours`, `minutes`, `seconds` | Invalidate the cache on a time interval. All three attributes are optional and additive. |
| `<flushOnExecute>` | `statement` | Flush this cache whenever the named statement executes (insert/update/delete). Multiple elements are allowed. |
| `<property>` | `name`, `value` | Implementation-specific setting. `LRU` and `FIFO` accept `size` (maximum entry count). |

#### Cache implementation types

| Type | Eviction | `size` property |
|---|---|---|
| `LRU` | Least-recently-used | Required |
| `FIFO` | First-in, first-out | Required |
| `MEMORY` | None (unbounded) | Ignored |

---

### `<resultMap>` — column-to-object mapping

Maps result-set columns to PHP object properties or array keys.

```xml
<resultMap id="account-result" class="App\Model\Account" extends="base-result" groupBy="id">
    <result property="id"           column="account_id"/>
    <result property="firstName"    column="account_first_name"/>
    <result property="emailAddress" column="account_email"    nullValue=""/>
    <result property="role"         column="account_role"     type="int"/>
    <result property="active"       column="account_active"   typeHandler="BoolHandler"/>
    <result property="lineItems"    column="order_id"         select="GetLineItemsForOrder" lazyLoad="true"/>
    <result property="address"                                resultMapping="address-result"/>
    <discriminator column="doc_type" type="string">
        <subMap value="Book"      resultMapping="book-result"/>
        <subMap value="Newspaper" resultMapping="newspaper-result"/>
    </discriminator>
</resultMap>
```

#### `<resultMap>` attributes

| Attribute | Description |
|---|---|
| `id` | Unique name within the namespace |
| `class` | PHP class name (or alias) to instantiate per row. Use `array` for associative arrays or `string`/`int`/`float` for scalar results. |
| `extends` | ID of another `<resultMap>` whose `<result>` elements are inherited. Allows sharing a base mapping. |
| `groupBy` | Property name (or comma-separated list) used to collapse repeated parent rows into one object with a child list. See [GroupBy](#groupby--nested-object-trees). |

#### `<result>` attributes

| Attribute | Description |
|---|---|
| `property` | PHP object property or array key to set. Supports dotted paths: `favouriteItem.id` sets `$obj->getFavouriteItem()->setId(...)`. |
| `column` | Result-set column name to read. Required unless `resultMapping` is used. |
| `columnIndex` | Zero-based column index. Use instead of `column` for positional reading (faster, but order-sensitive). |
| `type` | PHP type to coerce the value to: `string`, `int`, `float`, `bool`, `date`, or a class alias. |
| `dbType` | Database type hint used during result reading. |
| `nullValue` | Value to substitute when the column is `NULL`. The substituted value is set on the property; the property is not set to `null`. |
| `typeHandler` | Name of a registered type handler class to use for this column. |
| `select` | ID of a statement to execute for this property using the column value as the parameter (N+1 / association select). |
| `column` (multi) | For N+1 selects with multiple key columns: `"FK1=Alias1,FK2=Alias2"`. The alias names are passed as the parameter map to the sub-select. |
| `resultMapping` | ID of another `<resultMap>` to use for mapping a nested object from the **same joined row** (no extra query). |
| `lazyLoad` | `true` to defer the sub-select (`select` attribute) until the property is first accessed. Default `false`. |

#### `<discriminator>` attributes

| Attribute | Description |
|---|---|
| `column` | Column whose value determines which subMap to apply |
| `type` | PHP type to coerce the discriminator value to before comparison |
| `typeHandler` | Type handler to apply to the discriminator column value |

#### `<subMap>` attributes

| Attribute | Description |
|---|---|
| `value` | Discriminator value that triggers this sub-map |
| `resultMapping` | ID of the `<resultMap>` to use when the discriminator matches |

---

### `<parameterMap>` — explicit parameter binding

Maps named positions in a `?`-placeholder statement to object properties.

```xml
<parameterMap id="account-insert-params" class="App\Model\Account" extends="base-params">
    <parameter property="firstName"    dbType="VARCHAR"/>
    <parameter property="lastName"     dbType="VARCHAR"/>
    <parameter property="emailAddress" dbType="VARCHAR"  nullValue="no_email@provided.com"/>
    <parameter property="active"       dbType="TINYINT"  type="bool"   typeHandler="BoolHandler"/>
    <parameter property="id"           dbType="INTEGER"/>
</parameterMap>
```

#### `<parameterMap>` attributes

| Attribute | Description |
|---|---|
| `id` | Unique name within the namespace |
| `class` | Expected PHP class of the parameter object (informational; not enforced) |
| `extends` | ID of another `<parameterMap>` whose `<parameter>` elements are prepended |

#### `<parameter>` attributes

| Attribute | Description |
|---|---|
| `property` | Property name on the parameter object. Supports dotted paths: `account.id`. |
| `column` | Column name hint (used for stored-procedure output mapping) |
| `dbType` | Database type to bind as (e.g. `VARCHAR`, `INTEGER`, `TINYINT`) |
| `type` | PHP type to coerce the property value to before binding |
| `nullValue` | If the property equals this value, bind `NULL` to the parameter instead |
| `typeHandler` | Name of a registered type handler to convert the value |
| `mode` | `IN` (default), `OUT`, or `INOUT` — for stored-procedure output parameters |

---

### Statement elements

Six element types declare SQL statements. All share a common set of attributes; some elements add their own.

#### Common statement attributes

| Attribute | Description |
|---|---|
| `id` | Unique statement ID within the namespace |
| `parameterClass` | PHP class (or alias) of the parameter. Shortcuts: `int`, `string`, `array`, `list`, `map`, `Hashtable`. |
| `parameterMap` | ID of a `<parameterMap>` for `?`-placeholder binding. Mutually exclusive with `parameterClass` inline parameters. |
| `resultClass` | PHP class to instantiate per row without a `<resultMap>`. Alias columns in the SQL to match property names. |
| `resultMap` | ID of a `<resultMap>` for explicit column-to-property mapping |
| `listClass` | PHP collection class to populate (default `TList`). Any class with an `add($item)` method works. |
| `cacheModel` | ID of a `<cacheModel>` to cache results (select statements only) |
| `extends` | ID of another statement whose SQL is prepended to this one. The child adds clauses (e.g. `WHERE`, `ORDER BY`). |

#### `<select>`

Executes a `SELECT` query. Returns one object, a list, a map, or a paged list depending on the gateway method called.

```xml
<select id="GetAccount"
        parameterClass="int"
        resultMap="account-result"
        cacheModel="account-cache">
    SELECT * FROM accounts WHERE account_id = #value#
</select>
```

#### `<insert>`

Executes an `INSERT`. Returns the generated key when `<selectKey>` is present, otherwise `null`.

```xml
<insert id="InsertAccount" parameterClass="App\Model\Account">
    <selectKey property="id" type="post" resultClass="int">
        SELECT LAST_INSERT_ID()
    </selectKey>
    INSERT INTO accounts (first_name, last_name, email_address)
    VALUES (#firstName#, #lastName#, #emailAddress#)
</insert>
```

##### `<selectKey>` attributes

| Attribute | Description |
|---|---|
| `property` | Property on the parameter object to set with the generated key |
| `type` | `pre` — execute the key query **before** the insert (e.g. sequence `NEXTVAL`). `post` — execute **after** the insert (e.g. `LAST_INSERT_ID()`). |
| `resultClass` | PHP type of the returned key value (`int`, `string`, etc.) |

#### `<update>`

Executes an `UPDATE`. Returns the number of affected rows.

```xml
<update id="UpdateAccount" parameterClass="App\Model\Account">
    UPDATE accounts
    SET    first_name = #firstName#, last_name = #lastName#
    WHERE  account_id = #id#
</update>
```

#### `<delete>`

Executes a `DELETE`. Returns the number of affected rows.

```xml
<delete id="DeleteAccount" parameterClass="int">
    DELETE FROM accounts WHERE account_id = #value#
</delete>
```

#### `<statement>`

Generic statement element — accepts any SQL and any result type. Use when the DML type does not fit `<select>`/`<insert>`/`<update>`/`<delete>`, or for legacy compatibility. Supports all common attributes.

```xml
<statement id="GetOrders" resultMap="order-result">
    SELECT * FROM orders ORDER BY order_date DESC
</statement>
```

#### `<procedure>`

Calls a stored procedure. Requires a `<parameterMap>` for parameter binding. `OUT` and `INOUT` parameters are written back to the parameter object after execution.

```xml
<procedure id="SwapEmailAddresses" parameterMap="swap-params">
    ps_swap_email_address
</procedure>
```

---

### SQL substitution syntax

Three distinct substitution forms appear inside statement bodies.

#### `${name}` — global property substitution

Replaces `${name}` with the value of a `<property>` declared in `<properties>` of the config file. Substitution happens at parse time (startup), not at query time.

```xml
<!-- Config file: <property name="schema" value="myapp"/> -->
SELECT * FROM ${schema}.accounts WHERE account_id = #value#
```

#### `#property#` — prepared-statement parameter

Replaces `#property#` with a `?` placeholder and binds the value via PDO. This is the safe, injection-proof form for user-supplied values.

```sql
WHERE account_id = #id#
```

An inline parameter may carry modifiers separated by commas:

```sql
WHERE email = #emailAddress, dbType=VARCHAR, nullValue=no_email@provided.com#
```

| Modifier | Description |
|---|---|
| `dbType=X` | PDO type hint for binding |
| `type=X` | PHP type to coerce the value to before binding |
| `nullValue=X` | Bind `NULL` when the property equals this value |
| `typeHandler=X` | Registered type handler class name to convert the value |

For a list or array parameter, use `#[]#` (positional) or `#propertyName[]#` (named) inside `<iterate>`.

When the parameter is a scalar (`int`, `string`, etc.) rather than an object, use the special name `value`: `#value#`.

#### `$property$` — literal string substitution

Replaces `$property$` with the raw string value of the parameter property at query time, with no quoting or escaping. Use only for trusted values such as column names or SQL fragments.

```sql
ORDER BY $sortColumn$ $sortDirection$
```

> **Warning:** `$property$` is vulnerable to SQL injection when the value comes from user input. Never use it with untrusted data.

#### CDATA

Wrap SQL containing `<`, `>`, or `&` in a CDATA section to prevent XML parsing errors:

```xml
<select id="GetFewAccounts" resultMap="account-result">
    <![CDATA[ SELECT * FROM accounts WHERE account_id < #maxId# ]]>
</select>
```

---

### `extends` — statement inheritance

A statement may extend another statement in the same namespace. The child's SQL is appended to the parent's SQL.

```xml
<select id="GetAllAccounts" resultMap="account-result">
    SELECT account_id, first_name, last_name, email_address
    FROM   accounts
</select>

<!-- Adds an ORDER BY clause to the base select -->
<select id="GetAllAccountsByName" extends="GetAllAccounts" resultMap="account-result">
    ORDER BY first_name
</select>

<!-- Adds a WHERE clause -->
<select id="GetOneAccount" extends="GetAllAccounts" resultMap="account-result">
    WHERE account_id = #value#
</select>
```

`extends` also works on `<resultMap>` (inherits `<result>` elements) and `<parameterMap>` (inherits `<parameter>` elements).

---

### Dynamic SQL

Dynamic SQL tags conditionally include SQL fragments based on the parameter value at query time. They nest freely.

```xml
<select id="SearchAccounts" resultMap="account-result" parameterClass="App\Model\Account">
    SELECT account_id, first_name, last_name, email_address
    FROM   accounts
    <dynamic prepend="WHERE">
        <isGreaterThan prepend="AND" property="id" compareValue="0">
            account_id = #id#
        </isGreaterThan>
        <isNotEmpty prepend="AND" property="firstName">
            first_name = #firstName#
        </isNotEmpty>
        <isNotEmpty prepend="AND" property="lastName">
            last_name = #lastName#
        </isNotEmpty>
        <isNotNull prepend="AND" property="ids">
            account_id IN
            <iterate property="ids" open="(" close=")" conjunction=",">
                #ids[]#
            </iterate>
        </isNotNull>
    </dynamic>
    ORDER BY last_name
</select>
```

#### `<dynamic>`

A wrapper that emits its `prepend` string only when at least one child emits content. Without `<dynamic>`, each child's `prepend` is always emitted (even when that child is the first to emit content).

| Attribute | Description |
|---|---|
| `prepend` | SQL text prepended when any child emits content (e.g. `WHERE`, `AND`) |

#### Conditional tags

All conditional tags share these attributes:

| Attribute | Applies to | Description |
|---|---|---|
| `prepend` | All | SQL text prepended to this tag's content when it emits (stripped from the first tag that emits inside a `<dynamic>`) |
| `property` | Most | Property name on the parameter object to test. Omit when the parameter itself is the value being tested. |
| `compareValue` | Comparison tags | Literal value to compare against |

| Tag | Condition |
|---|---|
| `<isNull>` | Property is `null` |
| `<isNotNull>` | Property is not `null` |
| `<isEmpty>` | Property is `null`, empty string, or empty collection |
| `<isNotEmpty>` | Property is not `null` and not empty |
| `<isEqual>` | Property equals `compareValue` |
| `<isNotEqual>` | Property does not equal `compareValue` |
| `<isGreaterThan>` | Property is greater than `compareValue` (numeric) |
| `<isGreaterEqual>` | Property is greater than or equal to `compareValue` (numeric) |
| `<isLessThan>` | Property is less than `compareValue` (numeric) |
| `<isLessEqual>` | Property is less than or equal to `compareValue` (numeric) |
| `<isParameterPresent>` | A parameter was passed (not `null`) |
| `<isPropertyAvailable>` | The named property exists on the parameter object |

#### `<iterate>`

Iterates over an array or list property, emitting the body once per element.

```xml
<iterate property="ids" open="(" close=")" conjunction=",">
    #ids[]#
</iterate>
```

For a list parameter (not a property of an object), omit `property` and use `#[]#`:

```xml
<iterate open="(" close=")" conjunction=",">
    #[]#
</iterate>
```

| Attribute | Description |
|---|---|
| `property` | Property name holding the list/array. Omit when the parameter itself is the list. |
| `open` | SQL text emitted once before the first element (e.g. `(`) |
| `close` | SQL text emitted once after the last element (e.g. `)`) |
| `conjunction` | SQL text emitted between elements (e.g. `,`, `OR`) |

---

### N+1 select — loading associations

Use `select` on a `<result>` to load an associated object or collection with a second query.

```xml
<resultMap id="order-result" class="App\Model\Order">
    <result property="id"        column="order_id"/>
    <result property="lineItems" column="order_id"
            select="GetLineItemsForOrder" lazyLoad="true"/>
</resultMap>

<select id="GetLineItemsForOrder" parameterClass="int" resultMap="line-item-result">
    SELECT * FROM line_items WHERE order_id = #value#
</select>
```

When the association requires multiple key columns, list them as `"ColumnName=ParameterAlias"` pairs:

```xml
<result property="item"
        column="order_id=Order_ID,fav_item_id=LineItem_ID"
        select="GetSpecificLineItem"/>
```

The sub-select receives a parameter map with keys `Order_ID` and `LineItem_ID`.

Set `lazyLoad="true"` to defer the sub-select until the property is first accessed. The property is then a `TLazyLoadList` proxy.

---

### Joined result mapping

Use `resultMapping` on a `<result>` to map a nested object from columns already present in the same joined row — no additional query.

```xml
<resultMap id="order-with-address" class="App\Model\Order">
    <result property="id"      column="order_id"/>
    <result property="address" resultMapping="address-result"/>
</resultMap>

<resultMap id="address-result" class="App\Model\Address">
    <result property="street"  column="addr_street"/>
    <result property="city"    column="addr_city"/>
</resultMap>
```

Alternatively, use dotted property paths in `<result property="...">` to write directly into nested objects without a separate result map:

```xml
<resultMap id="order-result" class="App\Model\Order">
    <result property="id"                    column="order_id"/>
    <result property="favouriteItem.id"      column="line_item_id"/>
    <result property="favouriteItem.code"    column="line_item_code"/>
    <result property="favouriteItem.price"   column="line_item_price"/>
</resultMap>
```

---

### GroupBy — nested object trees

`groupBy` collapses repeated parent rows from a JOIN into a single parent instance with a list child property. The value is the parent result property (or comma-separated list of properties) that identifies a unique parent row.

```xml
<resultMap id="account-with-orders" class="App\Model\Account" groupBy="id">
    <result property="id"        column="account_id"/>
    <result property="firstName" column="account_first_name"/>
    <result property="orders"    resultMapping="order-result"/>
</resultMap>

<resultMap id="order-result" class="App\Model\Order">
    <result property="id"   column="order_id"/>
    <result property="date" column="order_date" type="date"/>
</resultMap>

<select id="GetAccountWithOrders" resultMap="account-with-orders">
    SELECT a.account_id, a.account_first_name, o.order_id, o.order_date
    FROM   accounts a
    LEFT JOIN orders o ON a.account_id = o.account_id
</select>
```

Each distinct `account_id` value produces one `Account` object; all matching `Order` rows are collected into its `orders` property.

---

### Polymorphic result maps

`<discriminator>` selects a different result map based on a column value. The discriminator and its `<subMap>` elements appear inside the base `<resultMap>`.

```xml
<resultMap id="document-result" class="App\Model\Document">
    <result property="id"    column="doc_id"/>
    <result property="title" column="doc_title"/>
    <discriminator column="doc_type" type="string">
        <subMap value="Book"      resultMapping="book-result"/>
        <subMap value="Newspaper" resultMapping="newspaper-result"/>
    </discriminator>
</resultMap>

<resultMap id="book-result" class="App\Model\Book" extends="document-result">
    <result property="pageCount" column="doc_page_count"/>
</resultMap>

<resultMap id="newspaper-result" class="App\Model\Newspaper" extends="document-result">
    <result property="city" column="doc_city"/>
</resultMap>
```

The sub-result maps extend the base to inherit its `<result>` elements. A custom `typeHandler` on `<discriminator>` lets the handler translate the raw column value to the string that matches a `<subMap value="...">`.

---

## Gateway API

Retrieve the gateway from the module:

```php
$sqlmap = Prado::getApplication()->getModule('sqlmap')->getClient();
```

### Query methods

```php
// Single object — returns null if not found
$account = $sqlmap->queryForObject('GetAccount', $id);

// Single object into a pre-created instance
$account = $sqlmap->queryForObject('GetAccount', $id, new Account());

// List (TList)
$accounts = $sqlmap->queryForList('GetAllAccounts');

// List with offset/limit
$accounts = $sqlmap->queryForList('GetAllAccounts', null, null, $skip, $max);

// Paged list (TSqlMapPagedList)
$paged = $sqlmap->queryForPagedList('GetAllAccounts', null, $pageSize);
$paged->gotoPage(2);

// Map keyed by a property
$map = $sqlmap->queryForMap('GetAllAccounts', null, 'id');

// Map keyed by one property, values from another
$map = $sqlmap->queryForMap('GetAllAccounts', null, 'id', 'emailAddress');

// Row delegate — callback fired per row; use to build custom collections
$sqlmap->queryWithRowDelegate(
    'GetAllAccounts',
    function ($sqlmap, $object, &$list) {
        $list[] = $object;
    }
);
```

### Mutation methods

```php
// Insert — returns the generated key (from <selectKey>) or null
$newId = $sqlmap->insert('InsertAccount', $account);

// Update — returns affected row count
$count = $sqlmap->update('UpdateAccount', $account);

// Delete — returns affected row count
$count = $sqlmap->delete('DeleteAccount', $id);
```

### Cache

```php
// Flush all cache models declared in the SqlMap config
$sqlmap->flushCaches();
```

## Type Handlers

A type handler controls how a PHP value is converted to and from a database column value.

Implement `TSqlMapTypeHandler`:

```php
use Prado\Data\SqlMap\DataMapper\TSqlMapTypeHandler;

class BoolHandler extends TSqlMapTypeHandler
{
    public function getResult($value): bool  { return (bool)(int)$value; }
    public function getParameter($value): int { return $value ? 1 : 0; }
    public function createNewInstance($data = null): bool { return false; }
}
```

Register in PHP:

```php
$sqlmap->registerTypeHandler(new BoolHandler());
```

Or in `sqlmap.xml`:

```xml
<typeHandlers>
    <typeHandler class="App\SqlMap\BoolHandler" dbType="TINYINT"/>
</typeHandlers>
```

## Development

### Setup

```bash
composer install
```

### Commands

| Command | Description |
|---|---|
| `composer fix` | Apply cs-fixer style fixes to `src/` |
| `composer stan` | PHPStan static analysis |
| `composer test` | PHPUnit unit suite (no database required) |
| `composer unittest` | Alias for `composer test` |
| `composer dbtest` | Unit + SQLite, MySQL, PostgreSQL, and Firebird in one PHPUnit run |
| `composer fulltest` | Pre-commit check: `php -l` + cs-fixer dry-run + phpstan + unit tests |

Run `composer fix` before `composer fulltest` when the style check fails — `fulltest` runs cs-fixer in dry-run (check-only) mode.

### Running a single driver suite

```bash
vendor/bin/phpunit --testsuite db-sqlite
vendor/bin/phpunit --testsuite db-mysql
vendor/bin/phpunit --testsuite db-pgsql
vendor/bin/phpunit --testsuite db-firebird
vendor/bin/phpunit --testsuite db-sqlsrv
vendor/bin/phpunit --testsuite db-oracle
vendor/bin/phpunit --testsuite db-ibm
```

### Filtering to one test class or method

```bash
vendor/bin/phpunit --testsuite unit --filter TInlineParameterMapParserTest
vendor/bin/phpunit --testsuite db-sqlite --filter testQueryForObject
```

### Database test setup

Each driver suite reads its connection from `tests/unit/Data/SqlMap/common.php`. Set the DSN, username, and password for your driver in the matching `*BaseTestConfig` class. SQLite requires no setup — it uses the bundled database files in `tests/unit/Data/SqlMap/sqlite/`.

The schema init scripts for each driver are in `tests/unit/Data/SqlMap/scripts/`.

### Pre-commit checklist

Run `composer fulltest` — all four steps must pass before committing:

1. `find src -name '*.php' | xargs php -l` — syntax check
2. `vendor/bin/php-cs-fixer fix --dry-run src/` — style check
3. `vendor/bin/phpstan analyse --memory-limit=512M` — static analysis
4. `vendor/bin/phpunit --testsuite unit` — unit tests

### Adding a new database driver

1. Add a `<Driver>BaseTestConfig` class to `tests/unit/Data/SqlMap/common.php` following the existing pattern (`getConnection()`, `getSqlMapConfigFile()`, `getScriptDir()`, `getScriptRunner()`, `hasFeature()`).
2. Create `tests/unit/Data/SqlMap/DbSpecific/<Driver>/`.
3. For each abstract base class in `tests/unit/Data/SqlMap/`, create a concrete wrapper:
   ```php
   <?php
   require_once(__DIR__ . '/../../StatementTest.php');
   class <Driver>StatementTest extends StatementTest
   {
       protected static string $configClass = '<Driver>BaseTestConfig';
   }
   ```
4. Add a `<testsuite name="db-<driver>">` entry to `phpunit.xml`.
5. Add SQL schema scripts to `tests/unit/Data/SqlMap/scripts/<driver>/`.
6. Add a SqlMap config file at `tests/unit/Data/SqlMap/maps/<driver>/sqlmap.xml`.

### Adding a new DB-dependent test method

Add the `abstract` test method to the relevant base class in `tests/unit/Data/SqlMap/` (e.g., `StatementTest.php`). All driver wrappers under `DbSpecific/` inherit it automatically.

## Architecture

```
src/Data/SqlMap/
├── TSqlMapConfig.php               — PRADO module; bootstraps the extension
├── TSqlMapGateway.php              — public API: queryForObject, insert, update, delete, …
├── TSqlMapManager.php              — holds parsed config, connection, type handlers
├── Configuration/                  — XML parsing and in-memory model
│   ├── TSqlMapXmlConfiguration.php
│   ├── TSqlMapXmlConfigBuilder.php
│   ├── TSqlMapXmlMappingConfiguration.php
│   ├── TSqlMapStatement.php / TSqlMapSelect.php / TSqlMapInsert.php / …
│   ├── TParameterMap.php / TParameterProperty.php
│   ├── TResultMap.php / TResultProperty.php
│   ├── TDiscriminator.php / TSubMap.php
│   ├── TSqlMapCacheModel.php / TSqlMapCacheKey.php / TSqlMapCacheTypes.php
│   ├── TSqlMapSelectKey.php
│   ├── TInlineParameterMapParser.php
│   └── TSimpleDynamicParser.php
├── DataMapper/                     — runtime helpers, type system, cache implementations
│   ├── TSqlMapTypeHandler.php / TSqlMapTypeHandlerRegistry.php
│   ├── TSqlMapFifoCache.php / TSqlMapLruCache.php / TSqlMapApplicationCache.php
│   ├── TSqlMapCache.php / TSqlMapPagedList.php
│   ├── TPropertyAccess.php / TObjectProxy.php / TLazyLoadList.php
│   └── T*Exception.php
└── Statements/                     — statement execution engine
    ├── TMappedStatement.php
    ├── TSelectMappedStatement.php / TInsertMappedStatement.php / …
    ├── TCachingStatement.php
    ├── TPreparedCommand.php / TPreparedStatement.php / TPreparedStatementFactory.php
    ├── TSimpleDynamicSql.php / TStaticSql.php
    ├── TSqlMapObjectCollectionTree.php
    └── TPostSelectBinding.php / TResultSet*.php
```

### Execution flow

`TSqlMapGateway::queryForObject($id, $param)`
→ `TSqlMapManager::getMappedStatement($id)`
→ `TMappedStatement::executeQueryForObject($conn, $param)`
→ `TPreparedCommand::create($conn, $statement, $param)`
→ PDO execute
→ result row
→ `TResultMap` property mapping
→ returned object

## History

Clinton Begin started iBATIS in 2001, and its SQL Maps component (2002) mapped SQL kept in XML files to Java objects. iBATIS 2.0 shipped in June 2004, and the project moved to the Apache Software Foundation, where a C# edition, iBATIS.NET DataMapper, was also developed. Wei Zhuo ported iBATIS.NET DataMapper to PHP for PRADO in April 2006, and it first shipped as SQLMap in PRADO 3.1.0 (alpha, January 15, 2007). In May 2010 the iBATIS team published iBATIS 3.0 and continued development under the name MyBatis on Google Code. Apache retired iBATIS to the Apache Attic in June 2010. MyBatis moved to GitHub in November 2013 and is maintained at [mybatis/mybatis-3](https://github.com/mybatis/mybatis-3). PRADO's SqlMap stayed in the framework through PRADO 4.3 and moved to this package for PRADO 4.4 in 2026.

## Versioning

The package has its own version line, starting at 1.0.0, independent of the PRADO framework version. The first release pairs with PRADO 4.4, the release that removes SqlMap from the framework. Point releases stay backward compatible. Changes are listed in the [changelog](CHANGELOG.md).

## License

BSD-3-Clause. See [LICENSE](LICENSE).

## Authors

- Wei Zhuo — original SqlMap implementation
- Fabio Bas — PRADO 4 maintenance
- Brad Anderson — extension extraction and maintenance
