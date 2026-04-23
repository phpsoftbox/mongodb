<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Migration;

use PhpSoftBox\MongoDb\Configurator\MongoFactory;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManager;
use PhpSoftBox\MongoDb\Migration\MigrationSchema;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function uniqid;

final class MigrationSchemaTest extends TestCase
{
    /**
     * Проверяет createCollection/ensureIndex/dropIndex/dropCollection.
     */
    public function testSchemaOperations(): void
    {
        $manager = $this->manager();
        $schema  = new MigrationSchema($manager);
        $name    = 'schema_test_' . uniqid();

        $this->assertFalse($schema->hasCollection($name));
        $schema->createCollection($name);
        $this->assertTrue($schema->hasCollection($name));

        $indexName = $schema->ensureIndex($name, ['tenant_id' => 1], ['name' => 'tenant_idx']);
        $this->assertSame('tenant_idx', $indexName);

        $schema->dropIndex($name, 'tenant_idx');
        $schema->dropCollection($name);
        $this->assertFalse($schema->hasCollection($name));
    }

    private function manager(): MongoConnectionManager
    {
        return new MongoConnectionManager(new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://mongo:27017',
                    'database' => sprintf('mongo_schema_test_%s', uniqid()),
                ],
            ],
        ]));
    }
}
