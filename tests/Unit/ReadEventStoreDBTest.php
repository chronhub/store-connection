<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Chronhub\Testing\Stubs\StreamStub;
use Illuminate\Database\Query\Builder;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Testing\Stubs\StreamNameStub;
use Chronhub\Contracts\Chronicler\QueryFilter;
use function iterator_to_array;

/**
 * @coversDefaultClass \Chronhub\Store\Connection\EventStoreDB
 */
final class ReadEventStoreDBTest extends EventStoreDBTest
{
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
    public function it_assert_read_query_builder_with_index(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn('some_index')->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->query()->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->fromRaw("`$tableName` USE INDEX(some_index)")->willReturn($builder->reveal())->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore()->getBuilderforRead($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }
}
