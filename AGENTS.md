# AGENTS.md — yii3-filestorage-attachments

Guidance for AI agents working on this package. Read before changing code.

`rasuvaeff/yii3-filestorage-attachments` stores owner-to-file links for
`rasuvaeff/yii3-filestorage` without an Active Record dependency. The public
namespace is `Rasuvaeff\Yii3FilestorageAttachments`; the main API is
`Attachments`, `Attachment`, `AttachmentTableName` and the migration under
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
- The file FK is inline because SQLite cannot add a foreign key with a later
  `ALTER TABLE`. Run the `yii3-filestorage-db` migration first.
- The package binds only its own `Attachments` service and table name. It never
  binds `StorageInterface`, `RepositoryInterface` or
  `FileScopeProviderInterface`; those keys belong to other packages or the app.

## When you finish

Run the build and integration gates, `git diff --check`, and update EN/RU docs,
examples and changelog as appropriate.
