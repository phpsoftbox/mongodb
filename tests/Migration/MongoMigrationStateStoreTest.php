<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Migration;

use PhpSoftBox\MongoDb\Configurator\MongoFactory;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManager;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Migration\MigrationInterface;
use PhpSoftBox\MongoDb\Migration\MongoMigrationStateStore;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function uniqid;

final class MongoMigrationStateStoreTest extends TestCase
{
    /**
     * Проверяет markApplied/appliedVersions/markRolledBack в Mongo-хранилище состояний.
     */
    public function testTracksAppliedAndRolledBackVersions(): void
    {
        $manager    = $this->manager();
        $collection = '_migrations_test_' . uniqid();
        $store      = new MongoMigrationStateStore($manager, $collection);

        $migration = new class () implements MigrationInterface {
            public function version(): string
            {
                return '20260422193000_create_cache';
            }

            public function description(): string
            {
                return 'create cache collection';
            }

            public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
            {
            }

            public function down(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
            {
            }
        };

        $store->markApplied($migration);
        $this->assertSame(['20260422193000_create_cache'], $store->appliedVersions());

        $store->markRolledBack($migration);
        $this->assertSame([], $store->appliedVersions());

        $manager->collection($collection)->drop();
    }

    private function manager(): MongoConnectionManager
    {
        return new MongoConnectionManager(new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://mongo:27017',
                    'database' => sprintf('mongo_test_%s', uniqid()),
                ],
            ],
        ]));
    }
}
