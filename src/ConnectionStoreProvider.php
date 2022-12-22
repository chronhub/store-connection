<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Database\Connection;
use Chronhub\Contracts\Stream\Factory;
use Chronhub\Chronicler\EventStoreProvider;
use Chronhub\Contracts\Chronicler\Chronicler;
use Chronhub\Contracts\Stream\StreamCategory;
use Chronhub\Contracts\Tracker\StreamTracker;
use Chronhub\Contracts\Store\StreamPersistence;
use Chronhub\Contracts\Store\WriteLockStrategy;
use Chronhub\Store\Connection\Loader\EventLoader;
use Chronhub\Contracts\Store\EventLoaderConnection;
use Chronhub\Store\Connection\Loader\LazyQueryLoader;
use Chronhub\Contracts\Chronicler\EventableChronicler;
use Chronhub\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Store\Connection\WriteLock\FakeWriteLock;
use Chronhub\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Store\Connection\Loader\CursorQueryLoader;
use Chronhub\Store\Connection\WriteLock\MysqlWriteLock;
use Chronhub\Store\Connection\WriteLock\PgsqlWriteLock;
use Chronhub\Store\Connection\WriteLock\NoMysqlWriteLock;
use Chronhub\Contracts\Tracker\TransactionalStreamTracker;
use Chronhub\Store\Connection\Persistence\SingleStreamPersistence;
use Chronhub\Store\Connection\Persistence\PerAggregateStreamPersistence;
use function explode;
use function ucfirst;
use function is_string;
use function method_exists;
use function str_starts_with;

final class ConnectionStoreProvider extends EventStoreProvider
{
    public function make(string $name, array $config): Chronicler
    {
        $chronicler = $this->resolve($name, $config);

        if ($chronicler instanceof EventableChronicler) {
            $this->attachStreamSubscribers($chronicler, $config['tracking']['subscribers'] ?? []);
        }

        return $chronicler;
    }

    private function resolve(string $name, array $config): Chronicler
    {
        [$streamTracker, $driver, $isTransactional] = $this->determineStorage($name, $config);

        $driverMethod = 'create'.ucfirst(Str::camel($driver.'Driver'));

        /**
         * @covers createMysqlDriver
         * @covers createPgsqlDriver
         */
        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($name, $config, $isTransactional, $streamTracker);
        }

        throw new InvalidArgumentException("Connection $name with provider $driver is not defined.");
    }

    private function createPgsqlDriver(string $name,
                                       array $config,
                                       bool $isTransactional,
                                       ?StreamTracker $streamTracker): ChroniclerConnection|EventableChronicler
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection('pgsql');

        $standalone = $this->newDatabaseInstance($connection, $name, $config, $isTransactional);

        $chronicler = $isTransactional
            ? new TransactionalPgsqlChronicler($standalone)
            : new PgsqlChronicler($standalone);

        return $streamTracker
            ? $this->decorateChronicler($chronicler, $streamTracker)
            : $chronicler;
    }

    private function createMysqlDriver(string $name,
                                       array $config,
                                       bool $isTransactional,
                                       ?StreamTracker $streamTracker): ChroniclerConnection|EventableChronicler
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection('mysql');

        $standalone = $this->newDatabaseInstance($connection, $name, $config, $isTransactional);

        $chronicler = $isTransactional
            ? new TransactionalMysqlChronicler($standalone)
            : new MysqlChronicler($standalone);

        return $streamTracker
            ? $this->decorateChronicler($chronicler, $streamTracker)
            : $chronicler;
    }

    private function determineStorage(string $name, array $config): array
    {
        $streamTracker = $this->resolveStreamTracker($config);

        $isTransactional = $config['is_transactional'] ?? null;

        if (null === $isTransactional && ! $streamTracker instanceof StreamTracker) {
            $exceptionMessage = "Unable to resolve chronicler name $name, ";
            $exceptionMessage .= 'Missing is_transactional key in config';

            throw new InvalidArgumentException($exceptionMessage);
        }

        $isTransactional = $streamTracker instanceof TransactionalStreamTracker;

        return [$streamTracker, $config['store'], $isTransactional];
    }

    private function newDatabaseInstance(Connection $connection,
                                         string $name,
                                         array $config,
                                         bool $isTransactional): ChroniclerConnection
    {
        $args = [
            $connection,
            $this->determineStreamPersistence($name, $connection, $config['strategy'] ?? null),
            $this->determineStreamEventLoader($config['query_loader'] ?? null),
            $this->determineEventStreamProvider(),
            $this->app[Factory::class],
            $this->app[StreamCategory::class],
            $this->determineWriteLock($connection, $config),
        ];

        return $isTransactional ? new TransactionalEventStoreDB(...$args) : new EventStoreDB(...$args);
    }

    private function determineEventStreamProvider(): EventStreamProvider
    {
        return $this->app->bound(EventStreamProvider::class)
            ? $this->app[EventStreamProvider::class]
            : new EventStream();
    }

    private function determineStreamPersistence(string $name, Connection $connection, ?string $persistence): StreamPersistence
    {
        if ($persistence === 'single_indexed' && $connection->getDriverName() !== 'mysql') {
            throw new InvalidArgumentException('Stream persistence single_indexed is only available for mysql');
        }

        return match (true) {
            $persistence === 'single' => $this->app[SingleStreamPersistence::class],
            //$persistence === 'single_indexed' => $this->app[IndexedSingleStreamPersistence::class],
            $persistence === 'per_aggregate' => $this->app[PerAggregateStreamPersistence::class],
            is_string($persistence) => $this->app[$persistence],
            default => throw new InvalidArgumentException("Invalid persistence strategy for chronicler $name")
        };
    }

    private function determineStreamEventLoader(?string $streamEventLoader): EventLoaderConnection
    {
        if ($streamEventLoader === 'cursor' || $streamEventLoader === null) {
            return $this->app[CursorQueryLoader::class];
        }

        if ($streamEventLoader === 'lazy') {
            return $this->app[LazyQueryLoader::class];
        }

        if (str_starts_with($streamEventLoader, 'lazy:')) {
            $chunkSize = (int) explode(':', $streamEventLoader)[1];

            return new LazyQueryLoader($this->app[EventLoader::class], $chunkSize);
        }

        return $this->app[$streamEventLoader];
    }

    private function determineWriteLock(Connection $connection, array $config): WriteLockStrategy
    {
        $writeLock = $config['write_lock'] ?? null;

        if ($writeLock === null) {
            throw new InvalidArgumentException('Write lock is not defined');
        }

        $driver = $connection->getDriverName();

        if ($writeLock === false) {
            return $driver === 'mysql' ? new NoMysqlWriteLock() : new FakeWriteLock();
        }

        // Use default write lock strategy
        if (true === $writeLock) {
            return match ($driver) {
                'pgsql' => new PgsqlWriteLock($connection),
                'mysql' => new MysqlWriteLock(),
                default => throw new InvalidArgumentException("Unavailable write lock strategy driver $driver"),
            };
        }

        // at this point, write lock strategy should be a service, and we just resolve it
        return $this->app[$writeLock];
    }
}
