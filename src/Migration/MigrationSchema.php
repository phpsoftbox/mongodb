<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use MongoDB\Collection;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Exception\ConfigurationException;

use function in_array;
use function iterator_to_array;
use function trim;

final readonly class MigrationSchema
{
    public function __construct(
        private MongoConnectionManagerInterface $mongo,
        private string $connection = 'default',
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createCollection(string $name, array $options = []): Collection
    {
        $name = $this->normalizeCollectionName($name);
        $db   = $this->mongo->database($this->connection);

        if (!$this->hasCollection($name)) {
            $db->createCollection($name, $options);
        }

        return $db->selectCollection($name);
    }

    public function dropCollection(string $name): void
    {
        $name = $this->normalizeCollectionName($name);
        if (!$this->hasCollection($name)) {
            return;
        }

        $this->mongo->database($this->connection)->dropCollection($name);
    }

    /**
     * @param array<string, int|string> $keys
     * @param array<string, mixed> $options
     */
    public function ensureIndex(string $collection, array $keys, array $options = []): string
    {
        return $this->collection($collection)->createIndex($keys, $options);
    }

    public function dropIndex(string $collection, string $name): void
    {
        $this->collection($collection)->dropIndex($name);
    }

    public function dropIndexes(string $collection): void
    {
        $this->collection($collection)->dropIndexes();
    }

    public function collection(string $name): Collection
    {
        return $this->mongo->collection($this->normalizeCollectionName($name), $this->connection);
    }

    public function hasCollection(string $name): bool
    {
        $name  = $this->normalizeCollectionName($name);
        $names = iterator_to_array(
            $this->mongo->database($this->connection)->listCollectionNames(),
            false,
        );

        return in_array($name, $names, true);
    }

    private function normalizeCollectionName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new ConfigurationException('Mongo collection name must be non-empty string.');
        }

        return $name;
    }
}
