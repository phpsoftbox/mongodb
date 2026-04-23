<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Configurator;

use PhpSoftBox\MongoDb\Configurator\MongoFactory;
use PhpSoftBox\MongoDb\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class MongoFactoryTest extends TestCase
{
    /**
     * Проверяет, что создается database для default connection.
     */
    public function testCreateDatabaseWithDefaultConnection(): void
    {
        $factory = new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://localhost:27017',
                    'database' => 'main_db',
                ],
            ],
        ]);

        $database = $factory->createDatabase();

        $this->assertSame('main_db', $database->getDatabaseName());
    }

    /**
     * Проверяет, что неизвестное подключение приводит к ConfigurationException.
     */
    public function testUnknownConnectionThrowsException(): void
    {
        $factory = new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://localhost:27017',
                    'database' => 'main_db',
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $factory->createDatabase('missing');
    }

    /**
     * Проверяет поддержку single-connection формата без connections массива.
     */
    public function testSingleConnectionFormatIsSupported(): void
    {
        $factory = new MongoFactory([
            'uri'      => 'mongodb://localhost:27017',
            'database' => 'single_db',
        ]);

        $database = $factory->createDatabase();

        $this->assertSame('single_db', $database->getDatabaseName());
    }
}
