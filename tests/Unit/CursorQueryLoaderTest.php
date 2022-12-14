<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use stdClass;
use Chronhub\Testing\ProphecyTest;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Testing\Double\SomeEvent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Testing\Stubs\StreamNameStub;
use Chronhub\Store\Connection\Loader\EventLoader;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Store\Connection\Loader\CursorQueryLoader;
use Chronhub\Contracts\Support\Serializer\StreamEventConverter;

final class CursorQueryLoaderTest extends ProphecyTest
{
    private Builder|ObjectProphecy $builder;

    private StreamEventConverter|ObjectProphecy $eventConverter;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->builder = $this->prophesize(Builder::class);
        $this->eventConverter = $this->prophesize(StreamEventConverter::class);
        $this->streamName = new StreamNameStub('operation');
    }

    /**
     * @test
     */
    public function it_generate_events(): void
    {
        $event = new stdClass();
        $event->headers = [];
        $event->content = [];
        $event->no = 1;

        $this->builder->cursor()->willReturn(new LazyCollection([$event]))->shouldBeCalled();

        $expectedEvent = SomeEvent::fromContent(['name' => 'stephbug']);
        $this->eventConverter->toDomainEvent($event)->willReturn($expectedEvent)->shouldBeCalled();

        $loader = new CursorQueryLoader(new EventLoader($this->eventConverter->reveal()));

        $eventLoaded = $loader->query($this->builder->reveal(), $this->streamName);

        foreach ($eventLoaded as $event) {
            $this->assertEquals($expectedEvent, $event);
        }
    }

    /**
     * @test
     */
    public function it_raise_stream_not_found_with_empty_events(): void
    {
        $this->expectException(StreamNotFound::class);
        $this->expectExceptionMessage('Stream operation not found');

        $this->builder->cursor()->willReturn(new LazyCollection([]))->shouldBeCalled();

        $this->eventConverter->toDomainEvent(new stdClass())->shouldNotBeCalled();

        $loader = new CursorQueryLoader(new EventLoader($this->eventConverter->reveal()));

        $eventsLoaded = $loader->query($this->builder->reveal(), $this->streamName);

        $this->assertEquals(0, $eventsLoaded->getReturn());
    }
}
