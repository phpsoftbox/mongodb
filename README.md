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
