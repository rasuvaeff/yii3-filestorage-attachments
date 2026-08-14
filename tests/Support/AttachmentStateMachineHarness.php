<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use Rasuvaeff\Yii3Filestorage\File;
use Rasuvaeff\Yii3FilestorageAttachments\Attachment;
use Rasuvaeff\Yii3FilestorageAttachments\Attachments;

/** @internal */
final readonly class AttachmentStateMachineHarness
{
    public const array OWNER_TYPES = ['order', 'message'];
    public const array OWNER_IDS = ['owner-1', 'owner-2'];
    public const array ROLES = ['default', 'preview'];
    public const array FILE_IDS = ['file-1', 'file-2'];

    public SqliteDatabase $database;
    public Attachments $attachments;

    /** @var array<string, File> */
    private array $files;

    public function __construct()
    {
        $this->database = new SqliteDatabase();
        $this->attachments = new Attachments(
            db: $this->database->db,
            files: $this->database->files,
        );

        $files = [];
        foreach (self::FILE_IDS as $fileId) {
            $file = SqliteDatabase::file($fileId, 'sha/e3/b0/' . $fileId);
            $this->database->files->save($file);
            $files[$fileId] = $file;
        }
        $this->files = $files;
    }

    public function file(string $fileId): File
    {
        return $this->files[$fileId];
    }

    public function matches(AttachmentModel $model): bool
    {
        foreach (self::OWNER_TYPES as $ownerType) {
            foreach (self::OWNER_IDS as $ownerId) {
                $actual = array_map(
                    static fn(Attachment $attachment): array => [
                        'role' => $attachment->role,
                        'fileId' => $attachment->file->id,
                    ],
                    $this->attachments->forOwner($ownerType, $ownerId),
                );
                if ($actual !== $model->ownerView($ownerType, $ownerId)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return list<array{role: string, fileId: string}> */
    public function ownerView(string $ownerType, string $ownerId, ?string $role): array
    {
        return array_map(
            static fn(Attachment $attachment): array => [
                'role' => $attachment->role,
                'fileId' => $attachment->file->id,
            ],
            $this->attachments->forOwner($ownerType, $ownerId, $role),
        );
    }

    public function close(): void
    {
        $this->database->close();
    }
}
