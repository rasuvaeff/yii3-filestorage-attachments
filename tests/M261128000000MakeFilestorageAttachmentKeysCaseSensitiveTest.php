<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use Rasuvaeff\Yii3FilestorageAttachments\Migration\M261128000000MakeFilestorageAttachmentKeysCaseSensitive;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\SqliteDatabase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * SQLite compares `=` byte for byte already, so the collation change is a
 * MySQL statement and does nothing here. The MySQL leg of
 * `Integration\CrossDatabaseMigrationTest` runs the real thing with owners
 * that differ only in case.
 */
#[Test]
#[Covers(M261128000000MakeFilestorageAttachmentKeysCaseSensitive::class)]
final class M261128000000MakeFilestorageAttachmentKeysCaseSensitiveTest
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

    public function upAndDownLeaveOtherEnginesAlone(): void
    {
        $builder = new MigrationBuilder(db: $this->database->db, informer: new NullMigrationInformer());
        $before = $this->types();

        $migration = new M261128000000MakeFilestorageAttachmentKeysCaseSensitive();
        $migration->down($builder);
        $migration->up($builder);

        Assert::same($this->types(), $before);
    }

    /** @return array<string, string|null> */
    private function types(): array
    {
        $types = [];
        foreach (['scope_id', 'owner_type', 'owner_id', 'role'] as $column) {
            $types[$column] = $this->database->db->getTableSchema('filestorage_attachment', true)
                ?->getColumn($column)?->getDbType();
        }

        return $types;
    }
}
