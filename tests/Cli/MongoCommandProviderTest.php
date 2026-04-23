<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Cli;

use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\MongoDb\Cli\MakeMigrationHandler;
use PhpSoftBox\MongoDb\Cli\MigrateHandler;
use PhpSoftBox\MongoDb\Cli\MongoCommandProvider;
use PhpSoftBox\MongoDb\Cli\RollbackHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MongoCommandProvider::class)]
#[CoversMethod(MongoCommandProvider::class, 'register')]
final class MongoCommandProviderTest extends TestCase
{
    /**
     * Проверяет регистрацию mongo CLI-команд миграций.
     */
    #[Test]
    public function registersMongoMigrationCommands(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);
        $provider = new MongoCommandProvider();

        $provider->register($registry);

        $this->assertSame(MigrateHandler::class, $registry->get('mongo:migrate')?->handler);
        $this->assertSame(RollbackHandler::class, $registry->get('mongo:migrate:rollback')?->handler);
        $this->assertSame(MakeMigrationHandler::class, $registry->get('mongo:migrate:make')?->handler);
    }
}
