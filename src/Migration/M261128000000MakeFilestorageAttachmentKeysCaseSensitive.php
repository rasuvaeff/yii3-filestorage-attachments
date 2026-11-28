<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Migration;

use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Yiisoft\Db\Connection\ConnectionInterface;
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
 * four columns to a binary NO PAD collation — `utf8mb4_0900_bin` on MySQL
 * 8.0.17+, `utf8mb4_nopad_bin` on MariaDB — so trailing spaces count too:
 * `utf8mb4_bin` is PAD SPACE and would make owner `42 ` owner `42`. Older
 * MySQL is refused with a `RuntimeException`. `file_id` keeps the collation
 * of the file table's id, which its foreign key requires. SQLite and
 * PostgreSQL already compare exactly, and there it does nothing.
 *
 * A collation that only gets stricter cannot fail on existing rows. `down()`
 * returns the columns to the table's default collation — and fails if the
 * table by then holds links that differ only in case, or (on MariaDB, whose
 * defaults are PAD SPACE) only in trailing spaces, which the old collation's
 * unique key cannot hold.
 *
 * Changing the collation of indexed columns rebuilds the table
 * (`ALGORITHM=COPY`): writes to it wait for the rebuild, so run it in a
 * maintenance window on a large table. The unique key needs the `DYNAMIC`
 * row format (the default since MySQL 5.7.9 and MariaDB 10.2.2).
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
        if ($b->getDb()->getDriverName() !== 'mysql') {
            return;
        }

        $this->modify($b, ' CHARACTER SET utf8mb4 COLLATE ' . $this->exactCollation($b->getDb()));
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

    /**
     * A binary collation that is also NO PAD, so a trailing space counts.
     *
     * `utf8mb4_bin` is PAD SPACE on both servers: it would make `acme ` and
     * `acme` one value — on MySQL 8, whose default `utf8mb4_0900_ai_ci` is NO
     * PAD, a match that did not exist before this migration. MariaDB names its
     * NO PAD binary collation differently, and MySQL before 8.0.17 has none;
     * there the migration refuses rather than promise a comparison it cannot
     * make.
     */
    private function exactCollation(ConnectionInterface $db): string
    {
        $version = (string) $db->createCommand('SELECT VERSION()')->queryScalar();
        if (stripos($version, 'mariadb') !== false) {
            return 'utf8mb4_nopad_bin';
        }

        $available = $db->createCommand(
            "SELECT COUNT(*) FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_0900_bin'",
        )->queryScalar();
        if ((int) $available === 0) {
            throw new \RuntimeException(sprintf(
                'MySQL %s has no NO PAD binary collation (utf8mb4_0900_bin needs MySQL 8.0.17 or later)',
                $version,
            ));
        }

        return 'utf8mb4_0900_bin';
    }
}
