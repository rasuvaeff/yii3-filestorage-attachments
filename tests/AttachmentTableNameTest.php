<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AttachmentTableName::class)]
final class AttachmentTableNameTest
{
    public function hasAStableDefaultAndStringRepresentation(): void
    {
        $table = new AttachmentTableName();

        Assert::same($table->value, 'filestorage_attachment');
        Assert::same((string) $table, $table->value);
        Assert::same((new AttachmentTableName('app.attachments'))->forIndexName(), 'app_attachments');
    }

    public function indexBaseIsBoundedForPortableConstraintNames(): void
    {
        $base = (new AttachmentTableName(str_repeat('a', 50)))->forIndexName();

        Assert::same(strlen($base), 36);
        Assert::same($base, str_repeat('a', 36));
    }

    #[DataProvider('invalidNames')]
    public function rejectsUnsafeNames(string $name): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Invalid table name');

        new AttachmentTableName($name);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['file attachments'];
        yield 'quote' => ["attachments'; DROP TABLE files; --"];
        yield 'leading digit' => ['1attachments'];
        yield 'trailing newline' => ["attachments\n"];
        yield 'too many components' => ['a.b.c'];
    }
}
