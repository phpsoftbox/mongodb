<?php

declare(strict_types=1);

namespace PhpSoftBox\MongoDb\Tests\Document;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\MongoDb\Document\DocumentHydrator;
use PhpSoftBox\MongoDb\Tests\Document\Fixture\CustomDateTime;
use PhpSoftBox\MongoDb\Tests\Document\Fixture\DateTypes;
use PhpSoftBox\MongoDb\Tests\Document\Fixture\Product;
use PhpSoftBox\MongoDb\Tests\Document\Fixture\ProductStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;

use const DATE_ATOM;

#[CoversClass(DocumentHydrator::class)]
#[CoversMethod(DocumentHydrator::class, 'hydrate')]
#[CoversMethod(DocumentHydrator::class, 'hydrateMany')]
#[CoversMethod(DocumentHydrator::class, 'extract')]
#[CoversMethod(DocumentHydrator::class, 'extractMany')]
final class DocumentHydratorTest extends TestCase
{
    private const array FIELD_MAP = [
        'id'        => '_id',
        'createdAt' => 'created_at',
    ];

    /**
     * Проверим hydrate/extract c field-map и enum: поля переименовываются, enum пишется значением.
     *
     * @see DocumentHydrator::hydrate()
     * @see DocumentHydrator::extract()
     */
    #[Test]
    public function hydrateAndExtractWithFieldMap(): void
    {
        $hydrator = new DocumentHydrator();

        $document = $hydrator->hydrate(Product::class, [
            '_id'        => '507f1f77bcf86cd799439011',
            'name'       => 'Demo',
            'status'     => 'active',
            'created_at' => new UTCDateTime(new DateTimeImmutable('2026-04-23T09:15:00+00:00')),
        ], self::FIELD_MAP);

        $this->assertInstanceOf(Product::class, $document);
        $this->assertSame('507f1f77bcf86cd799439011', $document->id);
        $this->assertSame('Demo', $document->name);
        $this->assertSame(ProductStatus::Active, $document->status);

        $extracted = $hydrator->extract($document, self::FIELD_MAP);

        $this->assertSame('507f1f77bcf86cd799439011', $extracted['_id']);
        $this->assertSame('active', $extracted['status']);
        $this->assertArrayHasKey('created_at', $extracted);
    }

    /**
     * Проверим пакетные операции hydrateMany/extractMany.
     *
     * @see DocumentHydrator::hydrateMany()
     * @see DocumentHydrator::extractMany()
     */
    #[Test]
    public function hydrateManyAndExtractMany(): void
    {
        $hydrator = new DocumentHydrator();

        $items = $hydrator->hydrateMany(Product::class, [
            ['id' => '1', 'name' => 'One', 'status' => 'active', 'createdAt' => '2026-04-23T10:00:00+00:00'],
            ['id' => '2', 'name' => 'Two', 'status' => 'inactive', 'createdAt' => '2026-04-23T10:01:00+00:00'],
        ]);

        $this->assertCount(2, $items);
        $this->assertSame('One', $items->all()[0]->name);
        $this->assertSame('inactive', $items->all()[1]->status->value);

        $extracted = $hydrator->extractMany($items->all());

        $this->assertCount(2, $extracted);
        $this->assertSame('Two', $extracted->all()[1]['name']);
    }

    /**
     * Проверим, что extract пишет дату как UTCDateTime с миллисекундами, а не строкой ATOM.
     *
     * @see DocumentHydrator::extract()
     */
    #[Test]
    public function extractWritesDateAsUtcDateTimeWithMilliseconds(): void
    {
        $product = new Product();

        $product->id        = 'p1';
        $product->name      = 'Demo';
        $product->status    = ProductStatus::Active;
        $product->createdAt = new DateTimeImmutable('2026-04-23T12:15:00.123456+03:00');

        $extracted = new DocumentHydrator()->extract($product, self::FIELD_MAP);

        $this->assertInstanceOf(UTCDateTime::class, $extracted['created_at']);
        // Момент сохраняется в UTC, точность — миллисекунды.
        $this->assertSame(
            '2026-04-23T09:15:00.123+00:00',
            $extracted['created_at']->toDateTimeImmutable()->format('Y-m-d\TH:i:s.vP'),
        );
    }

