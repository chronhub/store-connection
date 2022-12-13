<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Illuminate\Database\QueryException;
use Chronhub\Contracts\Stream\StreamName;
use Chronhub\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Store\Connection\Exceptions\ConnectionQueryFailure;
use Chronhub\Store\Connection\Exceptions\ConnectionConcurrencyException;

class PgsqlChronicler extends AbstractChroniclerDB
{
    protected function handleException(QueryException $exception, StreamName $streamName): void
    {
        if ($this->isDuringCreation()) {
            match ($exception->getCode()) {
                '23000', '23505' => throw StreamAlreadyExists::withStreamName($streamName),
                default => throw ConnectionQueryFailure::fromQueryException($exception)
            };
        }

        match ($exception->getCode()) {
            '42P01' => throw StreamNotFound::withStreamName($streamName),
            '23000', '23505' => throw ConnectionConcurrencyException::fromUnlockStreamFailure($exception),
            default => throw ConnectionQueryFailure::fromQueryException($exception)
        };
    }
}
