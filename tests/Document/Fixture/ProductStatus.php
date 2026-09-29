<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Document\Fixture;

enum ProductStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}
