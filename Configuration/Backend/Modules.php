<?php

use NITSAN\NsGoogledocs\Controller\UserInfoController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$typo3MajorVersion = (GeneralUtility::makeInstance(Typo3Version::class))->getMajorVersion();
$navigationComponent = $typo3MajorVersion <= 13
    ? '@typo3/backend/page-tree/page-tree-element'
    : '@typo3/backend/tree/page-tree-element';

$module = [
    'nitsan_nsgoogledocs' => [
        'parent' => 'nitsan_module',
        'position' => ['before' => 'top'],
        'access' => 'user',
        'path' => '/module/nitsan/NsGoogledocsNitsan',
        'icon' => 'EXT:ns_googledocs/Resources/Public/Icons/googledocs.svg',
        'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_googledocs.xlf',
        'navigationComponent' => $navigationComponent,
        'extensionName' => 'NsGoogledocs',
        'controllerActions' => [
            UserInfoController::class => [
                'dashboard',
                'import',
                'docsImport',
                'globalSettings',
                'update',
            ],
        ],
    ],
];

if (!ExtensionManagementUtility::isLoaded('ns_basetheme')) {
    $module['nitsan_module'] = [
        'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/BackendModule.xlf',
        'icon' => 'EXT:ns_googledocs/Resources/Public/Icons/googledocs.svg',
        'navigationComponent' => $navigationComponent,
        'position' => ['after' => $typo3MajorVersion >= 14 ? 'content' : 'web'],
    ];
}

return $module;
