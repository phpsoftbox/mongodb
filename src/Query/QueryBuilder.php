<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Query;

use InvalidArgumentException;

use function array_values;
use function trim;

/**
 * Lightweight query builder for MongoDB find/aggregate operations.
 */
final class QueryBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $filter = [];

    /**
     * @var array<string, int>
     */
    private array $projection = [];

    /**
     * @var array<string, int>
     */
    private array $sort = [];

    private ?int $limit = null;
    private ?int $skip  = null;

    /**
     * @var list<array<string, mixed>>
     */
    private array $customStages = [];

    /**
     * @param array<string, mixed> $filter
     */
    public function where(array $filter): self
    {
        if ($filter === []) {
            return $this;
        }

        if ($this->filter === []) {
            $this->filter = $filter;

            return $this;
        }

        $this->filter = [
            '$and' => [$this->filter, $filter],
        ];

        return $this;
    }

    public function whereEq(string $field, mixed $value): self
    {
        return $this->whereOperator($field, null, $value);
    }

    public function whereNe(string $field, mixed $value): self
    {
        return $this->whereOperator($field, '$ne', $value);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $field, array $values): self
    {
        return $this->whereOperator($field, '$in', array_values($values));
    }

    public function whereGt(string $field, int|float $value): self
    {
        return $this->whereOperator($field, '$gt', $value);
    }

    public function whereGte(string $field, int|float $value): self
    {
        return $this->whereOperator($field, '$gte', $value);
    }

    public function whereLt(string $field, int|float $value): self
    {
        return $this->whereOperator($field, '$lt', $value);
    }

    public function whereLte(string $field, int|float $value): self
    {
        return $this->whereOperator($field, '$lte', $value);
    }

    /**
     * @param array<string, int> $projection
     */
    public function project(array $projection): self
    {
        $this->projection = $projection;

        return $this;
    }

    /**
     * @param array<string, int> $sort
     */
    public function sort(array $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Mongo query limit must be >= 0.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function skip(int $skip): self
    {
        if ($skip < 0) {
            throw new InvalidArgumentException('Mongo query skip must be >= 0.');
        }

        $this->skip = $skip;

        return $this;
    }

    /**
     * @param array<string, mixed> $stage
     */
    public function stage(array $stage): self
    {
        if ($stage === []) {
            return $this;
        }

        $this->customStages[] = $stage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFilter(): array
    {
        return $this->filter;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFindOptions(): array
    {
        $options = [];

        if ($this->projection !== []) {
            $options['projection'] = $this->projection;
        }

        if ($this->sort !== []) {
            $options['sort'] = $this->sort;
        }

        if ($this->limit !== null) {
            $options['limit'] = $this->limit;
        }

        if ($this->skip !== null) {
            $options['skip'] = $this->skip;
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildAggregatePipeline(): array
    {
        $pipeline = [];

        if ($this->filter !== []) {
            $pipeline[] = ['$match' => $this->filter];
        }

        if ($this->sort !== []) {
            $pipeline[] = ['$sort' => $this->sort];
        }

        if ($this->skip !== null) {
            $pipeline[] = ['$skip' => $this->skip];
        }

        if ($this->limit !== null) {
            $pipeline[] = ['$limit' => $this->limit];
        }

        if ($this->projection !== []) {
            $pipeline[] = ['$project' => $this->projection];
        }

        foreach ($this->customStages as $stage) {
            $pipeline[] = $stage;
        }

        return $pipeline;
    }

    private function whereOperator(string $field, ?string $operator, mixed $value): self
    {
        $field = trim($field);
        if ($field === '') {
            throw new InvalidArgumentException('Mongo query field must be non-empty string.');
        }

        if ($operator === null) {
            return $this->where([$field => $value]);
        }

        return $this->where([$field => [$operator => $value]]);
    }
}
