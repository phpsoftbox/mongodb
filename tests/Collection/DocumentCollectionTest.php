<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Collection;

use PhpSoftBox\MongoDb\Collection\DocumentCollection;
use PHPUnit\Framework\TestCase;

final class DocumentCollectionTest extends TestCase
{
    /**
     * Проверяет, что коллекция хранит и возвращает документы как list.
     */
    public function testStoresAndReturnsDocuments(): void
    {
        $collection = new DocumentCollection([
            ['_id' => 1, 'name' => 'One'],
            ['_id' => 2, 'name' => 'Two'],
        ]);

        $this->assertCount(2, $collection);
        $this->assertSame(1, $collection->all()[0]['_id']);
        $this->assertSame('Two', $collection->all()[1]['name']);
    }
}
