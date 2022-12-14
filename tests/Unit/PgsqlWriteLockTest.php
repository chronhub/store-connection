<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Store\Connection\Tests\ProphecyTest;
use Chronhub\Store\Connection\WriteLock\PgsqlWriteLock;

final class PgsqlWriteLockTest extends ProphecyTest
{
    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_acquire_lock(bool $isLocked): void
    {
        $tableName = 'operation';
        $lockName = '_'.$tableName.'_write_lock';

        $connection = $this->prophesize(ConnectionInterface::class);

        $lock = new PgsqlWriteLock($connection->reveal());

        $connection
            ->statement('select pg_advisory_lock( hashtext(\''.$lockName.'\') )')
            ->shouldBeCalledOnce()
            ->willReturn($isLocked);

        $this->assertSame($isLocked, $lock->acquireLock($tableName));
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_release_lock(bool $isRealeased): void
    {
        $tableName = 'operation';
        $lockName = '_'.$tableName.'_write_lock';

        $connection = $this->prophesize(ConnectionInterface::class);

        $lock = new PgsqlWriteLock($connection->reveal());

        $connection
            ->statement('select pg_advisory_unlock( hashtext(\''.$lockName.'\') )')
            ->shouldBeCalledOnce()
            ->willReturn($isRealeased);

        $this->assertSame($isRealeased, $lock->releaseLock($tableName));
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
