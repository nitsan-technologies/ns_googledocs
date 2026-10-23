<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\RemoteContentFetcher;
use NITSAN\NsGoogledocs\Service\RemoteUrlGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * URL policy for downloads is owned by RemoteUrlGuard (covered without Core bootstrap).
 * Data-URI materialization is tested against RemoteContentFetcher with a stub RequestFactory.
 */
final class RemoteContentFetcherTest extends TestCase
{
    #[Test]
    public function urlPolicyMatchesGuard(): void
    {
        self::assertTrue(RemoteUrlGuard::isAllowedHttpsUrl('https://example.com/a.png'));
        self::assertFalse(RemoteUrlGuard::isAllowedHttpsUrl('http://example.com/a.png'));
    }

    #[Test]
    public function materializesGoogleDocsStyleDataUri(): void
    {
        // 1x1 PNG
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        self::assertNotFalse($png);
        $src = 'data:image/png;base64,' . base64_encode($png);

        $fetcher = new RemoteContentFetcher($this->createMock(RequestFactory::class));
        $dir = sys_get_temp_dir() . '/ns_gd_img_' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $target = $dir . '/test';
        $result = $fetcher->materializeImageSource($src, $target);

        self::assertNotNull($result);
        self::assertSame('image/png', $result['mime']);
        self::assertFileExists($result['path']);
        self::assertSame($png, file_get_contents($result['path']));
        @unlink($result['path']);
        @rmdir($dir);
    }

    #[Test]
    public function rejectsNonImageDataUriAndHttp(): void
    {
        $fetcher = new RemoteContentFetcher($this->createMock(RequestFactory::class));
        $dir = sys_get_temp_dir() . '/ns_gd_img_' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        self::assertNull($fetcher->materializeImageSource('data:text/plain;base64,YQ==', $dir . '/a'));
        self::assertNull($fetcher->materializeImageSource('http://example.com/a.png', $dir . '/b'));
        @rmdir($dir);
    }
}
