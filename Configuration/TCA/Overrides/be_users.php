<?php

defined('TYPO3') || die();

$languageFile = 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_db.xlf:';

$temporaryColumns = [
    'client_id' => [
        'exclude' => 0,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.client_id',
        'config' => [
            'type' => 'input',
            'size' => 40,
            'eval' => 'trim',
            'max' => 255,
        ],
    ],
    'client_secret' => [
        'exclude' => 0,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.client_secret',
        'config' => [
            'type' => 'input',
            'size' => 40,
            'eval' => 'trim',
            'max' => 255,
        ],
    ],
    'refresh_token' => [
        'exclude' => 0,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.refresh_token',
        'config' => [
            'type' => 'input',
            'size' => 40,
            'eval' => 'trim',
            'max' => 255,
        ],
    ],
    'import_type' => [
        'exclude' => true,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.import_type',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectMultipleSideBySide',
            'items' => [
                [
                    'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.import_type.1',
                    'value' => 1,
                ],
            ],
            'default' => '1',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'be_users',
    $temporaryColumns
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'be_users',
    'client_id, client_secret, refresh_token, import_type'
);
