<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

/** @internal */
final readonly class AttachmentCommandResult
{
    public function __construct(
        public mixed $value,
        public bool $modelMatches,
    ) {}
}
