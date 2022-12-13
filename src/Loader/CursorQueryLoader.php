<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Loader;

use Generator;
use Illuminate\Database\Query\Builder;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Contracts\Store\EventLoaderConnection;

final class CursorQueryLoader implements EventLoaderConnection
{
    public function __construct(private readonly EventLoader $eventLoader)
    {
    }

    public function query(Builder $builder, StreamName $streamName): Generator
    {
        $streamEvents = ($this->eventLoader)($builder->cursor(), $streamName);

        yield from $streamEvents;

        return $streamEvents->getReturn();
    }
}
