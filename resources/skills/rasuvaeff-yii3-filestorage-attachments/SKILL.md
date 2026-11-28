---
name: rasuvaeff-yii3-filestorage-attachments
description: >-
  Tenant-scoped polymorphic file attachments for Yii3 with
  rasuvaeff/yii3-filestorage-attachments — the Attachments service
  (attach/detach/sync/forOwner/detachOwner), the Attachment record,
  AttachmentTableName and the attachment-table migration. Use when writing,
  reviewing or debugging code that links files to owners, reads or syncs
  attachments, or cleans them up on owner deletion in a project that has
  this package installed.
---

# yii3-filestorage-attachments

Use this package for polymorphic file ownership links in Yii3 applications.

## API

- Inject `Rasuvaeff\Yii3FilestorageAttachments\Attachments`.
- Call `attach(File, ownerType, ownerId, role)`; repeated calls are idempotent.
- Call `forOwner(ownerType, ownerId, role?)` to read `Attachment` records.
- Call `detachOwner(ownerType, ownerId)` from owner deletion lifecycle code.

## Scope and migrations

Bind the application's `FileScopeProviderInterface` once when tenants are used;
the DB repository and attachment service must share that provider and database
connection. A bound provider returning null is refused with
`MissingScopeException` — never map it to the single-tenant empty key. On
MySQL/MariaDB keep `M261128000000MakeFilestorageAttachmentKeysCaseSensitive`
registered: without it the default collation makes owners `aB` and `Ab` one
owner (it uses a NO PAD binary collation, so `42` and `42 ` stay two owners). Run the `yii3-filestorage-db` file migration before
`M260814000000CreateFilestorageAttachmentTable`. Keep
`M261127000000AddFilestorageAttachmentFileForeignKey` registered too: MySQL
ignores the table migration's inline `REFERENCES`, and this migration deletes
links to missing files, then adds the cascading foreign key there (a no-op on
SQLite, PostgreSQL and MariaDB 10.5+, which create the key from the column
clause). Without it, removing a file on MySQL leaves
its links behind.
