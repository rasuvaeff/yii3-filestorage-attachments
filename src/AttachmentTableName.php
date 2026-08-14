<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments;

use InvalidArgumentException;

/**
 * Validated attachment table name shared by runtime code and the migration.
 *
 * @api
 */
final readonly class AttachmentTableName implements \Stringable
{
    public const string DEFAULT = 'filestorage_attachment';

    private const string PATTERN = '/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?\z/';

    public function __construct(
        public string $value = self::DEFAULT,
    ) {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid table name "%s"', $value));
        }
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function forIndexName(): string
    {
        return substr(str_replace('.', '_', $this->value), 0, 36);
    }
}
