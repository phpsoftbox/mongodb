<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Migration;

use InvalidArgumentException;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Migration\MigrationInterface;
use PhpSoftBox\MongoDb\Migration\MigrationStateStoreInterface;
use PhpSoftBox\MongoDb\Migration\Migrator;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;
use function in_array;
use function sort;

final class MigratorTest extends TestCase
{
    /**
     * Проверяет, что migrate применяет только непримененные миграции.
     */
    public function testMigrateAppliesOnlyPendingMigrations(): void
    {
        $mongo = $this->createMock(MongoConnectionManagerInterface::class);
        $store = new InMemoryMigrationStateStore(['001_create_cache']);

        $m001 = new FakeMigration('001_create_cache');
        $m002 = new FakeMigration('002_create_indexes');
        $m003 = new FakeMigration('003_add_ttl');

        $migrator = new Migrator($mongo, $store);

        $applied = $migrator->migrate([$m003, $m001, $m002]);

        $this->assertSame(['002_create_indexes', '003_add_ttl'], $applied);
        $this->assertSame(0, $m001->upCalls);
        $this->assertSame(1, $m002->upCalls);
        $this->assertSame(1, $m003->upCalls);
    }

    /**
     * Проверяет, что цифровые версии не преобразуются PHP в integer-ключи
     * и корректно работают во всём цикле migrate/rollback.
     */
    public function testNumericVersionsRemainStringsAcrossMigrateAndRollback(): void
    {
        $mongo = $this->createMock(MongoConnectionManagerInterface::class);
        $store = new InMemoryMigrationStateStore(['20260521143000']);

        $first = new FakeMigration('20260521143000');
        $next  = new FakeMigration('20260521143001');

        $migrator = new Migrator($mongo, $store);

        $applied = $migrator->migrate([$next, $first]);

        $this->assertSame(['20260521143001'], $applied);
        $this->assertIsString($applied[0]);
        $this->assertSame(0, $first->upCalls);
        $this->assertSame(1, $next->upCalls);

        $this->assertSame([], $migrator->migrate([$next, $first]));
        $this->assertSame(1, $next->upCalls);

        $rolledBack = $migrator->rollback([$first, $next], 2);

        $this->assertSame(['20260521143001', '20260521143000'], $rolledBack);
        $this->assertIsString($rolledBack[0]);
        $this->assertIsString($rolledBack[1]);
        $this->assertSame(1, $next->downCalls);
        $this->assertSame(1, $first->downCalls);
    }

    /**
     * Проверяет, что rollback откатывает последние примененные версии.
     */
    public function testRollbackAppliesStepsFromLatest(): void
    {
        $mongo = $this->createMock(MongoConnectionManagerInterface::class);
        $store = new InMemoryMigrationStateStore([
            '001_create_cache',
            '002_create_indexes',
            '003_add_ttl',
        ]);

        $m001 = new FakeMigration('001_create_cache');
        $m002 = new FakeMigration('002_create_indexes');
        $m003 = new FakeMigration('003_add_ttl');

        $migrator = new Migrator($mongo, $store);

        $rolledBack = $migrator->rollback([$m001, $m002, $m003], 2);

        $this->assertSame(['003_add_ttl', '002_create_indexes'], $rolledBack);
        $this->assertSame(0, $m001->downCalls);
        $this->assertSame(1, $m002->downCalls);
        $this->assertSame(1, $m003->downCalls);
    }

    /**
     * Проверяет, что rollback с steps=0 не выполняет откат.
     */
    public function testRollbackWithZeroStepsReturnsEmptyResult(): void
    {
        $mongo = $this->createMock(MongoConnectionManagerInterface::class);
        $store = new InMemoryMigrationStateStore(['001_create_cache']);

        $m001     = new FakeMigration('001_create_cache');
        $migrator = new Migrator($mongo, $store);

        $rolledBack = $migrator->rollback([$m001], 0);

        $this->assertSame([], $rolledBack);
        $this->assertSame(0, $m001->downCalls);
    }

    /**
     * Проверяет, что дубли версий в плане миграций отклоняются.
     */
    public function testRejectsDuplicateMigrationVersions(): void
    {
        $mongo = $this->createMock(MongoConnectionManagerInterface::class);
        $store = new InMemoryMigrationStateStore();

        $migrator = new Migrator($mongo, $store);

        $this->expectException(InvalidArgumentException::class);
        $migrator->migrate([
            new FakeMigration('20260521143000'),
            new FakeMigration('20260521143000'),
        ]);
    }
}

final class InMemoryMigrationStateStore implements MigrationStateStoreInterface
{
    /**
     * @param list<string> $versions
     */
    public function __construct(
        private array $versions = [],
    ) {
    }

    public function appliedVersions(string $connection = 'default'): array
    {
        return $this->versions;
    }

    public function markApplied(MigrationInterface $migration, string $connection = 'default'): void
    {
        if (!in_array($migration->version(), $this->versions, true)) {
            $this->versions[] = $migration->version();
            sort($this->versions);
        }
    }

    public function markRolledBack(MigrationInterface $migration, string $connection = 'default'): void
    {
        $this->versions = array_values(array_filter(
            $this->versions,
            static fn (string $version): bool => $version !== $migration->version(),
        ));
    }
}

final class FakeMigration implements MigrationInterface
{
    public int $upCalls   = 0;
    public int $downCalls = 0;

    public function __construct(
        private readonly string $version,
    ) {
    }

    public function version(): string
    {
        return $this->version;
    }

    public function description(): string
    {
        return '';
    }

    public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
    {
        $this->upCalls++;
    }

    public function down(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void
    {
        $this->downCalls++;
    }
}
