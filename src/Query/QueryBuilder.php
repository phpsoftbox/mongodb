<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Query;

use DateTimeInterface;
use InvalidArgumentException;
use PhpSoftBox\MongoDb\Bson\DateTimeConverter;

use function array_values;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Lightweight query builder for MongoDB find/aggregate operations.
 *
 * `DateTimeInterface` в условиях и стадиях приводится к `UTCDateTime`, чтобы сравнение шло с датами в документах.
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

        /** @var array<string, mixed> $filter */
        $filter = DateTimeConverter::normalize($filter);

        if ($this->filter === []) {
            $this->filter = $filter;

            return $this;
        }

        $this->filter = [
            '$and' => [$this->filter, $filter],
        ];

        return $this;
    }

    /**
     * Условие равенства через `$eq`: массив из пользовательского ввода (`['$ne' => '']`) сравнивается как значение,
     * а не разворачивается в операторы.
     */
    public function whereEq(string $field, mixed $value): self
    {
        return $this->whereOperator($field, '$eq', $value);
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

    public function whereGt(string $field, int|float|DateTimeInterface $value): self
    {
        return $this->whereOperator($field, '$gt', $value);
    }

    public function whereGte(string $field, int|float|DateTimeInterface $value): self
    {
        return $this->whereOperator($field, '$gte', $value);
    }

    public function whereLt(string $field, int|float|DateTimeInterface $value): self
    {
        return $this->whereOperator($field, '$lt', $value);
    }

    public function whereLte(string $field, int|float|DateTimeInterface $value): self
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

        /** @var array<string, mixed> $stage */
        $stage = DateTimeConverter::normalize($stage);

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

    private function whereOperator(string $field, string $operator, mixed $value): self
    {
        $field = trim($field);
        if ($field === '') {
            throw new InvalidArgumentException('Mongo query field must be non-empty string.');
        }

        // Имя поля с `$` — оператор верхнего уровня (`$where`, `$expr`), а не поле документа.
        if (str_starts_with($field, '$')) {
            throw new InvalidArgumentException(sprintf('Mongo query field must not start with "$": %s.', $field));
        }

        return $this->where([$field => [$operator => $value]]);
    }
}
