<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use Rasuvaeff\Yii3FilestorageAttachments\Migration\M261127000000AddFilestorageAttachmentFileForeignKey;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\ImpersonatingConnection;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\RecordingInformer;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\ReportedForeignKeysSchema;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\SqliteDatabase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Query\Query;

/**
 * SQLite keeps the inline constraint, so the migration has nothing to do
 * there. A table recreated without it stands in for MySQL: SQLite cannot add
 * a foreign key afterwards, but the orphan clean-up before that runs. The
 * whole MySQL path runs in `Integration\CrossDatabaseMigrationTest`.
 */
#[Test]
#[Covers(M261127000000AddFilestorageAttachmentFileForeignKey::class)]
final class M261127000000AddFilestorageAttachmentFileForeignKeyTest
{
    private SqliteDatabase $database;
    private MigrationBuilder $builder;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->database = new SqliteDatabase();
        $this->builder = new MigrationBuilder(db: $this->database->db, informer: new NullMigrationInformer());
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->database->close();
    }

    public function upLeavesSqliteLinksAndTheInlineForeignKeyAlone(): void
    {
        $this->database->db->createCommand('PRAGMA foreign_keys = OFF')->execute();
        $this->insertLink('missing');

        (new M261127000000AddFilestorageAttachmentFileForeignKey())->up($this->builder);

        Assert::same($this->links(), 1);
        Assert::same($this->foreignKeys(), [['file_id', 'filestorage_file', 'CASCADE']]);
    }

    public function downLeavesTheInlineForeignKeyAlone(): void
    {
        (new M261127000000AddFilestorageAttachmentFileForeignKey())->down($this->builder);

        Assert::same($this->foreignKeys(), [['file_id', 'filestorage_file', 'CASCADE']]);
    }

    public function upDeletesLinksToMissingFilesBeforeAddingAMissingConstraint(): void
    {
        $this->recreateWithoutForeignKey();
        $this->database->files->save(SqliteDatabase::file('live'));
        $this->insertLink('live');
        $this->insertLink('missing');

        $informer = new RecordingInformer();

        try {
            (new M261127000000AddFilestorageAttachmentFileForeignKey())->up(
                new MigrationBuilder(db: $this->database->db, informer: $informer),
            );
            Assert::fail('SQLite added a foreign key to an existing table');
        } catch (NotSupportedException $e) {
            Assert::string($e->getMessage())->contains('addForeignKey');
        }

        Assert::same($this->links('live'), 1);
        Assert::same($this->links('missing'), 0);
        // the operator sees how many links went: `migrate:up` prints it
        Assert::string(implode(' | ', $informer->ended))->contains('Deleted 1 row');
    }

    public function downIgnoresATableWithoutTheConstraint(): void
    {
        $this->recreateWithoutForeignKey();
        $this->insertLink('missing');

        (new M261127000000AddFilestorageAttachmentFileForeignKey())->down($this->builder);

        Assert::same($this->links(), 1);
        Assert::same($this->foreignKeys(), []);
    }

    /**
     * SQLite names its keys `'0'`, `'1'`, …, so a key named as this migration
     * names it on MySQL is reported by a schema double; SQLite then refuses
     * the drop, which shows the drop was asked for.
     */
    public function downDropsTheConstraintThisMigrationAdded(): void
    {
        $builder = $this->reportingForeignKey('fk_filestorage_attachment_file');

        Expect::exception(NotSupportedException::class)->withMessageContaining('dropForeignKey');
        (new M261127000000AddFilestorageAttachmentFileForeignKey())->down($builder);
    }

    public function downLeavesAKeyItDidNotAddAlone(): void
    {
        // MariaDB names the inline key itself
        $builder = $this->reportingForeignKey('filestorage_attachment_ibfk_1');

        (new M261127000000AddFilestorageAttachmentFileForeignKey())->down($builder);

        Assert::same($this->foreignKeys(), [['file_id', 'filestorage_file', 'CASCADE']]);
    }

    private function reportingForeignKey(string $name): MigrationBuilder
    {
        return new MigrationBuilder(
            db: new ImpersonatingConnection(
                inner: $this->database->db,
                driverName: 'mysql',
                schema: new ReportedForeignKeysSchema(
                    inner: $this->database->db->getSchema(),
                    foreignKeys: [new ForeignKey(name: $name, columnNames: ['file_id'])],
                ),
            ),
            informer: new NullMigrationInformer(),
        );
    }

    private function recreateWithoutForeignKey(): void
    {
        $this->database->db->createCommand('DROP TABLE filestorage_attachment')->execute();
        $this->database->db->createCommand(
            'CREATE TABLE filestorage_attachment (id INTEGER PRIMARY KEY AUTOINCREMENT, scope_id VARCHAR(191) NOT NULL, '
            . 'owner_type VARCHAR(128) NOT NULL, owner_id VARCHAR(191) NOT NULL, role VARCHAR(64) NOT NULL, '
            . 'file_id VARCHAR(64) NOT NULL)',
        )->execute();
    }

    private function insertLink(string $fileId): void
    {
        $this->database->db->createCommand()->insert('filestorage_attachment', [
            'scope_id' => '',
            'owner_type' => 'post',
            'owner_id' => '1',
            'role' => 'default',
            'file_id' => $fileId,
        ])->execute();
    }

    private function links(?string $fileId = null): int
    {
        $query = (new Query($this->database->db))->from('filestorage_attachment');
        if ($fileId !== null) {
            $query->where(['file_id' => $fileId]);
        }

        return (int) $query->count();
    }

    /** @return list<array{string, string, ?string}> */
    private function foreignKeys(): array
    {
        $keys = [];
        foreach ($this->database->db->getSchema()->getTableForeignKeys('filestorage_attachment', true) as $key) {
            $keys[] = [implode(',', $key->columnNames), $key->foreignTableName, $key->onDelete];
        }

        return $keys;
    }
}
