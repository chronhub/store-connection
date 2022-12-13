<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Chronhub\Contracts\Chronicler\TransactionalChronicler;

final class TransactionalMysqlChronicler extends MysqlChronicler implements TransactionalChronicler
{
    public function beginTransaction(): void
    {
        $this->chronicler->beginTransaction();
    }

    public function commitTransaction(): void
    {
        $this->chronicler->commitTransaction();
    }

    public function rollbackTransaction(): void
    {
        $this->chronicler->rollbackTransaction();
    }

    public function transactional(callable $callback): bool|array|string|int|float|object
    {
        return $this->chronicler->transactional($callback);
    }

    public function inTransaction(): bool
    {
        return $this->chronicler->inTransaction();
    }
}
