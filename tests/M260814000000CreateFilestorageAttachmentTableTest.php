<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use Rasuvaeff\Yii3FilestorageAttachments\Migration\M260814000000CreateFilestorageAttachmentTable;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\SqliteDatabase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

#[Test]
#[Covers(M260814000000CreateFilestorageAttachmentTable::class)]
final class M260814000000CreateFilestorageAttachmentTableTest
{
    private SqliteDatabase $database;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->database = new SqliteDatabase();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->database->close();
    }

    public function createsTheTableWithAnAutoIncrementedPrimaryKey(): void
    {
        $schema = $this->database->db->getTableSchema('filestorage_attachment', refresh: true);

        Assert::notNull($schema);
        Assert::same($schema?->getPrimaryKey(), ['id']);
        Assert::true($schema?->getColumn('id')?->isAutoIncrement());
    }

    public function createsTheUniqueLinkIndexAndTheLookupIndexes(): void
    {
        $indexes = [];
        foreach ($this->database->db->getSchema()->getTableIndexes('filestorage_attachment', refresh: true) as $index) {
            $indexes[$index->name] = [$index->columnNames, $index->isUnique];
        }

        Assert::same(
            $indexes['uq_filestorage_attachment_link'] ?? null,
            [['scope_id', 'owner_type', 'owner_id', 'role', 'file_id'], true],
        );
        Assert::same(
            $indexes['idx_filestorage_attachment_owner'] ?? null,
            [['scope_id', 'owner_type', 'owner_id', 'role', 'id'], false],
        );
        Assert::same(
            $indexes['idx_filestorage_attachment_file'] ?? null,
            [['scope_id', 'file_id'], false],
        );
    }

    public function downDropsTheTable(): void
    {
        $builder = new MigrationBuilder(db: $this->database->db, informer: new NullMigrationInformer());

        (new M260814000000CreateFilestorageAttachmentTable())->down($builder);

        Assert::null($this->database->db->getTableSchema('filestorage_attachment', refresh: true));
    }
}
