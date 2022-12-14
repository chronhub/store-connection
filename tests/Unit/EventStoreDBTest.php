<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Chronhub\Testing\ProphecyTest;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Contracts\Message\Header;
use Chronhub\Contracts\Stream\Factory;
use Chronhub\Testing\Double\SomeEvent;
use Chronhub\Testing\Stubs\StreamStub;
use Illuminate\Database\Query\Builder;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Testing\Stubs\StreamNameStub;
use Chronhub\Contracts\Stream\StreamCategory;
use Chronhub\Contracts\Chronicler\QueryFilter;
use Chronhub\Contracts\Store\StreamPersistence;
use Chronhub\Contracts\Store\WriteLockStrategy;
use Chronhub\Contracts\Store\EventLoaderConnection;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Store\Connection\Tests\Stub\ReadEventStoreDBStub;
use function iterator_to_array;

/**
 * @coversDefaultClass \Chronhub\Store\Connection\EventStoreDB
 */
final class EventStoreDBTest extends ProphecyTest
{
    /**
     * @test
     */
    public function it_can_be_constructed(): void
    {
        $es = $this->eventStore();

        $this->assertFalse($es->isDuringCreation());
        $this->assertSame($this->eventStreamProvider->reveal(), $es->getEventStreamProvider());
    }

    /**
     * @test
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events_with_single_stream_strategy(string $direction): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();
        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $this->streamPersistence->isAutoIncremented()->willReturn(false)->shouldBeCalledOnce();

        $builder->orderBy('no', $direction)->willReturn($builder)->shouldBeCalledOnce();

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());
        $this->eventLoader->query($builder->reveal(), $this->streamName)->willYield($expectedStreamsEvents)->shouldBeCalledOnce();

        $aggregateId = $this->prophesize(Identity::class);
        $aggregateId->toString()->willReturn('123-456')->shouldNotBeCalled();

        $events = $this->eventStore()->retrieveAll($this->streamName, $aggregateId->reveal(), $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    /**
     * @test
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events_with_one_stream_per_aggregate_strategy(string $direction): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();
        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $this->streamPersistence->isAutoIncremented()->willReturn(true)->shouldBeCalledOnce();

        $builder->where('aggregate_id', '123-456')->willReturn($builder)->shouldBeCalledOnce();
        $builder->orderBy('no', $direction)->willReturn($builder)->shouldBeCalledOnce();

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());
        $this->eventLoader->query($builder->reveal(), $this->streamName)->willYield($expectedStreamsEvents)->shouldBeCalledOnce();

        $aggregateId = $this->prophesize(Identity::class);
        $aggregateId->toString()->willReturn('123-456')->shouldBeCalledOnce();

        $events = $this->eventStore()->retrieveAll($this->streamName, $aggregateId->reveal(), $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    /**
     * @test
     */
    public function it_retrieve_filtered_stream_events(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();
        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $this->streamPersistence->isAutoIncremented()->shouldNotBeCalled();

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());
        $this->eventLoader->query($builder->reveal(), $this->streamName)->willYield($expectedStreamsEvents)->shouldBeCalledOnce();

        $callback = function (Builder $query) use ($builder): void {
            $this->assertSame($query, $builder->reveal());
        };

        $queryFilter = $this->prophesize(QueryFilter::class);
        $queryFilter->filter()->willReturn($callback)->shouldBeCalledOnce();

        $events = $this->eventStore()->retrieveFiltered($this->streamName, $queryFilter->reveal());

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    /**
     * @test
     */
    public function it_filter_stream_names(): void
    {
        $streams = [new StreamNameStub('foo'), new StreamNameStub('bar'), new StreamNameStub('foo_bar')];

        $this->streamFactory->__invoke('foo')->willReturn(new StreamStub(new StreamNameStub('foo')))->shouldBeCalledOnce();
        $this->streamFactory->__invoke('bar')->willReturn(new StreamStub(new StreamNameStub('bar')))->shouldBeCalledOnce();

        $this->eventStreamProvider->filterByStreams($streams)->willReturn(['foo', 'bar'])->shouldBeCalledOnce();

        $filteredStreams = $this->eventStore()->filterStreamNames(...$streams);

        $this->assertEquals([$streams[0], $streams[1]], $filteredStreams);
    }

