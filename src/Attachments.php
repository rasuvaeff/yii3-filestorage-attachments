<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments;

use InvalidArgumentException;
use Rasuvaeff\Yii3Filestorage\File;
use Rasuvaeff\Yii3Filestorage\Repository\FileScopeProviderInterface;
use Rasuvaeff\Yii3FilestorageDb\DbRepository;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\IntegrityException;
use Yiisoft\Db\Query\Query;

/**
 * Stores polymorphic, tenant-scoped links between files and application owners.
 *
 * Attachments are idempotent on `(scope, owner type, owner id, role, file)`.
 * The owner side has no foreign key because it may be any application table;
 * call {@see detachOwner()} from the owner's lifecycle when it is deleted.
 *
 * @api
 */
final readonly class Attachments
{
    private string $table;

    /**
     * @throws InvalidArgumentException when the repository uses another connection
     */
    public function __construct(
        private ConnectionInterface $db,
        private DbRepository $files,
        AttachmentTableName $table = new AttachmentTableName(),
        private ?FileScopeProviderInterface $scopes = null,
    ) {
        if (!$files->usesConnection($db)) {
            throw new InvalidArgumentException('Attachments and file repository must use the same database connection');
        }

        $this->table = $table->value;
    }

    /**
     * Adds a link, returning false when the exact link already exists.
     *
     * The scoped repository lookup prevents a caller from linking a file row
     * belonging to another tenant. The unique index handles concurrent callers.
     *
     * @param non-empty-string $ownerType
     * @param non-empty-string $ownerId
     * @param non-empty-string $role
     *
     * @throws InvalidArgumentException
     */
    public function attach(File $file, string $ownerType, string $ownerId, string $role = 'default'): bool
    {
        $this->validateOwner($ownerType, $ownerId, $role);
        if (!$this->files->find($file->id) instanceof File) {
            throw new InvalidArgumentException(sprintf('File "%s" is not visible in the current scope', $file->id));
        }

        $row = [
            'scope_id' => $this->scopeKey(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'role' => $role,
            'file_id' => $file->id,
        ];

        try {
            $inserted = 0;
            // The savepoint keeps a caller-owned PostgreSQL transaction usable
            // when a concurrent writer wins the unique-key race.
            $this->db->transaction(function () use ($row, &$inserted): void {
                $inserted = $this->db->createCommand()->insert($this->table, $row)->execute();
            });

            return $inserted > 0;
        } catch (IntegrityException $exception) {
            // Do not turn a concurrent file deletion / FK failure into a fake
            // duplicate. Only the exact surviving link is idempotent.
            if ($this->linkExists($row)) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Removes one link, returning false when it was absent.
     *
     * @param non-empty-string $ownerType
     * @param non-empty-string $ownerId
     * @param non-empty-string $role
     */
    public function detach(File $file, string $ownerType, string $ownerId, string $role = 'default'): bool
    {
        $this->validateOwner($ownerType, $ownerId, $role);

        return $this->db->createCommand()->delete($this->table, [
            'scope_id' => $this->scopeKey(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'role' => $role,
            'file_id' => $file->id,
        ])->execute() > 0;
    }

    /**
     * Replaces the file set of one owner's role in a single transaction:
     * links whose file is not in the list are removed, missing ones added.
     *
     * "Exactly one invoice per order" is this call — attach-new + detach-old
     * as two calls can crash between them and leave two invoices. Surviving
     * links keep their insertion order; the list's order is not a sort order
     * (there is deliberately no sort column).
     *
     * Concurrency: the delete locks the role's rows until commit, so syncs
     * of a role that holds anything serialize correctly. Two concurrent
     * syncs against a role that is empty on both sides may both commit
     * their sets (the unique key still prevents duplicate links) —
     * serialize those callers if that case matters.
     *
     * @param list<File> $files Every file must be visible in the current
     *        scope — checked before anything is written, like {@see attach()}.
     * @param non-empty-string $ownerType
     * @param non-empty-string $ownerId
     * @param non-empty-string $role
     *
     * @return array{added: int, removed: int}
     *
     * @throws InvalidArgumentException
     */
    public function sync(array $files, string $ownerType, string $ownerId, string $role = 'default'): array
    {
        $this->validateOwner($ownerType, $ownerId, $role);

        /** @var list<non-empty-string> $keepIds */
        $keepIds = [];
        foreach ($files as $file) {
            if (!$this->files->find($file->id) instanceof File) {
                throw new InvalidArgumentException(sprintf('File "%s" is not visible in the current scope', $file->id));
            }

            $keepIds[] = $file->id;
        }

        return $this->db->transaction(function () use ($files, $keepIds, $ownerType, $ownerId, $role): array {
            $where = [
                'scope_id' => $this->scopeKey(),
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'role' => $role,
            ];

            $removed = $this->db->createCommand()->delete(
                $this->table,
                $keepIds === [] ? $where : ['and', $where, ['not in', 'file_id', $keepIds]],
            )->execute();

            // No existence pre-check: inserting a link that already exists
            // runs into the unique key, and the catch below treats the exact
            // surviving link as idempotent — the same contract attach() has.
            $added = 0;
            foreach ($files as $file) {
                $row = [
                    'scope_id' => $this->scopeKey(),
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'role' => $role,
                    'file_id' => $file->id,
                ];

                try {
                    $inserted = 0;
                    // The savepoint keeps this transaction usable when a
                    // surviving link loses the unique-key race: on PostgreSQL
                    // an uncontained integrity error would abort the whole
                    // sync, and the idempotence check below could not even
                    // run.
                    $this->db->transaction(function () use ($row, &$inserted): void {
                        $inserted = $this->db->createCommand()->insert($this->table, $row)->execute();
                    });

                    $added += $inserted;
                } catch (IntegrityException $exception) {
                    // A concurrent writer — or this very sync's surviving
                    // link — won the unique-key race; only the exact
                    // surviving link is idempotent, everything else is real.
                    if (!$this->linkExists($row)) {
                        throw $exception;
                    }
                }
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }

    /**
     * Lists links in insertion order, optionally restricted to a role.
     * Missing file rows are skipped: a concurrent file delete can cascade the
     * link after the id query and before hydration.
     *
     * @param non-empty-string $ownerType
     * @param non-empty-string $ownerId
     * @param non-empty-string|null $role
     *
     * @return list<Attachment>
     */
    public function forOwner(string $ownerType, string $ownerId, ?string $role = null): array
    {
        $this->validateOwner($ownerType, $ownerId, $role);

        $where = [
            'scope_id' => $this->scopeKey(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ];
        if ($role !== null) {
            $where['role'] = $role;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = (new Query($this->db))
            ->from($this->table)
            ->where($where)
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $attachments = [];
        foreach ($rows as $row) {
            $fileId = $row['file_id'] ?? null;
            $rowRole = $row['role'] ?? null;
            if (!is_string($fileId) || $fileId === '' || !is_string($rowRole) || $rowRole === '') {
                continue;
            }

            $file = $this->files->find($fileId);
            if ($file instanceof File) {
                $attachments[] = new Attachment(
                    file: $file,
                    ownerType: $ownerType,
                    ownerId: $ownerId,
                    role: $rowRole,
                );
            }
        }

        return $attachments;
    }

    /**
     * Removes every role and file link for one owner.
     *
     * There is deliberately no generic database cascade for this operation:
     * the owner table is outside this package and may not even be relational.
     *
     * @param non-empty-string $ownerType
     * @param non-empty-string $ownerId
     */
    public function detachOwner(string $ownerType, string $ownerId): int
    {
        $this->validateOwner($ownerType, $ownerId, null);

        return $this->db->createCommand()->delete($this->table, [
            'scope_id' => $this->scopeKey(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ])->execute();
    }

    private function scopeKey(): string
    {
        $scope = $this->scopes?->currentScopeId();
        if ($scope === null) {
            return '';
        }

        return $scope;
    }

    /** @param array<string, string> $row */
    private function linkExists(array $row): bool
    {
        return (new Query($this->db))->from($this->table)->where($row)->exists();
    }

    private function validateOwner(string $ownerType, string $ownerId, ?string $role): void
    {
        if ($ownerType === '' || strlen($ownerType) > 128 || str_contains($ownerType, "\0")) {
            throw new InvalidArgumentException(sprintf('Invalid owner type "%s"', $ownerType));
        }
        if ($ownerId === '' || strlen($ownerId) > 191 || str_contains($ownerId, "\0")) {
            throw new InvalidArgumentException('Owner id must be non-empty and at most 191 bytes');
        }
        if ($role !== null && ($role === '' || strlen($role) > 64 || str_contains($role, "\0"))) {
            throw new InvalidArgumentException('Role must be non-empty and at most 64 bytes');
        }
    }
}
