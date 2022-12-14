<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Functional;

use Generator;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Stream\GenericStreamName;
use Illuminate\Support\Facades\Schema;
use Chronhub\Store\Connection\Tests\OrchestraTest;
use Chronhub\Store\Connection\Tests\Double\SomeEvent;
use Chronhub\Contracts\Support\Serializer\StreamEventConverter;
use Chronhub\Store\Connection\Persistence\PerAggregateStreamPersistence;

final class PerAggregateStreamPersistenceTest extends OrchestraTest
{
    use ProphecyTrait;

    private StreamEventConverter|ObjectProphecy $eventConverter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventConverter = $this->prophesize(StreamEventConverter::class);
    }

    /**
     * @test
     * @dataProvider provideStreamName
     */
    public function it_produce_table_name_from_stream_name(string $streamName): void
    {
        $expectedTableName = '_'.$streamName;

        $streamPersistence = $this->newInstance();

        $tableName = $streamPersistence->tableName(new GenericStreamName($streamName));

        $this->assertEquals($expectedTableName, $tableName);

        $this->assertNull($streamPersistence->indexName($tableName));
    }

    /**
     * @test
     * @dataProvider provideStreamName
     */
    public function it_up_stream_table(string $streamName): void
    {
        $tableName = '_'.$streamName;

        $streamPersistence = $this->newInstance();

        $this->assertNull($streamPersistence->up($tableName));

        $this->assertTrue(Schema::hasTable($tableName));

        $this->assertTrue(Schema::hasColumns($tableName, [
            'no', 'event_id', 'event_type', 'content', 'headers',
            'aggregate_id', 'aggregate_type', 'aggregate_version',
            'created_at',
        ]));

        $this->assertEquals('integer', Schema::getColumnType($tableName, 'no'));
        $this->assertEquals('string', Schema::getColumnType($tableName, 'event_id'));
        $this->assertEquals('text', Schema::getColumnType($tableName, 'content'));
        $this->assertEquals('text', Schema::getColumnType($tableName, 'headers'));
        $this->assertEquals('string', Schema::getColumnType($tableName, 'aggregate_id'));
        $this->assertEquals('string', Schema::getColumnType($tableName, 'aggregate_type'));
        $this->assertEquals('integer', Schema::getColumnType($tableName, 'aggregate_version'));
        $this->assertEquals('datetime', Schema::getColumnType($tableName, 'created_at'));

        $doctrine = Schema::getConnection()->getDoctrineSchemaManager();

        $indexes = $doctrine->listTableIndexes($tableName);

        $this->assertArrayHasKey($tableName.'_aggregate_version_unique', $indexes);
    }

    /**
     * @test
     */
    public function it_assert_true_is_support_one_stream_per_aggregate(): void
    {
        $this->assertFalse($this->newInstance()->isAutoIncremented());
    }

    /**
     * @test
     */
    public function it_serialize_event(): void
    {
        $isAutoIncremented = false;

        $event = SomeEvent::fromContent(['foo' => 'bar']);
        $convertedEvent = ['headers' => [], 'content' => ['foo' => 'bar']];

        $this->eventConverter->toArray($event, $isAutoIncremented)->willReturn($convertedEvent)->shouldBeCalledOnce();

        $this->assertEquals($convertedEvent, $this->newInstance($this->eventConverter->reveal())->serializeEvent($event));
    }

    private function newInstance(?StreamEventConverter $eventConverter = null): PerAggregateStreamPersistence
    {
        return new PerAggregateStreamPersistence($eventConverter ?? $this->eventConverter->reveal());
    }

    public function provideStreamName(): Generator
    {
        yield ['foo'];
        yield ['foo_bar'];
    }
}
