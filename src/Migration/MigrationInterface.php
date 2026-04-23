<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;

interface MigrationInterface
{
    public function version(): string;

    public function description(): string;

    public function up(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void;

    public function down(MongoConnectionManagerInterface $mongo, string $connection = 'default'): void;
}
