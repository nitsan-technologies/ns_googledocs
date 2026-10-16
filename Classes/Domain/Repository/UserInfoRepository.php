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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * The repository for UserInfos
 */
class UserInfoRepository extends Repository
{
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
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $header = $element['header'] ?? '';
        $headerLayout = $element['header_layout'] ?? 0;
        $colPos = (int)($request['colPos'] ?? 0);

        switch ($element['CType'] ?? '') {
            case 'image':
                $connection->insert(
                    'tt_content',
                    [
                        'pid' => $pageID,
                        'CType' => $element['CType'],
                        'colPos' => $colPos,
                        'sys_language_uid' => 0,
                        'sorting' => $element['sorting'] ?? 0,
                        'header' => $header,
                        'header_layout' => $headerLayout,
                        'image' => count($element['images'] ?? []),
                        'imageorient' => $element['imageorient'] ?? 0,
                        'imagecols' => 1,
                    ]
                );
                break;
            case 'textpic':
                $connection->insert(
                    'tt_content',
                    [
                        'pid' => $pageID,
                        'CType' => $element['CType'],
                        'header' => $header,
                        'header_layout' => $headerLayout,
                        'bodytext' => $element['bodytext'] ?? '',
                        'colPos' => $colPos,
                        'sys_language_uid' => 0,
                        'sorting' => $element['sorting'] ?? 0,
                        'image' => count($element['images'] ?? []),
                        'imageorient' => $element['imageorient'] ?? 0,
                        'imagecols' => 1,
                    ]
                );
                break;
            default:
                $connection->insert(
                    'tt_content',
                    [
                        'pid' => $pageID,
                        'CType' => $element['CType'] ?? 'text',
                        'header' => $header,
                        'header_layout' => $headerLayout,
                        'bodytext' => $element['bodytext'] ?? '',
                        'colPos' => $colPos,
                        'sys_language_uid' => 0,
                        'sorting' => $element['sorting'] ?? 0,
                    ]
                );
                break;
        }

        $lastUid = (int)$connection->lastInsertId('tt_content');
        if (
            ($element['CType'] === 'image' || $element['CType'] === 'textpic')
            && $lastUid > 0
            && !empty($element['images'])
        ) {
            $refConnection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_file_reference');
            foreach ($element['images'] as $key => $img) {
                $refConnection->insert(
                    'sys_file_reference',
                    [
                        'uid_local' => $img,
                        'uid_foreign' => $lastUid,
                        'tablenames' => 'tt_content',
                        'fieldname' => 'image',
                        'sorting_foreign' => 1 + (int)$key,
                        'table_local' => 'sys_file',
                    ]
                );
            }
        }

        return $lastUid > 0;
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
            ->set('client_secret', (string)($request['GoogleDocsSecretKey'] ?? ''), true, ParameterType::STRING)
            ->set('client_id', (string)($request['GoogleDocsClientId'] ?? ''), true, ParameterType::STRING)
            ->set('refresh_token', (string)($request['GoogleDocsRefreshToken'] ?? ''), true, ParameterType::STRING);

        if (!empty($request['importType']) && is_array($request['importType'])) {
            $queryBuilder->set(
                'import_type',
                implode(',', $request['importType']),
                true,
                ParameterType::STRING
            );
        } else {
            $queryBuilder->set('import_type', '', true, ParameterType::STRING);
        }

        try {
            $queryBuilder->executeStatement();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteOldApiData(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $queryBuilder
            ->delete('tx_nsgoogledocs_domain_model_apidata')
            ->where(
                $queryBuilder->expr()->comparison('last_update', '<', 'DATE_SUB(NOW() , INTERVAL 2 WEEK)')
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
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($curlSession);
        curl_close($curlSession);

        return $data;
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
