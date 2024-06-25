<?php

use NITSAN\NsGoogledocs\Controller\UserInfoController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die('Access denied.');


if ((GeneralUtility::makeInstance(Typo3Version::class))->getMajorVersion() < 12) {
    if (!array_key_exists('nitsan', $GLOBALS['TBE_MODULES'])) {
        $GLOBALS['TBE_MODULES'] = array_slice($GLOBALS['TBE_MODULES'], 0, 1, true) + ['nitsan' => ''] + array_slice($GLOBALS['TBE_MODULES'], 1, count($GLOBALS['TBE_MODULES']) - 1, true);
            $GLOBALS['TBE_MODULES']['_configuration']['nitsan'] = [
                'iconIdentifier' => 'googledocs',
                'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/BackendModule.xlf',
                'name' => 'nitsan'
            ];
    }
    $userInfo = UserInfoController::class;
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
            'icon' => 'EXT:ns_googledocs/Resources/Public/Icons/googledocs.svg',
            'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_googledocs.xlf',
            'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
            'inheritNavigationComponentFromMainModule' => false
        ]
    );
}
