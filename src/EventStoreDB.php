<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Generator;
use Chronhub\Contracts\Stream\Stream;
use Illuminate\Database\QueryException;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Contracts\Chronicler\QueryFilter;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;
use Chronhub\Store\Connection\Exceptions\ConnectionConcurrencyException;
use function count;
use function array_map;

class EventStoreDB extends AbstractEventStore
{
    public function firstCommit(Stream $stream): void
    {
        $this->isCreation = true;

        $this->createEventStream($stream->name());

        $this->upStreamTable($stream->name());

        $this->isCreation = false;

        $this->amend($stream);
    }

    public function amend(Stream $stream): void
    {
        $this->isCreation = false;

        $streamEvents = $this->serializeStreamEvents($stream->events());

        if (count($streamEvents) === 0) {
            return;
        }

        $tableName = $this->streamPersistence->tableName($stream->name());

        if (! $this->writeLock->acquireLock($tableName)) {
            throw ConnectionConcurrencyException::failedToAcquireLock();
        }

        try {
            $this->forWrite($stream->name())->insert($streamEvents);
        } finally {
            $this->writeLock->releaseLock($tableName);
        }
    }

    public function delete(StreamName $streamName): void
    {
        try {
            $result = $this->eventStreamProvider->deleteStream($streamName->name());

            if (! $result) {
                throw StreamNotFound::withStreamName($streamName);
            }
        } catch (QueryException $exception) {
            if ('00000' !== $exception->getCode()) {
                throw ConnectionQueryFailure::fromQueryException($exception);
            }
        }

        try {
            $this->connection->getSchemaBuilder()->drop(
                $this->streamPersistence->tableName($streamName)
            );
        } catch (QueryException $exception) {
            //checkMe handle stream not found when dropping table which not exist

            if ('00000' !== $exception->getCode()) {
                throw ConnectionQueryFailure::fromQueryException($exception);
            }
        }
    }

    public function retrieveAll(StreamName $streamName, Identity $aggregateId, string $direction = 'asc'): Generator
    {
        $query = $this->forRead($streamName);

        if ($this->streamPersistence->isAutoIncremented()) {
            $query = $query->where('aggregate_id', $aggregateId->toString());
        }

        $query = $query->orderBy('no', $direction);

        return $this->eventLoader->query($query, $streamName);
    }

    public function retrieveFiltered(StreamName $streamName, QueryFilter $queryFilter): Generator
    {
        $builder = $this->forRead($streamName);

        $queryFilter->filter()($builder);

        return $this->eventLoader->query($builder, $streamName);
    }

    public function filterStreamNames(StreamName ...$streamNames): array
    {
        return array_map(
            fn (string $streamName): StreamName => ($this->streamFactory)($streamName)->name(),
            $this->eventStreamProvider->filterByStreams($streamNames)
        );
    }

    public function filterCategoryNames(string ...$categoryNames): array
    {
        return $this->eventStreamProvider->filterByCategories($categoryNames);
    }

    public function hasStream(StreamName $streamName): bool
    {
        return $this->eventStreamProvider->hasRealStreamName($streamName->name());
    }
}
