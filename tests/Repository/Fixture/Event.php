<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Repository\Fixture;

use DateTimeImmutable;

final class Event
{
    public string $id;
    public DateTimeImmutable $occurredAt;

    public static function at(string $id, string $occurredAt): self
    {
        $event = new self();

        $event->id         = $id;
        $event->occurredAt = new DateTimeImmutable($occurredAt);

        return $event;
    }
}
