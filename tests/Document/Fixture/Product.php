<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Document\Fixture;

use DateTimeImmutable;

final class Product
{
    public string $id;
    public string $name;
    public ProductStatus $status;
    public DateTimeImmutable $createdAt;
}
