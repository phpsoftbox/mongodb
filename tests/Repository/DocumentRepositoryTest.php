<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Repository;

use DateTimeImmutable;
use PhpSoftBox\MongoDb\Configurator\MongoFactory;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManager;
use PhpSoftBox\MongoDb\Repository\DocumentRepository;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function uniqid;

final class DocumentRepositoryTest extends TestCase
{
    /**
     * Проверяет базовые read/write операции для массива документов.
     */
    public function testWorksWithArrayDocuments(): void
    {
        $manager = $this->manager();
        $repo    = new DocumentRepository($manager, 'products_array_test');

        $repo->insertOne(['_id' => 'p1', 'company_id' => 10, 'name' => 'One', 'price' => 100]);
        $repo->insertOne(['_id' => 'p2', 'company_id' => 10, 'name' => 'Two', 'price' => 150]);

        $one = $repo->findOne(['_id' => 'p1']);
        $this->assertIsArray($one);
        $this->assertSame('One', $one['name'] ?? null);

        $many = $repo->findMany(['company_id' => 10], ['sort' => ['price' => -1]]);
        $this->assertCount(2, $many);
        $this->assertSame('Two', $many->all()[0]['name'] ?? null);

        $this->assertSame(2, $repo->count(['company_id' => 10]));
        $this->assertTrue($repo->exists(['_id' => 'p2']));

        $repo->upsertOne(['_id' => 'p2'], ['_id' => 'p2', 'company_id' => 10, 'name' => 'Two+', 'price' => 155]);
        $updated = $repo->findOne(['_id' => 'p2']);
        $this->assertIsArray($updated);
        $this->assertSame('Two+', $updated['name'] ?? null);

        $grouped = $repo->aggregate([
            ['$match' => ['company_id' => 10]],
            ['$group' => ['_id' => '$company_id', 'total' => ['$sum' => 1]]],
        ]);
        $this->assertCount(1, $grouped);
        $this->assertSame(2, $grouped->all()[0]['total'] ?? null);

        $manager->collection('products_array_test')->drop();
    }

    /**
     * Проверяет работу с typed document через hydrator и field-map.
     */
    public function testWorksWithTypedDocument(): void
    {
        $manager = $this->manager();
        $repo    = new DocumentRepository(
            mongo: $manager,
            collection: 'products_typed_test',
            documentClass: DocumentRepositoryTestProduct::class,
            fieldMap: [
                'id'        => '_id',
                'createdAt' => 'created_at',
            ],
        );

        $product = new DocumentRepositoryTestProduct();

        $product->id        = 't1';
        $product->name      = 'Typed';
        $product->createdAt = new DateTimeImmutable('2026-04-23T10:30:00+00:00');

        $repo->insertOne($product);

        $loaded = $repo->findOne(['_id' => 't1']);
        $this->assertInstanceOf(DocumentRepositoryTestProduct::class, $loaded);
        $this->assertSame('Typed', $loaded->name);
        $this->assertSame('2026-04-23T10:30:00+00:00', $loaded->createdAt->format(DateTimeImmutable::ATOM));

        $manager->collection('products_typed_test')->drop();
    }

    private function manager(): MongoConnectionManager
    {
        return new MongoConnectionManager(new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://mongo:27017',
                    'database' => sprintf('mongo_repo_test_%s', uniqid()),
                ],
            ],
        ]));
    }
}

final class DocumentRepositoryTestProduct
{
    public string $id;
    public string $name;
    public DateTimeImmutable $createdAt;
}
