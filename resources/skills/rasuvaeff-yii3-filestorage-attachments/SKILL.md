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
