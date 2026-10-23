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
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Persistence helpers for Google Docs import configuration and content writes.
 *
 * Content elements are created via DataHandler so reference index, permissions
 * and history stay consistent. Not an Extbase repository: there is no
 * tx_nsgoogledocs_domain_model_userinfo table.
 */
final class UserInfoRepository
{
    /**
     * @var list<string>
     */
    private array $lastDataHandlerErrors = [];

    private int $lastInsertedUid = 0;

    public function __construct(
        private readonly ?RemoteContentFetcher $remoteContentFetcher = null
    ) {}

    /**
     * @return list<string>
     */
    public function getLastDataHandlerErrors(): array
    {
        return $this->lastDataHandlerErrors;
    }

    /**
     * uid of the tt_content record created by the last successful insertPageElement() call.
     */
    public function getLastInsertedUid(): int
    {
        return $this->lastInsertedUid;
    }

    /**
     * Create one tt_content record via DataHandler.
     *
     * DataHandler ignores an incoming "sorting" value for new records: a positive pid
     * always places the record at the TOP of the page (v12–v14), a negative pid
     * (-uid) places it directly AFTER that record. Pass $insertAfterUid to append.
     *
     * @param array<string, mixed> $element
     * @param array<string, mixed> $request
     * @param int $insertAfterUid tt_content uid to insert after; 0 = top of the page
     */
    public function insertPageElement(int $pageID, array $element, array $request, int $insertAfterUid = 0): bool
    {
        $contentId = StringUtility::getUniqueId('NEW');
        $header = (string)($element['header'] ?? '');
        $headerLayout = (int)($element['header_layout'] ?? 0);
        $colPos = (int)($request['colPos'] ?? 0);
        $cType = (string)($element['CType'] ?? 'text');

        $record = [
            'pid' => $insertAfterUid > 0 ? -$insertAfterUid : $pageID,
            'CType' => $cType,
            'colPos' => $colPos,
            'sys_language_uid' => 0,
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
                ];
                $imageRefIds[] = $refId;
            }
            $data['tt_content'][$contentId]['image'] = implode(',', $imageRefIds);
        }

        $this->lastDataHandlerErrors = [];
        $this->lastInsertedUid = 0;
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
            $this->lastInsertedUid = $newUid;
            return true;
        }

        // Fallback: DataHandler may rewrite NEW keys; accept any substituted tt_content uid.
        foreach ($dataHandler->substNEWwithIDs as $newIdKey => $uid) {
            if (str_starts_with((string)$newIdKey, 'NEW') && (int)$uid > 0) {
                if (($dataHandler->substNEWwithIDs_table[$newIdKey] ?? 'tt_content') === 'tt_content') {
                    $this->lastInsertedUid = (int)$uid;
                }
                return true;
            }
        }

        $this->lastDataHandlerErrors[] = $this->translateLabel(
            'error.dataHandlerNoRecord',
            'DataHandler did not create a tt_content record (CType=%s, pid=%s).',
            [$cType, (string)$pageID]
        );
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->warning('ns_googledocs DataHandler: no substNEWwithIDs for {id}', [
            'id' => $contentId,
            'map' => $dataHandler->substNEWwithIDs,
        ]);
        return false;
    }

    /**
     * uid of the last default-language live tt_content record in a page column
     * (highest sorting), hidden records included, deleted ones excluded. 0 = column is empty.
     */
    public function findLastContentElementUidInColumn(int $pid, int $colPos): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $uid = $queryBuilder
            ->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter($colPos, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_oid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('sorting', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return (int)$uid;
    }

    /**
     * @param list<string> $importTypes
     */
    public function updateDocsConfig(
        int $userID,
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        array $importTypes
    ): bool
    {
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
                $clientId,
                true,
                ParameterType::STRING
            )
            ->set(
                'tx_nsgoogledocs_client_secret',
                $clientSecret,
                true,
                ParameterType::STRING
            )
            ->set(
                'tx_nsgoogledocs_refresh_token',
                $refreshToken,
                true,
                ParameterType::STRING
            )
            ->set(
                'tx_nsgoogledocs_import_type',
                implode(',', $importTypes),
                true,
                ParameterType::STRING
            );

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
        $this->deleteEmptyApiData();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function checkApiData(): array|false
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $cutoff = (new \DateTimeImmutable('-2 weeks'))->format('Y-m-d');
        $row = $queryBuilder
            ->select('*')
            ->from('tx_nsgoogledocs_domain_model_apidata')
            ->where(
                $queryBuilder->expr()->eq(
                    'extension_key',
                    $queryBuilder->createNamedParameter('ns_googledocs')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->gte(
                    'last_update',
                    $queryBuilder->createNamedParameter($cutoff)
                )
            )
            ->orderBy('id', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false || !$this->apiRowHasContent($row)) {
            return false;
        }
        return $row;
    }

    /**
     * Marketing snippets only — keep a short timeout so a down API cannot stall the module.
     */
    public function curlInitCall(string $url, int $timeout = 2): string|false
    {
        $fetcher = $this->remoteContentFetcher
            ?? GeneralUtility::makeInstance(RemoteContentFetcher::class);
        $result = $fetcher->fetchText($url, $timeout);
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

    /**
     * @param array<string, mixed> $row
     */
    public function apiRowHasContent(array $row): bool
    {
        foreach (['support_html', 'footer_html'] as $field) {
            if (trim((string)($row[$field] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private function deleteEmptyApiData(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $rows = $queryBuilder
            ->select('id', 'support_html', 'footer_html')
            ->from('tx_nsgoogledocs_domain_model_apidata')
            ->executeQuery()
            ->fetchAllAssociative();
        foreach ($rows as $row) {
            if ($this->apiRowHasContent($row)) {
                continue;
            }
            $delete = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
            $delete
                ->delete('tx_nsgoogledocs_domain_model_apidata')
                ->where(
                    $delete->expr()->eq(
                        'id',
                        $delete->createNamedParameter((int)($row['id'] ?? 0), ParameterType::INTEGER)
                    )
                )
                ->executeStatement();
        }
    }

    /**
     * @param list<int|string>|array<int, int|string> $arguments
     */
    private function translateLabel(string $key, string $fallback, array $arguments = []): string
    {
        try {
            $label = LocalizationUtility::translate($key, 'ns_googledocs', $arguments !== [] ? $arguments : null);
        } catch (\Throwable) {
            $label = null;
        }
        if (!is_string($label) || $label === '' || $label === $key) {
            return $arguments !== [] ? sprintf($fallback, ...$arguments) : $fallback;
        }
        return $label;
    }
}
