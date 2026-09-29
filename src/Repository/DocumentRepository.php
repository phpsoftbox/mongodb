<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Repository;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use PhpSoftBox\MongoDb\Bson\DateTimeConverter;
use PhpSoftBox\MongoDb\Collection\DocumentCollection;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Document\DocumentHydrator;
use PhpSoftBox\MongoDb\Document\DocumentHydratorInterface;

use function is_array;

/**
 * Даты пишутся как `UTCDateTime`: `DateTimeInterface` в фильтрах, update, pipeline и документах-массивах приводится
 * автоматически. При чтении `UTCDateTime` становится `DateTimeImmutable` в `date_default_timezone_get()`
 * (для typed-документов — в таймзоне гидратора).
 *
 * @template TDocument of array<string, mixed>|object
 */
final class DocumentRepository
{
    public function __construct(
        private readonly MongoConnectionManagerInterface $mongo,
        private readonly string $collection,
        private readonly string $connection = 'default',
        private readonly ?string $documentClass = null,
        private readonly DocumentHydratorInterface $hydrator = new DocumentHydrator(),
        /**
         * @var array<string, string>
         */
        private readonly array $fieldMap = [],
    ) {
    }

    public function collection(): Collection
    {
        return $this->mongo->collection($this->collection, $this->connection);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @return TDocument|null
     */
    public function findOne(array $filter = [], array $options = []): mixed
    {
        $document = $this->collection()->findOne($this->bson($filter), $options);
        if ($document === null) {
            return null;
        }

        /** @var TDocument */
        return $this->fromDocument($this->normalizeDocument($document));
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @return DocumentCollection<TDocument>
     */
    public function findMany(array $filter = [], array $options = []): DocumentCollection
    {
        $cursor = $this->collection()->find($this->bson($filter), $options);
        $items  = [];
        foreach ($cursor as $document) {
            $items[] = $this->fromDocument($this->normalizeDocument($document));
        }

        return DocumentCollection::from($items);
    }

    /**
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed> $options
     * @return DocumentCollection<TDocument>
     */
    public function aggregate(array $pipeline, array $options = []): DocumentCollection
    {
        $cursor = $this->collection()->aggregate($this->bson($pipeline), $options);
        $items  = [];
        foreach ($cursor as $document) {
            $items[] = $this->fromDocument($this->normalizeDocument($document));
        }

        return DocumentCollection::from($items);
    }

    /**
     * @param array<string, mixed>|object $document
     */
    public function insertOne(array|object $document, array $options = []): mixed
    {
        return $this->collection()->insertOne($this->toDocument($document), $options);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed>|object $document
     */
    public function replaceOne(array $filter, array|object $document, array $options = []): mixed
    {
        return $this->collection()->replaceOne($this->bson($filter), $this->toDocument($document), $options);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed>|object $document
     */
    public function upsertOne(array $filter, array|object $document, array $options = []): mixed
    {
        return $this->replaceOne($filter, $document, ['upsert' => true, ...$options]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     */
    public function updateOne(array $filter, array $update, array $options = []): mixed
    {
        return $this->collection()->updateOne($this->bson($filter), $this->bson($update), $options);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     */
    public function updateMany(array $filter, array $update, array $options = []): mixed
    {
        return $this->collection()->updateMany($this->bson($filter), $this->bson($update), $options);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function deleteOne(array $filter, array $options = []): mixed
    {
        return $this->collection()->deleteOne($this->bson($filter), $options);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function deleteMany(array $filter, array $options = []): mixed
    {
        return $this->collection()->deleteMany($this->bson($filter), $options);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function count(array $filter = [], array $options = []): int
    {
        return $this->collection()->countDocuments($this->bson($filter), $options);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function exists(array $filter): bool
    {
        return $this->count($filter, ['limit' => 1]) > 0;
    }

    /**
     * @param array<string, mixed>|object|BSONDocument|BSONArray $document
     * @return array<string, mixed>
     */
    private function normalizeDocument(mixed $document): array
    {
        if ($document instanceof BSONDocument || $document instanceof BSONArray) {
            /** @var array<string, mixed> $array */
            $array      = $document->getArrayCopy();
            $normalized = [];
            foreach ($array as $key => $value) {
                $normalized[$key] = $this->normalizeValue($value);
            }

            return $normalized;
        }

        if (is_array($document)) {
            $normalized = [];
            foreach ($document as $key => $value) {
                $normalized[$key] = $this->normalizeValue($value);
            }

            return $normalized;
        }

        return $this->normalizeDocument((array) $document);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            return $this->normalizeDocument($value);
        }

        if ($value instanceof UTCDateTime) {
            return DateTimeConverter::toDateTime($value);
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

    /**
     * Приводит `DateTimeInterface` в фильтре, update, pipeline или документе-массиве к `UTCDateTime`.
     *
     * @template T of array
     * @param T $value
     * @return T
     */
    private function bson(array $value): array
    {
        /** @var T */
        return DateTimeConverter::normalize($value);
    }

    /**
     * @param array<string, mixed>|object $document
     * @return array<string, mixed>
     */
    private function toDocument(array|object $document): array
    {
        if (is_array($document)) {
            return $this->bson($document);
        }

        return $this->hydrator->extract($document, $this->fieldMap);
    }

    /**
     * @param array<string, mixed> $document
     * @return TDocument
     */
    private function fromDocument(array $document): mixed
    {
        if ($this->documentClass === null) {
            return $document;
        }

        return $this->hydrator->hydrate($this->documentClass, $document, $this->fieldMap);
    }
}
