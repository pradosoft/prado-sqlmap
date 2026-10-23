[SqlMap Docs](../README.md) -> [Configuration](README.md) -> cacheModel

# cacheModel

A `cacheModel` defines a named cache that [`select`](../mapping/select.md) statements reference to avoid repeated database round-trips. Results returned by a cached select are stored in the cache; subsequent calls with the same statement and parameters return the cached value without hitting the database. An `insert`, `update`, or `delete` statement that references the cache model flushes it when it executes.

Cache models are declared inside a [mapping file](../mapping/README.md) and are global to the [`TSqlMapManager`](../api/TSqlMapManager.md) instance.

## Syntax

```xml
<sqlMap namespace="Account">

    <cacheModel id="accountCache" implementation="LRU">
        <flushInterval hours="1" minutes="30" />
        <flushOnExecute statement="Account.insertAccount" />
        <flushOnExecute statement="Account.updateAccount" />
        <flushOnExecute statement="Account.deleteAccount" />
        <property name="CacheSize" value="100" />
    </cacheModel>

    <select id="selectAllAccounts"
            resultClass="Account"
            cacheModel="accountCache">
        SELECT * FROM accounts
    </select>

</sqlMap>
```

## Attributes

| Attribute | Required | Values | Description |
|---|---|---|---|
| `id` | yes | string | Unique identifier for this cache model within the SqlMap. Referenced by the `cacheModel` attribute of statements. |
| `implementation` | no | `LRU`, `FIFO`, `Basic` | Cache strategy. Defaults to `Basic` ([`TSqlMapApplicationCache`](../api/TSqlMapApplicationCache.md)). |
| `readOnly` | no | `true`/`false` | Reserved for future use; not currently enforced by the runtime. |
| `serialize` | no | `true`/`false` | Reserved for future use; not currently enforced by the runtime. |

## Implementation Types

| Value | Class | Scope | Description |
|---|---|---|---|
| `LRU` | [`TSqlMapLruCache`](../api/TSqlMapLruCache.md) | Request | Least-recently-used eviction. Keeps the most-accessed entries up to `CacheSize`. Use when a small hot set of queries dominates. |
| `FIFO` | [`TSqlMapFifoCache`](../api/TSqlMapFifoCache.md) | Request | First-in-first-out eviction. Removes the oldest entry when the cache is full. Use when access patterns are roughly uniform. |
| `Basic` | [`TSqlMapApplicationCache`](../api/TSqlMapApplicationCache.md) | Application | Delegates to the PRADO `ICache` application component. Survives across requests. Use for shared or distributed caching. |

`LRU` and `FIFO` caches are in-memory and live only for the duration of the current PHP request. All cached data is lost when the request ends.

## flushInterval

`flushInterval` sets the time-to-live for cached entries. Each attribute contributes to a combined duration in seconds. Omitting `flushInterval` means entries never expire due to time (they may still be evicted by size limits or `flushOnExecute`).

```xml
<flushInterval hours="0" minutes="30" seconds="0" />
```

| Attribute | Unit | Multiplier |
|---|---|---|
| `hours` | hours | × 3600 |
| `minutes` | minutes | × 60 |
| `seconds` | seconds | × 1 |
| `days` | days | × 86400 |
| `duration` | seconds (raw) | overrides all others |

All attributes are additive except `duration`, which sets the interval directly and ignores the remaining attributes.

## flushOnExecute

`flushOnExecute` registers a write statement that, when executed, causes the entire cache to be flushed. Multiple elements may be declared.

```xml
<flushOnExecute statement="Account.insertAccount" />
<flushOnExecute statement="Account.updateAccount" />
<flushOnExecute statement="Account.deleteAccount" />
```

| Attribute | Required | Description |
|---|---|---|
| `statement` | yes | Fully-qualified statement id (`namespace.statementId` or bare id) of the insert, update, or delete statement that triggers the flush. |

When the named statement executes — regardless of whether it modifies any rows — the cache is cleared immediately.

## property size

The `property` child element passes configuration values to the underlying cache implementation. For `LRU` and `FIFO` caches the `CacheSize` property sets the maximum number of entries before eviction begins. The default is `100`.

```xml
<property name="CacheSize" value="200" />
```

Setting `CacheSize` to `0` in XML is treated as `100` by the implementation.

## Attaching to a select

Add the `cacheModel` attribute to a `select` statement to enable caching for it. The value must match the `id` of a `cacheModel` in scope.

```xml
<select id="selectAccountById"
        parameterClass="int"
        resultClass="Account"
        cacheModel="accountCache">
    SELECT * FROM accounts WHERE account_id = #value#
</select>
```

When the same statement is executed with the same parameter, the result is returned from the cache. The cache key is derived from the statement id and a hash of the parameter object.

## Attaching to a write statement

The `cacheModel` attribute on an `insert`, `update`, or `delete` statement flushes the named cache model when the statement executes. It has the same effect as a `flushOnExecute` element that names the statement. The cache model may be declared in any mapping file.

```xml
<update id="updateAccount"
        parameterClass="Account"
        cacheModel="accountCache">
    UPDATE accounts SET account_email = #EmailAddress# WHERE account_id = #Id#
</update>
```

## APPLICATION cache

The `Basic` implementation delegates to the PRADO application cache (`Prado::getApplication()->getCache()`). This requires that an [`ICache`](https://github.com/pradosoft/prado) component — such as `TMemCache` or `TDbCache` — is configured in the application and that [`TSqlMapApplicationCache`](../api/TSqlMapApplicationCache.md) is available.

```xml
<cacheModel id="sharedCache" implementation="Basic">
    <flushInterval hours="2" />
    <flushOnExecute statement="Product.updateProduct" />
</cacheModel>
```

`flushInterval` maps directly to the `expire` parameter passed to `ICache::set()`. Because the PRADO cache persists across requests, this implementation suits data that changes infrequently and is accessed by multiple concurrent requests or workers.

## Programmatic flush

[`TSqlMapGateway::flushCaches()`](../api/TSqlMapGateway.md#flushCaches) flushes every registered cache model at once.

```php
$gateway = $sqlMapManager->getSqlMapGateway();
$gateway->flushCaches();
```

To flush a single model, retrieve it from the manager directly:

```php
$sqlMapManager->getCacheModel('accountCache')->flush();
```

---

[SqlMap Docs](../README.md) -> [Configuration](README.md) -> cacheModel

## See also

- [`select`](../mapping/select.md) — `cacheModel` attribute that attaches a cache model to a query
- [`TSqlMapGateway`](../api/TSqlMapGateway.md) — public API, including `flushCaches()`
- [`TSqlMapApplicationCache`](../api/TSqlMapApplicationCache.md) — PRADO `ICache`-backed implementation used by the `Basic` type
- [`TSqlMapManager`](../api/TSqlMapManager.md) — holds all parsed cache models and mapped statements
