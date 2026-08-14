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

The table has a real foreign key to `filestorage_file`, so deleting a file row
automatically removes its links. There is deliberately no generic foreign key
to owner tables.

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
