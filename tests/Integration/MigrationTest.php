<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Integration;

use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Rasuvaeff\Yii3FilestorageAttachments\Migration\M260814000000CreateFilestorageAttachmentTable;
use Rasuvaeff\Yii3FilestorageDb\FileTableName;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000000CreateFilestorageFileTable;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000001CreateFilestorageBlobTables;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

#[Test]
#[CoversNothing]
final class MigrationTest
{
    public function migrationBuiltThroughInjectorCreatesExpectedSchema(): void
    {
        $db = new Connection(
            driver: new Driver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new ArrayCache()),
        );
        $db->open();
        $builder = new MigrationBuilder(db: $db, informer: new NullMigrationInformer());
        (new M260807000000CreateFilestorageFileTable())->up($builder);
        (new M260807000001CreateFilestorageBlobTables())->up($builder);

        $container = new Container(ContainerConfig::create()->withDefinitions([
            AttachmentTableName::class => new AttachmentTableName('app_attachments'),
            FileTableName::class => new FileTableName('app_files'),
        ]));
        // The migration is created by the injector, not by a container class
        // definition keyed by the migration FQCN.
        $migration = (new Injector($container))->make(M260814000000CreateFilestorageAttachmentTable::class);

        // The custom file table must exist for the inline FK, so apply the
        // dependency migrations with matching typed names in a fresh schema.
        $db->close();
        $db = new Connection(
            driver: new Driver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new ArrayCache()),
        );
        $db->open();
        $builder = new MigrationBuilder(db: $db, informer: new NullMigrationInformer());
        (new M260807000000CreateFilestorageFileTable(new FileTableName('app_files')))->up($builder);
        (new M260807000001CreateFilestorageBlobTables())->up($builder);
        $migration->up($builder);

        $schema = $db->getTableSchema('app_attachments', refresh: true);
        Assert::notNull($schema);
        foreach (['id', 'scope_id', 'owner_type', 'owner_id', 'role', 'file_id'] as $column) {
            Assert::notNull($schema?->getColumn($column), "missing column {$column}");
        }
        Assert::same($schema?->getPrimaryKey(), ['id']);

        $migration->down($builder);
        Assert::null($db->getTableSchema('app_attachments', refresh: true));
        $db->close();
    }
}
