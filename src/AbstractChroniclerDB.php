<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Generator;
use Chronhub\Contracts\Stream\Stream;
use Illuminate\Database\QueryException;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Contracts\Chronicler\Chronicler;
use Chronhub\Contracts\Chronicler\QueryFilter;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;
use Chronhub\Store\Connection\Exceptions\ConnectionConcurrencyException;

abstract class AbstractChroniclerDB implements ChroniclerConnection, ChroniclerDecorator
{
    public function __construct(protected readonly ChroniclerConnection|TransactionalChronicler $chronicler)
    {
    }

    public function firstCommit(Stream $stream): void
    {
        try {
            $this->chronicler->firstCommit($stream);
        } catch (QueryException $exception) {
            $this->handleException($exception, $stream->name());
        }
    }

    public function amend(Stream $stream): void
    {
        try {
            $this->chronicler->amend($stream);
        } catch (QueryException $exception) {
            $this->handleException($exception, $stream->name());
        }
    }

    public function delete(StreamName $streamName): void
    {
        $this->chronicler->delete($streamName);
    }

    public function retrieveAll(StreamName $streamName, Identity $aggregateId, string $direction = 'asc'): Generator
    {
        return $this->chronicler->retrieveAll($streamName, $aggregateId, $direction);
    }

    public function retrieveFiltered(StreamName $streamName, QueryFilter $queryFilter): Generator
    {
        return $this->chronicler->retrieveFiltered($streamName, $queryFilter);
    }

    public function filterStreamNames(StreamName ...$streamNames): array
    {
        return $this->chronicler->filterStreamNames(...$streamNames);
    }

    public function filterCategoryNames(string ...$categoryNames): array
    {
        return $this->chronicler->filterCategoryNames(...$categoryNames);
    }

    public function hasStream(StreamName $streamName): bool
    {
        return $this->chronicler->hasStream($streamName);
    }

    public function getEventStreamProvider(): EventStreamProvider
    {
        return $this->chronicler->getEventStreamProvider();
    }

    public function innerChronicler(): Chronicler
    {
        return $this->chronicler;
    }

    public function isDuringCreation(): bool
    {
        return $this->chronicler->isDuringCreation();
    }

    /**
     * Handle query exception depends on connection driver
     * and if it is during creation of stream
     *
     * @param  QueryException  $exception
     * @param  StreamName  $streamName
     * @return void
     *
     * @throws StreamNotFound
     * @throws StreamAlreadyExists
     * @throws ConnectionQueryFailure
     * @throws ConnectionConcurrencyException
     */
    abstract protected function handleException(QueryException $exception, StreamName $streamName): void;
}