    /**
     * @test
     */
    public function it_filter_categories(): void
    {
        $categories = ['foo', 'bar', 'foo_bar'];

        $this->eventStreamProvider->filterByCategories($categories)->willReturn(['foo', 'bar'])->shouldBeCalledOnce();

        $filteredStreams = $this->eventStore()->filterCategoryNames(...$categories);

        $this->assertEquals([$categories[0], $categories[1]], $filteredStreams);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_check_stream_exists(bool $streamExists): void
    {
        $this->eventStreamProvider->hasRealStreamName($this->streamName->name())->willReturn($streamExists)->shouldBeCalledOnce();

        $this->assertEquals($streamExists, $this->eventStore()->hasStream($this->streamName));
    }

    /**
     * @test
     */
    public function it_assert_read_query_builder(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->table($tableName)->willReturn($builder->reveal())->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore()->getBuilderforRead($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }

    /**
     * @test
     */
    public function it_assert_serialized_stream_events(): void
    {
        $streamsEvents = [
            SomeEvent::fromContent(['foo' => 'bar']),
            SomeEvent::fromContent(['foo' => 'bar']),
        ];

        $eventAsArray = [
            'headers' => [
                Header::EVENT_TYPE => SomeEvent::class,
            ],
            'content' => ['foo' => 'bar'],
        ];

        $this->streamPersistence->serializeEvent($streamsEvents[0])->willReturn($eventAsArray)->shouldBeCalledTimes(2);
        $this->streamPersistence->serializeEvent($streamsEvents[1])->willReturn($eventAsArray)->shouldBeCalledTimes(2);

        $events = $this->eventStore()->getStreamEventsSerialized($streamsEvents);

        $this->assertEquals([$eventAsArray, $eventAsArray], $events);
    }

    public function provideDirection(): Generator
    {
        yield ['asc'];
        yield ['desc'];
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }

    private function provideStreamEvents(): Generator
    {
        yield[SomeEvent::fromContent(['foo' => 'bar'])];
        yield[SomeEvent::fromContent(['foo' => 'bar'])];
        yield[SomeEvent::fromContent(['foo' => 'bar'])];
        yield[SomeEvent::fromContent(['foo' => 'bar'])];

        return 4;
    }

    private Connection|ObjectProphecy $connection;

    private StreamPersistence|ObjectProphecy $streamPersistence;

    private EventLoaderConnection|ObjectProphecy $eventLoader;

    private EventStreamProvider|ObjectProphecy $eventStreamProvider;

    private Factory|ObjectProphecy $streamFactory;

    private StreamCategory|ObjectProphecy $streamCategory;

    private WriteLockStrategy|ObjectProphecy $writeLock;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->connection = $this->prophesize(Connection::class);
        $this->streamPersistence = $this->prophesize(StreamPersistence::class);
        $this->eventLoader = $this->prophesize(EventLoaderConnection::class);
        $this->eventStreamProvider = $this->prophesize(EventStreamProvider::class);
        $this->streamFactory = $this->prophesize(Factory::class);
        $this->streamCategory = $this->prophesize(StreamCategory::class);
        $this->writeLock = $this->prophesize(WriteLockStrategy::class);
        $this->streamName = new StreamNameStub('customer');
    }

    private function eventStore(): ReadEventStoreDBStub
    {
        return new ReadEventStoreDBStub(
            $this->connection->reveal(),
            $this->streamPersistence->reveal(),
            $this->eventLoader->reveal(),
            $this->eventStreamProvider->reveal(),
            $this->streamFactory->reveal(),
            $this->streamCategory->reveal(),
            $this->writeLock->reveal(),
        );
    }
}
