<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

/** @internal */
final readonly class AttachmentModel
{
    /** @param list<AttachmentLink> $links */
    public function __construct(
        public array $links = [],
    ) {}

    public function contains(AttachmentLink $link): bool
    {
        foreach ($this->links as $candidate) {
            if ($candidate->equals($link)) {
                return true;
            }
        }

        return false;
    }

    public function attach(AttachmentLink $link): self
    {
        if ($this->contains($link)) {
            return $this;
        }

        return new self([...$this->links, $link]);
    }

    public function detach(AttachmentLink $link): self
    {
        if (!$this->contains($link)) {
            return $this;
        }

        return new self(array_values(array_filter(
            $this->links,
            static fn(AttachmentLink $candidate): bool => !$candidate->equals($link),
        )));
    }

    public function detachOwner(string $ownerType, string $ownerId): self
    {
        $links = array_values(array_filter(
            $this->links,
            static fn(AttachmentLink $link): bool => $link->ownerType !== $ownerType || $link->ownerId !== $ownerId,
        ));
        if (count($links) === count($this->links)) {
            return $this;
        }

        return new self($links);
    }

    public function countOwner(string $ownerType, string $ownerId): int
    {
        return count(array_filter(
            $this->links,
            static fn(AttachmentLink $link): bool => $link->ownerType === $ownerType && $link->ownerId === $ownerId,
        ));
    }

    /** @return list<array{role: string, fileId: string}> */
    public function ownerView(string $ownerType, string $ownerId, ?string $role = null): array
    {
        $view = [];
        foreach ($this->links as $link) {
            if ($link->ownerType === $ownerType && $link->ownerId === $ownerId && ($role === null || $link->role === $role)) {
                $view[] = ['role' => $link->role, 'fileId' => $link->fileId];
            }
        }

        return $view;
    }
}
