<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Stub;

use Illuminate\Database\QueryException;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Store\Connection\AbstractChroniclerDB;

final class ChroniclerDBStub extends AbstractChroniclerDB
{
    private ?QueryException $exception = null;

    protected function handleException(QueryException $exception, StreamName $streamName): void
    {
        $this->exception = $exception;
    }

    public function getRaisedException(): ?QueryException
    {
        return $this->exception;
    }
}
