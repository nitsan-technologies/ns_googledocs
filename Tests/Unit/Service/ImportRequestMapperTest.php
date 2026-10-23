<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\ImportRequestMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportRequestMapperTest extends TestCase
{
    #[Test]
    public function prefersExtbasePluginNamespaceBag(): void
    {
        $mapped = ImportRequestMapper::map(
            [
                'tx_nsgoogledocs_nitsan_nsgoogledocs' => [
                    'googleDocsID' => '1abcFILE',
                    'pageId' => '12',
                    'colPos' => '2',
                    'sections' => [
                        0 => ['enabled' => '1', 'CType' => 'text', 'header' => 'Intro'],
                    ],
                    'googleDocsName' => 'My Doc',
                ],
                'unrelated' => 'x',
            ],
            ['id' => '12']
        );

        self::assertSame('1abcFILE', $mapped['googleDocsID']);
        self::assertSame(12, $mapped['pageId']);
        self::assertSame(2, $mapped['colPos']);
        self::assertSame('My Doc', $mapped['googleDocsName']);
        self::assertSame('text', $mapped['sections'][0]['CType']);
    }

    #[Test]
    public function pageIdComesFromQueryWhenPluginOmitsIt(): void
    {
        $mapped = ImportRequestMapper::map(
            ['tx_nsgoogledocs_nitsan_nsgoogledocs' => ['googleDocsID' => 'file1']],
            ['id' => '44']
        );
        self::assertSame(44, $mapped['pageId']);
        self::assertSame(44, $mapped['id']);
    }

    #[Test]
    public function unwrapsCorruptedArrayGoogleDocsIdWithoutRecursingSections(): void
    {
        $mapped = ImportRequestMapper::map([
            'tx_nsgoogledocs_nitsan_nsgoogledocs' => [
                'googleDocsID' => ['id' => 'recoveredId'],
                'sections' => [
                    0 => ['googleDocsID' => 'must-not-win', 'CType' => 'text'],
                ],
            ],
        ]);

        self::assertSame('recoveredId', $mapped['googleDocsID']);
    }

    #[Test]
    public function mapsGlobalSettingsFields(): void
    {
        $mapped = ImportRequestMapper::map([
            'tx_nsgoogledocs_nitsan_nsgoogledocs' => [
                'GoogleDocsClientId' => ' cid ',
                'GoogleDocsSecretKey' => 'secret',
                'GoogleDocsRefreshToken' => '1//token',
                'importType' => ['1', '', '137'],
            ],
        ]);

        self::assertSame('cid', $mapped['GoogleDocsClientId']);
        self::assertSame('secret', $mapped['GoogleDocsSecretKey']);
        self::assertSame('1//token', $mapped['GoogleDocsRefreshToken']);
        self::assertSame(['1', '137'], $mapped['importType']);
    }

    #[Test]
    public function acceptsTopLevelBodyAndExtbaseArguments(): void
    {
        $fromBody = ImportRequestMapper::map(['googleDocsID' => 'topLevel']);
        self::assertSame('topLevel', $fromBody['googleDocsID']);

        $fromExtbase = ImportRequestMapper::map(null, [], ['googleDocsID' => 'fromExtbase']);
        self::assertSame('fromExtbase', $fromExtbase['googleDocsID']);
    }
}
