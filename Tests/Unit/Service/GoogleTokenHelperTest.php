<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\GoogleTokenHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GoogleTokenHelperTest extends TestCase
{
    #[Test]
    public function acceptsAccessTokenAndRefreshToken(): void
    {
        self::assertNull(GoogleTokenHelper::validateRefreshToken('ya29.a0AfH6SMBxyz'));
        self::assertNull(GoogleTokenHelper::validateRefreshToken('1//0gAbCdEf-GhIj'));
        self::assertNull(GoogleTokenHelper::validateRefreshToken(' 1//0gAbCdEf '));
        self::assertNull(GoogleTokenHelper::validateRefreshToken(''));
    }

    #[Test]
    public function detectsAccessTokenPrefix(): void
    {
        self::assertTrue(GoogleTokenHelper::isAccessToken('ya29.a0AfH6SMBxyz'));
        self::assertTrue(GoogleTokenHelper::isAccessToken('  ya29.token  '));
        self::assertFalse(GoogleTokenHelper::isAccessToken('1//0gAbCdEf-GhIj'));
    }

    #[Test]
    public function rejectsTokenWithWhitespace(): void
    {
        $error = GoogleTokenHelper::validateRefreshToken("1//abc\ndef");
        self::assertNotNull($error);
        self::assertStringContainsString('spaces', $error);
    }

    #[Test]
    public function explainsInvalidGrant(): void
    {
        $message = GoogleTokenHelper::explain('{"error": "invalid_grant", "error_description": "Bad Request"}');
        self::assertSame(GoogleTokenHelper::INVALID_GRANT_MESSAGE, $message);
        self::assertSame('Other error', GoogleTokenHelper::explain('Other error'));
    }
}
