<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Document;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PhpSoftBox\MongoDb\Bson\DateTimeConverter;
use PhpSoftBox\MongoDb\Collection\DocumentCollection;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

use function array_key_exists;
use function get_object_vars;
use function is_a;
use function is_array;
use function is_subclass_of;
use function method_exists;

/**
 * Даты (`DateTimeInterface`, в т.ч. `DatePoint`) записываются как `UTCDateTime` с точностью до миллисекунд.
 * При чтении свойство с типом даты получает значение из `UTCDateTime` или строки (старый формат ATOM)
 * в таймзоне `$timezone`; без неё — в `date_default_timezone_get()` на момент гидрации.
 */
final class DocumentHydrator implements DocumentHydratorInterface
{
    public function __construct(
        private readonly ?DateTimeZone $timezone = null,
    ) {
    }

    public function hydrate(string $documentClass, array $data, array $fieldMap = []): object
    {
        $reflection = new ReflectionClass($documentClass);

        $document = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $fieldName    = $fieldMap[$propertyName] ?? $propertyName;
            if (!array_key_exists($fieldName, $data)) {
                continue;
            }

            $property->setValue($document, $this->castToPropertyType($property, $data[$fieldName]));
        }

        return $document;
    }

    public function hydrateMany(string $documentClass, iterable $documents, array $fieldMap = []): DocumentCollection
    {
        $items = [];
        foreach ($documents as $document) {
            $items[] = $this->hydrate($documentClass, $document, $fieldMap);
        }

        return DocumentCollection::from($items);
    }

    public function extract(object $document, array $fieldMap = []): array
    {
        $result = [];
        foreach (get_object_vars($document) as $property => $value) {
            $fieldName          = $fieldMap[$property] ?? $property;
            $result[$fieldName] = $this->normalizeValue($value);
        }

        return $result;
    }

    public function extractMany(iterable $documents, array $fieldMap = []): DocumentCollection
    {
        $items = [];
        foreach ($documents as $document) {
            $items[] = $this->extract($document, $fieldMap);
        }

        return DocumentCollection::from($items);
    }

    private function castToPropertyType(ReflectionProperty $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();
        if ($type->isBuiltin()) {
            return match ($typeName) {
                'int'    => (int) $value,
                'float'  => (float) $value,
                'string' => (string) $value,
                'bool'   => (bool) $value,
                'array'  => is_array($value) ? $value : [$value],
                default  => $value,
            };
        }

        if (is_a($typeName, DateTimeInterface::class, true)) {
            return $this->castToDateTime($typeName, $value);
        }

        if ($value instanceof $typeName) {
            return $value;
        }

        if (is_subclass_of($typeName, BackedEnum::class) && method_exists($typeName, 'from')) {
            /** @var class-string<BackedEnum> $typeName */
            return $typeName::from($value);
        }

        return $value;
    }

    /**
     * @param class-string<DateTimeInterface> $typeName
     */
    private function castToDateTime(string $typeName, mixed $value): DateTimeInterface
    {
        $dateTime = DateTimeConverter::toDateTime($value, $this->timezone);

        if ($typeName === DateTimeInterface::class || $typeName === DateTimeImmutable::class) {
            return $dateTime;
        }

        // DateTime, DatePoint и другие наследники: createFromInterface() возвращает экземпляр вызванного класса.
        if (is_a($typeName, DateTimeImmutable::class, true) || is_a($typeName, DateTime::class, true)) {
            return $typeName::createFromInterface($dateTime);
        }

        return $dateTime;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeConverter::toUtcDateTime($value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        return $value;
    }
}
