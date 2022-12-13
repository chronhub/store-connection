<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Double;

use Throwable;
use RuntimeException;

final class SomeException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function setCodeAsString(string $errorCode): void
    {
        $this->code = $errorCode;
    }
}
