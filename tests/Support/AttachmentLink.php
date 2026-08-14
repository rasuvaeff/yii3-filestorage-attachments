<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

/** @internal */
final readonly class AttachmentLink
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $role,
        public string $fileId,
    ) {}

    public function equals(self $other): bool
    {
        return $this->ownerType === $other->ownerType
            && $this->ownerId === $other->ownerId
            && $this->role === $other->role
            && $this->fileId === $other->fileId;
    }
}
