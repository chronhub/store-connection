<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

abstract class ProphecyTest extends TestCase
{
    use ProphecyTrait;
}
