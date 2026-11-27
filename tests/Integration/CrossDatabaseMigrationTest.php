<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Integration;

use DateInterval;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3Filestorage\File;
use Rasuvaeff\Yii3Filestorage\Id\Uuid7IdGenerator;
use Rasuvaeff\Yii3Filestorage\Mime\FinfoMimeTypeDetector;
use Rasuvaeff\Yii3Filestorage\Path\RandomPathGenerator;
use Rasuvaeff\Yii3Filestorage\Policy\DeliveryPolicyRegistry;
use Rasuvaeff\Yii3Filestorage\Policy\PolicyRegistry;
use Rasuvaeff\Yii3Filestorage\Storage;
use Rasuvaeff\Yii3Filestorage\Store\StoreRegistry;
use Rasuvaeff\Yii3Filestorage\Test\InMemoryStore;
use Rasuvaeff\Yii3Filestorage\Upload;
use Rasuvaeff\Yii3FilestorageAttachments\Attachment;
use Rasuvaeff\Yii3FilestorageAttachments\Attachments;
use Rasuvaeff\Yii3FilestorageAttachments\Migration\M260814000000CreateFilestorageAttachmentTable;
use Rasuvaeff\Yii3FilestorageAttachments\Migration\M261127000000AddFilestorageAttachmentFileForeignKey;
use Rasuvaeff\Yii3FilestorageAttachments\Migration\M261128000000MakeFilestorageAttachmentKeysCaseSensitive;
use Rasuvaeff\Yii3FilestorageDb\DbRepository;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000000CreateFilestorageFileTable;
use Rasuvaeff\Yii3FilestorageDb\Migration\M260807000001CreateFilestorageBlobTables;
use Rasuvaeff\Yii3FilestorageDb\Migration\M261127000000AddFilestorageFileStorePathIndex;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\IntegrityException;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;
use Yiisoft\Db\Mysql\Driver as MysqlDriver;
use Yiisoft\Db\Pgsql\Connection as PgsqlConnection;
use Yiisoft\Db\Pgsql\Driver as PgsqlDriver;
use Yiisoft\Test\Support\Clock\StaticClock;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * The attachment migrations and the file cascade on MySQL and PostgreSQL.
 *
 * Every other test runs on SQLite. MySQL parses the column-level
 * `REFERENCES ... ON DELETE CASCADE` of the table migration and ignores it,
 * so removing a file there left its links behind while every SQLite test
 * stayed green. This applies all migrations, seeds a link that outlived its
 * file the way such an installation has them, and checks that the follow-up
 * migration removes it and that a file removed through core takes its links
 * along.
 *
 * Runs only when `FILESTORAGE_TEST_DB` names a live server; the ungated
 * `database-integration` matrix job in CI supplies both.
 */
#[Test]
#[CoversNothing]
final class CrossDatabaseMigrationTest
{
    private const string ATTACHMENTS = 'filestorage_attachment';
    private const array TABLES = [
        self::ATTACHMENTS,
        'filestorage_blob_reservation',
        'filestorage_blob',
        'filestorage_file',
    ];

    public function removingAFileRemovesItsLinksOnTheConfiguredDatabase(): void
    {
        $database = getenv('FILESTORAGE_TEST_DB');

        if ($database !== 'mysql' && $database !== 'pgsql') {
            Assert::true($database === false || $database === '');

            return;
        }

        $db = $this->connection($database);
        $db->open();

        try {
            $this->dropTables($db);

            $builder = new MigrationBuilder(db: $db, informer: new NullMigrationInformer());
            $files = new M260807000000CreateFilestorageFileTable();
            $blobs = new M260807000001CreateFilestorageBlobTables();
            $pathIndex = new M261127000000AddFilestorageFileStorePathIndex();
            $table = new M260814000000CreateFilestorageAttachmentTable();
            $foreignKey = new M261127000000AddFilestorageAttachmentFileForeignKey();
            $keys = new M261128000000MakeFilestorageAttachmentKeysCaseSensitive();
            $files->up($builder);
            $blobs->up($builder);
            $pathIndex->up($builder);
            $table->up($builder);

            $repository = new DbRepository($db);
            $attachments = new Attachments(db: $db, files: $repository);
            $storage = $this->storage($repository);

            $kept = $this->add($storage);
            $attachments->attach(file: $kept, ownerType: 'post', ownerId: '1');
            $orphan = $this->seedOrphanLink(db: $db, storage: $storage, attachments: $attachments, database: $database);

            $foreignKey->up($builder);

            Assert::same($this->fileForeignKeys($db), 1);
            Assert::same($this->links($db, $orphan), 0, 'the link of a file deleted earlier survived');
            Assert::same($this->links($db, $kept->id), 1, 'a live link was deleted');

            $removed = $this->add($storage);
            $attachments->attach(file: $removed, ownerType: 'post', ownerId: '1');
            Assert::same($this->links($db, $removed->id), 1);
            Assert::true($storage->remove($removed->id));
            Assert::same($this->links($db, $removed->id), 0, 'removing a file left its link behind');

            // down() undoes only what up() added; up() again finds no orphans
            // and reuses the index MySQL created for the first constraint
            $foreignKey->down($builder);
            Assert::same($this->fileForeignKeys($db), $database === 'mysql' ? 0 : 1);
            $foreignKey->up($builder);
            Assert::same($this->fileForeignKeys($db), 1);
            Assert::same($this->links($db, $kept->id), 1);

            $keys->up($builder);
            $this->ownersDifferingOnlyInCaseAreApart($attachments, $storage);
            $keys->down($builder);

            $foreignKey->down($builder);
            $table->down($builder);
            $pathIndex->down($builder);
            $blobs->down($builder);
            $files->down($builder);

            foreach (self::TABLES as $name) {
                Assert::null($db->getTableSchema($name, true), "{$name} was not dropped");
            }
        } finally {
            $this->dropTables($db);
            $db->close();
        }
    }

