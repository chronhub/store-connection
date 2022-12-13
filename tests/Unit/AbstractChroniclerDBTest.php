<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use RuntimeException;
use Chronhub\Stream\GenericStream;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Stream\GenericStreamName;
use Illuminate\Database\QueryException;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Contracts\Chronicler\QueryFilter;
use Chronhub\Store\Connection\Tests\ProphecyTest;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\Tests\Stub\ChroniclerDBStub;

final class AbstractChroniclerDBTest extends ProphecyTest
{
    private ObjectProphecy|ChroniclerConnection $chronicler;

    protected function setUp(): void
    {
        $this->chronicler = $this->prophesize(ChroniclerConnection::class);
    }

    /**
     * @test
     */
    public function it_create_stream(): void
    {
        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->firstCommit($stream)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->firstCommit($stream);
    }

    /**
     * @test
     */
    public function it_update_stream(): void
    {
        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->amend($stream)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->amend($stream);
    }

    /**
     * @test
     */
    public function it_delete_stream(): void
    {
        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->delete($stream->name())->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->delete($stream->name());
    }

    /**
     * @test
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events(string $direction): void
    {
        $identity = $this->prophesize(Identity::class)->reveal();

        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->retrieveAll($stream->name(), $identity, $direction)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->retrieveAll($stream->name(), $identity, $direction);
    }

    /**
     * @test
     */
    public function it_retrieve_filtered_stream_events(): void
    {
        $queryFilter = $this->prophesize(QueryFilter::class)->reveal();

        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->retrieveFiltered($stream->name(), $queryFilter)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->retrieveFiltered($stream->name(), $queryFilter);
    }

    /**
     * @test
     */
    public function it_filter_stream_names_ordered_by_name(): void
    {
        $barStream = new GenericStreamName('bar');
        $fooStream = new GenericStreamName('foo');
        $zooStream = new GenericStreamName('zoo');

        $this->chronicler->filterStreamNames($zooStream, $barStream, $fooStream)->willReturn([$fooStream, $zooStream])->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertEquals([$fooStream, $zooStream], $es->filterStreamNames($zooStream, $barStream, $fooStream));
    }

    /**
     * @test
     */
    public function it_filter_categories(): void
    {
        $this->chronicler->filterCategoryNames('transaction')->willReturn(['add', 'subtract'])->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertEquals(['add', 'subtract'], $es->filterCategoryNames('transaction'));
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_check_stream_exists(bool $isStreamExists): void
    {
        $streamName = new GenericStreamName('transaction');

        $this->chronicler->hasStream($streamName)->willReturn($isStreamExists)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertEquals($isStreamExists, $es->hasStream($streamName));
    }

    /**
     * @test
     */
    public function it_return_event_stream_provider(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $this->chronicler->getEventStreamProvider()->willReturn($provider)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertSame($provider, $es->getEventStreamProvider());
    }

    /**
     * @test
     */
    public function it_return_inner_chronicler(): void
    {
        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertSame($this->chronicler->reveal(), $es->innerChronicler());
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_check_if_persistence_is_during_creation_of_stream(bool $isCreation): void
    {
        $this->chronicler->isDuringCreation()->willReturn($isCreation)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertEquals($isCreation, $es->isDuringCreation());
    }

    /**
     * @test
     */
    public function it_raise_exception_during_creation(): void
    {
        $exception = new QueryException('some sql', [], new RuntimeException('foo'));

        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->firstCommit($stream)->willThrow($exception)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->firstCommit($stream);

        $this->assertSame($exception, $es->getRaisedException());
    }

    /**
     * @test
     */
    public function it_raise_exception_during_update(): void
    {
        $exception = new QueryException('some sql', [], new RuntimeException('foo'));

        $stream = new GenericStream(new GenericStreamName('foo'));

        $this->chronicler->amend($stream)->willThrow($exception)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->amend($stream);

        $this->assertSame($exception, $es->getRaisedException());
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
}
