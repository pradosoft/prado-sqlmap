[SqlMap Docs](../README.md) -> [Statements](README.md) -> delete

# delete

A `<delete>` element maps a SQL `DELETE` statement to a named identifier. It accepts an optional input parameter object and returns the number of rows affected.

---

## Syntax

```xml
<delete id="deleteAccount" parameterClass="Account">
    DELETE FROM account WHERE account_id = #id#
</delete>
```

The body is the SQL text to execute. [Inline parameters](../dynamic-sql/inline-parameters.md) (`#property#`) and [dynamic SQL](../dynamic-sql/README.md) blocks are both supported.

---

## Attributes

| Attribute | Required | Description |
|---|---|---|
| `id` | Yes | Unique identifier for the statement within its namespace. Passed to [`TSqlMapGateway::delete()`](../../src/Data/SqlMap/TSqlMapGateway.php) as the first argument. |
| `parameterClass` | No | Fully qualified PHP class name (or primitive alias) of the input parameter. When omitted, the framework infers the type from the value passed at runtime. |
| `parameterMap` | No | References a named [`<parameterMap>`](../mappings/parameterMap.md) that defines explicit column-to-property bindings. `parameterClass` and `parameterMap` are mutually exclusive; prefer `parameterClass` with inline parameters for simple cases. |

---

## TSqlMapGateway::delete()

```php
public function delete(string $statementName, mixed $parameter = null): int
```

**Parameters**

| Parameter | Type | Description |
|---|---|---|
| `$statementName` | `string` | The `id` of the `<delete>` statement to execute. |
| `$parameter` | `mixed` | The input parameter object, array, or scalar. Pass `null` when the statement requires no parameters. |

**Return value** — `int` — the number of rows deleted by the statement.

Retrieve the gateway from the configured [`TSqlMapConfig`](../../src/Data/SqlMap/TSqlMapConfig.php) module:

```php
/** @var \Prado\Data\SqlMap\TSqlMapGateway $gateway */
$gateway = $this->getModule('sqlmap')->getClient();
$rowsAffected = $gateway->delete('deleteAccount', $account);
```

---

## Examples

### Delete by object

Pass the domain object directly. The framework reads the `id` property via the inline parameter `#id#`.

```xml
<delete id="deleteAccount" parameterClass="Account">
    DELETE FROM account WHERE account_id = #id#
</delete>
```

```php
$account = new Account();
$account->setId(42);

$rows = $gateway->delete('deleteAccount', $account);
// $rows === 1 on success
```

### Delete by scalar ID

Use a primitive `int` or `string` as the parameter. Reference it with `#value#` (the conventional alias for scalar parameters).

```xml
<delete id="deleteAccountById" parameterClass="int">
    DELETE FROM account WHERE account_id = #value#
</delete>
```

```php
$rows = $gateway->delete('deleteAccountById', 42);
```

### Delete with a parameterMap

A [`<parameterMap>`](../mappings/parameterMap.md) gives explicit control over type, `dbType`, and `nullValue` for each bound value.

```xml
<parameterMap id="deleteAccountParam" class="Account">
    <parameter property="id" column="account_id" dbType="INT" />
</parameterMap>

<delete id="deleteAccountMapped" parameterMap="deleteAccountParam">
    DELETE FROM account WHERE account_id = ?
</delete>
```

---

## Notes

### No result mapping

`<delete>` produces no result set. Specifying `resultClass` or `resultMap` has no effect. Use [`<select>`](select.md) when rows must be returned.

### Cache invalidation

Any [`<cacheModel>`](../configuration/cacheModel.md) that lists this statement's `id` in a `<flushOnExecute>` element is flushed when the delete executes successfully. A `cacheModel` attribute on the `<delete>` flushes the named cache model the same way. This behavior is identical to [`<update>`](update.md).

```xml
<cacheModel id="accountCache" implementation="LRU">
    <flushOnExecute statement="deleteAccount" />
    <flushOnExecute statement="updateAccount" />
</cacheModel>
```

---

[SqlMap Docs](../README.md) -> [Statements](README.md) -> delete

## See also

- [`TSqlMapGateway`](../../src/Data/SqlMap/TSqlMapGateway.php) — public API facade; hosts `delete()`, `insert()`, `update()`, and all query methods.
- [update](update.md) — same parameter model and cache-invalidation behavior as `delete`.
- [parameterMap](../mappings/parameterMap.md) — explicit parameter binding with type, `dbType`, and `nullValue` control.
- [cacheModel](../configuration/cacheModel.md) — cache configuration and `<flushOnExecute>` invalidation rules.
- [inline parameters](../dynamic-sql/inline-parameters.md) — `#property#` and `#property:dbType:nullValue#` syntax used in statement bodies.
