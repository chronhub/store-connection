<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Chronhub\Testing\ProphecyTest;
use Chronhub\Contracts\Stream\Stream;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Testing\Stubs\StreamStub;
use Chronhub\Testing\Stubs\StreamNameStub;
use Chronhub\Store\Connection\MysqlChronicler;
use Chronhub\Testing\Stubs\QueryExceptionStub;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;
use Chronhub\Store\Connection\Exceptions\ConnectionConcurrencyException;

final class MysqlChroniclerTest extends ProphecyTest
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
    public function it_raise_stream_already_exists_during_creation(): void
    {
        $this->expectException(StreamAlreadyExists::class);

        $queryException = QueryExceptionStub::withCode('23000');

        $this->chronicler->isDuringCreation()->willReturn(true)->shouldBeCalled();
        $this->chronicler->firstCommit($this->stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new MysqlChronicler($this->chronicler->reveal());

        $chronicler->firstCommit($this->stream);
    }

    /**
     * @test
     * @dataProvider provideAnyOtherCodeThanStreamExists
     */
    public function it_raise_query_failure_during_creation_on_any_other_error_code(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->isDuringCreation()->willReturn(true)->shouldBeCalled();
        $this->chronicler->firstCommit($this->stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new MysqlChronicler($this->chronicler->reveal());

        $chronicler->firstCommit($this->stream);
    }

    /**
     * @test
     */
    public function it_raise_stream_not_found_during_update(): void
    {
        $this->expectException(StreamNotFound::class);

        $queryException = QueryExceptionStub::withCode('42S02');

        $this->chronicler->isDuringCreation()->willReturn(false)->shouldBeCalled();
        $this->chronicler->amend($this->stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new MysqlChronicler($this->chronicler->reveal());

        $chronicler->amend($this->stream);
    }

    /**
     * @test
     */
    public function it_raise_concurrency_exception_during_update(): void
    {
        $this->expectException(ConnectionConcurrencyException::class);

        $queryException = QueryExceptionStub::withCode('23000');

        $this->chronicler->isDuringCreation()->willReturn(false)->shouldBeCalled();
        $this->chronicler->amend($this->stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new MysqlChronicler($this->chronicler->reveal());

        $chronicler->amend($this->stream);
    }

    /**
     * @test
     * @dataProvider provideAnyOtherCodeThanStreamExists
     */
    public function it_raise_query_failure_during_update_on_any_other_error_code(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->isDuringCreation()->willReturn(false)->shouldBeCalled();
        $this->chronicler->amend($this->stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new MysqlChronicler($this->chronicler->reveal());

        $chronicler->amend($this->stream);
    }

    public function provideAnyOtherCodeThanStreamExists(): Generator
    {
        yield ['40000'];
        yield ['00000'];
    }
}
