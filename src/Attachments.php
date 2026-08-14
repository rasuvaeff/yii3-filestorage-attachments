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
