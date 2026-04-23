<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Query;

use InvalidArgumentException;
use PhpSoftBox\MongoDb\Query\PipelineBuilder;
use PHPUnit\Framework\TestCase;

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
            ['$sort'    => ['created_at' => -1]],
            ['$skip'    => 20],
            ['$limit'   => 10],
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
}
