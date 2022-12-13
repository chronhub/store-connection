<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Chronhub\Stream\GenericStream;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Stream\GenericStreamName;
use Chronhub\Store\Connection\PgsqlChronicler;
use Chronhub\Store\Connection\Tests\ProphecyTest;
use Chronhub\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\Tests\Stub\QueryExceptionStub;

final class PgsqlChroniclerTest extends ProphecyTest
{
    private ObjectProphecy|ChroniclerConnection $chronicler;

    protected function setUp(): void
    {
        $this->chronicler = $this->prophesize(ChroniclerConnection::class);
    }

    /**
     * @test
     * @dataProvider provideStreamAlreadyExistsCode
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

    public function provideStreamAlreadyExistsCode(): Generator
    {
        yield ['23000'];
        yield ['23505'];
    }
}
