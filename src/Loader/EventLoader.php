<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Loader;

use Generator;
use Illuminate\Database\QueryException;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Contracts\Support\Serializer\StreamEventConverter;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;

final class EventLoader
{
    public function __construct(private readonly StreamEventConverter $eventConverter)
    {
    }

    /**
     * @param  iterable  $streamEvents
     * @param  StreamName  $streamName
     * @return Generator
     *
     * @throws StreamNotFound
     * @throws ConnectionQueryFailure
     */
    public function __invoke(iterable $streamEvents, StreamName $streamName): Generator
    {
        try {
            $count = 0;

            foreach ($streamEvents as $streamEvent) {
                yield $this->eventConverter->toDomainEvent($streamEvent);

                $count++;
            }

            if (0 === $count) {
                throw StreamNotFound::withStreamName($streamName);
            }

            return $count;
        } catch (QueryException $queryException) {
            if ('00000' !== $queryException->getCode()) {
                throw StreamNotFound::withStreamName($streamName);
            }

            throw ConnectionQueryFailure::fromQueryException($queryException);
        }
    }
}
