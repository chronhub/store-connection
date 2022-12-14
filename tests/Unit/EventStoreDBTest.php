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
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Testing\Stubs\StreamNameStub;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Contracts\Stream\StreamCategory;
use Chronhub\Contracts\Store\StreamPersistence;
use Chronhub\Contracts\Store\WriteLockStrategy;
use Chronhub\Contracts\Store\EventLoaderConnection;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Store\Connection\Tests\Stub\EventStoreDBStub;

abstract class EventStoreDBTest extends ProphecyTest
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

    protected function provideStreamEvents(): Generator
    {
        yield[SomeEvent::fromContent(['foo' => 'bar'])];
        yield[SomeEvent::fromContent(['foo' => 'bar'])];
        yield[SomeEvent::fromContent(['foo' => 'bar'])];
        yield[SomeEvent::fromContent(['foo' => 'bar'])];

        return 4;
    }

    protected function eventStore(?WriteLockStrategy $writeLock = null): EventStoreDBStub
    {
        return new EventStoreDBStub(
            $this->connection->reveal(),
            $this->streamPersistence->reveal(),
            $this->eventLoader->reveal(),
            $this->eventStreamProvider->reveal(),
            $this->streamFactory->reveal(),
            $this->streamCategory->reveal(),
            $writeLock ?? $this->writeLock->reveal(),
        );
    }

    protected Connection|ConnectionInterface|ObjectProphecy $connection;

    protected StreamPersistence|ObjectProphecy $streamPersistence;

    protected EventLoaderConnection|ObjectProphecy $eventLoader;

    protected EventStreamProvider|ObjectProphecy $eventStreamProvider;

    protected Factory|ObjectProphecy $streamFactory;

    protected StreamCategory|ObjectProphecy $streamCategory;

    protected WriteLockStrategy|ObjectProphecy $writeLock;

    protected StreamName $streamName;

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
}
