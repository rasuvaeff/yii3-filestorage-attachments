<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Migration;

use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Rasuvaeff\Yii3FilestorageDb\FileTableName;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Creates polymorphic owner-to-file links.
 *
 * The file foreign key is real and cascades when a metadata row is removed.
 * An arbitrary owner table cannot be referenced generically, so owner cleanup
 * is explicit through {@see \Rasuvaeff\Yii3FilestorageAttachments\Attachments::detachOwner()}.
 *
 * @api
 */
final readonly class M260814000000CreateFilestorageAttachmentTable implements
    RevertibleMigrationInterface,
    TransactionalMigrationInterface
{
    public function __construct(
        private AttachmentTableName $table = new AttachmentTableName(),
        private FileTableName $files = new FileTableName(),
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $table = $this->table->value;
        $index = $this->table->forIndexName();

        $b->createTable($table, [
            'id' => ColumnBuilder::bigPrimaryKey(),
            // An empty string is the canonical single-tenant scope. Keeping
            // this NOT NULL makes the unique key idempotent on every driver;
            // SQL NULLs would make duplicate unscoped links possible.
            'scope_id' => 'string(191) NOT NULL',
            'owner_type' => 'string(128) NOT NULL',
            'owner_id' => 'string(191) NOT NULL',
            'role' => 'string(64) NOT NULL',
            // Inline REFERENCES keeps the migration portable to SQLite, whose
            // ALTER TABLE does not support ADD CONSTRAINT after creation.
            'file_id' => sprintf(
                'string(64) NOT NULL REFERENCES %s (id) ON DELETE CASCADE',
                $this->files->value,
            ),
        ]);

        $b->createIndex(
            $table,
            sprintf('uq_%s_link', $index),
            ['scope_id', 'owner_type', 'owner_id', 'role', 'file_id'],
            'UNIQUE',
        );
        $b->createIndex(
            $table,
            sprintf('idx_%s_owner', $index),
            ['scope_id', 'owner_type', 'owner_id', 'role', 'id'],
        );
        $b->createIndex($table, sprintf('idx_%s_file', $index), ['scope_id', 'file_id']);
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable($this->table->value);
    }
}
