<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Migration;

use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Rasuvaeff\Yii3FilestorageDb\FileTableName;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Query\Query;

/**
 * Adds the file foreign key where the table migration left none: on MySQL.
 *
 * MySQL parses a column-level `REFERENCES ... ON DELETE CASCADE` and drops
 * it, so the table migration created no constraint there and removing a file
 * kept its links. When the table has no foreign key on `file_id`, this first
 * deletes links whose file no longer exists (the informer prints the count),
 * then adds the constraint. SQLite, PostgreSQL and MariaDB 10.5+ (which
 * creates a foreign key from the column clause) keep the inline one, so there
 * it does nothing; `down()` drops only the constraint this migration added.
 *
 * Not transactional: MySQL commits implicitly on `ALTER TABLE`. Re-running
 * after a failure is safe — the delete finds nothing and the constraint is
 * added again.
 *
 * @api
 */
final readonly class M261127000000AddFilestorageAttachmentFileForeignKey implements RevertibleMigrationInterface
{
    public function __construct(
        private AttachmentTableName $table = new AttachmentTableName(),
        private FileTableName $files = new FileTableName(),
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $db = $b->getDb();
        if ($this->fileForeignKeyName($db) !== null) {
            return;
        }

        $b->delete(
            $this->table->value,
            ['not in', 'file_id', (new Query($db))->select('id')->from($this->files->value)],
        );
        $b->addForeignKey(
            table: $this->table->value,
            name: $this->name(),
            columns: 'file_id',
            referenceTable: $this->files->value,
            referenceColumns: 'id',
            delete: 'CASCADE',
        );
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        if ($this->fileForeignKeyName($b->getDb()) !== $this->name()) {
            return;
        }

        $b->dropForeignKey($this->table->value, $this->name());
    }

    private function fileForeignKeyName(ConnectionInterface $db): ?string
    {
        foreach ($db->getSchema()->getTableForeignKeys($this->table->value, refresh: true) as $key) {
            if ($key->columnNames === ['file_id']) {
                return $key->name;
            }
        }

        return null;
    }

    private function name(): string
    {
        return sprintf('fk_%s_file', $this->table->forIndexName());
    }
}
