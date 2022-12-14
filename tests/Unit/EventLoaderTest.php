<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use stdClass;
use Illuminate\Support\Collection;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Stream\GenericStreamName;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Store\Connection\Loader\EventLoader;
use Chronhub\Store\Connection\Tests\ProphecyTest;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Store\Connection\Tests\Double\SomeEvent;
use Chronhub\Store\Connection\Tests\Stub\QueryExceptionStub;
use Chronhub\Contracts\Support\Serializer\StreamEventConverter;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;

final class EventLoaderTest extends ProphecyTest
{
    private StreamEventConverter|ObjectProphecy $eventConverter;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->eventConverter = $this->prophesize(StreamEventConverter::class);
        $this->streamName = new GenericStreamName('customer');
    }

    /**
     * @test
     */
    public function it_yield_domain_events(): void
    {
        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'stephbug'];
        $event->no = 5;

        $streamEvents = new Collection([$event]);

        $expectedEvent = SomeEvent::fromContent(['name' => 'stephbug'])->withHeader('some', 'header');
        $this->eventConverter->toDomainEvent($event)->willReturn($expectedEvent)->shouldBeCalledOnce();

        $eventLoader = new EventLoader($this->eventConverter->reveal());

        $generator = $eventLoader($streamEvents, $this->streamName);

        foreach ($generator as $domainEvent) {
            $this->assertEquals($expectedEvent, $domainEvent);
        }
    }

    /**
     * @test
     */
    public function it_raise_exception_when_no_stream_event_has_been_yield(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->eventConverter->toDomainEvent([])->shouldNotBeCalled();

        $eventLoader = new EventLoader($this->eventConverter->reveal());

        $eventLoader(new Collection([]), $this->streamName)->current();
    }

    /**
     * @test
     */
    public function it_raise_exception_when_stream_name_not_found_in_database(): void
    {
        $this->expectException(StreamNotFound::class);

        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'stephbug'];
        $event->no = 5;

        $streamEvents = new Collection([$event, $event]);

        $queryException = QueryExceptionStub::withCode('1234');

        $expectedEvent = SomeEvent::fromContent(['name' => 'stephbug'])->withHeader('some', 'header');

        $this->eventConverter->toDomainEvent($event)->willReturn($expectedEvent)->shouldBeCalledTimes(2);

        $this->eventConverter->toDomainEvent($event)
            ->willThrow($queryException)
            ->shouldBeCalledOnce();

        $eventLoader = new EventLoader($this->eventConverter->reveal());

        $eventLoader($streamEvents, $this->streamName)->current();
    }

    /**
     * @test
     */
    public function it_raise_exception_on_query_exception_when_no_row_has_been_affected(): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'stephbug'];
        $event->no = 5;

        $streamEvents = new Collection([$event, $event]);

        $queryException = QueryExceptionStub::withCode('00000');

        $expectedEvent = SomeEvent::fromContent(['name' => 'stephbug'])->withHeader('some', 'header');

        $this->eventConverter->toDomainEvent($event)->willReturn($expectedEvent)->shouldBeCalledTimes(2);
        $this->eventConverter->toDomainEvent($event)->willThrow($queryException)->shouldBeCalledOnce();

        $eventLoader = new EventLoader($this->eventConverter->reveal());

        $eventLoader($streamEvents, $this->streamName)->current();
    }
}
