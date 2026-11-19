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

- Inject `Rasuvaeff\\Yii3FilestorageAttachments\\Attachments`.
- Call `attach(File, ownerType, ownerId, role)`; repeated calls are idempotent.
- Call `forOwner(ownerType, ownerId, role?)` to read `Attachment` records.
- Call `detachOwner(ownerType, ownerId)` from owner deletion lifecycle code.

## Scope and migrations

Bind the application's `FileScopeProviderInterface` once when tenants are used;
the DB repository and attachment service must share that provider and database
connection. Run the `yii3-filestorage-db` file migration before
`M260814000000CreateFilestorageAttachmentTable`.
