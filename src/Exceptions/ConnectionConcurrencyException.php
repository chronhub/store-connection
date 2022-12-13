<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Exceptions;

use Illuminate\Database\QueryException;
use Chronhub\Chronicler\Exceptions\ConcurrencyException;
use function sprintf;
use function is_array;

class ConnectionConcurrencyException extends ConcurrencyException
{
    public static function failedToAcquireLock(): self
    {
        return new self('Failed to acquire lock');
    }

    public static function fromUnlockStreamFailure(QueryException $exception): self
    {
        $message = 'Events or Aggregates ids have already been used in the same stream';

        $errorInfo = $exception->errorInfo;

        if (is_array($errorInfo)) {
            $message .= sprintf("Error %s. \nError-Info: %s", $errorInfo[0], $errorInfo[2]);
        }

        return new self($message);
    }
}
