<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments;

use Rasuvaeff\Yii3Filestorage\File;

/**
 * One owner-to-file link returned by {@see Attachments::forOwner()}.
 *
 * @api
 */
final readonly class Attachment
{
    /**
     * @param non-empty-string $ownerType
     * @param non-empty-string $ownerId
     * @param non-empty-string $role
     */
    public function __construct(
        public File $file,
        public string $ownerType,
        public string $ownerId,
        public string $role,
    ) {}
}
