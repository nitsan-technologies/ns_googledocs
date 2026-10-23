<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\ImportPageTypeGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportPageTypeGuardTest extends TestCase
{
    public static function blockedDoktypes(): array
    {
        return [
            'link' => [3], 'shortcut' => [4], 'be user section' => [6], 'mount point' => [7],
            'spacer' => [199], 'sysfolder' => [254], 'recycler' => [255], 'invalid' => [0],
        ];
    }

    #[Test]
    public function allowsStandardPageByDefault(): void
    {
        self::assertTrue(ImportPageTypeGuard::isAllowed(1));
    }

    #[Test]
    #[DataProvider('blockedDoktypes')]
    public function blocksNonContentPageTypes(int $doktype): void
    {
        self::assertFalse(ImportPageTypeGuard::isAllowed($doktype));
        // Even an explicit allowlist cannot enable them.
        self::assertFalse(ImportPageTypeGuard::isAllowed($doktype, [1, $doktype]));
    }

    #[Test]
    public function customAllowlistEnablesCustomDoktype(): void
    {
        $allow = ImportPageTypeGuard::parseAllowlist('1, 116,abc,');
        self::assertSame([1, 116], $allow);
        self::assertTrue(ImportPageTypeGuard::isAllowed(116, $allow));
        self::assertFalse(ImportPageTypeGuard::isAllowed(116));
        self::assertSame([1], ImportPageTypeGuard::parseAllowlist(''));
    }
}
