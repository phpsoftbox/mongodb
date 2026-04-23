<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Repository;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use PhpSoftBox\MongoDb\Collection\DocumentCollection;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MongoDb\Document\DocumentHydrator;
use PhpSoftBox\MongoDb\Document\DocumentHydratorInterface;

use function is_array;

/**
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
        $document = $this->collection()->findOne($filter, $options);
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
        $cursor = $this->collection()->find($filter, $options);
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
        $cursor = $this->collection()->aggregate($pipeline, $options);
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
        return $this->collection()->replaceOne($filter, $this->toDocument($document), $options);
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
        return $this->collection()->updateOne($filter, $update, $options);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     */
    public function updateMany(array $filter, array $update, array $options = []): mixed
    {
        return $this->collection()->updateMany($filter, $update, $options);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function deleteOne(array $filter, array $options = []): mixed
    {
        return $this->collection()->deleteOne($filter, $options);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function deleteMany(array $filter, array $options = []): mixed
    {
        return $this->collection()->deleteMany($filter, $options);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function count(array $filter = [], array $options = []): int
    {
        return $this->collection()->countDocuments($filter, $options);
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
            return $value->toDateTimeImmutable();
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
     * @param array<string, mixed>|object $document
     * @return array<string, mixed>
     */
    private function toDocument(array|object $document): array
    {
        if (is_array($document)) {
            return $document;
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
