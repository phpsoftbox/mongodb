<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Query;

use InvalidArgumentException;
use PhpSoftBox\MongoDb\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBuilder::class)]
#[CoversMethod(QueryBuilder::class, 'whereEq')]
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
                ['company_id' => ['$eq' => 10]],
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
            ['$sort' => ['price' => -1]],
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

    /**
     * Проверяет, что массив с операторами из пользовательского ввода в whereEq остаётся значением под `$eq`.
     *
     * @see QueryBuilder::whereEq()
     * @see QueryBuilder::buildFilter()
     */
    #[Test]
    public function whereEqKeepsOperatorArrayAsValue(): void
    {
        $query = new QueryBuilder()->whereEq('token', ['$ne' => '']);

        $this->assertSame(['token' => ['$eq' => ['$ne' => '']]], $query->buildFilter());
    }

    /**
     * Проверяет, что имя поля с `$` (оператор верхнего уровня вроде `$where`) отклоняется.
     *
     * @see QueryBuilder::whereEq()
     */
    #[Test]
    public function rejectsOperatorAsFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueryBuilder()->whereEq('$where', 'sleep(1000)');
    }
}
