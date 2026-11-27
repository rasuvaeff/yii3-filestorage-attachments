# Yii3 Filestorage Attachments

`rasuvaeff/yii3-filestorage-attachments` provides a small database-backed
owner-to-file link service for Yii3 Filestorage. It covers the common
polymorphic attachment case without requiring Active Record.

## Install

```bash
composer require rasuvaeff/yii3-filestorage-attachments
```

The package requires `rasuvaeff/yii3-filestorage` `^0.10` and
`rasuvaeff/yii3-filestorage-db` `^0.4`. Run the `-db` migrations first, then
register this package's migration namespace:

```php
MigrationService::class => [
    'setSourceNamespaces()' => [[
        'Rasuvaeff\\Yii3FilestorageDb\\Migration',
        'Rasuvaeff\\Yii3FilestorageAttachments\\Migration',
    ]],
],
```

The config plugin supplies `AttachmentTableName` and `Attachments`. It does not
bind `FileScopeProviderInterface`; applications with tenants bind that contract
once, and the same provider is used by `yii3-filestorage-db`.

## Usage

```php
$attachments->attach(
    file: $file,
    ownerType: 'order',
    ownerId: (string) $orderId,
    role: 'invoice',
);

$invoices = $attachments->forOwner(
    ownerType: 'order',
    ownerId: (string) $orderId,
    role: 'invoice',
);

$attachments->detach(
    file: $file,
    ownerType: 'order',
    ownerId: (string) $orderId,
    role: 'invoice',
);
```

`attach()` is idempotent and returns `false` when the exact link already
exists. `forOwner()` returns `list<Attachment>` in insertion order; omit
`role` to list every role. `detachOwner()` removes every role and file link for
an owner and must be called by the application or an Active Record lifecycle
when that owner is deleted.

"Exactly one invoice per order" is `sync()` — replacing a role's file set in
one transaction, so a crash cannot leave two invoices where one was meant:

```php
$result = $attachments->sync(
    files: [$newInvoice],
    ownerType: 'order',
    ownerId: (string) $orderId,
    role: 'invoice',
);
// ['added' => 1, 'removed' => 1]
```

Links whose file is not in the list are removed, missing ones added — both
inside one database transaction. Surviving links keep their insertion order
(the list's order is not a sort order; there is deliberately no sort column),
and every file must be visible in the current scope before anything is
written.

Concurrency: the transaction's delete locks the role's rows until commit, so
syncs of a role that already holds anything serialize correctly. Two
concurrent syncs against a role that is empty on both sides may both commit
their sets (the unique key still prevents duplicate links) — serialize such
callers if that case matters.

The table has a real foreign key to `filestorage_file`, so deleting a file row
automatically removes its links. There is deliberately no generic foreign key
to owner tables.

> **SQLite prerequisite:** SQLite enforces `ON DELETE CASCADE` only when every
> connection enables `PRAGMA foreign_keys = ON`. The package does not configure
> connections injected into it — do this in your application's connection
> setup. PostgreSQL enforces the cascade by default.

> **MySQL/MariaDB:** they ignore the column-level `REFERENCES` of the table
> migration, so the constraint comes from the follow-up migration
> `M261127000000AddFilestorageAttachmentFileForeignKey`. Before adding it, that
> migration **deletes links whose file no longer exists** — on an installation
> that ran the table migration earlier, every file removed since left its links
> behind. The migration output prints how many rows it deleted (`Deleted N
> rows`); back up the table first if you need them. It cannot be atomic: MySQL
> commits the `DELETE` before the `ALTER TABLE`, so if adding the constraint
> fails, the deleted links stay deleted. On SQLite and PostgreSQL it does
> nothing.

> **MySQL/MariaDB compare case-insensitively by default.** Owners `aB` and `Ab`
> were one owner there: `forOwner()` listed, and `detachOwner()` removed, the
> other's links. `M261128000000MakeFilestorageAttachmentKeysCaseSensitive`
> (0.6.0) switches `scope_id`, `owner_type`, `owner_id` and `role` to
> `utf8mb4_bin`; run `migrate:up` after upgrading. It does nothing on SQLite and
> PostgreSQL, which already compare exactly.

## Table names and scope

Set `rasuvaeff/yii3-filestorage-attachments.attachmentTable` and `tablePrefix`
in application params when the defaults conflict. The migration and runtime
service resolve the same typed `AttachmentTableName`.

With no scope provider, links use an internal empty scope key. With a bound
`FileScopeProviderInterface`, every operation is restricted to its current
scope and a file must be visible in that scope before it can be attached. A
bound provider that returns `null` is a tenant that did not resolve: every
method throws `yii3-filestorage-db`'s `MissingScopeException` rather than fall
back to the empty key (before 0.6.0 it did, and the file lookup ran without a
tenant predicate). `forOwner()` reads the owner's files in one scoped query
(`DbRepository::findMany()`), not one per link.

## Verification

```bash
make build
make test-integration
make rector
```

The integration suite applies the real `yii3-filestorage-db` and attachments
migrations on SQLite and exercises the documented migration path. The same
suite runs against MySQL or PostgreSQL when `FILESTORAGE_TEST_DB` names one (CI
does both); the image needs `pdo_mysql`/`pdo_pgsql`, which `composer:2` lacks:

```bash
docker run --rm --network host -v "$PWD":/app -w /app \
  -e FILESTORAGE_TEST_DB=mysql -e FILESTORAGE_TEST_MYSQL_PORT=3306 \
  <php-image-with-pdo_mysql-and-pdo_pgsql> vendor/bin/testo --suite=Integration
```
