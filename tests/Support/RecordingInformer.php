<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use Override;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiisoft\Db\Migration\Informer\MigrationInformerInterface;

/**
 * Keeps what a migration reports, so a test can read what `migrate:up` would print.
 *
 * @internal
 */
final class RecordingInformer implements MigrationInformerInterface
{
    /** @var list<string> */
    public array $ended = [];

    #[Override]
    public function beginCreateHistoryTable(string $message): void {}

    #[Override]
    public function endCreateHistoryTable(string $message): void {}

    #[Override]
    public function beginCommand(string $message): void {}

    #[Override]
    public function endCommand(string $message): void
    {
        $this->ended[] = $message;
    }

    #[Override]
    public function setIo(?SymfonyStyle $io): void {}
}
