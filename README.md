# Mongo

## About

`phpsoftbox/mongo` — минимальный компонент управления MongoDB-подключениями для PhpSoftBox.

Компонент:
- не зависит от SQL `Database`;
- не пытается быть ORM;
- дает фабрику и connection manager для `Client`/`Database`/`Collection`;
- предоставляет `DocumentCollection` как типизированную обертку над списком документов;
- включает `QueryBuilder` и `PipelineBuilder` для сборки `find`/`aggregate`;
- включает `DocumentHydrator` и `DocumentRepository` для типизированной работы с документами;
- включает слой миграций (`MigrationInterface`, `Migrator`, `MongoMigrationStateStore`, `FileMigrationLoader`, `MigrationCreator`, `MigrationSchema`).

## Requirements

`ext-mongodb` `^2.4` и `mongodb/mongodb` `^2.4.1`: более ранние версии библиотеки закрыты security advisory
(`PKSA-61k5-cqr9-b8b4`) и не устанавливаются.

## Configuration

```php
return [
    'connections' => [
        'default' => 'main',
        'main' => [
            'uri' => env('APP_MONGO_URI', 'mongodb://mongo:27017'),
            'database' => env('APP_MONGO_DB', 'app'),
            'uri_options' => [],
            'driver_options' => [],
            'database_options' => [],
        ],
    ],
];
```

Поддерживается также сокращенный single-connection формат:

```php
return [
    'uri' => env('APP_MONGO_URI', 'mongodb://mongo:27017'),
    'database' => env('APP_MONGO_DB', 'app'),
];
```

## Usage

```php
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;

$mongo = $container->get(MongoConnectionManagerInterface::class);

$collection = $mongo->collection('marketplace_cache');
$collection->replaceOne(
    ['cache_key' => 'ozon:company:1:page:2'],
    ['cache_key' => 'ozon:company:1:page:2', 'payload' => $payload],
    ['upsert' => true],
);
```

## QueryBuilder

```php
use PhpSoftBox\MongoDb\Query\QueryBuilder;

$query = (new QueryBuilder())
    ->whereEq('company_id', 10)
    ->whereIn('source', ['wb', 'ozon'])
    ->sort(['created_at' => -1])
    ->limit(100);

$cursor = $collection->find($query->buildFilter(), $query->buildFindOptions());
```

`whereEq` собирает условие через `$eq`: значение из пользовательского ввода, даже массив вида `['$ne' => '']`, сравнивается
как значение и не превращается в оператор. Имя поля, начинающееся с `$` (`$where`, `$expr`), отклоняется
`InvalidArgumentException`. Фильтры, переданные массивом в `where()`, `DocumentRepository` или `Collection` напрямую,
не проверяются — пользовательские значения в них подставляйте через `whereEq`/`whereIn` или явный `$eq`.

## PipelineBuilder

```php
use PhpSoftBox\MongoDb\Query\PipelineBuilder;

$pipeline = (new PipelineBuilder())
    ->match(['company_id' => 10])
    ->sort(['created_at' => -1])
    ->skip(100)
    ->limit(50)
    ->build();

$cursor = $collection->aggregate($pipeline);
```

## Typed Documents

```php
use PhpSoftBox\MongoDb\Document\DocumentHydrator;
use PhpSoftBox\MongoDb\Repository\DocumentRepository;

final class ProductDocument
{
    public string $id;
    public string $name;
    public DateTimeImmutable $createdAt;
}

$repository = new DocumentRepository(
    mongo: $mongo,
    collection: 'products',
    documentClass: ProductDocument::class,
    hydrator: new DocumentHydrator(),
    fieldMap: [
        'id' => '_id',
        'createdAt' => 'created_at',
    ],
);

$product = $repository->findOne(['_id' => 'p1']); // ProductDocument|null
```

## Dates

Даты хранятся нативным BSON-типом date (`MongoDB\BSON\UTCDateTime`) — моментом времени в UTC с точностью до
миллисекунд (микросекунды отбрасываются). Поэтому диапазонные запросы и сортировка работают по моменту времени
независимо от смещения, в котором дата создана, и по полю можно строить TTL-индекс.

- `DocumentHydrator::extract()` (и `DocumentRepository::insertOne()`/`replaceOne()`/`upsertOne()` с typed-документом)
  пишет любой `DateTimeInterface` — `DateTimeImmutable`, `DateTime`, `DatePoint` из `phpsoftbox/clock` — как
  `UTCDateTime`.
