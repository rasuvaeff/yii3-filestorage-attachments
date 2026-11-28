<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Constraint\Index;
use Yiisoft\Db\Schema\Column\ColumnInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Schema\TableSchemaInterface;

/**
 * The SQLite schema, except that tables report the given foreign keys.
 *
 * SQLite names every foreign key `'0'`, `'1'`, …, so the constraint a MySQL
 * migration added under its own name can only be described, not created.
 *
 * @internal
 */
final readonly class ReportedForeignKeysSchema implements SchemaInterface
{
    /**
     * @param list<ForeignKey> $foreignKeys
     */
    public function __construct(
        private SchemaInterface $inner,
        private array $foreignKeys,
    ) {}

    #[\Override]
    public function getTableForeignKeys(string $name, bool $refresh = false): array
    {
        return $this->foreignKeys;
    }

    #[\Override]
    public function getSchemaChecks(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getSchemaChecks($schema, $refresh);
    }

    #[\Override]
    public function getSchemaDefaultValues(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getSchemaDefaultValues($schema, $refresh);
    }

    #[\Override]
    public function getSchemaForeignKeys(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getSchemaForeignKeys($schema, $refresh);
    }

    #[\Override]
    public function getSchemaIndexes(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getSchemaIndexes($schema, $refresh);
    }

    #[\Override]
    public function getSchemaPrimaryKeys(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getSchemaPrimaryKeys($schema, $refresh);
    }

    #[\Override]
    public function getSchemaUniques(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getSchemaUniques($schema, $refresh);
    }

    #[\Override]
    public function getTableChecks(string $name, bool $refresh = false): array
    {
        return $this->inner->getTableChecks($name, $refresh);
    }

    #[\Override]
    public function getTableDefaultValues(string $name, bool $refresh = false): array
    {
        return $this->inner->getTableDefaultValues($name, $refresh);
    }

    #[\Override]
    public function getTableIndexes(string $name, bool $refresh = false): array
    {
        return $this->inner->getTableIndexes($name, $refresh);
    }

    #[\Override]
    public function getTablePrimaryKey(string $name, bool $refresh = false): ?Index
    {
        return $this->inner->getTablePrimaryKey($name, $refresh);
    }

    #[\Override]
    public function getTableUniques(string $name, bool $refresh = false): array
    {
        return $this->inner->getTableUniques($name, $refresh);
    }

    #[\Override]
    public function getDefaultSchema(): string
    {
        return $this->inner->getDefaultSchema();
    }

    #[\Override]
    public function getDataType(mixed $data): int
    {
        return $this->inner->getDataType($data);
    }

    #[\Override]
    public function getResultColumn(array $metadata): ?ColumnInterface
    {
        return $this->inner->getResultColumn($metadata);
    }

    #[\Override]
    public function getSchemaNames(bool $refresh = false): array
    {
        return $this->inner->getSchemaNames($refresh);
    }

    #[\Override]
    public function getTableNames(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getTableNames($schema, $refresh);
    }

    #[\Override]
    public function getTableSchema(string $name, bool $refresh = false): ?TableSchemaInterface
    {
        return $this->inner->getTableSchema($name, $refresh);
    }

    #[\Override]
    public function getTableSchemas(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getTableSchemas($schema, $refresh);
    }

    #[\Override]
    public function refresh(): void
    {
        $this->inner->refresh();
    }

    #[\Override]
    public function refreshTableSchema(string $name): void
    {
        $this->inner->refreshTableSchema($name);
    }

    #[\Override]
    public function enableCache(bool $value): void
    {
        $this->inner->enableCache($value);
    }

    #[\Override]
    public function getViewNames(string $schema = '', bool $refresh = false): array
    {
        return $this->inner->getViewNames($schema, $refresh);
    }

    #[\Override]
    public function hasTable(string $tableName, string $schema = '', bool $refresh = false): bool
    {
        return $this->inner->hasTable($tableName, $schema, $refresh);
    }

    #[\Override]
    public function hasSchema(string $schema, bool $refresh = false): bool
    {
        return $this->inner->hasSchema($schema, $refresh);
    }

    #[\Override]
    public function hasView(string $viewName, string $schema = '', bool $refresh = false): bool
    {
        return $this->inner->hasView($viewName, $schema, $refresh);
    }
}
