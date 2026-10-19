<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Functional\Domain\Repository;

use NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class UserInfoRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'frontend',
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/ns_license',
        'typo3conf/ext/ns_googledocs',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function insertPageElementCreatesTextContentViaDataHandler(): void
    {
        /** @var UserInfoRepository $repository */
        $repository = $this->get(UserInfoRepository::class);

        $ok = $repository->insertPageElement(
            1,
            [
                'CType' => 'text',
                'header' => 'Imported header',
                'header_layout' => 2,
                'bodytext' => '<p>Imported body</p>',
                'sorting' => 256,
            ],
            ['colPos' => 0]
        );

        self::assertTrue($ok, 'DataHandler should create the content element without errors');

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $row = $queryBuilder
            ->select('uid', 'pid', 'CType', 'header', 'header_layout', 'bodytext', 'colPos')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(1, (int)$row['pid']);
        self::assertSame('text', $row['CType']);
        self::assertSame('Imported header', $row['header']);
        self::assertSame(2, (int)$row['header_layout']);
        self::assertSame('<p>Imported body</p>', $row['bodytext']);
        self::assertSame(0, (int)$row['colPos']);
        self::assertGreaterThan(0, (int)$row['uid']);
    }
}
