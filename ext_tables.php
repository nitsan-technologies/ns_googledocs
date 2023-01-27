<?php

use NITSAN\NsGoogledocs\Controller\UserInfoController;

defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function () {
        if (TYPO3_MODE === 'BE') {
            $isVersion9Up = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 9000000;

            if (!array_key_exists('nitsan', $GLOBALS['TBE_MODULES'])) {
                $GLOBALS['TBE_MODULES'] = array_slice($GLOBALS['TBE_MODULES'], 0, 1, true) + ['nitsan' => ''] + array_slice($GLOBALS['TBE_MODULES'], 1, count($GLOBALS['TBE_MODULES']) - 1, true);

                if (version_compare(TYPO3_branch, '8.0', '>=')) {
                    $GLOBALS['TBE_MODULES']['_configuration']['nitsan'] = [
                        'iconIdentifier' => 'googledocs',
                        'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/BackendModule.xlf',
                        'name' => 'nitsan'
                    ];
                }
            }
            $userInfo = 'UserInfo';
            if (version_compare(TYPO3_branch, '10.0', '>=')) {
                $userInfo = UserInfoController::class;
            }
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'NITSAN.NsGoogledocs',
                'nitsan', // Make module a submodule of 'tools'
                'googledocs', // Submodule key
                '', // Position
                [
                    $userInfo => 'dashboard, import, reports, globalSettings, premium, docsImport, update',
                ],
                [
                    'access' => 'user,group',
                    'icon'   => 'EXT:ns_googledocs/Resources/Public/Icons/googledocs.svg',
                    'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_googledocs.xlf',
                    'navigationComponentId' => ($isVersion9Up ? 'TYPO3/CMS/Backend/PageTree/PageTreeElement' : 'typo3-pagetree'),
                    'inheritNavigationComponentFromMainModule' => false
                ]
            );
        }

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_nsgoogledocs_domain_model_userinfo', 'EXT:ns_googledocs/Resources/Private/Language/locallang_csh_tx_nsgoogledocs_domain_model_userinfo.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_nsgoogledocs_domain_model_userinfo');
    }
);
