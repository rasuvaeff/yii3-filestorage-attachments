# Upgrade

## 0.5.x → 0.6.0

1. Requires `rasuvaeff/yii3-filestorage` `^0.10` and `rasuvaeff/yii3-filestorage-db` `^0.4` — see their CHANGELOGs and `-db`'s `UPGRADE.md` (it adds its own migration and the same null-scope refusal).
2. Run `./yii migrate:up`. On MySQL/MariaDB the new
   `M261128000000MakeFilestorageAttachmentKeysCaseSensitive` switches `scope_id`, `owner_type`, `owner_id` and
   `role` to a binary NO PAD collation — `utf8mb4_0900_bin` on MySQL 8.0.17+, `utf8mb4_nopad_bin` on MariaDB.
   Making the comparison stricter cannot fail on existing rows. After it, owner ids, types, roles and tenant ids
   that differ only in case, accents or trailing spaces are different values — if your application relied on
   `Order-1` (or `order-1 `) finding the links of `order-1`, normalise (and trim) the ids it passes.
   - **MySQL older than 8.0.17** has no NO PAD binary collation: the migration throws `RuntimeException` there.
   - **The table is locked for writes while it is rebuilt** (`ALGORITHM=COPY` for a collation change of indexed
     columns): `attach()`, `detach()` and `sync()` wait. Roughly one copy of `filestorage_attachment` — run it in a
     maintenance window on a large table.
   - **Row format `DYNAMIC` is required** (the default since MySQL 5.7.9 and MariaDB 10.2.2) for the rebuilt unique
     key; convert a `COMPACT` table with `ALTER TABLE filestorage_attachment ROW_FORMAT=DYNAMIC` first.
   - `down()` fails while the table holds links that differ only in case — or, on MariaDB, whose default collations
     are PAD SPACE, only in trailing spaces — because the old collation's unique key cannot hold them.
3. Multi-tenant applications: a bound `FileScopeProviderInterface` that returns `null` now makes every
   `Attachments` method throw `MissingScopeException` (it used to fall back to the single-tenant key `''`).
   Make sure the tenant is established wherever attachments are read or written.

## Before 0.5.0 → 0.5.0 (MySQL)

`M261127000000AddFilestorageAttachmentFileForeignKey` deletes links whose file no longer exists, then adds the
foreign key. MariaDB 10.5+ created the key from the table migration already; there the migration does nothing. The deletion is committed before the `ALTER TABLE` (MySQL commits DDL implicitly), so it is not
undone if the constraint cannot be added. To see what would go before running it:

```sql
SELECT COUNT(*) FROM filestorage_attachment a
LEFT JOIN filestorage_file f ON f.id = a.file_id
WHERE f.id IS NULL;
```

The migration prints the number it deleted (`Deleted N rows`).
