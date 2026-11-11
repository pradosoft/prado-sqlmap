[SqlMap Docs](../README.md) -> [Statements](README.md) -> insertOrIgnore and upsert

# insertOrIgnore and upsert

The `insertOrIgnore` element maps an `INSERT` that skips a row conflicting with an existing key. The `upsert` element maps an `INSERT` that updates the conflicting row. Both run through `TSqlMapGateway::insert()` and behave as [`insert`](insert.md): they take the same attributes, accept a [`selectKey`](selectKey.md) child, return the `selectKey` value or `null`, and flush the `cacheModel` they name.

The SQL in the element is the driver's own conflict syntax. SqlMap does not rewrite it.

| Driver | `insertOrIgnore` | `upsert` |
|---|---|---|
| SQLite | `INSERT OR IGNORE INTO ...` | `INSERT ... ON CONFLICT (col) DO UPDATE SET ...` |
| MySQL | `INSERT IGNORE INTO ...` | `INSERT ... ON DUPLICATE KEY UPDATE ...` |
| PostgreSQL | `INSERT ... ON CONFLICT DO NOTHING` | `INSERT ... ON CONFLICT (col) DO UPDATE SET ...` |
| SQL Server, Oracle, DB2, Firebird | `MERGE` without `WHEN MATCHED` | `MERGE ... WHEN MATCHED THEN UPDATE ...` |

---

## Syntax

```xml
<sqlMap>
    <insertOrIgnore id="addTag" parameterClass="Application\Model\Tag">
        INSERT OR IGNORE INTO tags (name) VALUES (#name#)
    </insertOrIgnore>

    <upsert id="saveScore" parameterClass="Application\Model\Score"
            updateColumns="score, updated" conflictColumns="player">
        INSERT INTO scores (player, score, updated) VALUES (#player#, #score#, #updated#)
        ON CONFLICT (player) DO UPDATE SET score = excluded.score, updated = excluded.updated
    </upsert>
</sqlMap>
```

```php
$gateway->insert('addTag', $tag);
$gateway->insert('saveScore', $score);
```

---

## Attributes

`insertOrIgnore` takes the attributes of [`insert`](insert.md#attributes). `upsert` adds two:

| Attribute | Type | Description |
|---|---|---|
| `updateColumns` | comma-separated list | Columns the statement updates on a conflict. Stored as `TSqlMapUpsert::getUpdateColumns()`; `null` when absent. |
| `conflictColumns` | comma-separated list | Columns that detect the conflict. Stored as `TSqlMapUpsert::getConflictColumns()`; `null` when absent. |

The two attributes describe the statement for code that reads the mapping. They do not change the SQL.

---

## Classes

| Element | Statement class | Mapped statement class |
|---|---|---|
| `insertOrIgnore` | `TSqlMapInsertOrIgnore` (a `TSqlMapInsert`) | `TInsertOrIgnoreMappedStatement` (a `TInsertMappedStatement`) |
| `upsert` | `TSqlMapUpsert` (a `TSqlMapInsert`) | `TUpsertMappedStatement` (a `TInsertMappedStatement`) |

---

[SqlMap Docs](../README.md) -> [Statements](README.md) -> insertOrIgnore and upsert
