<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use Rasuvaeff\Yii3FilestorageAttachments\Attachments;
use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Rasuvaeff\Yii3FilestorageDb\DbRepository;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Config files are outside Psalm and the normal source-scoped checks, so this
 * test executes the package definition through a real Yii container.
 */
#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    private ConnectionInterface $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->db = new Connection(
            driver: new Driver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new ArrayCache()),
        );
        $this->db->open();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function attachmentsResolvesFromThePackageDefinition(): void
    {
        /** @var array<string, mixed> $params */
        $params = require __DIR__ . '/../config/params.php';
        /** @var array<string, mixed> $definitions */
        $definitions = require __DIR__ . '/../config/di.php';
        $container = new Container(ContainerConfig::create()->withDefinitions(
            $definitions + [
                ConnectionInterface::class => $this->db,
                DbRepository::class => static fn(ConnectionInterface $db): DbRepository => new DbRepository($db),
            ],
        ));

        Assert::instanceOf($container->get(AttachmentTableName::class), AttachmentTableName::class);
        Assert::instanceOf($container->get(Attachments::class), Attachments::class);
        Assert::same($params['rasuvaeff/yii3-filestorage-attachments']['attachmentTable'], 'filestorage_attachment');
    }
}
