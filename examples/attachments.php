<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Filestorage\File;
use Rasuvaeff\Yii3FilestorageAttachments\Attachments;
use Rasuvaeff\Yii3FilestorageAttachments\Migration\M260814000000CreateFilestorageAttachmentTable;
use Rasuvaeff\Yii3FilestorageDb\DbRepository;
use Rasuvaeff\Yii3FilestorageDb\FileTableName;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000000CreateFilestorageFileTable;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000001CreateFilestorageBlobTables;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new Connection(
    driver: new Driver(dsn: 'sqlite::memory:'),
    schemaCache: new SchemaCache(psrCache: new ArrayCache()),
);
$db->open();
$db->createCommand('PRAGMA foreign_keys = ON')->execute();

try {
    $builder = new MigrationBuilder(db: $db, informer: new NullMigrationInformer());
    (new M260807000000CreateFilestorageFileTable())->up($builder);
    (new M260807000001CreateFilestorageBlobTables())->up($builder);
    (new M260814000000CreateFilestorageAttachmentTable())->up($builder);

    $files = new DbRepository(db: $db, table: new FileTableName());
    $attachments = new Attachments(db: $db, files: $files);
    $file = File::create(
        id: 'file-1',
        storeName: 'upload',
        groupName: 'common',
        relativePath: 'sha/e3/b0/invoice.pdf',
        originalName: 'invoice.pdf',
        size: 12,
        createdAt: new DateTimeImmutable('2026-01-01T00:00:00.000000+00:00'),
        mimeType: 'application/pdf',
    );
    $files->save($file);

    $attachments->attach(
        file: $file,
        ownerType: 'order',
        ownerId: 'order-42',
        role: 'invoice',
    );

    $invoice = $attachments->forOwner(
        ownerType: 'order',
        ownerId: 'order-42',
        role: 'invoice',
    );

    printf("Found %d invoice attachment(s)\n", count($invoice));
} finally {
    $db->close();
}
