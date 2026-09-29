<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Document\Fixture;

use DateTimeImmutable;

/**
 * Наследник DateTimeImmutable (как DatePoint из phpsoftbox/clock).
 */
final class CustomDateTime extends DateTimeImmutable
{
}
