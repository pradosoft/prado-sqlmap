[SqlMap Docs](../README.md) -> [Statements](README.md) -> update

# update

The `update` tag maps a SQL `UPDATE` statement to a named statement identifier. [`TSqlMapGateway`](../../src/Data/SqlMap/TSqlMapGateway.php) executes it and returns the number of affected rows.

## Syntax

```xml
<update id="UpdateAccount" parameterClass="Account">
    UPDATE accounts
    SET username = #username#,
        email    = #email#
    WHERE account_id = #accountId#
</update>
```

The SQL body is a plain SQL `UPDATE` string. Inline parameter tokens (`#property#`) or a `parameterMap` reference are used to bind values.

## Attributes

| Attribute | Required | Description |
|---|---|---|
| `id` | Yes | Unique name for this statement within the mapping file. Used as the key passed to `TSqlMapGateway::update()`. |
| `parameterClass` | No | Fully qualified class name (or [type alias](../configuration/README.md#typealias)) of the parameter object. When omitted, SqlMap accepts any object or associative array. |
| `parameterMap` | No | Name of a [`parameterMap`](../mappings/README.md#parametermap) defined in this or another mapping file. Mutually exclusive with inline `#property#` tokens; use one or the other. |

`update` does not support `resultClass` or `resultMap` — the only return value is the affected-row count.

## TSqlMapGateway::update()

```php
public function update(string $statementName, mixed $parameter = null): int
```

| Parameter | Type | Description |
|---|---|---|
| `$statementName` | `string` | The `id` attribute of the `update` element to execute. |
| `$parameter` | `mixed` | Parameter object, associative array, or scalar bound to the statement. Pass `null` when the statement needs no parameters. |

**Return value:** `int` — the number of rows affected by the `UPDATE`.

Example:

```php
$account = new Account();
$account->accountId = 42;
$account->username  = 'jdoe';
$account->email     = 'jdoe@example.com';

$rows = $gateway->update('UpdateAccount', $account);
// $rows === 1 when exactly one row was modified
```

## Inline Parameters Example

Inline parameter tokens embed parameter binding directly in the SQL body. The token format is `#property#` or `#property:DbType:nullValue#`.

```xml
<update id="UpdateAccountEmail" parameterClass="Account">
    UPDATE accounts
    SET email = #email:VARCHAR#
    WHERE account_id = #accountId:INTEGER#
</update>
```

The optional `:DbType` segment controls the PDO parameter type used during binding. The optional `:nullValue` segment specifies a sentinel value that SqlMap treats as SQL `NULL`.

See [Inline Parameters](../mappings/README.md#inline-parameters) for the full token syntax.

## Parameter Map Example

A [`parameterMap`](../mappings/README.md#parametermap) provides explicit control over parameter ordering, types, and null handling.

```xml
<parameterMap id="UpdateAccountParam" class="Account">
    <parameter property="username"  dbType="VARCHAR"  />
    <parameter property="email"     dbType="VARCHAR"  />
    <parameter property="accountId" dbType="INTEGER"  />
</parameterMap>

<update id="UpdateAccount" parameterMap="UpdateAccountParam">
    UPDATE accounts
    SET username = ?,
        email    = ?
    WHERE account_id = ?
</update>
```

The `?` placeholders are filled in the order the `parameter` elements appear in the map.

## Dynamic UPDATE

The [`dynamic`](../dynamic-sql/README.md) tag and its conditional children allow a `SET` clause to include only the columns that are actually provided by the caller.

```xml
<update id="UpdateAccountSelective" parameterClass="Account">
    UPDATE accounts
    <dynamic prepend="SET">
        <isNotNull prepend="," property="username">
            username = #username#
        </isNotNull>
        <isNotNull prepend="," property="email">
            email = #email#
        </isNotNull>
        <isNotNull prepend="," property="status">
            status = #status#
        </isNotNull>
    </dynamic>
    WHERE account_id = #accountId#
</update>
```

`dynamic` strips its own `prepend` (`SET`) when all children evaluate to empty. Each conditional child strips its own `prepend` (`,`) when it is the first active child. The result is a valid `SET` clause containing only the non-null columns.

Available conditional tags: [`isNull`](../dynamic-sql/README.md#null-checks), [`isNotNull`](../dynamic-sql/README.md#null-checks), [`isEmpty`](../dynamic-sql/README.md#empty-checks), [`isNotEmpty`](../dynamic-sql/README.md#empty-checks), [`isEqual`](../dynamic-sql/README.md#comparisons), [`isNotEqual`](../dynamic-sql/README.md#comparisons), [`isGreaterThan`](../dynamic-sql/README.md#comparisons), [`isLessEqual`](../dynamic-sql/README.md#comparisons), and others. See [Dynamic SQL](../dynamic-sql/README.md) for the full reference.

## Cache Invalidation via flushOnExecute

A `cacheModel` accumulates `SELECT` results until it is flushed. The [`flushOnExecute`](../configuration/README.md#flushonexecute) element inside a `cacheModel` declaration names statements whose execution causes an immediate cache flush.

Declare `flushOnExecute` in the cache model (in `sqlMapConfig` or a mapping file):

```xml
<cacheModel id="AccountCache" implementation="LRU">
    <flushInterval hours="1" />
    <flushOnExecute statement="UpdateAccount" />
    <flushOnExecute statement="DeleteAccount" />
    <property name="size" value="100" />
</cacheModel>
```

Assign the cache model to the `SELECT` that should be invalidated:

```xml
<select id="GetAccountById" parameterClass="int"
        resultClass="Account" cacheModel="AccountCache">
    SELECT * FROM accounts WHERE account_id = #value#
</select>
```

When `TSqlMapGateway::update('UpdateAccount', ...)` executes, SqlMap automatically flushes `AccountCache`, so subsequent calls to `GetAccountById` return fresh data.

A `cacheModel` attribute on an `<insert>`, `<update>`, or `<delete>` statement has the same effect as listing the statement in `flushOnExecute`:

```xml
<update id="UpdateAccount" parameterClass="Account" cacheModel="AccountCache">
    UPDATE accounts SET account_email = #EmailAddress# WHERE account_id = #Id#
</update>
```

See [cacheModel](../configuration/README.md#cachemodel) for the full cache model reference.

---

[SqlMap Docs](../README.md) -> [Statements](README.md) -> update

## See Also

- [Statements Overview](README.md) — all DML statement tags and the `TSqlMapGateway` PHP API
- [Dynamic SQL](../dynamic-sql/README.md) — `dynamic`, conditional tags, and `iterate` for building SQL at runtime
- [Mappings — parameterMap](../mappings/README.md#parametermap) — explicit parameter ordering, types, and null handling
- [Configuration — cacheModel](../configuration/README.md#cachemodel) — cache model declaration, `flushInterval`, and `flushOnExecute`
