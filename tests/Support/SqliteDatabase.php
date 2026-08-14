<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use DateTimeImmutable;
use Rasuvaeff\Yii3Filestorage\File;
use Rasuvaeff\Yii3FilestorageAttachments\Migration\M260814000000CreateFilestorageAttachmentTable;
use Rasuvaeff\Yii3FilestorageDb\DbRepository;
use Rasuvaeff\Yii3FilestorageDb\FileTableName;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000000CreateFilestorageFileTable;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000001CreateFilestorageBlobTables;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

/** @internal */
final class SqliteDatabase
{
    public ConnectionInterface $db;
    public DbRepository $files;

    public function __construct(?FixedScope $scope = null)
    {
        $this->db = new Connection(
            driver: new Driver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new ArrayCache()),
        );
        $this->db->open();
        $this->db->createCommand('PRAGMA foreign_keys = ON')->execute();

        $builder = new MigrationBuilder(db: $this->db, informer: new NullMigrationInformer());
        (new M260807000000CreateFilestorageFileTable())->up($builder);
        (new M260807000001CreateFilestorageBlobTables())->up($builder);
        (new M260814000000CreateFilestorageAttachmentTable())->up($builder);

        $this->files = new DbRepository(
            db: $this->db,
            table: new FileTableName(),
            scopes: $scope,
        );
    }

    public function close(): void
    {
        $this->db->close();
    }

    public static function file(string $id, string $path = 'sha/e3/b0/original'): File
    {
        return File::create(
            id: $id,
            storeName: 'upload',
            groupName: 'common',
            relativePath: $path,
            originalName: $id . '.txt',
            size: 12,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00.000000+00:00'),
            mimeType: 'text/plain',
        );
    }
}
