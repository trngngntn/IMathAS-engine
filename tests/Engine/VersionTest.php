<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function test_engine_api_version_is_exposed(): void
    {
        self::assertSame('0.1.0', Version::ENGINE_API);
    }
}
