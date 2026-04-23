<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Connection;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use PhpSoftBox\MongoDb\Configurator\MongoFactoryInterface;
use PhpSoftBox\MongoDb\Exception\ConfigurationException;

use function trim;

final class MongoConnectionManager implements MongoConnectionManagerInterface
{
    /**
     * @var array<string, Client>
     */
    private array $clients = [];

    /**
     * @var array<string, Database>
     */
    private array $databases = [];

    public function __construct(
        private readonly MongoFactoryInterface $factory,
    ) {
    }

    public function client(string $name = 'default'): Client
    {
        if (!isset($this->clients[$name])) {
            $this->clients[$name] = $this->factory->createClient($name);
        }

        return $this->clients[$name];
    }

    public function database(string $name = 'default'): Database
    {
        if (!isset($this->databases[$name])) {
            $this->databases[$name] = $this->factory->createDatabase($name);
        }

        return $this->databases[$name];
    }

    public function collection(string $collection, string $connection = 'default'): Collection
    {
        $collection = trim($collection);
        if ($collection === '') {
            throw new ConfigurationException('Mongo collection name must be non-empty string.');
        }

        return $this->database($connection)->selectCollection($collection);
    }

    public function reconnect(string $name = 'default'): Database
    {
        unset($this->clients[$name], $this->databases[$name]);

        return $this->database($name);
    }
}
