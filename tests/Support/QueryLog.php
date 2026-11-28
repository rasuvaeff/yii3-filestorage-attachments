<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Collects the SQL `yiisoft/db` logs for every query it runs.
 *
 * @internal
 */
final class QueryLog extends AbstractLogger
{
    /** @var list<string> */
    public array $statements = [];

    #[\Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->statements[] = (string) $message;
    }

    public function selectsFrom(string $table): int
    {
        return \count(array_filter(
            $this->statements,
            static fn(string $sql): bool => str_starts_with($sql, 'SELECT')
                && preg_match('/\\bFROM\\W+' . preg_quote($table, '/') . '\\b/', $sql) === 1,
        ));
    }
}