    /**
     * Проверим, что DateTime, DateTimeInterface и наследник DateTimeImmutable пишутся как UTCDateTime.
     *
     * @see DocumentHydrator::extract()
     */
    #[Test]
    public function extractWritesAllDateTypesAsUtcDateTime(): void
    {
        $document = new DateTypes();

        $document->immutable = new DateTimeImmutable('2026-04-23T10:00:00+00:00');

        $document->mutable = new DateTime('2026-04-23T10:00:00+00:00');

        $document->interface = new DateTimeImmutable('2026-04-23T10:00:00+00:00');

        $document->custom = new CustomDateTime('2026-04-23T10:00:00+00:00');

        $extracted = new DocumentHydrator()->extract($document);

        $this->assertInstanceOf(UTCDateTime::class, $extracted['immutable']);
        $this->assertInstanceOf(UTCDateTime::class, $extracted['mutable']);
        $this->assertInstanceOf(UTCDateTime::class, $extracted['interface']);
        $this->assertInstanceOf(UTCDateTime::class, $extracted['custom']);
        $this->assertNull($extracted['nullable']);
    }

    /**
     * Проверим, что UTCDateTime читается в таймзоне, заданной гидратору.
     *
     * @see DocumentHydrator::hydrate()
     */
    #[Test]
    public function hydrateReadsUtcDateTimeInConfiguredTimezone(): void
    {
        $hydrator = new DocumentHydrator(new DateTimeZone('Europe/Moscow'));

        $document = $hydrator->hydrate(Product::class, [
            'created_at' => new UTCDateTime(new DateTimeImmutable('2026-04-23T09:15:00.123+00:00')),
        ], self::FIELD_MAP);

        $this->assertSame('2026-04-23T12:15:00.123+03:00', $document->createdAt->format('Y-m-d\TH:i:s.vP'));
        $this->assertSame('Europe/Moscow', $document->createdAt->getTimezone()->getName());
    }

    /**
     * Проверим, что без явной таймзоны дата читается в date_default_timezone_get().
     *
     * @see DocumentHydrator::hydrate()
     */
    #[Test]
    public function hydrateUsesDefaultTimezoneWhenNotConfigured(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Yekaterinburg');

        try {
            $document = new DocumentHydrator()->hydrate(Product::class, [
                'created_at' => new UTCDateTime(new DateTimeImmutable('2026-04-23T09:15:00+00:00')),
            ], self::FIELD_MAP);
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame('Asia/Yekaterinburg', $document->createdAt->getTimezone()->getName());
        $this->assertSame('2026-04-23T14:15:00+05:00', $document->createdAt->format(DATE_ATOM));
    }

    /**
     * Проверим обратную совместимость чтения: строка ATOM (формат до 1.0) превращается в тот же момент времени.
     *
     * @see DocumentHydrator::hydrate()
     */
    #[Test]
    public function hydrateReadsLegacyAtomString(): void
    {
        $hydrator = new DocumentHydrator(new DateTimeZone('UTC'));

        $document = $hydrator->hydrate(Product::class, [
            'created_at' => '2026-04-23T12:15:00+03:00',
        ], self::FIELD_MAP);

        $this->assertSame('2026-04-23T09:15:00+00:00', $document->createdAt->format(DATE_ATOM));
    }

    /**
     * Проверим гидрацию в свойства DateTime, DateTimeInterface, наследника DateTimeImmutable и nullable-даты.
     *
     * @see DocumentHydrator::hydrate()
     */
    #[Test]
    public function hydrateCastsToDeclaredDateType(): void
    {
        $date = new UTCDateTime(new DateTimeImmutable('2026-04-23T09:15:00+00:00'));

        $document = new DocumentHydrator(new DateTimeZone('UTC'))->hydrate(DateTypes::class, [
            'immutable' => $date,
            'mutable'   => $date,
            'interface' => $date,
            'custom'    => '2026-04-23T12:15:00+03:00',
            'nullable'  => null,
        ]);

        $this->assertInstanceOf(DateTimeImmutable::class, $document->immutable);
        $this->assertInstanceOf(DateTime::class, $document->mutable);
        $this->assertInstanceOf(DateTimeImmutable::class, $document->interface);
        $this->assertInstanceOf(CustomDateTime::class, $document->custom);
        $this->assertSame('2026-04-23T09:15:00+00:00', $document->custom->format(DATE_ATOM));
        $this->assertNull($document->nullable);
    }
}
