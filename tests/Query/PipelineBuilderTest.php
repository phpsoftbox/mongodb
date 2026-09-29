<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Query;

use DateTimeImmutable;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\MongoDb\Query\PipelineBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

#[CoversClass(PipelineBuilder::class)]
#[CoversMethod(PipelineBuilder::class, 'match')]
#[CoversMethod(PipelineBuilder::class, 'stage')]
final class PipelineBuilderTest extends TestCase
{
    /**
     * Проверяет сборку pipeline через специализированные stage-методы.
     */
    public function testBuildsPipeline(): void
    {
        $pipeline = new PipelineBuilder()
            ->match(['company_id' => 10])
            ->sort(['created_at' => -1])
            ->skip(20)
            ->limit(10)
            ->project(['_id' => 1, 'name' => 1])
            ->build();

        $this->assertSame([
            ['$match' => ['company_id' => 10]],
            ['$sort' => ['created_at' => -1]],
            ['$skip' => 20],
            ['$limit' => 10],
            ['$project' => ['_id' => 1, 'name' => 1]],
        ], $pipeline);
    }

    /**
     * Проверяет stages() и clear().
     */
    public function testStagesAndClear(): void
    {
        $builder = new PipelineBuilder()
            ->stages([
                ['$match' => ['status' => 'new']],
                ['$limit' => 5],
            ]);

        $this->assertCount(2, $builder->build());

        $builder->clear();
        $this->assertSame([], $builder->build());
    }

    /**
     * Проверяет валидацию limit/skip и пустых stage/unwind.
     */
    public function testRejectsInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PipelineBuilder()->limit(-1);
    }

    /**
     * Проверяет валидацию пустого stage.
     */
    public function testRejectsEmptyStage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PipelineBuilder()->stage([]);
    }

    /**
     * Проверяет валидацию пустого unwind-пути.
     */
    public function testRejectsEmptyUnwindPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PipelineBuilder()->unwind('   ');
    }

    /**
     * Проверим, что даты в стадиях pipeline приводятся к UTCDateTime в UTC.
     *
     * @see PipelineBuilder::match()
     * @see PipelineBuilder::stage()
     * @see PipelineBuilder::build()
     */
    #[Test]
    public function matchConvertsDatesToUtcDateTime(): void
    {
        $pipeline = new PipelineBuilder()
            ->match(['created_at' => ['$gte' => new DateTimeImmutable('2026-04-23T12:00:00+03:00')]])
            ->build();

        $from = $pipeline[0]['$match']['created_at']['$gte'];

        $this->assertInstanceOf(UTCDateTime::class, $from);
        $this->assertSame('2026-04-23T09:00:00+00:00', $from->toDateTimeImmutable()->format(DATE_ATOM));
    }
}
