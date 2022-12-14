<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Illuminate\Database\Query\Builder;
use Chronhub\Contracts\Store\WriteLockStrategy;
use Chronhub\Store\Connection\WriteLock\FakeWriteLock;
use Chronhub\Store\Connection\WriteLock\MysqlWriteLock;
use Chronhub\Store\Connection\WriteLock\NoMysqlWriteLock;

final class WriteEventStoreDBTest extends EventStoreDBTest
{
    /**
     * @test
     * @dataProvider provideWriteLock
     */
    public function it_assert_write_query_builder(?WriteLockStrategy $writeLock = null): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->table($tableName)->willReturn($builder->reveal())->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore($writeLock)->getBuilderforWrite($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }

    /**
     * @test
     */
    public function it_assert_write_query_builder_with_mysql_write_lock(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->table($tableName)->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->lockForUpdate()->willReturn($builder)->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore(new MysqlWriteLock())->getBuilderforWrite($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }

    public function provideWriteLock(): Generator
    {
        yield [new FakeWriteLock()];
        yield [new NoMysqlWriteLock()];
        yield [null];
    }
}
