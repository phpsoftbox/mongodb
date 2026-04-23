<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Migration;

use PhpSoftBox\MongoDb\Exception\ConfigurationException;
use PhpSoftBox\MongoDb\Migration\MigrationsConfig;
use PHPUnit\Framework\TestCase;

final class MigrationsConfigTest extends TestCase
{
    /**
     * Проверяет, что default connection берется из first configured path.
     */
    public function testDefaultConnectionIsResolvedFromPaths(): void
    {
        $config = new MigrationsConfig(
            basePath: '/tmp/migrations',
            paths: [
                'tenant' => '/tmp/migrations/tenant',
                'core'   => '/tmp/migrations/core',
            ],
        );

        $this->assertSame('tenant', $config->defaultConnection());
        $this->assertSame(['/tmp/migrations/core'], $config->paths('core'));
    }

    /**
     * Проверяет fallback path как "{basePath}/{connection}".
     */
    public function testFallbackPathUsesBasePathAndConnection(): void
    {
        $config = new MigrationsConfig('/tmp/mongo-migrations');

        $this->assertSame('default', $config->defaultConnection());
        $this->assertSame(['/tmp/mongo-migrations/marketplace'], $config->paths('marketplace'));
    }

    /**
     * Проверяет валидацию пустого base path.
     */
    public function testRejectsEmptyBasePath(): void
    {
        $this->expectException(ConfigurationException::class);
        new MigrationsConfig('   ');
    }
}
