<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Domain\Repository;

/***
 *
 * This file is part of the "Googledocs" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use NITSAN\NsGoogledocs\Service\RemoteContentFetcher;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Persistence helpers for Google Docs import configuration and content writes.
 *
 * Content elements are created via DataHandler so reference index, permissions
 * and history stay consistent.
 */
class UserInfoRepository extends Repository
{
    /**
     * @var list<string>
     */
    private array $lastDataHandlerErrors = [];

    public function __construct(
        private readonly ?RemoteContentFetcher $remoteContentFetcher = null
    ) {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function getLastDataHandlerErrors(): array
    {
        return $this->lastDataHandlerErrors;
    }

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $request
     */
    public function insertPageElement(int $pageID, array $element, array $request): bool
    {
        $contentId = StringUtility::getUniqueId('NEW');
        $header = (string)($element['header'] ?? '');
        $headerLayout = (int)($element['header_layout'] ?? 0);
        $colPos = (int)($request['colPos'] ?? 0);
        $cType = (string)($element['CType'] ?? 'text');
        $sorting = (int)($element['sorting'] ?? 0);

        $record = [
            'pid' => $pageID,
            'CType' => $cType,
            'colPos' => $colPos,
            'sys_language_uid' => 0,
            'sorting' => $sorting,
            'header' => $header,
            'header_layout' => $headerLayout,
        ];

        if ($cType === 'image' || $cType === 'textpic') {
            $record['imageorient'] = (int)($element['imageorient'] ?? 0);
            $record['imagecols'] = 1;
        }
        if ($cType === 'text' || $cType === 'textpic') {
            $record['bodytext'] = (string)($element['bodytext'] ?? '');
        }
        // CType "header" only uses header / header_layout (no bodytext / images).

        $data = [
            'tt_content' => [
                $contentId => $record,
            ],
        ];

        $imageRefIds = [];
        if (
            ($cType === 'image' || $cType === 'textpic')
            && !empty($element['images'])
            && is_array($element['images'])
        ) {
            foreach ($element['images'] as $key => $fileUid) {
                $refId = StringUtility::getUniqueId('NEW');
                $data['sys_file_reference'][$refId] = [
                    'uid_local' => (int)$fileUid,
                    'uid_foreign' => $contentId,
                    'tablenames' => 'tt_content',
                    'fieldname' => 'image',
                    'pid' => $pageID,
                    'sorting_foreign' => 1 + (int)$key,
                    'table_local' => 'sys_file',
                ];
                $imageRefIds[] = $refId;
            }
            $data['tt_content'][$contentId]['image'] = implode(',', $imageRefIds);
        }

        $this->lastDataHandlerErrors = [];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        if (isset($GLOBALS['BE_USER']) && is_object($GLOBALS['BE_USER'])) {
            $dataHandler->BE_USER = $GLOBALS['BE_USER'];
        }
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            $this->lastDataHandlerErrors = array_values($dataHandler->errorLog);
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            foreach ($this->lastDataHandlerErrors as $message) {
                $logger->warning('ns_googledocs DataHandler: {message}', ['message' => $message]);
            }
            return false;
        }

        $newUid = (int)($dataHandler->substNEWwithIDs[$contentId] ?? 0);
        if ($newUid > 0) {
            return true;
        }

        // Fallback: DataHandler may rewrite NEW keys; accept any substituted tt_content uid.
        foreach ($dataHandler->substNEWwithIDs as $newIdKey => $uid) {
            if (str_starts_with((string)$newIdKey, 'NEW') && (int)$uid > 0) {
                return true;
            }
        }

        $this->lastDataHandlerErrors[] = 'DataHandler did not create a tt_content record (CType=' . $cType . ', pid=' . $pageID . ').';
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->warning('ns_googledocs DataHandler: no substNEWwithIDs for {id}', [
            'id' => $contentId,
            'map' => $dataHandler->substNEWwithIDs,
        ]);
        return false;
    }

    public function findElementSorting(int $pid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tt_content')
            ->createQueryBuilder();
        $result = $queryBuilder
            ->addSelectLiteral(
                $queryBuilder->expr()->max('sorting', 'sorting')
            )
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        if (empty($result['sorting'])) {
            return 0;
        }

        return (int)$result['sorting'];
    }

    /**
     * @param array<string, mixed> $request
     */
    public function updateDocsConfig(int $userID, array $request): bool
    {
        if (!isset($request['GoogleDocsClientId']) && !isset($request['GoogleDocsSecretKey'])) {
            foreach ($request as $value) {
                if (is_array($value) && (
                    isset($value['GoogleDocsClientId']) || isset($value['GoogleDocsSecretKey'])
                )) {
                    $request = $value;
                    break;
                }
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('be_users');

        $queryBuilder
            ->update('be_users')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($userID, ParameterType::INTEGER)
                )
            )
            ->set(
                'tx_nsgoogledocs_client_id',
                (string)($request['GoogleDocsClientId'] ?? ''),
                true,
                ParameterType::STRING
            )
            ->set(
                'tx_nsgoogledocs_client_secret',
                (string)($request['GoogleDocsSecretKey'] ?? ''),
                true,
                ParameterType::STRING
            )
            ->set(
                'tx_nsgoogledocs_refresh_token',
                (string)($request['GoogleDocsRefreshToken'] ?? ''),
                true,
                ParameterType::STRING
            );

        if (!empty($request['importType']) && is_array($request['importType'])) {
            $queryBuilder->set(
                'tx_nsgoogledocs_import_type',
                implode(',', $request['importType']),
                true,
                ParameterType::STRING
            );
        } else {
            $queryBuilder->set('tx_nsgoogledocs_import_type', '', true, ParameterType::STRING);
        }

        try {
            $queryBuilder->executeStatement();
            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function deleteOldApiData(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $cutoff = (new \DateTimeImmutable('-2 weeks'))->format('Y-m-d');
        $queryBuilder
            ->delete('tx_nsgoogledocs_domain_model_apidata')
            ->where(
                $queryBuilder->expr()->lt(
                    'last_update',
                    $queryBuilder->createNamedParameter($cutoff)
                )
            )
            ->executeStatement();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function checkApiData(): array|false
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        return $queryBuilder
            ->select('*')
            ->from('tx_nsgoogledocs_domain_model_apidata')
            ->executeQuery()
            ->fetchAssociative();
    }

    public function curlInitCall(string $url): string|false
    {
        $fetcher = $this->remoteContentFetcher
            ?? GeneralUtility::makeInstance(RemoteContentFetcher::class);
        $result = $fetcher->fetchText($url);
        return $result === '' ? false : $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertNewData(array $data): int|string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $queryBuilder
            ->insert('tx_nsgoogledocs_domain_model_apidata')
            ->values($data)
            ->executeStatement();

        return $queryBuilder->getConnection()->lastInsertId();
    }
}
