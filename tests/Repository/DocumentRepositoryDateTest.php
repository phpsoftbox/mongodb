<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Repository;

use DateTimeImmutable;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\MongoDb\Configurator\MongoFactory;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManager;
use PhpSoftBox\MongoDb\Document\DocumentHydrator;
use PhpSoftBox\MongoDb\Query\PipelineBuilder;
use PhpSoftBox\MongoDb\Query\QueryBuilder;
use PhpSoftBox\MongoDb\Repository\DocumentRepository;
use PhpSoftBox\MongoDb\Tests\Repository\Fixture\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function sprintf;
use function uniqid;

use const DATE_ATOM;

/**
 * Интеграционные тесты хранения дат на реальном MongoDB (сервис `mongo` из docker-compose).
 */
#[CoversClass(DocumentRepository::class)]
#[CoversClass(DocumentHydrator::class)]
#[CoversMethod(DocumentRepository::class, 'insertOne')]
#[CoversMethod(DocumentRepository::class, 'findMany')]
#[CoversMethod(DocumentRepository::class, 'findOne')]
#[CoversMethod(DocumentRepository::class, 'aggregate')]
#[CoversMethod(DocumentRepository::class, 'updateMany')]
final class DocumentRepositoryDateTest extends TestCase
{
    private const array FIELD_MAP = [
        'id'         => '_id',
        'occurredAt' => 'occurred_at',
    ];

    private MongoConnectionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new MongoConnectionManager(new MongoFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'uri'      => 'mongodb://mongo:27017',
                    'database' => sprintf('mongo_date_test_%s', uniqid()),
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        $this->manager->database()->drop();
    }

    /**
     * Проверим, что диапазонный запрос и сортировка по датам, записанным с разными смещениями, идут по моменту
     * времени, а не по строке.
     *
     * @see DocumentRepository::insertOne()
     * @see DocumentRepository::findMany()
     * @see QueryBuilder::whereGte()
     * @see QueryBuilder::whereLt()
     */
    #[Test]
    public function rangeQueryComparesMomentsAcrossOffsets(): void
    {
        $repo = $this->repository();

        // 07:00Z, 08:00Z, 07:30Z, 10:00Z — строкой ATOM порядок был бы другим.
        $repo->insertOne(Event::at('a', '2026-04-23T10:00:00+03:00'));
        $repo->insertOne(Event::at('b', '2026-04-23T08:00:00+00:00'));
        $repo->insertOne(Event::at('c', '2026-04-23T12:30:00+05:00'));
        $repo->insertOne(Event::at('d', '2026-04-23T05:00:00-05:00'));

        // Границы тоже в разных смещениях: [07:15Z, 09:00Z).
        $query = new QueryBuilder()
            ->whereGte('occurred_at', new DateTimeImmutable('2026-04-23T10:15:00+03:00'))
            ->whereLt('occurred_at', new DateTimeImmutable('2026-04-23T04:00:00-05:00'))
            ->sort(['occurred_at' => 1]);

        $events = $repo->findMany($query->buildFilter(), $query->buildFindOptions());

        $this->assertSame(['c', 'b'], array_map(static fn (Event $event): string => $event->id, $events->all()));
    }

    /**
     * Проверим, что даты в фильтрах PipelineBuilder сравниваются с датами документов.
     *
     * @see DocumentRepository::aggregate()
     * @see PipelineBuilder::match()
     */
    #[Test]
    public function aggregateMatchesDatesFromPipelineBuilder(): void
    {
        $repo = $this->repository();

        $repo->insertOne(Event::at('a', '2026-04-23T10:00:00+03:00'));
        $repo->insertOne(Event::at('b', '2026-04-23T08:00:00+00:00'));

        $pipeline = new PipelineBuilder()
            ->match(['occurred_at' => ['$gt' => new DateTimeImmutable('2026-04-23T10:30:00+03:00')]])
            ->build();

        $events = $repo->aggregate($pipeline);

        $this->assertSame(['b'], array_map(static fn (Event $event): string => $event->id, $events->all()));
    }

    /**
     * Проверим, что дата хранится нативным BSON-типом date с миллисекундами.
     *
     * @see DocumentRepository::insertOne()
     */
    #[Test]
    public function insertStoresNativeUtcDateTime(): void
    {
        $repo = $this->repository();

        $repo->insertOne(Event::at('a', '2026-04-23T10:00:00.123456+03:00'));

        $raw = $this->manager->collection('events')->findOne(['_id' => 'a']);

        $this->assertInstanceOf(UTCDateTime::class, $raw['occurred_at']);
        $this->assertSame(
            '2026-04-23T07:00:00.123+00:00',
            $raw['occurred_at']->toDateTimeImmutable()->format('Y-m-d\TH:i:s.vP'),
        );
    }

    /**
     * Проверим обратную совместимость чтения: документ со строкой ATOM (формат до 1.0) гидрируется в дату.
     *
     * @see DocumentRepository::findOne()
     */
    #[Test]
    public function legacyAtomStringIsHydrated(): void
    {
        $this->manager->collection('events')->insertOne(['_id' => 'old', 'occurred_at' => '2026-04-23T10:00:00+03:00']);

        $event = $this->repository()->findOne(['_id' => 'old']);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('2026-04-23T07:00:00+00:00', $event->occurredAt->format(DATE_ATOM));
    }

    /**
     * Проверим update-пайплайн миграции из README: строки ATOM превращаются в даты и начинают попадать в диапазон.
     *
     * @see DocumentRepository::updateMany()
     * @see DocumentRepository::findMany()
     */
    #[Test]
    public function migrationPipelineConvertsLegacyStrings(): void
    {
        $collection = $this->manager->collection('events');
        $collection->insertOne(['_id' => 'a', 'occurred_at' => '2026-04-23T10:00:00+03:00']);
        $collection->insertOne(['_id' => 'b', 'occurred_at' => '2026-04-23T08:00:00+00:00']);

        $repo = $this->repository();

        // Пайплайн из раздела README «Миграция существующих документов».
        $repo->updateMany(
            ['occurred_at' => ['$type' => 'string']],
            [['$set' => ['occurred_at' => ['$dateFromString' => ['dateString' => '$occurred_at']]]]],
        );

        $query = new QueryBuilder()
            ->whereGte('occurred_at', new DateTimeImmutable('2026-04-23T07:30:00+00:00'))
            ->sort(['occurred_at' => 1]);

        $events = $repo->findMany($query->buildFilter(), $query->buildFindOptions());

        $this->assertSame(['b'], array_map(static fn (Event $event): string => $event->id, $events->all()));
        $this->assertSame(0, $repo->count(['occurred_at' => ['$type' => 'string']]));
    }

    /**
     * @return DocumentRepository<Event>
     */
    private function repository(): DocumentRepository
    {
        return new DocumentRepository(
            mongo: $this->manager,
            collection: 'events',
            documentClass: Event::class,
            hydrator: new DocumentHydrator(new DateTimeZone('UTC')),
            fieldMap: self::FIELD_MAP,
        );
    }
}
