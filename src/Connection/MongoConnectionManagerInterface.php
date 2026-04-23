<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Connection;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;

interface MongoConnectionManagerInterface
{
    public function client(string $name = 'default'): Client;

    public function database(string $name = 'default'): Database;

    public function collection(string $collection, string $connection = 'default'): Collection;
}
