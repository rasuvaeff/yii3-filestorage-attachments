<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Benchmarks;

use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\AttachmentStateMachineHarness;
use Testo\Assert\ExpectNoAssertions;
use Testo\Bench;

/**
 * Regression benchmarks for the database-bound attachment hot paths.
 *
 * SQLite in memory removes network and disk variance while retaining the SQL,
 * constraint and hydration work performed by the package.
 *
 * @internal
 */
final class AttachmentsBench
{
    private static ?AttachmentStateMachineHarness $harness = null;

    #[ExpectNoAssertions]
    #[Bench(
        callables: [
            'duplicate attach' => [self::class, 'rejectDuplicateAttach'],
            'list two roles' => [self::class, 'listOwnerWithTwoRoles'],
        ],
        calls: 100,
        iterations: 5,
    )]
    public static function attachAndDetach(): bool
    {
        $harness = self::harness();
        $file = $harness->file('file-2');
        $harness->attachments->attach($file, 'message', 'owner-1', 'preview');

        return $harness->attachments->detach($file, 'message', 'owner-1', 'preview');
    }

    public static function rejectDuplicateAttach(): bool
    {
        $harness = self::harness();

        return $harness->attachments->attach(
            $harness->file('file-1'),
            'order',
            'owner-1',
            'default',
        );
    }

    public static function listOwnerWithTwoRoles(): int
    {
        return count(self::harness()->attachments->forOwner('order', 'owner-2'));
    }

    private static function harness(): AttachmentStateMachineHarness
    {
        if (self::$harness instanceof AttachmentStateMachineHarness) {
            return self::$harness;
        }

        $harness = new AttachmentStateMachineHarness();
        $harness->attachments->attach($harness->file('file-1'), 'order', 'owner-1', 'default');
        $harness->attachments->attach($harness->file('file-1'), 'order', 'owner-2', 'default');
        $harness->attachments->attach($harness->file('file-2'), 'order', 'owner-2', 'preview');

        return self::$harness = $harness;
    }
}
