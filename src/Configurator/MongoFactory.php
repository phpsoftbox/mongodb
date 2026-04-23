<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Configurator;

use MongoDB\Client;
use MongoDB\Database;
use PhpSoftBox\MongoDb\Exception\ConfigurationException;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

final readonly class MongoFactory implements MongoFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
    ) {
    }

    public function createClient(string $connection = 'default'): Client
    {
        $resolved = $this->resolveConnectionConfig($connection);

        $uri = trim((string) ($resolved['uri'] ?? $resolved['dsn'] ?? ''));
        if ($uri === '') {
            throw new ConfigurationException(sprintf(
                'Mongo connection "%s" must contain non-empty "uri" or "dsn".',
                $connection,
            ));
        }

        $uriOptions = $resolved['uri_options'] ?? $resolved['uriOptions'] ?? [];
        if (!is_array($uriOptions)) {
            throw new ConfigurationException(sprintf(
                'Mongo connection "%s" option "uri_options" must be an array.',
                $connection,
            ));
        }

        $driverOptions = $resolved['driver_options'] ?? $resolved['driverOptions'] ?? [];
        if (!is_array($driverOptions)) {
            throw new ConfigurationException(sprintf(
                'Mongo connection "%s" option "driver_options" must be an array.',
                $connection,
            ));
        }

        return new Client($uri, $uriOptions, $driverOptions);
    }

    public function createDatabase(string $connection = 'default'): Database
    {
        $resolved     = $this->resolveConnectionConfig($connection);
        $databaseName = trim((string) ($resolved['database'] ?? $resolved['db'] ?? $resolved['database_name'] ?? ''));
        if ($databaseName === '') {
            throw new ConfigurationException(sprintf(
                'Mongo connection "%s" must contain non-empty "database".',
                $connection,
            ));
        }

        $databaseOptions = $resolved['database_options'] ?? $resolved['databaseOptions'] ?? [];
        if (!is_array($databaseOptions)) {
            throw new ConfigurationException(sprintf(
                'Mongo connection "%s" option "database_options" must be an array.',
                $connection,
            ));
        }

        return $this->createClient($connection)->selectDatabase($databaseName, $databaseOptions);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConnectionConfig(string $connection): array
    {
        $connections = $this->config['connections'] ?? null;
        if (!is_array($connections)) {
            $connections = $this->fallbackToSingleConnection();
        }

        if (!array_key_exists('default', $connections) || !is_string($connections['default'])) {
            throw new ConfigurationException('Mongo config must contain "connections.default" string.');
        }

        if ($connection === 'default') {
            $connection = $connections['default'];
        }

        $connConfig = $connections[$connection] ?? null;
        if (!is_array($connConfig)) {
            throw new ConfigurationException(sprintf('Unknown mongo connection "%s".', $connection));
        }

        return $connConfig;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackToSingleConnection(): array
    {
        $uri      = $this->config['uri'] ?? $this->config['dsn'] ?? null;
        $database = $this->config['database'] ?? $this->config['db'] ?? null;

        if (!is_string($uri) || trim($uri) === '' || !is_string($database) || trim($database) === '') {
            throw new ConfigurationException('Mongo config must contain "connections" array or root-level "uri"+"database".');
        }

        return [
            'default' => 'main',
            'main'    => $this->config,
        ];
    }
}
