<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use PhpSoftBox\MongoDb\Exception\ConfigurationException;

use function array_key_first;
use function is_string;
use function rtrim;
use function trim;

final class MigrationsConfig
{
    private string $basePath;
    private string $defaultConnection;

    /**
     * @var array<string, string>
     */
    private array $paths = [];

    /**
     * @param array<string, string> $paths
     */
    public function __construct(string $basePath, array $paths = [], ?string $defaultConnection = null)
    {
        $basePath = trim(rtrim($basePath, '/'));
        if ($basePath === '') {
            throw new ConfigurationException('Mongo migrations base path is required.');
        }

        $this->basePath = $basePath;

        foreach ($paths as $connection => $path) {
            if (!is_string($connection) || $connection === '') {
                continue;
            }

            if (!is_string($path) || $path === '') {
                throw new ConfigurationException('Mongo migration path for connection "' . $connection . '" must be string.');
            }

            $this->paths[$connection] = rtrim($path, '/');
        }

        if ($defaultConnection === null || $defaultConnection === '') {
            $defaultConnection = $this->paths !== [] ? (string) array_key_first($this->paths) : 'default';
        }

        $this->defaultConnection = $defaultConnection;
    }

    public function defaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * @return list<string>
     */
    public function paths(string $connection): array
    {
        if ($connection === '') {
            throw new ConfigurationException('Mongo migration connection name is required.');
        }

        $path = $this->paths[$connection] ?? ($this->basePath . '/' . $connection);

        return [$path];
    }
}
