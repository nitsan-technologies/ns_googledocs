<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\RemoteUrlGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * URL policy for downloads is owned by RemoteUrlGuard (covered without Core bootstrap).
 */
final class RemoteContentFetcherTest extends TestCase
{
    #[Test]
    public function urlPolicyMatchesGuard(): void
    {
        self::assertTrue(RemoteUrlGuard::isAllowedHttpsUrl('https://example.com/a.png'));
        self::assertFalse(RemoteUrlGuard::isAllowedHttpsUrl('http://example.com/a.png'));
    }
}
