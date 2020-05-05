<?php
namespace NITSAN\NsGoogledocs\Domain\Repository;

/***
 *
 * This file is part of the "[NITSAN] Googledocs" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The repository for UserInfos
 */
class UserInfoRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{

    // change default query settings
    public function initializeObject()
    {
        // get the current settings
        $querySettings = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Typo3QuerySettings');
        // change the default setting, whether the storage page ID is ignored by the plugins (FALSE) or not (TRUE - default setting)
        $querySettings->setRespectStoragePage(false);
        // store the new setting(s)
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param int $pageID
     * @param array|null $element
     * @param array|null $request
     */
    public function insertPageElement($pageID, $element, $request)
    {
        $status = 0;
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        switch ($element['CType']) {
            case 'image':
                $queryBuilder
                    ->insert(
                        'tt_content',
                        [
                            'pid' => $pageID,
                            'CType' => $element['CType'],
                            'colPos' => $request['colPos'],
                            'sys_language_uid' => 0,
                            'sorting' => $element['sorting'],
                            'header' => (!is_null($element['header']) ? $element['header'] : ''),
                            'header_layout' => (!is_null($element['header_layout']) ? $element['header_layout'] : 0),
                            'image' => count($element['images']),
                            'imageorient' => $element['imageorient'],
                            'imagecols' => 1
                      ]
                    );
                break;
            case 'textpic':
                $queryBuilder
                    ->insert(
                        'tt_content',
                        [
                            'pid' => $pageID,
                            'CType' => $element['CType'],
                            'header' => (!is_null($element['header']) ? $element['header'] : ''),
                            'header_layout' => (!is_null($element['header_layout']) ? $element['header_layout'] : 0),
                            'bodytext' => $element['bodytext'],
                            'colPos' => $request['colPos'],
                            'sys_language_uid' => 0,
                            'sorting' => $element['sorting'],
                            'image' => count($element['images']),
                            'imageorient' => $element['imageorient'],
                            'imagecols' => 1
                      ]
                    );
                break;
            default:
                $queryBuilder
                    ->insert(
                        'tt_content',
                        [
                            'pid' => $pageID,
                            'CType' => $element['CType'],
                            'header' => (!is_null($element['header']) ? $element['header'] : ''),
                            'header_layout' => (!is_null($element['header_layout']) ? $element['header_layout'] : 0),
                            'bodytext' => $element['bodytext'],
                            'colPos' => $request['colPos'],
                            'sys_language_uid' => 0,
                            'sorting' => $element['sorting']
                      ]
                    );
                break;
        }
        // find the last inserted records uid
        $lastUid =  (int)$queryBuilder->lastInsertId('tt_content');
        if (($element['CType'] == 'image' || $element['CType'] == 'textpic') && ($lastUid != '' || !is_null($lastUid))) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
            foreach ($element['images'] as $key => $img) {
                $sortingForeign = 1 + $key;
                $queryBuilder
                    ->insert(
                        'sys_file_reference',
                        [
                            'uid_local' => $img,
                            'uid_foreign' => $lastUid,
                            'tablenames' => 'tt_content',
                            'fieldname' => 'image',
                            'sorting_foreign' => $sortingForeign,
                            'table_local' => 'sys_file'
                      ]
                    );
            }
        }
        if ($lastUid) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param int $pid
     * @return array|null
     */
    public function findElementSorting($pid)
    {
        $finalReports = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content')->createQueryBuilder();
        $result = $queryBuilder
            ->addSelectLiteral(
                $queryBuilder->expr()->max('sorting', 'sorting')
            )
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $pid)
            )
            ->execute()
            ->fetchAll();
        if (is_null($result[0]['sorting'])) {
            return 0;
        } else {
            return $result[0]['sorting'];
        }
    }

    /**
     *
     * @param int $userID
     * @param array|null $request
     */
    public function updateDocsConfig($userID, $request)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');
        $queryBuilder
            ->update('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userID, \PDO::PARAM_INT))
            )
            ->set('client_secret', $request['GoogleDocsSecretKey'])
            ->set('client_id', $request['GoogleDocsClientId'])
            ->set('refresh_token', $request['GoogleDocsRefreshToken'])
            ->set('google_files_request', $request['GoogleDocsApiRequest']);
        if ($request['importType'] != '') {
            $queryBuilder->set('import_type', implode(',', $request['importType']));
        } else {
            $queryBuilder->set('import_type', '');
        }
        try {
            $queryBuilder->execute();
            $error = 0;
        } catch (DBALException $e) {
            $error = 1;
        }
        if ($error) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Remove old entries from database
     */
    public function deleteOldApiData()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $queryBuilder
            ->delete('tx_nsgoogledocs_domain_model_apidata')
            ->where(
                $queryBuilder->expr()->comparison('last_update', '<', 'DATE_SUB(NOW() , INTERVAL 2 WEEK)')
            )
            ->execute();
    }
    /**
     *
     * @return array|null
     */
    public function checkApiData()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $queryBuilder
            ->select('*')
            ->from('tx_nsgoogledocs_domain_model_apidata');
        $query = $queryBuilder->execute();
        return $query->fetch();
    }

    /**
     *
     * @return array|null
     */
    public function curlInitCall($url)
    {
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($curlSession);
        curl_close($curlSession);

        return $data;
    }
    /**
     *
     * @param array|null $data
     */
    public function insertNewData($data)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_nsgoogledocs_domain_model_apidata');
        $row = $queryBuilder
            ->insert('tx_nsgoogledocs_domain_model_apidata')
            ->values($data);

        $query = $queryBuilder->execute();
        return $query;
    }
}
