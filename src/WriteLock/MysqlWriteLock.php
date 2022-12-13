<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\WriteLock;

use Chronhub\Contracts\Store\WriteLockStrategy;

/**
 * Not a lock strategy per se
 * but it instructs to lock for update during insertion
 * this mecanism should help mitigate gaps in auto incrementation
 * when retrieving stream events from a projection
 */
final class MysqlWriteLock implements WriteLockStrategy
{
    public function acquireLock(string $tableName): bool
    {
        return true;
    }

    public function releaseLock(string $tableName): bool
    {
        return true;
    }
}
