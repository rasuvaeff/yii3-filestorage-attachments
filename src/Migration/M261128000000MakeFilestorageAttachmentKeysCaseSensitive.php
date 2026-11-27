<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Migration;

use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

/**
 * Compares the link key byte for byte on MySQL and MariaDB.
 *
 * Their default collations ignore case and accents, so owner `aB` and owner
 * `Ab` were one owner: `forOwner()` listed the other's files,
 * `detachOwner()` removed them, and the unique key refused the second
 * owner's first link — which `attach()` then reported as "already linked".
 * The same held for `scope_id`, `owner_type` and `role`. This switches the
 * four columns to `utf8mb4_bin`. `file_id` keeps the collation of the file
 * table's id, which its foreign key requires. SQLite and PostgreSQL already
 * compare exactly, and there it does nothing.
 *
 * A collation that only gets stricter cannot fail on existing rows. `down()`
 * returns the columns to the table's default collation — and fails if the
 * table by then holds links that differ only in case, which the old
 * collation's unique key cannot hold.
 *
 * Not transactional: MySQL commits implicitly on `ALTER TABLE`.
 *
 * @api
 */
final readonly class M261128000000MakeFilestorageAttachmentKeysCaseSensitive implements RevertibleMigrationInterface
{
    private const array COLUMNS = [
        'scope_id' => 191,
        'owner_type' => 128,
        'owner_id' => 191,
        'role' => 64,
    ];

    public function __construct(
        private AttachmentTableName $table = new AttachmentTableName(),
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $this->modify($b, ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $this->modify($b, '');
    }

    private function modify(MigrationBuilder $b, string $collation): void
    {
        if ($b->getDb()->getDriverName() !== 'mysql') {
            return;
        }

        $quoter = $b->getDb()->getQuoter();
        $columns = [];
        foreach (self::COLUMNS as $column => $length) {
            $columns[] = sprintf(
                'MODIFY %s VARCHAR(%d)%s NOT NULL',
                $quoter->quoteColumnName($column),
                $length,
                $collation,
            );
        }

        $b->execute(sprintf(
            'ALTER TABLE %s %s',
            $quoter->quoteTableName($this->table->value),
            implode(', ', $columns),
        ));
    }
}
