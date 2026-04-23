<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;

abstract class AbstractMigration implements MigrationInterface
{
    final public function __construct(
        private readonly string $version,
        private readonly string $description = '',
    ) {
    }

    final public function version(): string
    {
        return $this->version;
    }

    final public function description(): string
    {
        return $this->description;
    }

    final protected function schema(
        MongoConnectionManagerInterface $mongo,
        string $connection = 'default',
    ): MigrationSchema {
        return new MigrationSchema($mongo, $connection);
    }

    abstract public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void;

    abstract public function down(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void;
}