    /**
     * MySQL's default collation compares `aB` and `Ab` (and `Post` and
     * `post`) as equal: one owner saw, and `detachOwner()` removed, the other's
     * links, and the unique key refused the second owner's first link.
     */
    private function ownersDifferingOnlyInCaseAreApart(Attachments $attachments, Storage $storage): void
    {
        $first = $this->add($storage);
        $second = $this->add($storage);

        Assert::true($attachments->attach($first, 'post', 'aB'));
        Assert::true($attachments->attach($second, 'post', 'Ab'), 'a case variant is another owner');
        Assert::true($attachments->attach($first, 'Post', 'aB'), 'a case variant is another owner type');
        Assert::true($attachments->attach($first, 'post', 'aB', 'Cover'));
        Assert::true($attachments->attach($first, 'post', 'aB', 'cover'), 'a case variant is another role');

        Assert::same(
            array_map(static fn(Attachment $a): string => $a->file->id, $attachments->forOwner('post', 'Ab')),
            [$second->id],
        );
        Assert::same($attachments->detachOwner('post', 'Ab'), 1);
        Assert::same(\count($attachments->forOwner('post', 'aB')), 3);
        Assert::same($attachments->detachOwner('Post', 'aB'), 1);
        Assert::same($attachments->detachOwner('post', 'aB'), 3);
    }

    /**
     * On MySQL the table migration left no constraint, so removing a file
     * keeps its link: the state an existing installation is in. PostgreSQL
     * has the constraint from the start and refuses such a row outright.
     */
    private function seedOrphanLink(
        ConnectionInterface $db,
        Storage $storage,
        Attachments $attachments,
        string $database,
    ): string {
        $file = $this->add($storage);
        $attachments->attach(file: $file, ownerType: 'post', ownerId: '1');

        if ($database === 'mysql') {
            Assert::same($this->fileForeignKeys($db), 0);
            Assert::true($storage->remove($file->id));
            Assert::same($this->links($db, $file->id), 1);

            return $file->id;
        }

        Assert::same($this->fileForeignKeys($db), 1);
        Assert::true($storage->remove($file->id));
        Assert::same($this->links($db, $file->id), 0);

        try {
            $db->createCommand()->insert(self::ATTACHMENTS, [
                'scope_id' => '',
                'owner_type' => 'post',
                'owner_id' => '1',
                'role' => 'default',
                'file_id' => 'missing',
            ])->execute();
            Assert::fail('PostgreSQL accepted a link to a missing file');
        } catch (IntegrityException) {
        }

        return 'missing';
    }

    private function add(Storage $storage): File
    {
        $http = new Psr17Factory();

        return $storage->add(Upload::fromStream($http->createStream('hello'), 'a.txt', $http));
    }

    private function links(ConnectionInterface $db, string $fileId): int
    {
        return (int) $db->createCommand(
            sprintf('SELECT COUNT(*) FROM %s WHERE file_id = :id', self::ATTACHMENTS),
            [':id' => $fileId],
        )->queryScalar();
    }

    private function fileForeignKeys(ConnectionInterface $db): int
    {
        $count = 0;
        foreach ($db->getSchema()->getTableForeignKeys(self::ATTACHMENTS, true) as $key) {
            if ($key->foreignTableName === 'filestorage_file' && $key->columnNames === ['file_id']) {
                Assert::same(strtoupper((string) $key->onDelete), 'CASCADE');
                ++$count;
            }
        }

        return $count;
    }

    private function storage(DbRepository $repository): Storage
    {
        $clock = new StaticClock(new DateTimeImmutable('2026-01-01T00:00:00.000000+00:00'));

        return new Storage(
            stores: new StoreRegistry([new InMemoryStore('upload', new Psr17Factory(), $clock)]),
            repository: $repository,
            pathGenerator: new RandomPathGenerator(),
            mimeTypeDetector: new FinfoMimeTypeDetector(),
            idGenerator: new Uuid7IdGenerator($clock),
            policies: new PolicyRegistry(),
            deliveryPolicies: new DeliveryPolicyRegistry(),
            clock: $clock,
            defaultUrlTtl: new DateInterval('PT1H'),
        );
    }

    private function dropTables(ConnectionInterface $db): void
    {
        foreach (self::TABLES as $table) {
            $db->createCommand(sprintf('DROP TABLE IF EXISTS %s', $table))->execute();
        }
    }

    private function connection(string $database): ConnectionInterface
    {
        $cache = new SchemaCache(psrCache: new MemorySimpleCache());
        $mysqlPort = getenv('FILESTORAGE_TEST_MYSQL_PORT') ?: '3306';
        $pgsqlPort = getenv('FILESTORAGE_TEST_PGSQL_PORT') ?: '5432';

        return $database === 'mysql'
            ? new MysqlConnection(
                driver: new MysqlDriver(
                    dsn: sprintf('mysql:host=127.0.0.1;port=%s;dbname=filestorage;charset=utf8mb4', $mysqlPort),
                    username: 'root',
                    password: 'filestorage',
                ),
                schemaCache: $cache,
            )
            : new PgsqlConnection(
                driver: new PgsqlDriver(
                    dsn: sprintf('pgsql:host=127.0.0.1;port=%s;dbname=filestorage', $pgsqlPort),
                    username: 'postgres',
                    password: 'filestorage',
                ),
                schemaCache: $cache,
            );
    }
}
