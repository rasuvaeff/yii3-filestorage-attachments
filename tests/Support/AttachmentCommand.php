<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use Rasuvaeff\PropertyTesting\StateMachine\Command;

/** @internal */
final readonly class AttachmentCommand implements Command
{
    private function __construct(
        public AttachmentCommandType $type,
        public string $ownerType,
        public string $ownerId,
        public ?string $role = null,
        public ?string $fileId = null,
    ) {}

    public static function attach(string $ownerType, string $ownerId, string $role, string $fileId): self
    {
        return new self(AttachmentCommandType::Attach, $ownerType, $ownerId, $role, $fileId);
    }

    public static function detach(string $ownerType, string $ownerId, string $role, string $fileId): self
    {
        return new self(AttachmentCommandType::Detach, $ownerType, $ownerId, $role, $fileId);
    }

    public static function detachOwner(string $ownerType, string $ownerId): self
    {
        return new self(AttachmentCommandType::DetachOwner, $ownerType, $ownerId);
    }

    public static function listOwner(string $ownerType, string $ownerId, ?string $role): self
    {
        return new self(AttachmentCommandType::ListOwner, $ownerType, $ownerId, $role);
    }

    #[\Override]
    public function preCondition(mixed $model): bool
    {
        return $model instanceof AttachmentModel;
    }

    #[\Override]
    public function nextState(mixed $model): AttachmentModel
    {
        \assert($model instanceof AttachmentModel);

        return match ($this->type) {
            AttachmentCommandType::Attach => $model->attach($this->link()),
            AttachmentCommandType::Detach => $model->detach($this->link()),
            AttachmentCommandType::DetachOwner => $model->detachOwner($this->ownerType, $this->ownerId),
            AttachmentCommandType::ListOwner => $model,
        };
    }

    #[\Override]
    public function run(mixed $model, mixed $system): AttachmentCommandResult
    {
        \assert($model instanceof AttachmentModel);
        \assert($system instanceof AttachmentStateMachineHarness);

        $value = match ($this->type) {
            AttachmentCommandType::Attach => $system->attachments->attach(
                $system->file($this->requiredFileId()),
                $this->ownerType,
                $this->ownerId,
                $this->requiredRole(),
            ),
            AttachmentCommandType::Detach => $system->attachments->detach(
                $system->file($this->requiredFileId()),
                $this->ownerType,
                $this->ownerId,
                $this->requiredRole(),
            ),
            AttachmentCommandType::DetachOwner => $system->attachments->detachOwner($this->ownerType, $this->ownerId),
            AttachmentCommandType::ListOwner => $system->ownerView($this->ownerType, $this->ownerId, $this->role),
        };

        return new AttachmentCommandResult(
            value: $value,
            modelMatches: $system->matches($this->nextState($model)),
        );
    }

    #[\Override]
    public function postCondition(mixed $model, mixed $result): bool
    {
        \assert($model instanceof AttachmentModel);
        \assert($result instanceof AttachmentCommandResult);
        if (!$result->modelMatches) {
            return false;
        }

        $expected = match ($this->type) {
            AttachmentCommandType::Attach => !$model->contains($this->link()),
            AttachmentCommandType::Detach => $model->contains($this->link()),
            AttachmentCommandType::DetachOwner => $model->countOwner($this->ownerType, $this->ownerId),
            AttachmentCommandType::ListOwner => $model->ownerView($this->ownerType, $this->ownerId, $this->role),
        };

        return $result->value === $expected;
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            '%s(%s, %s, %s, %s)',
            $this->type->name,
            $this->ownerType,
            $this->ownerId,
            $this->role ?? '*',
            $this->fileId ?? '*',
        );
    }

    private function link(): AttachmentLink
    {
        return new AttachmentLink(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            role: $this->requiredRole(),
            fileId: $this->requiredFileId(),
        );
    }

    private function requiredRole(): string
    {
        \assert($this->role !== null);

        return $this->role;
    }

    private function requiredFileId(): string
    {
        \assert($this->fileId !== null);

        return $this->fileId;
    }
}
