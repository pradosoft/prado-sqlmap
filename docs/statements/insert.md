[SqlMap Docs](../README.md) -> [Statements](README.md) -> insert

# insert

The `insert` element maps an SQL `INSERT` statement. It optionally contains a [`selectKey`](#selectkey) child element that retrieves a generated primary key before or after the row is inserted.

---

## Syntax

Full example showing both `pre` and `post` key retrieval patterns:

```xml
<sqlMap namespace="Person">

    <!-- Auto-increment (post): MySQL / SQLite style -->
    <insert id="insertPerson" parameterClass="Application\Model\Person">
        <selectKey type="post" property="id" resultClass="int">
            SELECT LAST_INSERT_ID()
        </selectKey>
        INSERT INTO person (name, email)
        VALUES (#name#, #email#)
    </insert>

    <!-- Sequence (pre): Oracle / PostgreSQL style -->
    <insert id="insertPersonSeq" parameterClass="Application\Model\Person">
        <selectKey type="pre" property="id" resultClass="int">
            SELECT person_seq.NEXTVAL FROM dual
        </selectKey>
        INSERT INTO person (id, name, email)
        VALUES (#id#, #name#, #email#)
    </insert>

</sqlMap>
```

---

## Attributes

| Attribute | Required | Description |
|---|---|---|
| `id` | Yes | Unique identifier within the namespace. Passed to [`TSqlMapGateway::insert()`](#tsqlmapgatewayinsert) as `$statementName`. |
| `parameterClass` | No | Fully qualified PHP class name (or primitive alias) of the parameter object. Used when no explicit `parameterMap` is needed; SqlMap maps `#property#` tokens directly to object properties. |
| `parameterMap` | No | Reference to a named [`<parameterMap>`](../mappings/parameter-map.md). Takes precedence over `parameterClass` when both are present. Used for positional `?` placeholders or explicit type/null-value control. |
| `cacheModel` | No | Name of a [`<cacheModel>`](../configuration/cacheModel.md) that is flushed when the insert executes. |

The `insert` element does not support `resultClass`, `resultMap`, or `listClass`. It returns only a generated key or `null`.

---

## TSqlMapGateway::insert()

```php
public function insert(string $statementName, mixed $parameter = null): mixed
```

Executes the named `insert` statement with the supplied parameter object or array.

**Return value:**

- When the `insert` contains a `<selectKey type="post">` element: returns the generated key value retrieved immediately after the insert.
- When the `insert` contains a `<selectKey type="pre">` element: returns the key value retrieved before the insert (the same value already written into `$parameter->property`).
- When no `<selectKey>` is present: returns `null`.

**Example:**

```php
$person = new Person();
$person->name  = 'Alice';
$person->email = 'alice@example.com';

$newId = $gateway->insert('insertPerson', $person);
// $newId holds the LAST_INSERT_ID() value
// $person->id is also set when selectKey property="id"
```

---

## selectKey

`selectKey` is an optional child element of `insert` that retrieves a primary key value.

| Attribute | Required | Description |
|---|---|---|
| `type` | No (default `post`) | `pre` — execute the key query **before** the insert; `post` — execute it **after**. |
| `property` | Yes | Property name on the parameter object that receives the generated key value. |
| `resultClass` | No | PHP class or primitive alias (`int`, `string`) for the returned key. Defaults to `string`. |

Use `type="post"` for auto-increment columns (MySQL `LAST_INSERT_ID()`, SQLite `last_insert_rowid()`). Use `type="pre"` for sequence-based databases that require the key before the row is written (Oracle `NEXTVAL`, PostgreSQL `nextval()`).

For full `selectKey` attribute details see [selectKey](selectKey.md).

---

## Inline Parameters Example

Inline parameters embed the parameter property name directly in the SQL using `#property#` syntax. An optional db type and null-value sentinel can be added as `#property:dbType:nullValue#`.

```xml
<insert id="insertAccount" parameterClass="Application\Model\Account">
    INSERT INTO account (username, balance, created_at)
    VALUES (#username#, #balance:DECIMAL:0.00#, #createdAt:TIMESTAMP#)
</insert>
```

- `#username#` — reads `$parameter->username` (or `$parameter['username']` for arrays).
- `#balance:DECIMAL:0.00#` — casts to `DECIMAL`; substitutes `0.00` when the value is `null`.
- `#createdAt:TIMESTAMP#` — casts to `TIMESTAMP`.

For the full inline parameter syntax see [Inline Parameters](../mappings/inline-parameters.md).

---

## Parameter Map Example

A [`<parameterMap>`](../mappings/parameter-map.md) with positional `?` placeholders gives explicit control over ordering, types, and null values.

```xml
<parameterMap id="insertAccountParams" class="Application\Model\Account">
    <parameter property="username"  dbType="VARCHAR" />
    <parameter property="balance"   dbType="DECIMAL" nullValue="0.00" />
    <parameter property="createdAt" dbType="TIMESTAMP" />
</parameterMap>

<insert id="insertAccountMapped" parameterMap="insertAccountParams">
    INSERT INTO account (username, balance, created_at)
    VALUES (?, ?, ?)
</insert>
```

Parameters bind to `?` placeholders in declaration order.

---

## Examples

### Auto-Increment Key — MySQL `LAST_INSERT_ID()`

```xml
<insert id="insertProduct" parameterClass="Application\Model\Product">
    <selectKey type="post" property="id" resultClass="int">
        SELECT LAST_INSERT_ID()
    </selectKey>
    INSERT INTO product (name, price)
    VALUES (#name#, #price#)
</insert>
```

```php
$product = new Product();
$product->name  = 'Widget';
$product->price = 9.99;

$id = $gateway->insert('insertProduct', $product);
// $id === $product->id === the new auto-increment value
```

### Sequence Key — Oracle `NEXTVAL`

```xml
<insert id="insertOrder" parameterClass="Application\Model\Order">
    <selectKey type="pre" property="orderId" resultClass="int">
        SELECT order_seq.NEXTVAL FROM dual
    </selectKey>
    INSERT INTO orders (order_id, customer_id, total)
    VALUES (#orderId#, #customerId#, #total#)
</insert>
```

```php
$order = new Order();
$order->customerId = 42;
$order->total      = 150.00;

$gateway->insert('insertOrder', $order);
// $order->orderId is set from the sequence before the INSERT runs
```

### PostgreSQL `nextval()`

```xml
<insert id="insertInvoice" parameterClass="Application\Model\Invoice">
    <selectKey type="pre" property="invoiceId" resultClass="int">
        SELECT nextval('invoice_id_seq')
    </selectKey>
    INSERT INTO invoice (invoice_id, amount)
    VALUES (#invoiceId#, #amount#)
</insert>
```

---

[SqlMap Docs](../README.md) -> [Statements](README.md) -> insert

## See Also

- [selectKey](selectKey.md) — full attribute reference for the `<selectKey>` child element
- [Inline Parameters](../mappings/inline-parameters.md) — `#property#`, type tokens, and null-value sentinels
- [parameterMap](../mappings/parameter-map.md) — explicit parameter binding with `<parameterMap>` and `<parameter>`
- [update](update.md) — mapped `UPDATE` statements; returns affected-row count
- [delete](delete.md) — mapped `DELETE` statements; returns affected-row count
