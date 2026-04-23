<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Collection;

use PhpSoftBox\Collection\Collection;

/**
 * @template TDocument of array<string, mixed>|object
 * @extends Collection<int, TDocument>
 */
final class DocumentCollection extends Collection
{
    /**
     * @param list<TDocument> $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * @param list<TDocument> $items
     */
    public static function from(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<TDocument>
     */
    public function all(): array
    {
        /** @var list<TDocument> $items */
        $items = parent::all();

        return $items;
    }
}
