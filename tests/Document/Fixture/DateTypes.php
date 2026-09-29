<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Document\Fixture;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

final class DateTypes
{
    public DateTimeImmutable $immutable;
    public DateTime $mutable;
    public DateTimeInterface $interface;
    public CustomDateTime $custom;
    public ?DateTimeImmutable $nullable = null;
}
