<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Stub;

use Symfony\Component\Uid\Uuid;
use Chronhub\Contracts\Aggregate\Identity;
use Chronhub\Aggregate\HasAggregateIdentity;

final class GenericAggregateIdStub implements Identity
{
    use HasAggregateIdentity;

    private static string $uid = 'bc70b5b8-31be-4e5d-991d-84e6bfcf6dae';

    public static function create(): self|Identity
    {
        return new self(Uuid::v4());
    }

    public static function fix(): self|Identity
    {
        return new self(Uuid::fromString(self::$uid));
    }

    public function getFixUid(): string
    {
        return self::$uid;
    }
}
