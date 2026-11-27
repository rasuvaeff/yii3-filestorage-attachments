# Upgrade

## 0.5.x → 0.6.0

1. Requires `rasuvaeff/yii3-filestorage` `^0.10` and `rasuvaeff/yii3-filestorage-db` `^0.4` — see their CHANGELOGs and `-db`'s `UPGRADE.md` (it adds its own migration and the same null-scope refusal).
2. Run `./yii migrate:up`. On MySQL/MariaDB the new
   `M261128000000MakeFilestorageAttachmentKeysCaseSensitive` switches `scope_id`, `owner_type`, `owner_id` and
   `role` to `utf8mb4_bin`. Making the comparison stricter cannot fail on existing rows. After it, owner ids,
   types, roles and tenant ids that differ only in case or accents are different values — if your application
   relied on `Order-1` finding the links of `order-1`, normalise the ids it passes.
3. Multi-tenant applications: a bound `FileScopeProviderInterface` that returns `null` now makes every
   `Attachments` method throw `MissingScopeException` (it used to fall back to the single-tenant key `''`).
   Make sure the tenant is established wherever attachments are read or written.

## Before 0.5.0 → 0.5.0 (MySQL/MariaDB)

`M261127000000AddFilestorageAttachmentFileForeignKey` deletes links whose file no longer exists, then adds the
foreign key. The deletion is committed before the `ALTER TABLE` (MySQL commits DDL implicitly), so it is not
undone if the constraint cannot be added. To see what would go before running it:

```sql
SELECT COUNT(*) FROM filestorage_attachment a
LEFT JOIN filestorage_file f ON f.id = a.file_id
WHERE f.id IS NULL;
```

The migration prints the number it deleted (`Deleted N rows`).
