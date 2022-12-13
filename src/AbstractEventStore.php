<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Generator;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Contracts\Stream\StreamCategory;
use Chronhub\Contracts\Store\StreamPersistence;
use Chronhub\Contracts\Store\WriteLockStrategy;
use Chronhub\Contracts\Store\EventLoaderConnection;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\WriteLock\MysqlWriteLock;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;
use function is_string;

abstract class AbstractEventStore implements ChroniclerConnection
{
    protected bool $isCreation = false;

    public function __construct(protected readonly Connection $connection,
                                protected readonly StreamPersistence $streamPersistence,
                                protected readonly EventLoaderConnection $eventLoader,
                                protected readonly EventStreamProvider $eventStreamProvider,
                                protected readonly StreamCategory $streamCategory,
                                protected readonly WriteLockStrategy $writeLock)
    {
    }

    public function isDuringCreation(): bool
    {
        return $this->isCreation;
    }

    public function getEventStreamProvider(): EventStreamProvider
    {
        return $this->eventStreamProvider;
    }

    protected function forWrite(StreamName $streamName): Builder
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        $builder = $this->connection->table($tableName);

        if ($this->writeLock instanceof MysqlWriteLock) {
            return $builder->lockForUpdate();
        }

        return $builder;
    }

    protected function forRead(StreamName $streamName): Builder
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        $indexName = $this->streamPersistence->indexName($tableName);

        if (is_string($indexName)) {
            $raw = "`$tableName` USE INDEX($indexName)";

            return $this->connection->query()->fromRaw($raw);
        }

        return $this->connection->table($tableName);
    }

    protected function serializeStreamEvents(iterable|Generator $streamEvents): array
    {
        $events = [];

        foreach ($streamEvents as $streamEvent) {
            $events[] = $this->streamPersistence->serializeEvent($streamEvent);
        }

        return $events;
    }

    protected function upStreamTable(StreamName $streamName): void
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        try {
            $this->streamPersistence->up($tableName);
        } catch (QueryException $exception) {
            $this->connection->getSchemaBuilder()->drop($tableName);

            $this->eventStreamProvider->deleteStream($streamName->name());

            throw ConnectionQueryFailure::fromQueryException($exception);
        }
    }

    protected function createEventStream(StreamName $streamName): void
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        $result = $this->eventStreamProvider->createStream(
            $streamName->name(),
            $tableName,
            ($this->streamCategory)($streamName->name())
        );

        if (! $result) {
            throw new ConnectionQueryFailure("Unable to insert data for stream $streamName in event stream table");
        }
    }
}
