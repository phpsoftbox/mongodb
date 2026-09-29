<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Bson;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\MongoDb\Bson\DateTimeConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

#[CoversClass(DateTimeConverter::class)]
#[CoversMethod(DateTimeConverter::class, 'toUtcDateTime')]
#[CoversMethod(DateTimeConverter::class, 'normalize')]
#[CoversMethod(DateTimeConverter::class, 'toDateTime')]
final class DateTimeConverterTest extends TestCase
{
    /**
     * Проверим, что normalize рекурсивно заменяет даты на UTCDateTime и не трогает остальные значения.
     *
     * @see DateTimeConverter::normalize()
     */
    #[Test]
    public function normalizeReplacesNestedDates(): void
    {
        $objectId = new ObjectId();

        $normalized = DateTimeConverter::normalize([
            '_id'        => $objectId,
            'created_at' => ['$gte' => new DateTimeImmutable('2026-04-23T12:00:00+03:00')],
            'name'       => 'Demo',
        ]);

        $this->assertSame($objectId, $normalized['_id']);
        $this->assertSame('Demo', $normalized['name']);
        $this->assertInstanceOf(UTCDateTime::class, $normalized['created_at']['$gte']);
        $this->assertSame('1776934800000', (string) $normalized['created_at']['$gte']);
    }

    /**
     * Проверим, что строка без смещения трактуется в переданной таймзоне.
     *
     * @see DateTimeConverter::toDateTime()
     */
    #[Test]
    public function toDateTimeParsesStringWithoutOffsetInTimezone(): void
    {
        $date = DateTimeConverter::toDateTime('2026-04-23 12:00:00', new DateTimeZone('Europe/Moscow'));

        $this->assertSame('2026-04-23T12:00:00+03:00', $date->format(DATE_ATOM));
    }

    /**
     * Проверим, что значение неподдерживаемого типа отклоняется.
     *
     * @see DateTimeConverter::toDateTime()
     */
    #[Test]
    public function toDateTimeRejectsUnsupportedValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateTimeConverter::toDateTime(1776934800);
    }
}
