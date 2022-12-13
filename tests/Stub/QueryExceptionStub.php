<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Stub;

use Illuminate\Database\QueryException;
use Chronhub\Store\Connection\Tests\Double\SomeException;

final class QueryExceptionStub extends QueryException
{
    public static function withCode(string $code)
    {
        $previousException = new SomeException('an error occured');
        $previousException->setCodeAsString($code);

        return new self('some_sql', [], $previousException);
    }
}
