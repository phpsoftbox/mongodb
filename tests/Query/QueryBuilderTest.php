<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Query;

use DateTimeImmutable;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\MongoDb\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

#[CoversClass(QueryBuilder::class)]
#[CoversMethod(QueryBuilder::class, 'whereEq')]
#[CoversMethod(QueryBuilder::class, 'whereGte')]
#[CoversMethod(QueryBuilder::class, 'whereLt')]
#[CoversMethod(QueryBuilder::class, 'where')]
#[CoversMethod(QueryBuilder::class, 'stage')]
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

    /**
     * Проверим, что даты в диапазонных условиях приводятся к UTCDateTime в UTC (с учётом смещения).
     *
     * @see QueryBuilder::whereGte()
     * @see QueryBuilder::whereLt()
     * @see QueryBuilder::buildFilter()
     */
    #[Test]
    public function whereRangeConvertsDatesToUtcDateTime(): void
    {
        $filter = new QueryBuilder()
            ->whereGte('created_at', new DateTimeImmutable('2026-04-23T12:00:00+03:00'))
            ->whereLt('created_at', new DateTimeImmutable('2026-04-23T10:00:00+00:00'))
            ->buildFilter();

        $from = $filter['$and'][0]['created_at']['$gte'];
        $to   = $filter['$and'][1]['created_at']['$lt'];

        $this->assertInstanceOf(UTCDateTime::class, $from);
        $this->assertInstanceOf(UTCDateTime::class, $to);
        $this->assertSame('2026-04-23T09:00:00+00:00', $from->toDateTimeImmutable()->format(DATE_ATOM));
        $this->assertSame('2026-04-23T10:00:00+00:00', $to->toDateTimeImmutable()->format(DATE_ATOM));
    }

    /**
     * Проверим, что даты в фильтре where() и в дополнительной стадии приводятся к UTCDateTime.
     *
     * @see QueryBuilder::where()
     * @see QueryBuilder::stage()
     * @see QueryBuilder::buildAggregatePipeline()
     */
    #[Test]
    public function whereAndStageConvertNestedDates(): void
    {
        $date = new DateTimeImmutable('2026-04-23T09:00:00+00:00');

        $pipeline = new QueryBuilder()
            ->where(['created_at' => ['$in' => [$date]]])
            ->stage(['$match' => ['updated_at' => ['$gt' => $date]]])
            ->buildAggregatePipeline();

        $this->assertInstanceOf(UTCDateTime::class, $pipeline[0]['$match']['created_at']['$in'][0]);
        $this->assertInstanceOf(UTCDateTime::class, $pipeline[1]['$match']['updated_at']['$gt']);
    }
}
