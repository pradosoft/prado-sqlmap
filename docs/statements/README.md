[SqlMap Docs](../README.md) -> Statements

# Statements

SqlMap supports seven statement types for executing SQL: [`select`](select.md), [`insert`](insert.md), [`insertOrIgnore` and `upsert`](upsert.md), [`update`](update.md), [`delete`](delete.md), and [`statement`](statement.md) (generic). The [`selectKey`](insert.md#selectkey) element appears only as a child of `insert`, `insertOrIgnore`, or `upsert` and retrieves generated key values before or after the insert executes.

All statement elements share a common set of attributes, and all are defined inside a [mapping file](../mappings/README.md) as children of the root `<sqlMap>` element.

---

## Common Attributes

| Attribute | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Unique identifier within the namespace; used to look up the statement via [`TSqlMapGateway`](../api/TSqlMapGateway.md). |
| `parameterClass` | string | no | Fully qualified PHP class name (or primitive alias) of the parameter object. Shorthand when no explicit `parameterMap` is needed. |
| `parameterMap` | string | no | Reference to a named [`<parameterMap>`](../mappings/parameter-map.md); takes precedence over `parameterClass` when both are present. |
| `resultClass` | string | no | PHP class name (or primitive alias) of each result row object. Shorthand when no explicit `resultMap` is needed. |
| `resultMap` | string | no | Reference to a named [`<resultMap>`](../mappings/result-map.md) for full column-to-property control. |
| `listClass` | string | no | PHP class that implements `ArrayAccess` to collect multi-row results; defaults to a plain PHP array. |
| `cacheModel` | string | no | Reference to a named [`<cacheModel>`](../configuration/cache-model.md) that caches query results. Applicable to `select` and `statement` only. |
| `extends` | string | no | `id` of another statement whose SQL body this statement inherits and can append to. See [Extends](#extends). |
| `remapResults` | bool | no | When `true`, the result map is re-evaluated on every execution instead of being cached after the first call. Useful when result columns change dynamically. |

---

## Statement Types

| Type | [`TSqlMapGateway`](../api/TSqlMapGateway.md) methods | Key feature |
|---|---|---|
| [`select`](select.md) | `queryForObject`, `queryForList`, `queryForMap`, `queryForPagedList` | Reads rows; result mapping applied; supports caching. |
| [`insert`](insert.md) | `insert` | Writes one row; optional `<selectKey>` child returns the generated key. |
| [`insertOrIgnore`](upsert.md) | `insert` | Writes one row unless it conflicts with an existing key. |
| [`upsert`](upsert.md) | `insert` | Writes one row, or updates the row it conflicts with. |
| [`update`](update.md) | `update` | Modifies rows; returns affected-row count. |
| [`delete`](delete.md) | `delete` | Removes rows; returns affected-row count. |
| [`statement`](statement.md) | `queryForObject`, `queryForList`, `insert`, `update`, `delete` | Generic; use when the DML type is variable or unknown at mapping time. |
| [`procedure`](#procedure) | same as `statement` | Calls a stored procedure via PDO; maps IN/OUT parameters. |

---

## Extends

The `extends` attribute lets one statement inherit the SQL body of another and append additional clauses. This avoids duplicating shared SQL fragments.

```xml
<!-- Base statement — not called directly -->
<select id="selectAccountBase"
        parameterClass="Account"
        resultMap="account-result">
    SELECT acc_id, acc_first_name, acc_last_name, acc_email
    FROM   accounts
</select>

<!-- Adds a WHERE clause -->
<select id="selectAccountById"
        extends="selectAccountBase">
    WHERE acc_id = #id#
</select>

<!-- Adds a different WHERE clause -->
<select id="selectAccountByEmail"
        extends="selectAccountBase">
    WHERE acc_email = #emailAddress#
</select>
```

`selectAccountById` and `selectAccountByEmail` each prepend the SQL from `selectAccountBase` before their own body. The base statement itself may also be executed standalone if it carries its own `id`.

---

## procedure

The `<procedure>` element calls a stored procedure. It behaves like `<statement>` but signals the driver to use a procedure-call syntax. Parameter direction (IN, OUT, INOUT) is declared in the referenced [`<parameterMap>`](../mappings/parameter-map.md) using the `direction` attribute on each `<parameter>`.

```xml
<parameterMap id="swapEmailsParam" class="object">
    <parameter property="email1" column="email1" direction="InOut"/>
    <parameter property="email2" column="email2" direction="InOut"/>
</parameterMap>

<procedure id="swapAccountEmails"
           parameterMap="swapEmailsParam">
    {call swap_emails(?,?)}
</procedure>
```

Call through the gateway with any method appropriate to the procedure's return behavior (`queryForObject`, `queryForList`, `insert`, etc.).

---

## Individual Statement Pages

- [select](select.md)
- [insert and selectKey](insert.md)
- [update](update.md)
- [delete](delete.md)
- [statement](statement.md)

---

[SqlMap Docs](../README.md) -> Statements

## See Also

- [Mapping Files](../mappings/README.md) — `resultMap`, `parameterMap`, and the root `<sqlMap>` element
- [Dynamic SQL](../dynamic-sql/README.md) — conditional and iterative SQL fragments inside statements
- [Configuration](../configuration/README.md) — `cacheModel`, `typeAlias`, and the top-level config file
