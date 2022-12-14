<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Stub;

use Illuminate\Database\Query\Builder;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Store\Connection\EventStoreDB;

final class EventStoreDBStub extends EventStoreDB
{
    public function getBuilderforWrite(StreamName $streamName): Builder
    {
        return $this->forWrite($streamName);
    }

    public function getBuilderforRead(StreamName $streamName): Builder
    {
        return $this->forRead($streamName);
    }

    public function getStreamEventsSerialized(iterable $streamEvents): array
    {
        return $this->serializeStreamEvents($streamEvents);
    }
}
