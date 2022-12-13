<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Chronhub\Stream\GenericStream;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Stream\GenericStreamName;
use Chronhub\Store\Connection\PgsqlChronicler;
use Chronhub\Store\Connection\Tests\ProphecyTest;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\Tests\Stub\QueryExceptionStub;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;
use Chronhub\Store\Connection\Exceptions\ConnectionConcurrencyException;

final class PgsqlChroniclerTest extends ProphecyTest
{
    private ObjectProphecy|ChroniclerConnection $chronicler;

    protected function setUp(): void
    {
        $this->chronicler = $this->prophesize(ChroniclerConnection::class);
    }

    /**
     * @test
     * @dataProvider provideStreamExistsCode
     */
    public function it_raise_stream_already_exists_during_creation(string $errorCode): void
    {
        $this->expectException(StreamAlreadyExists::class);

        $stream = new GenericStream(new GenericStreamName('foo'));

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->isDuringCreation()->willReturn(true)->shouldBeCalled();
        $this->chronicler->firstCommit($stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new PgsqlChronicler($this->chronicler->reveal());

        $chronicler->firstCommit($stream);
    }

    /**
     * @test
     * @dataProvider provideAnyOtherCodeThanStreamExists
     */
    public function it_raise_query_failure_during_creation_on_any_other_error_code(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $stream = new GenericStream(new GenericStreamName('foo'));

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->isDuringCreation()->willReturn(true)->shouldBeCalled();
        $this->chronicler->firstCommit($stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new PgsqlChronicler($this->chronicler->reveal());

        $chronicler->firstCommit($stream);
    }

    /**
     * @test
     */
    public function it_raise_stream_not_found_during_update(): void
    {
        $this->expectException(StreamNotFound::class);

        $stream = new GenericStream(new GenericStreamName('foo'));

        $queryException = QueryExceptionStub::withCode('42P01');

        $this->chronicler->isDuringCreation()->willReturn(false)->shouldBeCalled();
        $this->chronicler->amend($stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new PgsqlChronicler($this->chronicler->reveal());

        $chronicler->amend($stream);
    }

    /**
     * @test
     * @dataProvider provideStreamExistsCode
     */
    public function it_raise_concurrency_exception_during_update(string $errorCode): void
    {
        $this->expectException(ConnectionConcurrencyException::class);

        $stream = new GenericStream(new GenericStreamName('foo'));

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->isDuringCreation()->willReturn(false)->shouldBeCalled();
        $this->chronicler->amend($stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new PgsqlChronicler($this->chronicler->reveal());

        $chronicler->amend($stream);
    }

    /**
     * @test
     * @dataProvider provideAnyOtherCodeThanStreamExists
     */
    public function it_raise_query_failure_during_updaten_on_any_other_error_code(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $stream = new GenericStream(new GenericStreamName('foo'));

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->isDuringCreation()->willReturn(false)->shouldBeCalled();
        $this->chronicler->amend($stream)->willThrow($queryException)->shouldBeCalledOnce();

        $chronicler = new PgsqlChronicler($this->chronicler->reveal());

        $chronicler->amend($stream);
    }

    public function provideAnyOtherCodeThanStreamExists(): Generator
    {
        yield ['40000'];
        yield ['00000'];
    }

    public function provideStreamExistsCode(): Generator
    {
        yield ['23000'];
        yield ['23505'];
    }
}
