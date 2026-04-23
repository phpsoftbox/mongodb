<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Document;

use PhpSoftBox\MongoDb\Collection\DocumentCollection;

interface DocumentHydratorInterface
{
    /**
     * @param class-string $documentClass
     * @param array<string, mixed> $data
     * @param array<string, string> $fieldMap
     */
    public function hydrate(string $documentClass, array $data, array $fieldMap = []): object;

    /**
     * @param class-string $documentClass
     * @param iterable<array<string, mixed>> $documents
     * @param array<string, string> $fieldMap
     * @return DocumentCollection<object>
     */
    public function hydrateMany(string $documentClass, iterable $documents, array $fieldMap = []): DocumentCollection;

    /**
     * @param array<string, string> $fieldMap
     * @return array<string, mixed>
     */
    public function extract(object $document, array $fieldMap = []): array;

    /**
     * @param iterable<object> $documents
     * @param array<string, string> $fieldMap
     * @return DocumentCollection<array<string, mixed>>
     */
    public function extractMany(iterable $documents, array $fieldMap = []): DocumentCollection;
}
