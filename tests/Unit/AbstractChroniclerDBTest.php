<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use RuntimeException;
use Chronhub\Contracts\Stream\Stream;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Testing\ProphecyTestCase;
use Chronhub\Testing\Stubs\StreamStub;
use Illuminate\Database\QueryException;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Testing\Stubs\StreamNameStub;
use Chronhub\Contracts\Chronicler\QueryFilter;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\Tests\Stub\ChroniclerDBStub;

final class AbstractChroniclerDBTest extends ProphecyTestCase
{
    private ObjectProphecy|ChroniclerConnection $chronicler;

    private Stream|StreamStub $stream;

    protected function setUp(): void
    {
        $this->chronicler = $this->prophesize(ChroniclerConnection::class);
        $this->stream = new StreamStub(new StreamNameStub('customer'));
    }

    /**
     * @test
     */
    public function it_create_stream(): void
    {
        $this->chronicler->firstCommit($this->stream)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->firstCommit($this->stream);
    }

    /**
     * @test
     */
    public function it_update_stream(): void
    {
        $this->chronicler->amend($this->stream)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->amend($this->stream);
    }

    /**
     * @test
     */
    public function it_delete_stream(): void
    {
        $this->chronicler->delete($this->stream->name())->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->delete($this->stream->name());
    }

    /**
     * @test
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events(string $direction): void
    {
        $identity = $this->prophesize(Identity::class)->reveal();

        $this->chronicler->retrieveAll($this->stream->name(), $identity, $direction)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->retrieveAll($this->stream->name(), $identity, $direction);
    }

    /**
     * @test
     */
    public function it_retrieve_filtered_stream_events(): void
    {
        $queryFilter = $this->prophesize(QueryFilter::class)->reveal();

        $this->chronicler->retrieveFiltered($this->stream->name(), $queryFilter)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->retrieveFiltered($this->stream->name(), $queryFilter);
    }

    /**
     * @test
     */
    public function it_filter_stream_names_ordered_by_name(): void
    {
        $barStream = new StreamNameStub('bar');
        $fooStream = new StreamNameStub('foo');
        $zooStream = new StreamNameStub('zoo');

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
        $this->chronicler->hasStream($this->stream->name())->willReturn($isStreamExists)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $this->assertEquals($isStreamExists, $es->hasStream($this->stream->name()));
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

        $this->chronicler->firstCommit($this->stream)->willThrow($exception)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->firstCommit($this->stream);

        $this->assertSame($exception, $es->getRaisedException());
    }

    /**
     * @test
     */
    public function it_raise_exception_during_update(): void
    {
        $exception = new QueryException('some sql', [], new RuntimeException('foo'));

        $this->chronicler->amend($this->stream)->willThrow($exception)->shouldBeCalledOnce();

        $es = new ChroniclerDBStub($this->chronicler->reveal());

        $es->amend($this->stream);

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
