<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Functional\Domain\Repository;

use NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository;
use NITSAN\NsGoogledocs\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class UserInfoRepositoryTest extends AbstractFunctionalTestCase
{
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
    #[Test]
    public function insertPageElementAfterUidAppendsInDocumentOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');
        /** @var UserInfoRepository $repository */
        $repository = $this->get(UserInfoRepository::class);

        $afterUid = $repository->findLastContentElementUidInColumn(1, 0);
        self::assertSame(2, $afterUid, 'Last CE of colPos 0 (highest sorting) is the insert target');

        foreach (['First imported', 'Second imported'] as $header) {
            self::assertTrue($repository->insertPageElement(
                1,
                ['CType' => 'text', 'header' => $header, 'bodytext' => '<p>x</p>'],
                ['colPos' => 0],
                $afterUid
            ));
            $afterUid = $repository->getLastInsertedUid();
            self::assertGreaterThan(0, $afterUid);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $headers = $queryBuilder
            ->select('header')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchFirstColumn();

        self::assertSame(['Existing one', 'Existing two', 'First imported', 'Second imported'], $headers);
    }

    #[Test]
    public function insertPageElementCreatesSysFileReferenceForImages(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=');
        self::assertNotFalse($png);
        $tempDir = Environment::getPublicPath() . '/typo3temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $tempFile = $tempDir . '/ns_googledocs_fal_test.png';
        file_put_contents($tempFile, $png);

        /** @var StorageRepository $storageRepository */
        $storageRepository = $this->get(StorageRepository::class);
        $storage = $storageRepository->getDefaultStorage();
        self::assertNotNull($storage, 'Functional instance must provide the default FAL storage');
        $file = $storage->addFile($tempFile, $storage->getDefaultFolder(), 'ns_googledocs_fal_test.png');
        self::assertGreaterThan(0, $file->getUid());

        /** @var UserInfoRepository $repository */
        $repository = $this->get(UserInfoRepository::class);
        $ok = $repository->insertPageElement(
            1,
            [
                'CType' => 'textpic',
                'header' => 'Imported image',
                'header_layout' => 1,
                'bodytext' => '<p>Caption</p>',
                'images' => [$file->getUid()],
            ],
            ['colPos' => 0]
        );
        self::assertTrue($ok, implode('; ', $repository->getLastDataHandlerErrors()));

        $contentUid = $repository->getLastInsertedUid();
        self::assertGreaterThan(0, $contentUid);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $reference = $queryBuilder
            ->select('uid', 'uid_local', 'uid_foreign', 'tablenames', 'fieldname')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($contentUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($reference);
        self::assertSame($file->getUid(), (int)$reference['uid_local']);
        self::assertSame('tt_content', $reference['tablenames']);
        self::assertSame('image', $reference['fieldname']);

        $contentQuery = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $imageCount = $contentQuery
            ->select('image')
            ->from('tt_content')
            ->where(
                $contentQuery->expr()->eq(
                    'uid',
                    $contentQuery->createNamedParameter($contentUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
        self::assertGreaterThan(0, (int)$imageCount);
    }
}
