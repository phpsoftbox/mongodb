<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use InvalidArgumentException;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;

use function array_key_exists;
use function count;
use function in_array;
use function krsort;
use function ksort;
use function max;

final class Migrator
{
    private const string VERSION_KEY_PREFIX = 'version:';

    public function __construct(
        private readonly MongoConnectionManagerInterface $mongo,
        private readonly MigrationStateStoreInterface $stateStore,
    ) {
    }

    /**
     * @param list<MigrationInterface> $migrations
     * @return list<string>
     */
    public function migrate(array $migrations, string $connection = 'default'): array
    {
        $registry = $this->indexedByVersion($migrations);
        ksort($registry);

        $applied  = $this->stateStore->appliedVersions($connection);
        $executed = [];

        foreach ($registry as $migration) {
            $version = $migration->version();
            if (in_array($version, $applied, true)) {
                continue;
            }

            $migration->up($this->mongo, $connection);
            $this->stateStore->markApplied($migration, $connection);
            $executed[] = $version;
        }

        return $executed;
    }

    /**
     * @param list<MigrationInterface> $migrations
     * @return list<string>
     */
    public function rollback(array $migrations, int $steps = 1, string $connection = 'default'): array
    {
        $steps = max($steps, 0);
        if ($steps === 0) {
            return [];
        }

        $registry = $this->indexedByVersion($migrations);
        krsort($registry);

        $applied  = $this->stateStore->appliedVersions($connection);
        $executed = [];

        foreach ($registry as $migration) {
            $version = $migration->version();
            if (!in_array($version, $applied, true)) {
                continue;
            }

            $migration->down($this->mongo, $connection);
            $this->stateStore->markRolledBack($migration, $connection);
            $executed[] = $version;

            if (count($executed) >= $steps) {
                break;
            }
        }

        return $executed;
    }

    /**
     * @param list<MigrationInterface> $migrations
     * @return array<string, MigrationInterface>
     */
    private function indexedByVersion(array $migrations): array
    {
        $indexed = [];
        foreach ($migrations as $migration) {
            $version = $migration->version();
            $key     = self::VERSION_KEY_PREFIX . $version;
            if (array_key_exists($key, $indexed)) {
                throw new InvalidArgumentException('Duplicate mongo migration version: ' . $version);
            }

            $indexed[$key] = $migration;
        }

        return $indexed;
    }
}
