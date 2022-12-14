<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use stdClass;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Stream\GenericStreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Store\Connection\Loader\EventLoader;
use Chronhub\Store\Connection\Tests\ProphecyTest;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Store\Connection\Loader\LazyQueryLoader;
use Chronhub\Store\Connection\Tests\Double\SomeEvent;
use Chronhub\Contracts\Support\Serializer\StreamEventConverter;
use function iterator_to_array;

final class LazyQueryLoaderTest extends ProphecyTest
{
    private Builder|ObjectProphecy $builder;

    private StreamEventConverter|ObjectProphecy $eventConverter;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->builder = $this->prophesize(Builder::class);
        $this->eventConverter = $this->prophesize(StreamEventConverter::class);
        $this->streamName = new GenericStreamName('operation');
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

        $this->builder->lazy(50)->willReturn(new LazyCollection([$event]))->shouldBeCalled();

        $expectedEvent = SomeEvent::fromContent(['name' => 'stephbug']);
        $this->eventConverter->toDomainEvent($event)->willReturn($expectedEvent)->shouldBeCalled();

        $loader = new LazyQueryLoader(new EventLoader($this->eventConverter->reveal()), 50);

        $eventLoaded = $loader->query($this->builder->reveal(), $this->streamName);

        $iterator = iterator_to_array($eventLoaded);

        $this->assertCount(1, $iterator);

        $this->assertEquals($expectedEvent, $iterator[0]);
    }

    /**
     * @test
     */
    public function it_raise_stream_not_found_with_empty_events(): void
    {
        $this->expectException(StreamNotFound::class);
        $this->expectExceptionMessage('Stream operation not found');

        $this->builder->lazy(150)->willReturn(new LazyCollection([]))->shouldBeCalled();

        $this->eventConverter->toDomainEvent(new stdClass())->shouldNotBeCalled();

        $loader = new LazyQueryLoader(new EventLoader($this->eventConverter->reveal()), 150);

        $eventsLoaded = $loader->query($this->builder->reveal(), $this->streamName);

        $this->assertEquals(0, $eventsLoaded->getReturn());
    }
}
