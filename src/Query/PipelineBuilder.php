<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Query;

use InvalidArgumentException;

use function array_values;
use function is_string;
use function trim;

final class PipelineBuilder
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $stages = [];

    /**
     * @param array<string, mixed> $match
     */
    public function match(array $match): self
    {
        return $this->stage(['$match' => $match]);
    }

    /**
     * @param array<string, mixed> $project
     */
    public function project(array $project): self
    {
        return $this->stage(['$project' => $project]);
    }

    /**
     * @param array<string, mixed> $group
     */
    public function group(array $group): self
    {
        return $this->stage(['$group' => $group]);
    }

    /**
     * @param array<string, int> $sort
     */
    public function sort(array $sort): self
    {
        return $this->stage(['$sort' => $sort]);
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Mongo pipeline limit must be >= 0.');
        }

        return $this->stage(['$limit' => $limit]);
    }

    public function skip(int $skip): self
    {
        if ($skip < 0) {
            throw new InvalidArgumentException('Mongo pipeline skip must be >= 0.');
        }

        return $this->stage(['$skip' => $skip]);
    }

    /**
     * @param string|array<string, mixed> $unwind
     */
    public function unwind(string|array $unwind): self
    {
        if (is_string($unwind)) {
            $path = trim($unwind);
            if ($path === '') {
                throw new InvalidArgumentException('Mongo pipeline unwind path must be non-empty string.');
            }

            return $this->stage(['$unwind' => $path]);
        }

        return $this->stage(['$unwind' => $unwind]);
    }

    /**
     * @param array<string, mixed> $lookup
     */
    public function lookup(array $lookup): self
    {
        return $this->stage(['$lookup' => $lookup]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function addFields(array $fields): self
    {
        return $this->stage(['$addFields' => $fields]);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $facets
     */
    public function facet(array $facets): self
    {
        return $this->stage(['$facet' => $facets]);
    }

    /**
     * @param array<string, mixed> $stage
     */
    public function stage(array $stage): self
    {
        if ($stage === []) {
            throw new InvalidArgumentException('Mongo pipeline stage must not be empty.');
        }

        $this->stages[] = $stage;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $stages
     */
    public function stages(array $stages): self
    {
        foreach (array_values($stages) as $stage) {
            $this->stage($stage);
        }

        return $this;
    }

    public function clear(): self
    {
        $this->stages = [];

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(): array
    {
        return $this->stages;
    }
}
