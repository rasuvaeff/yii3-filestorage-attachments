<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Filestorage\Repository\FileScopeProviderInterface;
use Rasuvaeff\Yii3FilestorageAttachments\AttachmentTableName;
use Rasuvaeff\Yii3FilestorageAttachments\Attachments;
use Rasuvaeff\Yii3FilestorageDb\DbRepository;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var array $params */

return [
    AttachmentTableName::class => static fn (): AttachmentTableName => new AttachmentTableName(
        ((string) $params['rasuvaeff/yii3-filestorage-attachments']['tablePrefix'])
        . ((string) $params['rasuvaeff/yii3-filestorage-attachments']['attachmentTable']),
    ),

    Attachments::class => static fn (
        ConnectionInterface $db,
        DbRepository $files,
        AttachmentTableName $table,
        ?FileScopeProviderInterface $scopes = null,
    ): Attachments => new Attachments(
        db: $db,
        files: $files,
        table: $table,
        scopes: $scopes,
    ),
];
