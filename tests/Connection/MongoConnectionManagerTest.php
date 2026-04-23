<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Connection;

use PhpSoftBox\MongoDb\Configurator\MongoFactory;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManager;
use PhpSoftBox\MongoDb\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class MongoConnectionManagerTest extends TestCase
{
    private function manager(): MongoConnectionManager
    {
        return new MongoConnectionManager(new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://localhost:27017',
                    'database' => 'main_db',
                ],
            ],
        ]));
    }

    /**
     * Проверяет, что manager кэширует database instance.
     */
    public function testDatabaseIsCached(): void
    {
        $manager = $this->manager();
        $first   = $manager->database();
        $second  = $manager->database();

        $this->assertSame($first, $second);
    }

    /**
     * Проверяет, что reconnect сбрасывает кеш и возвращает новый database instance.
     */
    public function testReconnectCreatesNewDatabaseInstance(): void
    {
        $manager = $this->manager();
        $first   = $manager->database();
        $second  = $manager->reconnect();

        $this->assertNotSame($first, $second);
    }

    /**
     * Проверяет, что manager создает коллекцию по имени.
     */
    public function testCollection(): void
    {
        $manager    = $this->manager();
        $collection = $manager->collection('cache');

        $this->assertSame('cache', $collection->getCollectionName());
    }

    /**
     * Проверяет, что пустое имя коллекции отклоняется.
     */
    public function testEmptyCollectionNameThrowsException(): void
    {
        $manager = $this->manager();

        $this->expectException(ConfigurationException::class);
        $manager->collection('   ');
    }
}
