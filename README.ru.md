# Yii3 Filestorage Attachments

`rasuvaeff/yii3-filestorage-attachments` предоставляет сервис связей файлов
Yii3 Filestorage с произвольными владельцами. Он покрывает обычный сценарий
полиморфных вложений без зависимости от Active Record.

## Установка

```bash
composer require rasuvaeff/yii3-filestorage-attachments
```

Пакет требует `rasuvaeff/yii3-filestorage-db`. Сначала примените миграцию
таблицы файлов, затем зарегистрируйте namespace миграций этого пакета:

```php
MigrationService::class => [
    'setSourceNamespaces()' => [[
        'Rasuvaeff\\Yii3FilestorageDb\\Migration',
        'Rasuvaeff\\Yii3FilestorageAttachments\\Migration',
    ]],
],
```

Config plugin предоставляет `AttachmentTableName` и `Attachments`. Контракт
`FileScopeProviderInterface` он не связывает: приложение с tenant-моделью
связывает его один раз, и тот же provider используют `yii3-filestorage-db`.

## Использование

```php
$attachments->attach(
    file: $file,
    ownerType: 'order',
    ownerId: (string) $orderId,
    role: 'invoice',
);

$invoices = $attachments->forOwner(
    ownerType: 'order',
    ownerId: (string) $orderId,
    role: 'invoice',
);
```

`attach()` идемпотентен и возвращает `false`, если такая связь уже есть.
`forOwner()` возвращает `list<Attachment>` в порядке добавления; без `role`
возвращаются все роли. `detachOwner()` удаляет все связи владельца, его нужно
вызывать из lifecycle приложения или Active Record перед удалением владельца.

У таблицы есть настоящий внешний ключ на `filestorage_file`, поэтому удаление
строки файла автоматически удаляет связи. Универсальный внешний ключ на
таблицу владельца невозможен и намеренно не создаётся.

> **Пререквизит SQLite:** SQLite применяет `ON DELETE CASCADE` только когда
> каждое соединение включает `PRAGMA foreign_keys = ON`. Пакет не настраивает
> переданные ему соединения — включите pragma в конфигурации соединения
> приложения. В MySQL/MariaDB и PostgreSQL каскад работает по умолчанию.

## Имена таблиц и scope

При конфликте имён настройте параметры
`rasuvaeff/yii3-filestorage-attachments.attachmentTable` и `tablePrefix`.
Миграция и runtime используют один типизированный `AttachmentTableName`.

Без scope provider связи используют внутренний пустой ключ scope. При наличии
`FileScopeProviderInterface` каждая операция ограничена текущим scope, а файл
можно привязать только если он виден в этом scope.

## Проверка

```bash
make build
make test-integration
make rector
```

Интеграционный suite применяет настоящие миграции `yii3-filestorage-db` и
attachments на SQLite и проверяет документированный путь миграций.
