<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\ImportContentElementTypeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportContentElementTypeResolverTest extends TestCase
{
    #[Test]
    public function keepsHeaderAndImageWhenTheyAreAvailable(): void
    {
        $available = static fn(string $cType): bool => in_array($cType, ['header', 'text', 'textpic', 'image'], true);

        self::assertSame('header', ImportContentElementTypeResolver::normalize('header', $available));
        self::assertSame('image', ImportContentElementTypeResolver::normalize('image', $available));
        self::assertSame('text', ImportContentElementTypeResolver::normalize('text', $available));
        self::assertSame('textpic', ImportContentElementTypeResolver::normalize('textpic', $available));
    }

    #[Test]
    public function mapsHeaderAndImageOnlyWhenTheyWereRemoved(): void
    {
        $available = static fn(string $cType): bool => in_array($cType, ['text', 'textpic'], true);

        self::assertSame('text', ImportContentElementTypeResolver::normalize('header', $available));
        self::assertSame('textpic', ImportContentElementTypeResolver::normalize('image', $available));
        self::assertSame('text', ImportContentElementTypeResolver::normalize('text', $available));
        self::assertSame('textpic', ImportContentElementTypeResolver::normalize('textpic', $available));
    }

    #[Test]
    public function unknownTypeFallsBackToAnAvailableType(): void
    {
        $available = static fn(string $cType): bool => $cType === 'textpic';
        self::assertSame('textpic', ImportContentElementTypeResolver::normalize('bullets', $available));
    }

    #[Test]
    public function isAllowedHonoursTcaAndRemoveItems(): void
    {
        self::assertTrue(ImportContentElementTypeResolver::isAllowed('header', true, []));
        self::assertFalse(ImportContentElementTypeResolver::isAllowed('header', true, ['header', 'image']));
        self::assertFalse(ImportContentElementTypeResolver::isAllowed('header', false, []));
        self::assertFalse(ImportContentElementTypeResolver::isAllowed('bullets', true, []));
    }

    #[Test]
    public function parseRemoveItemsSplitsCommaList(): void
    {
        self::assertSame(
            ['header', 'image', 'textmedia'],
            ImportContentElementTypeResolver::parseRemoveItems('header, image,textmedia')
        );
        self::assertSame([], ImportContentElementTypeResolver::parseRemoveItems(''));
    }

    #[Test]
    public function filterOptionsDropsUnavailableTypes(): void
    {
        $labels = [
            'header' => 'Header',
            'text' => 'Text Element',
            'textpic' => 'Text & Image',
            'image' => 'Image Only',
        ];
        $available = static fn(string $cType): bool => in_array($cType, ['text', 'textpic'], true);
        $options = ImportContentElementTypeResolver::filterOptions($labels, $available);

        self::assertSame(['text', 'textpic'], array_keys($options));
        self::assertArrayNotHasKey('header', $options);
        self::assertArrayNotHasKey('image', $options);
    }
}
