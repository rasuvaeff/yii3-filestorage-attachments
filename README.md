# Yii3 Filestorage Attachments

`rasuvaeff/yii3-filestorage-attachments` provides a small database-backed
owner-to-file link service for Yii3 Filestorage. It covers the common
polymorphic attachment case without requiring Active Record.

## Install

```bash
composer require rasuvaeff/yii3-filestorage-attachments
```

The package requires `rasuvaeff/yii3-filestorage-db`. Run its file-table
migration first, then register this package's migration namespace:

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

The table has a real foreign key to `filestorage_file`, so deleting a file row
automatically removes its links. There is deliberately no generic foreign key
to owner tables.

> **SQLite prerequisite:** SQLite enforces `ON DELETE CASCADE` only when every
> connection enables `PRAGMA foreign_keys = ON`. The package does not configure
> connections injected into it — do this in your application's connection
> setup. MySQL/MariaDB and PostgreSQL enforce the cascade by default.

## Table names and scope

Set `rasuvaeff/yii3-filestorage-attachments.attachmentTable` and `tablePrefix`
in application params when the defaults conflict. The migration and runtime
service resolve the same typed `AttachmentTableName`.

With no scope provider, links use an internal empty scope key. With a bound
`FileScopeProviderInterface`, every operation is restricted to its current
scope and a file must be visible in that scope before it can be attached.

## Verification

```bash
make build
make test-integration
make rector
```

The integration suite applies the real `yii3-filestorage-db` and attachments
migrations on SQLite and exercises the documented migration path.
