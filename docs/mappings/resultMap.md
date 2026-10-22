[SqlMap Docs](../README.md) -> [Mappings](README.md) -> resultMap

# resultMap

A `<resultMap>` maps the columns of a SQL result set to the properties of a PHP object. It controls column-to-property name translation, PHP type coercion, null value substitution, custom type handlers, nested object graphs (via `resultMapping`), lazy-loaded associations (via `select`), one-to-many aggregation (via `groupBy`), and polymorphic mapping (via `discriminator`/`subMap`).

Statements reference a result map by ID using the `resultMap` attribute. Use a result map when column names differ from property names, when type coercion or null replacement is required, or when any of the advanced features above are needed. For simple cases where column aliases match PHP property names exactly, see [Auto-mapping with resultClass](#auto-mapping-with-resultclass).

The runtime class for result maps is [`TResultMap`](../../src/Data/SqlMap/Configuration/TResultMap.php); individual column descriptors are [`TResultProperty`](../../src/Data/SqlMap/Configuration/TResultProperty.php).

---

## Syntax

```xml
<resultMap id="account-full" class="Account" extends="account-base" groupBy="Id">

    <!-- scalar columns -->
    <result property="Id"           column="Account_Id"        type="integer" />
    <result property="FirstName"    column="Account_FirstName" type="string"  />
    <result property="LastName"     column="Account_LastName"  type="string"  />
    <result property="Email"        column="Account_Email"     nullValue=""   />
    <result property="CreatedAt"    column="Account_Created"   type="date"    />

    <!-- nested result map (one-to-many, requires groupBy on parent) -->
    <result property="Orders" resultMapping="account-orders" />

    <!-- lazy-loaded association -->
    <result property="Preferences"
            column="Account_Id"
            select="GetPreferencesByAccountId"
            lazyLoad="true"
            type="TList" />

    <!-- custom type handler -->
    <result property="Status"
            column="Account_Status"
            typeHandler="AccountStatusHandler" />

    <!-- discriminator for polymorphic mapping -->
    <discriminator column="Account_Type" type="string" />
    <subMap value="premium" resultMapping="premium-account" />
    <subMap value="trial"   resultMapping="trial-account"   />

</resultMap>
```

---

## Attributes — `<resultMap>`

| Attribute | Required | Description |
|---|---|---|
| `id` | Yes | Unique identifier within the mapping file namespace. Referenced by `resultMap` attribute on statements. |
| `class` | Yes | Fully-qualified PHP class name, type alias, or `array`. An instance of this class is created for each result row. |
| `extends` | No | ID of another `<resultMap>` whose `<result>` children are prepended to this map's property list. See [extends](#extends). |
| `groupBy` | No | Comma-separated list of property names. Rows sharing identical values for these properties are collapsed into a single parent object; collection properties populated via `resultMapping` accumulate child rows. See [Nested result maps with groupBy](#nested-result-maps-with-groupby). |

---

## Attributes — `<result>`

| Attribute | Required | Description |
|---|---|---|
| `property` | Yes | Name of the property on the result object to set. Supports dot-notation for nested access (e.g., `Address.City`). |
| `column` | No | Column name in the result set. Required when `select` is absent and `columnIndex` is not used. For multi-column `select` parameters use the form `ParamName=ColumnName,…`. |
| `columnIndex` | No | Integer (1-based) position of the column in the result set. Replaces `column` for index-based access. See [columnIndex](#columnindex). |
| `type` | No | PHP type to coerce the column value to. Recognized values include `string`, `integer`, `float`, `boolean`, `date`, `TList`, `array`, and any registered type alias. When omitted the framework infers the type from the property. |
| `dbType` | No | Database type hint passed to the type handler. Used when `typeHandler` is set and needs to distinguish storage types. |
| `nullValue` | No | PHP value assigned to the property when the column value is SQL `NULL`. See [nullValue](#nullvalue). |
| `resultMapping` | No | ID of another `<resultMap>` used to map this property. Enables nested object graphs. Requires `groupBy` on the parent map for collection properties. |
| `select` | No | ID of another mapped statement. SqlMap executes that statement with the value of `column` as the parameter to populate this property. Combine with `lazyLoad="true"` to defer execution. |
| `lazyLoad` | No | `true` or `false` (default `false`). Defers execution of the `select` statement until the property is first accessed. Requires `select` to be set. |
| `typeHandler` | No | Class name of a custom [`TSqlMapTypeHandler`](../../src/Data/SqlMap/DataMapper/TSqlMapTypeHandler.php) registered for this property. Overrides all automatic type resolution. |

---

## Auto-mapping with resultClass

When a statement's column aliases match PHP property names exactly, the `resultClass` attribute on the statement eliminates the need for a `<resultMap>` block.

```xml
<select id="GetAccount" resultClass="Account" parameterClass="integer">
    SELECT
        Account_Id       AS Id,
        Account_FirstName AS FirstName,
        Account_LastName  AS LastName,
        Account_Email     AS Email
    FROM Accounts
    WHERE Account_Id = #value#
</select>
```

SqlMap creates an `Account` instance and sets each property from the matching alias. Use `resultClass` when:

- All needed columns can be aliased to property names in the SQL.
- No type coercion beyond PHP's default casting is required.
- None of the advanced features (lazy loading, `groupBy`, discriminator, `nullValue`) are needed.

When `resultClass` is `array`, each row becomes an associative array keyed by column name or alias.

---

## extends

The `extends` attribute causes a result map to inherit all `<result>` children from a parent result map. Parent properties are applied first, then the child's own properties.

```xml
<resultMap id="lite-order-result" class="Order">
    <result property="Id"         column="Order_Id"         type="integer" />
    <result property="CardType"   column="Order_CardType"   type="string"  />
    <result property="CardNumber" column="Order_CardNumber" type="string"  />
    <result property="Street"     column="Order_Street"     type="string"  />
    <result property="City"       column="Order_City"       type="string"  />
</resultMap>

<resultMap id="order-with-lines" class="Order" extends="lite-order-result">
    <result property="LineItemsList"
            column="Order_Id"
            select="GetLineItemsForOrder"
            lazyLoad="true"
            type="TList" />
</resultMap>
```

`order-with-lines` contains all five properties from `lite-order-result` plus the lazy-loaded `LineItemsList`. Any property defined in the child with the same `property` name overrides the parent entry.

---

## Nested result maps with groupBy

A single SQL `JOIN` query can produce multiple rows per parent entity (one-to-many). The `groupBy` attribute on the parent result map collapses those rows into a single parent object and accumulates the child rows into a collection property.

**Rules:**

- `groupBy` lists one or more property names whose combined values identify a unique parent row.
- The child collection property must have `resultMapping` pointing to a separate `<resultMap>` for the child entity.
- The collection property type must be `TList`, `array`, or another list-compatible type.

**Example — Account with Orders:**

```xml
<resultMap id="Account-with-Orders" class="AccountWithOrders" groupBy="Id">
    <result property="Id"           column="Account_Id"        type="integer" />
    <result property="FirstName"    column="Account_FirstName" />
    <result property="LastName"     column="Account_LastName"  />
    <result property="EmailAddress" column="Account_Email"     />
    <result property="Orders"       resultMapping="account-orders" />
</resultMap>

<resultMap id="account-orders" class="Order">
    <result property="Id"         column="Order_Id"         type="integer" />
    <result property="Date"       column="Order_Date"       type="date"
                                  nullValue="0001-01-01 00:00:00" />
    <result property="CardExpiry" column="Order_CardExpiry" />
    <result property="CardType"   column="Order_CardType"   />
    <result property="CardNumber" column="Order_CardNumber" />
    <result property="Street"     column="Order_Street"     />
    <result property="City"       column="Order_City"       />
    <result property="Province"   column="Order_Province"   />
    <result property="PostalCode" column="Order_PostalCode" />
</resultMap>
```

The corresponding query joins the two tables:

```xml
<select id="GetAccountsWithOrders" resultMap="Account-with-Orders">
    SELECT a.*, o.*
    FROM Accounts a
    LEFT JOIN Orders o ON o.Order_AccountId = a.Account_Id
    ORDER BY a.Account_Id
</select>
```

SqlMap iterates the result set, grouping by `Id`. For each unique `Account_Id` value it creates one `AccountWithOrders` object. Each `Order_*` row is mapped via `account-orders` and appended to the parent's `Orders` collection. An account without orders has null in every `Order_*` column; it is returned with an empty `Orders` collection.

---

## Lazy loading

The `select` attribute on a `<result>` element names another mapped statement. SqlMap executes that statement — passing the value of `column` as the parameter — and assigns the result to the property. Setting `lazyLoad="true"` defers that execution until the property is first accessed.

```xml
<resultMap id="order-with-lines-result" class="Order" extends="lite-order-result">
    <result property="LineItemsList"
            column="Order_Id"
            select="GetLineItemsForOrder"
            lazyLoad="true"
            type="TList" />
</resultMap>
```

**How lazy loading works:**

- When `lazyLoad="true"`, the result object's property is replaced with a [`TLazyLoadList`](../../src/Data/SqlMap/DataMapper/TLazyLoadList.php) proxy (for collection types) or a [`TObjectProxy`](../../src/Data/SqlMap/DataMapper/TObjectProxy.php) wrapper (for single objects).
- The proxy intercepts the first access and executes the deferred `select` statement at that point.
- Subsequent accesses use the already-loaded value.

**Multi-column parameters** — when the sub-select requires multiple columns, supply them as `column="ParamA=ColA,ParamB=ColB"`:

```xml
<result property="FavouriteLineItem"
        column="Order_ID=Order_Id,LineItem_ID=Order_FavouriteLineItem"
        select="GetSpecificLineItem" />
```

**Namespace-qualified selects** — cross-namespace references use dot notation:

```xml
<result property="LineItemsList"
        column="Order_Id"
        select="LineItem.GetLineItemsForOrder"
        lazyLoad="true"
        type="TList" />
```

Setting `lazyLoad="false"` explicitly (the default) causes the sub-select to execute immediately during the parent result-set traversal.

---

## discriminator / subMap

A `<discriminator>` inside a `<resultMap>` enables polymorphic result mapping: a single query returns rows of different concrete types, determined by the value of a designated column.

The runtime classes are [`TDiscriminator`](../../src/Data/SqlMap/Configuration/TDiscriminator.php) and [`TSubMap`](../../src/Data/SqlMap/Configuration/TSubMap.php).

**How it works:**

1. SqlMap reads the discriminator column value from the current row.
2. It compares that value (as a string) against each `<subMap value="…">`.
3. When a match is found, the named `resultMapping` is used to populate the object instead of the default map.
4. When no match is found, the containing `<resultMap>` is used as the fallback.
5. Sub-maps can themselves contain discriminators, enabling multi-level hierarchies.

**Example — Document polymorphism:**

```xml
<!-- Base map: covers all document types -->
<resultMap id="document" class="Document">
    <result property="Id"    column="Document_Id"    type="integer" />
    <result property="Title" column="Document_Title" />
    <discriminator column="Document_Type" type="string" />
    <subMap value="Book"      resultMapping="book"      />
    <subMap value="Newspaper" resultMapping="newspaper" />
</resultMap>

<!-- Concrete map for books — inherits base columns -->
<resultMap id="book" class="Book" extends="document">
    <result property="PageNumber" column="Document_PageNumber" />
</resultMap>

<!-- Concrete map for newspapers — inherits base columns -->
<resultMap id="newspaper" class="Newspaper" extends="document">
    <result property="City" column="Document_City" />
</resultMap>
```

The statement uses the base map:

```xml
<statement id="GetAllDocuments" resultMap="document">
    SELECT * FROM Documents ORDER BY Document_Type, Document_Id
</statement>
```

When `Document_Type` is `"Book"`, SqlMap uses the `book` result map and creates a `Book` instance. When it is `"Newspaper"`, a `Newspaper` instance is created. Any other value falls back to a plain `Document` instance.

**Attributes — `<discriminator>`:**

| Attribute | Description |
|---|---|
| `column` | Column name in the result set whose value drives submap selection. |
| `type` | PHP type to coerce the column value to before string comparison. |
| `columnIndex` | 1-based column position; alternative to `column`. |
| `nullValue` | Value used when the column is SQL `NULL`. |
| `typeHandler` | Custom type handler class name for the discriminator column. |

**Attributes — `<subMap>`:**

| Attribute | Description |
|---|---|
| `value` | String value compared against the discriminator column. |
| `resultMapping` | ID of the `<resultMap>` to use when `value` matches. |

---

## columnIndex

The `columnIndex` attribute selects a column by its 1-based integer position in the result set instead of by name. This can improve performance on large result sets by avoiding name-based column lookup in the PDO result.

```xml
<resultMap id="order-by-index" class="Order">
    <result property="Id"         columnIndex="1" type="integer" />
    <result property="CardType"   columnIndex="2" />
    <result property="CardNumber" columnIndex="3" />
    <result property="Street"     columnIndex="4" />
</resultMap>
```

`column` and `columnIndex` are mutually exclusive on a single `<result>` element. Do not mix them within the same property mapping. When using `columnIndex`, the order of `<result>` elements must match the column order in the `SELECT` list.

---

## nullValue

The `nullValue` attribute on a `<result>` element specifies the PHP value assigned to the property when the database column contains SQL `NULL`. Without `nullValue`, a `NULL` column maps to PHP `null`.

```xml
<result property="Date"
        column="Order_Date"
        type="date"
        nullValue="0001-01-01 00:00:00" />

<result property="Email"
        column="Account_Email"
        nullValue="" />

<result property="Score"
        column="Account_Score"
        type="integer"
        nullValue="0" />
```

The value is coerced to the property's `type` before assignment. `nullValue` is also supported on `<discriminator>` elements.

---

[SqlMap Docs](../README.md) -> [Mappings](README.md) -> resultMap

## See also

- [parameterMap](parameterMap.md) — explicit parameter mapping with `dbType`, `nullValue`, and `mode` control
- [Inline Parameters](inline-parameters.md) — `#property#` and `$property$` syntax embedded directly in SQL strings
- [Statements](../statements/README.md) — `resultMap` and `resultClass` attributes on `select`, `statement`, and `procedure` tags
- [Dynamic SQL](../dynamic-sql/README.md) — conditional SQL fragments that combine with result maps
- [Configuration](../configuration/README.md) — global `typeAlias` and `typeHandler` registration referenced by result maps
