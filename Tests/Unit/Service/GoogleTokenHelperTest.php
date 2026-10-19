<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\GoogleTokenHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GoogleTokenHelperTest extends TestCase
{
    #[Test]
    public function rejectsAccessTokenAsRefreshToken(): void
    {
        $error = GoogleTokenHelper::validateRefreshToken('ya29.a0AfH6SMBxyz');
        self::assertNotNull($error);
        self::assertStringContainsString('1//', $error);
    }

    #[Test]
    public function acceptsRefreshTokenAndEmptyValue(): void
    {
        self::assertNull(GoogleTokenHelper::validateRefreshToken('1//0gAbCdEf-GhIj'));
        self::assertNull(GoogleTokenHelper::validateRefreshToken(' 1//0gAbCdEf '));
        self::assertNull(GoogleTokenHelper::validateRefreshToken(''));
    }

    #[Test]
    public function explainsInvalidGrant(): void
    {
        $message = GoogleTokenHelper::explain('{"error": "invalid_grant", "error_description": "Bad Request"}');
        self::assertSame(GoogleTokenHelper::INVALID_GRANT_MESSAGE, $message);
        self::assertSame('Other error', GoogleTokenHelper::explain('Other error'));
    }
}
