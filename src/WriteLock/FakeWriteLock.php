<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\WriteLock;

use Chronhub\Contracts\Store\WriteLockStrategy;

final class FakeWriteLock implements WriteLockStrategy
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
