<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Bson;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

use function date_default_timezone_get;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Преобразование дат между PHP и BSON.
 *
 * Даты хранятся в MongoDB нативным `UTCDateTime` (момент времени в UTC с точностью до миллисекунд): так работают
 * диапазонные запросы, сортировка и TTL-индексы независимо от смещения, в котором дата была создана.
 */
final class DateTimeConverter
{
    public static function toUtcDateTime(DateTimeInterface $value): UTCDateTime
    {
        return new UTCDateTime($value);
    }

    /**
     * Рекурсивно заменяет `DateTimeInterface` на `UTCDateTime` в значении или массиве (фильтр, update, стадия
     * pipeline, документ). Остальные значения, включая BSON-объекты, возвращаются без изменений.
     */
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return self::toUtcDateTime($value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalize($item);
            }

            return $normalized;
        }

        return $value;
    }

    /**
     * Приводит значение из документа к `DateTimeImmutable` в таймзоне `$timezone`
     * (по умолчанию — `date_default_timezone_get()`).
     *
     * Поддерживаются `UTCDateTime`, `DateTimeInterface` и строки (в т.ч. ATOM-строки, которые пакет писал до 1.0).
     * Строка без смещения трактуется в таймзоне `$timezone`.
     *
     * @throws InvalidArgumentException
     */
    public static function toDateTime(mixed $value, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        $timezone ??= new DateTimeZone(date_default_timezone_get());

        if ($value instanceof UTCDateTime) {
            return $value->toDateTimeImmutable()->setTimezone($timezone);
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone($timezone);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value, $timezone)->setTimezone($timezone);
        }

        throw new InvalidArgumentException(sprintf(
            'Mongo date value must be UTCDateTime, DateTimeInterface or string, %s given.',
            get_debug_type($value),
        ));
    }
}
