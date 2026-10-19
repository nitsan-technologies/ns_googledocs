<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Domain\Model;

use NITSAN\NsGoogledocs\Domain\Model\UserInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserInfoTest extends TestCase
{
    #[Test]
    public function importTypeIsStringTyped(): void
    {
        $userInfo = new UserInfo();
        $userInfo->setImportType('1');
        self::assertSame('1', $userInfo->getImportType());
    }

    #[Test]
    public function credentialAccessorsRoundTrip(): void
    {
        $userInfo = new UserInfo();
        $userInfo->setClientId('cid');
        $userInfo->setClientSecret('secret');
        $userInfo->setRefreshToken('token');
        $userInfo->setGoogleFilesRequest(20);

        self::assertSame('cid', $userInfo->getClientId());
        self::assertSame('secret', $userInfo->getClientSecret());
        self::assertSame('token', $userInfo->getRefreshToken());
        self::assertSame(20, $userInfo->getGoogleFilesRequest());
    }
}
