<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use Rasuvaeff\Yii3FilestorageAttachments\Migration\M261128000000MakeFilestorageAttachmentKeysCaseSensitive;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\ImpersonatingConnection;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\SqliteDatabase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * SQLite compares `=` byte for byte already, so the collation change is a
 * MySQL statement and does nothing here. The MySQL leg of
 * `Integration\CrossDatabaseMigrationTest` runs the real thing with owners
 * that differ only in case. The statement it sends there is pinned below on
 * an SQLite connection that reports the `mysql` driver.
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

    #[DataProvider('mysqlFamilyProvider')]
    public function upOnMysqlSwitchesTheKeyColumnsToTheNoPadBinaryCollation(string $version, string $collation): void
    {
        $db = $this->mysql(version: $version, collationCount: '1');

        (new M261128000000MakeFilestorageAttachmentKeysCaseSensitive())->up(
            new MigrationBuilder(db: $db, informer: new NullMigrationInformer()),
        );

        Assert::same(end($db->statements), $this->alter(' CHARACTER SET utf8mb4 COLLATE ' . $collation));
    }

    public static function mysqlFamilyProvider(): iterable
    {
        yield 'MySQL 8.4' => ['8.4.13', 'utf8mb4_0900_bin'];
        yield 'MariaDB 11.8' => ['11.8.9-MariaDB-ubu2404', 'utf8mb4_nopad_bin'];
    }

    public function upOnMysqlWithoutTheCollationAltersNothing(): void
    {
        $db = $this->mysql(version: '5.7.44', collationCount: '0');

        try {
            Expect::exception(\RuntimeException::class)->withMessageContaining('MySQL 5.7.44 has no NO PAD');
            (new M261128000000MakeFilestorageAttachmentKeysCaseSensitive())->up(
                new MigrationBuilder(db: $db, informer: new NullMigrationInformer()),
            );
        } finally {
            Assert::same(
                array_filter($db->statements, static fn(string $sql): bool => str_starts_with($sql, 'ALTER')),
                [],
            );
        }
    }

    public function downOnMysqlReturnsTheKeyColumnsToTheTableDefault(): void
    {
        $db = $this->mysql(version: '8.4.13', collationCount: '1');

        (new M261128000000MakeFilestorageAttachmentKeysCaseSensitive())->down(
            new MigrationBuilder(db: $db, informer: new NullMigrationInformer()),
        );

        Assert::same($db->statements, [$this->alter('')]);
    }

    private function mysql(string $version, string $collationCount): ImpersonatingConnection
    {
        return new ImpersonatingConnection(
            inner: $this->database->db,
            driverName: 'mysql',
            answers: [
                'SELECT VERSION()' => sprintf("SELECT '%s'", $version),
                "SELECT COUNT(*) FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_0900_bin'"
                    => sprintf("SELECT '%s'", $collationCount),
            ],
        );
    }

    /** Quoted by the SQLite quoter underneath; MySQL would use backticks. */
    private function alter(string $collation): string
    {
        return sprintf(
            'ALTER TABLE "filestorage_attachment" MODIFY "scope_id" VARCHAR(191)%1$s NOT NULL, '
            . 'MODIFY "owner_type" VARCHAR(128)%1$s NOT NULL, MODIFY "owner_id" VARCHAR(191)%1$s NOT NULL, '
            . 'MODIFY "role" VARCHAR(64)%1$s NOT NULL',
            $collation,
        );
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
