<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}
$laguageFile = 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_db.xlf:';
// Add some fields to FE Users table to show TCA fields definitions
$temporaryColumns  = [
    'client_id'  => [
        'exclude' => 0,
        'label' => $laguageFile . 'tx_nsgoogledocs_domain_model_userinfo.client_id',
        'config' => [
                'type' => 'input',
                'size' => 40,
                'eval' => 'trim',
                'max' => 255,
            ]
    ],
    'client_secret'  => [
        'exclude' => 0,
        'label' => $laguageFile . 'tx_nsgoogledocs_domain_model_userinfo.client_secret',
        'config' => [
                'type' => 'input',
                'size' => 40,
                'eval' => 'trim',
                'max' => 255,
            ]
    ],
    'refresh_token'  => [
        'exclude' => 0,
        'label' => $laguageFile . 'tx_nsgoogledocs_domain_model_userinfo.refresh_token',
        'config' => [
                'type' => 'input',
                'size' => 40,
                'eval' => 'trim',
                'max' => 255,
            ]
    ],
    'import_type' => [
        'exclude' => true,
        'label' => $laguageFile . 'tx_nsgoogledocs_domain_model_userinfo.import_type',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectMultipleSideBySide',
            'items' => [
                [$laguageFile . 'tx_nsgoogledocs_domain_model_userinfo.import_type.1', 1]
            ],
            'default' => '1',
        ],
    ],

];

ExtensionManagementUtility::addTCAcolumns(
    'be_users',
    $temporaryColumns
);
ExtensionManagementUtility::addToAllTCAtypes(
    'be_users',
    'client_id, client_secret, refresh_token, import_type'
);
