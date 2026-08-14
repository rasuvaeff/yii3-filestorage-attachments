<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3FilestorageAttachments\Attachment;
use Rasuvaeff\Yii3FilestorageAttachments\Attachments;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\FixedScope;
use Rasuvaeff\Yii3FilestorageAttachments\Tests\Support\SqliteDatabase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(Attachments::class)]
#[Covers(Attachment::class)]
final class AttachmentsTest
{
    private SqliteDatabase $database;
    private Attachments $attachments;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->database = new SqliteDatabase();
        $this->attachments = new Attachments(
            db: $this->database->db,
            files: $this->database->files,
        );
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->database->close();
    }

    public function attachIsIdempotentAndListsRolesInInsertionOrder(): void
    {
        $file = SqliteDatabase::file('file-1');
        $this->database->files->save($file);
        $other = SqliteDatabase::file('file-2', 'sha/da/39/original');
        $this->database->files->save($other);

        Assert::true($this->attachments->attach($file, 'order', 'order-1', 'invoice'));
        Assert::false($this->attachments->attach($file, 'order', 'order-1', 'invoice'));
        Assert::true($this->attachments->attach($other, 'order', 'order-1', 'receipt'));

        $all = $this->attachments->forOwner('order', 'order-1');
        Assert::same(count($all), 2);
        Assert::same($all[0]->file->id, 'file-1');
        Assert::same($all[0]->role, 'invoice');
        Assert::same($all[1]->file->id, 'file-2');
        Assert::same(array_map(static fn(Attachment $a): string => $a->role, $this->attachments->forOwner('order', 'order-1', 'invoice')), ['invoice']);
    }

    public function detachAndDetachOwnerAreScopedToTheOwner(): void
    {
        $file = SqliteDatabase::file('file-1');
        $this->database->files->save($file);
        $this->attachments->attach($file, 'order', 'order-1', 'invoice');
        $this->attachments->attach($file, 'order', 'order-1', 'receipt');
        $this->attachments->attach($file, 'order', 'order-2', 'invoice');

        Assert::true($this->attachments->detach($file, 'order', 'order-1', 'invoice'));
        Assert::false($this->attachments->detach($file, 'order', 'order-1', 'invoice'));
        Assert::same($this->attachments->detachOwner('order', 'order-1'), 1);
        Assert::same(count($this->attachments->forOwner('order', 'order-1')), 0);
        Assert::same(count($this->attachments->forOwner('order', 'order-2')), 1);
    }

    public function deletingAFileCascadesItsLinks(): void
    {
        $file = SqliteDatabase::file('file-1');
        $this->database->files->save($file);
        $this->attachments->attach($file, 'order', 'order-1');

        Assert::true($this->database->files->delete($file->id));
        Assert::same(count($this->attachments->forOwner('order', 'order-1')), 0);
    }

    public function tenantScopePreventsCrossScopeAttachReadAndDelete(): void
    {
        $scope = new FixedScope('tenant-a');
        $database = new SqliteDatabase($scope);
        $attachments = new Attachments(db: $database->db, files: $database->files, scopes: $scope);
        $file = SqliteDatabase::file('file-1');
        $database->files->save($file);
        $attachments->attach($file, 'order', 'order-1');

        $scope->switchTo('tenant-b');
        Assert::same(count($attachments->forOwner('order', 'order-1')), 0);
        Assert::same($attachments->detachOwner('order', 'order-1'), 0);
        Assert::false($attachments->detach($file, 'order', 'order-1'));
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('not visible');
        $attachments->attach($file, 'order', 'order-2');

        $scope->switchTo('tenant-a');
        Assert::same(count($attachments->forOwner('order', 'order-1')), 1);
        $database->close();
    }

    public function acceptsMaximumColumnLengths(): void
    {
        $file = SqliteDatabase::file('file-1');
        $this->database->files->save($file);

        Assert::true($this->attachments->attach(
            $file,
            str_repeat('t', 128),
            str_repeat('i', 191),
            str_repeat('r', 64),
        ));
    }

    #[DataProvider('invalidOwnerParts')]
    public function detachValidatesEveryOwnerPart(string $ownerType, string $ownerId, string $role): void
    {
        Expect::exception(InvalidArgumentException::class);

        $this->attachments->detach(SqliteDatabase::file('file-1'), $ownerType, $ownerId, $role);
    }

    public function forOwnerValidatesItsArguments(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Invalid owner type');

        $this->attachments->forOwner('', 'owner-1');
    }

    public function detachOwnerValidatesItsArguments(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Owner id');

        $this->attachments->detachOwner('order', '');
    }

    public function corruptEmptyRolesAreNotHydrated(): void
    {
        $file = SqliteDatabase::file('file-1');
        $this->database->files->save($file);
        $this->database->db->createCommand()->insert('filestorage_attachment', [
            'scope_id' => '',
            'owner_type' => 'order',
            'owner_id' => 'order-1',
            'role' => '',
            'file_id' => $file->id,
        ])->execute();

        Assert::same($this->attachments->forOwner('order', 'order-1'), []);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidOwnerParts(): iterable
    {
        yield 'empty type' => ['', 'owner-1', 'default'];
        yield 'long type' => [str_repeat('t', 129), 'owner-1', 'default'];
        yield 'nul type' => ["order\0item", 'owner-1', 'default'];
        yield 'empty id' => ['order', '', 'default'];
        yield 'long id' => ['order', str_repeat('i', 192), 'default'];
        yield 'nul id' => ['order', "owner\0id", 'default'];
        yield 'empty role' => ['order', 'owner-1', ''];
        yield 'long role' => ['order', 'owner-1', str_repeat('r', 65)];
        yield 'nul role' => ['order', 'owner-1', "invoice\0draft"];
    }
}
