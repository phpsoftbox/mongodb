<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Document;

use DateTimeImmutable;
use PhpSoftBox\MongoDb\Document\DocumentHydrator;
use PHPUnit\Framework\TestCase;

final class DocumentHydratorTest extends TestCase
{
    /**
     * Проверяет hydrate/extract c field-map и типами enum/datetime.
     */
    public function testHydrateAndExtract(): void
    {
        $hydrator = new DocumentHydrator();

        $document = $hydrator->hydrate(DocumentHydratorTestProduct::class, [
            '_id'        => '507f1f77bcf86cd799439011',
            'name'       => 'Demo',
            'status'     => 'active',
            'created_at' => '2026-04-23T09:15:00+00:00',
        ], [
            'id'        => '_id',
            'createdAt' => 'created_at',
        ]);

        $this->assertInstanceOf(DocumentHydratorTestProduct::class, $document);
        $this->assertSame('507f1f77bcf86cd799439011', $document->id);
        $this->assertSame('Demo', $document->name);
        $this->assertSame(DocumentHydratorTestStatus::Active, $document->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $document->createdAt);

        $extracted = $hydrator->extract($document, [
            'id'        => '_id',
            'createdAt' => 'created_at',
        ]);
        $this->assertSame('507f1f77bcf86cd799439011', $extracted['_id']);
        $this->assertSame('active', $extracted['status']);
        $this->assertSame('2026-04-23T09:15:00+00:00', $extracted['created_at']);
    }

    /**
     * Проверяет пакетные операции hydrateMany/extractMany.
     */
    public function testHydrateManyAndExtractMany(): void
    {
        $hydrator = new DocumentHydrator();

        $items = $hydrator->hydrateMany(DocumentHydratorTestProduct::class, [
                    ['id' => '1', 'name' => 'One', 'status' => 'active', 'createdAt' => '2026-04-23T10:00:00+00:00'],
                    ['id' => '2', 'name' => 'Two', 'status' => 'inactive', 'createdAt' => '2026-04-23T10:01:00+00:00'],
                ]);

        $this->assertCount(2, $items);
        $this->assertSame('One', $items->all()[0]->name);
        $this->assertSame('inactive', $items->all()[1]->status->value);

        $extracted = $hydrator->extractMany($items->all());
        $this->assertCount(2, $extracted);
        $this->assertSame('Two', $extracted->all()[1]['name']);
    }
}

enum DocumentHydratorTestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

final class DocumentHydratorTestProduct
{
    public string $id;
    public string $name;
    public DocumentHydratorTestStatus $status;
    public DateTimeImmutable $createdAt;
}
