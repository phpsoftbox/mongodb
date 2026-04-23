<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Configurator;

use MongoDB\Client;
use MongoDB\Database;

interface MongoFactoryInterface
{
    public function createClient(string $connection = 'default'): Client;

    public function createDatabase(string $connection = 'default'): Database;
}
