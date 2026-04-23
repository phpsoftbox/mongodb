<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;

use function is_string;
use function sprintf;
use function trim;

final class MongoMigrationStateStore implements MigrationStateStoreInterface
{
    public function __construct(
        private readonly MongoConnectionManagerInterface $mongo,
        private readonly string $collection = '_migrations',
    ) {
    }

    public function appliedVersions(string $connection = 'default'): array
    {
        $collection = $this->migrationCollection($connection);
        $cursor     = $collection->find(
            [],
            ['projection' => ['version' => 1], 'sort' => ['version' => 1]],
        );

        $versions = [];
        foreach ($cursor as $item) {
            $version = $item['version'] ?? null;
            if (is_string($version) && $version !== '') {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    public function markApplied(MigrationInterface $migration, string $connection = 'default'): void
    {
        $collection = $this->migrationCollection($connection);
        $collection->replaceOne(
            ['version' => $migration->version()],
            [
                'version'     => $migration->version(),
                'description' => $migration->description(),
                'applied_at'  => new UTCDateTime(),
            ],
            ['upsert' => true],
        );
    }

    public function markRolledBack(MigrationInterface $migration, string $connection = 'default'): void
    {
        $collection = $this->migrationCollection($connection);
        $collection->deleteOne(['version' => $migration->version()]);
    }

    private function migrationCollection(string $connection): Collection
    {
        $collection = trim($this->collection);
        if ($collection === '') {
            throw new InvalidArgumentException('Mongo migrations collection name must be non-empty string.');
        }

        $mongoCollection = $this->mongo->collection($collection, $connection);
        $mongoCollection->createIndex(['version' => 1], [
            'name'   => sprintf('%s_version_unique', $collection),
            'unique' => true,
        ]);

        return $mongoCollection;
    }
}
