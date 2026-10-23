<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\RemoteUrlGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoteUrlGuardTest extends TestCase
{
    #[Test]
    public function isAllowedHttpsUrlAcceptsValidHttps(): void
    {
        self::assertTrue(
            RemoteUrlGuard::isAllowedHttpsUrl('https://lh3.googleusercontent.com/a/doc.png')
        );
    }

    #[Test]
    public function isAllowedHttpsUrlRejectsHttp(): void
    {
        self::assertFalse(
            RemoteUrlGuard::isAllowedHttpsUrl('http://example.com/image.png')
        );
    }

    #[Test]
    public function isAllowedHttpsUrlRejectsEmptyAndRelative(): void
    {
        self::assertFalse(RemoteUrlGuard::isAllowedHttpsUrl(''));
        self::assertFalse(RemoteUrlGuard::isAllowedHttpsUrl('/fileadmin/x.png'));
        self::assertFalse(RemoteUrlGuard::isAllowedHttpsUrl('file:///etc/passwd'));
    }

    #[Test]
    public function isAllowedHttpUrlAcceptsHttpAndHttps(): void
    {
        self::assertTrue(RemoteUrlGuard::isAllowedHttpUrl('https://composer.t3terminal.com/API/x'));
        self::assertTrue(RemoteUrlGuard::isAllowedHttpUrl('http://composer.t3terminal.com/API/x'));
        self::assertFalse(RemoteUrlGuard::isAllowedHttpUrl('ftp://example.com/x'));
    }
}
