<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Query;

use InvalidArgumentException;
use PhpSoftBox\MongoDb\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    /**
     * Проверяет сборку filter/options для find-запросов.
     */
    public function testBuildsFindFilterAndOptions(): void
    {
        $query = new QueryBuilder()
            ->whereEq('company_id', 10)
            ->whereIn('status', ['new', 'done'])
            ->sort(['created_at' => -1])
            ->project(['_id' => 1, 'name' => 1])
            ->limit(25)
            ->skip(50);

        $this->assertSame([
            '$and' => [
                ['company_id' => 10],
                ['status' => ['$in' => ['new', 'done']]],
            ],
        ], $query->buildFilter());

        $this->assertSame([
            'projection' => ['_id' => 1, 'name' => 1],
            'sort'       => ['created_at' => -1],
            'limit'      => 25,
            'skip'       => 50,
        ], $query->buildFindOptions());
    }

    /**
     * Проверяет сборку aggregate pipeline.
     */
    public function testBuildsAggregatePipeline(): void
    {
        $query = new QueryBuilder()
            ->whereGte('price', 100)
            ->sort(['price' => -1])
            ->limit(10)
            ->stage(['$group' => ['_id' => '$brand', 'total' => ['$sum' => 1]]]);

        $this->assertSame([
            ['$match' => ['price' => ['$gte' => 100]]],
            ['$sort'  => ['price' => -1]],
            ['$limit' => 10],
            ['$group' => ['_id' => '$brand', 'total' => ['$sum' => 1]]],
        ], $query->buildAggregatePipeline());
    }

    /**
     * Проверяет валидацию limit/skip.
     */
    public function testRejectsNegativeLimitAndSkip(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QueryBuilder()->limit(-1);
    }

    /**
     * Проверяет валидацию пустого имени поля.
     */
    public function testRejectsEmptyFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QueryBuilder()->whereEq('   ', 1);
    }
}
