<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Migration;

interface MigrationStateStoreInterface
{
    /**
     * @return list<string>
     */
    public function appliedVersions(string $connection = 'default'): array;

    public function markApplied(MigrationInterface $migration, string $connection = 'default'): void;

    public function markRolledBack(MigrationInterface $migration, string $connection = 'default'): void;
}
