<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

use Closure;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ServerInfoInterface;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\BatchQueryResultInterface;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\Column\ColumnFactoryInterface;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Schema\TableSchemaInterface;
use Yiisoft\Db\Transaction\TransactionInterface;

/**
 * An SQLite connection that reports another driver name, so a migration takes
 * the branch it takes on that server and the SQL it emits can be read.
 *
 * Every SQL string handed to `createCommand()` is recorded. A statement listed
 * in `$answers` is run as its replacement instead (`SELECT VERSION()` →
 * `SELECT '8.4.13'`), and an `ALTER TABLE` is recorded and run as a no-op:
 * this checks the statement the package builds, not what the server does
 * with it — the integration suite runs that against real servers.
 *
 * @internal
 */
final class ImpersonatingConnection implements ConnectionInterface
{
    /** @var list<string> */
    public array $statements = [];

    /**
     * @param array<string, string> $answers
     */
    public function __construct(
        private readonly ConnectionInterface $inner,
        private readonly string $driverName,
        private readonly array $answers = [],
        private readonly ?SchemaInterface $schema = null,
    ) {}

    #[\Override]
    public function createCommand(?string $sql = null, array $params = []): CommandInterface
    {
        if ($sql !== null) {
            $this->statements[] = $sql;
            $sql = $this->answers[$sql] ?? (str_starts_with($sql, 'ALTER TABLE') ? 'SELECT 1' : $sql);
        }

        return $this->inner->createCommand($sql, $params);
    }

    #[\Override]
    public function getDriverName(): string
    {
        return $this->driverName;
    }

    #[\Override]
    public function getSchema(): SchemaInterface
    {
        return $this->schema ?? $this->inner->getSchema();
    }

    #[\Override]
    public function beginTransaction(?string $isolationLevel = null): TransactionInterface
    {
        return $this->inner->beginTransaction($isolationLevel);
    }

    #[\Override]
    public function createBatchQueryResult(QueryInterface $query): BatchQueryResultInterface
    {
        return $this->inner->createBatchQueryResult($query);
    }

    #[\Override]
    public function createQuery(): QueryInterface
    {
        return $this->inner->createQuery();
    }

    #[\Override]
    public function createTransaction(): TransactionInterface
    {
        return $this->inner->createTransaction();
    }

    #[\Override]
    public function close(): void
    {
        $this->inner->close();
    }

    #[\Override]
    public function getColumnBuilderClass(): string
    {
        return $this->inner->getColumnBuilderClass();
    }

    #[\Override]
    public function getColumnFactory(): ColumnFactoryInterface
    {
        return $this->inner->getColumnFactory();
    }

    #[\Override]
    public function getLastInsertId(?string $sequenceName = null): string
    {
        return $this->inner->getLastInsertId($sequenceName);
    }

    #[\Override]
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->inner->getQueryBuilder();
    }

    #[\Override]
    public function getQuoter(): QuoterInterface
    {
        return $this->inner->getQuoter();
    }

    #[\Override]
    public function getServerInfo(): ServerInfoInterface
    {
        return $this->inner->getServerInfo();
    }

    #[\Override]
    public function getTablePrefix(): string
    {
        return $this->inner->getTablePrefix();
    }

    #[\Override]
    public function getTableSchema(string $name, bool $refresh = false): ?TableSchemaInterface
    {
        return $this->inner->getTableSchema($name, $refresh);
    }

    #[\Override]
    public function getTransaction(): ?TransactionInterface
    {
        return $this->inner->getTransaction();
    }

    #[\Override]
    public function isActive(): bool
    {
        return $this->inner->isActive();
    }

    #[\Override]
    public function isSavepointEnabled(): bool
    {
        return $this->inner->isSavepointEnabled();
    }

    #[\Override]
    public function open(): void
    {
        $this->inner->open();
    }

    #[\Override]
    public function quoteValue(mixed $value): mixed
    {
        return $this->inner->quoteValue($value);
    }

    #[\Override]
    public function setEnableSavepoint(bool $value): void
    {
        $this->inner->setEnableSavepoint($value);
    }

    #[\Override]
    public function select(
        array|bool|float|int|string|ExpressionInterface $columns = [],
        ?string $option = null,
    ): QueryInterface {
        return $this->inner->select($columns, $option);
    }

    #[\Override]
    public function setTablePrefix(string $value): void
    {
        $this->inner->setTablePrefix($value);
    }

    #[\Override]
    public function transaction(Closure $closure, ?string $isolationLevel = null): mixed
    {
        return $this->inner->transaction($closure, $isolationLevel);
    }
}
