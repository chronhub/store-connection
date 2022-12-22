<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Unit;

use Generator;
use Chronhub\Testing\UnitTestCase;
use Chronhub\Store\Connection\WriteLock\MysqlWriteLock;

final class MysqlWriteLockTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider provideTableName
     */
    public function it_always_acquire_lock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->acquireLock($tableName));
    }

    /**
     * @test
     * @dataProvider provideTableName
     */
    public function it_always_release_lock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->releaseLock($tableName));
    }

    public function provideTableName(): Generator
    {
        yield [''];
        yield ['customer'];
        yield ['transaction'];
        yield ['transaction-add'];
    }
}
