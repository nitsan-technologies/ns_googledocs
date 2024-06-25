<?php

use NITSAN\NsGoogledocs\Controller\UserInfoController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$module = [
    'nitsan_nsgoogledocs' => [
        'parent' => 'nitsan_module',
        'position' => ['before' => 'top'],
        'access' => 'user',
        'path' => '/module/nitsan/NsGoogledocsNitsan',
        'icon' => 'EXT:ns_googledocs/Resources/Public/Icons/googledocs.svg',
        'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_googledocs.xlf',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'extensionName' => 'NsGoogledocs',
        'controllerActions' => [
            UserInfoController::class => [
                'dashboard',
                'import',
                'reports',
                'globalSettings',
                'premium',
                'docsImport',
                'update'
            ],
        ],
    ],
];

if (!ExtensionManagementUtility::isLoaded('ns_basetheme')) {
    $module['nitsan_module'] = [
        'labels' => 'LLL:EXT:ns_googledocs/Resources/Private/Language/BackendModule.xlf',
        'icon' => 'EXT:ns_googledocs/Resources/Public/Icons/module-nsgoogledocs.svg',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'position' => ['after' => 'web'],
    ];
}

return $module;
