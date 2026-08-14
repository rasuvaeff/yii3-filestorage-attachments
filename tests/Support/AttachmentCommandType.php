<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FilestorageAttachments\Tests\Support;

/** @internal */
enum AttachmentCommandType
{
    case Attach;
    case Detach;
    case DetachOwner;
    case ListOwner;
}