- `DocumentHydrator::hydrate()` заполняет свойство с типом `DateTimeImmutable`, `DateTimeInterface`, `DateTime` или
  наследника (`DatePoint`) из `UTCDateTime` и из строки (формат ATOM, который пакет писал до 1.0). Дата
  возвращается в таймзоне гидратора: `new DocumentHydrator(new DateTimeZone('Europe/Moscow'))`; без аргумента — в
  `date_default_timezone_get()` на момент чтения. Строка без смещения трактуется в этой же таймзоне.
- `QueryBuilder` (`where*()`, `where()`, `stage()`), `PipelineBuilder` (все стадии) и `DocumentRepository` (фильтры,
  update, pipeline, документы-массивы) приводят `DateTimeInterface` к `UTCDateTime`. `whereGt`/`whereGte`/`whereLt`/
  `whereLte` принимают `DateTimeInterface`.
- `DocumentRepository` для документов-массивов возвращает `UTCDateTime` как `DateTimeImmutable` в
  `date_default_timezone_get()`.

```php
$query = (new QueryBuilder())
    ->whereGte('created_at', new DateTimeImmutable('2026-04-23 00:00:00', new DateTimeZone('Europe/Moscow')))
    ->whereLt('created_at', new DateTimeImmutable('2026-04-24 00:00:00', new DateTimeZone('Europe/Moscow')))
    ->sort(['created_at' => 1]);

$products = $repository->findMany($query->buildFilter(), $query->buildFindOptions());
```

TTL-индекс работает только по полю типа date:

```php
$schema->ensureIndex('marketplace_cache', ['created_at' => 1], ['name' => 'created_at_ttl', 'expireAfterSeconds' => 86400]);
```

### Миграция существующих документов

До 1.0 `DocumentHydrator` писал даты строкой ATOM (`2026-04-23T12:15:00+03:00`). Такие документы читаются и после
обновления, но в диапазонные запросы, сортировку по дате и TTL не попадают: строка и дата в MongoDB не сравниваются.
Преобразуйте их миграцией (MongoDB 4.2+; файл создаётся `mongo:migrate:make`, применяется `mongo:migrate` через
`Migrator`) — update-пайплайн с `$dateFromString` для каждого поля-даты:

```php
<?php

declare(strict_types=1);

use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Migration\AbstractMigration;

return new class (
    '20261001120000',
    'Даты products: строки ATOM -> UTCDateTime',
) extends AbstractMigration {
    public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
    {
        foreach (['created_at', 'updated_at'] as $field) {
            $mongo->collection('products', $connection)->updateMany(
                [$field => ['$type' => 'string']],
                [['$set' => [$field => ['$dateFromString' => ['dateString' => '$' . $field]]]]],
            );
        }
    }

    public function down(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
    {
        foreach (['created_at', 'updated_at'] as $field) {
            $mongo->collection('products', $connection)->updateMany(
                [$field => ['$type' => 'date']],
                [['$set' => [$field => ['$dateToString' => ['date' => '$' . $field, 'format' => '%Y-%m-%dT%H:%M:%S+00:00']]]]],
            );
        }
    }
};
```

`$dateFromString` учитывает смещение в строке, поэтому документы, записанные в разных таймзонах, получают верный
момент времени. Поля-даты во вложенных массивах пайплайном верхнего уровня не преобразуются — для них прочитайте
документы и перезапишите их через `DocumentRepository::replaceOne()` (гидратор прочитает строку и запишет
`UTCDateTime`).

## Migrations

```php
use PhpSoftBox\MongoDb\Migration\FileMigrationLoader;
use PhpSoftBox\MongoDb\Migration\Migrator;
use PhpSoftBox\MongoDb\Migration\MongoMigrationStateStore;

$loader = new FileMigrationLoader();
$migrations = array_map(
    static fn (array $item): object => $item['migration'],
    $loader->load('/app/database/migrations/mongo/default'),
);

$migrator = new Migrator($mongo, new MongoMigrationStateStore($mongo));
$applied = $migrator->migrate($migrations, 'default');
```

Для DSL-операций внутри миграции можно использовать `MigrationSchema`:

```php
public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
{
    $schema = $this->schema($mongo, $connection);
    $schema->createCollection('marketplace_cache');
    $schema->ensureIndex('marketplace_cache', ['cache_key' => 1], ['name' => 'cache_key_unique', 'unique' => true]);
}
```
