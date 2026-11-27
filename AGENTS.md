# AGENTS.md — yii3-filestorage-attachments

Guidance for AI agents working on this package. Read before changing code.

`rasuvaeff/yii3-filestorage-attachments` stores owner-to-file links for
`rasuvaeff/yii3-filestorage` without an Active Record dependency. The public
namespace is `Rasuvaeff\Yii3FilestorageAttachments`; the main API is
`Attachments`, `Attachment`, `AttachmentTableName` and the migrations under
`src/Migration/`.

The package depends on `yii3-filestorage-db` and uses its scoped
`DbRepository`. It owns only the attachment table and its `Attachments` service;
it does not bind core storage contracts or the application's scope provider.

## Golden rules

1. **Verification is mandatory.** Never claim done without fresh green
   `make build` and `make test-integration`.
2. **No suppressions.** No `@psalm-suppress` and no baseline. Fix root causes.
3. **Owner cleanup is explicit.** A polymorphic `owner_type`/`owner_id` pair
   cannot have a meaningful database foreign key. Call `detachOwner()` from
   the application or Active Record lifecycle when an owner is deleted.
4. **Preserve the public contract.** Update both README files, `llms.txt` and
   tests with every API change.

## Commands

No PHP/Composer is available on the host. Use Docker via `make`:

```bash
make build
make test-integration
make cs-fix
make psalm
make rector
make mutation
make release-check
```

`tests/Integration/CrossDatabaseMigrationTest` runs only when
`FILESTORAGE_TEST_DB=mysql|pgsql` names a live server (database `filestorage`,
password `filestorage`; ports via `FILESTORAGE_TEST_MYSQL_PORT` /
`FILESTORAGE_TEST_PGSQL_PORT`). `composer:2` has neither `pdo_mysql` nor
`pdo_pgsql` — use an image with them (`FROM composer:2` +
`docker-php-ext-install pdo_mysql pdo_pgsql`) and `--network host`:

```bash
docker run -d --name fsa-mysql -p 127.0.0.1:13306:3306 \
  -e MYSQL_DATABASE=filestorage -e MYSQL_ROOT_PASSWORD=filestorage mysql:8.4
docker run --rm --network host -v "$PWD":/app -w /app \
  -e FILESTORAGE_TEST_DB=mysql -e FILESTORAGE_TEST_MYSQL_PORT=13306 \
  <php-with-pdo-image> vendor/bin/testo --suite=Integration
```

`composer.lock` is gitignored for this library. `examples/` is part of the
public contract and must stay runnable.

## Invariants and migration rules

- Code uses `declare(strict_types=1)`, explicit types, named arguments and
  `#[\Override]` where applicable.
- Validation patterns anchor with `\z`, never `$`; the latter accepts a final
  newline in PCRE.
- Migrations live under `src/Migration/`, use typed table-name value objects,
  and are built through `Injector::make()`.
- `scope_id` is non-null. The empty string represents single-tenant mode;
  SQL `NULL` would let unique indexes admit duplicate unscoped links.
- The empty string is for an **unbound** provider only. A bound provider
  returning null throws `MissingScopeException` (from `-db`); never map it to
  `''`.
- MySQL/MariaDB compare the link key case-insensitively unless
  `M261128000000MakeFilestorageAttachmentKeysCaseSensitive` ran; any new key
  column needs `utf8mb4_bin` there too (`file_id` excepted — the FK needs the
  file table's collation).
- The file FK is inline because SQLite cannot add a foreign key with a later
  `ALTER TABLE`. MySQL/MariaDB parse that inline `REFERENCES` and **ignore**
  it, so `M261127000000AddFilestorageAttachmentFileForeignKey` adds the
  constraint wherever the table has none (deleting orphaned links first). Never
  edit an applied migration; fix schema with a new one. Run the
  `yii3-filestorage-db` migration first.
- **Everything here must run on MySQL and PostgreSQL, not only SQLite.**
  `tests/Integration/CrossDatabaseMigrationTest` applies all migrations and
  removes a file through core on both servers; CI runs it in the ungated
  `database-integration` matrix job (`mysql integration`, `pgsql integration`).
  Mutation runs on SQLite only, so the FK-adding path is unit-tested on a table
  recreated without the constraint.
- The package binds only its own `Attachments` service and table name. It never
  binds `StorageInterface`, `RepositoryInterface` or
  `FileScopeProviderInterface`; those keys belong to other packages or the app.

## When you finish

Run the build and integration gates, `git diff --check`, and update EN/RU docs,
examples and changelog as appropriate.
