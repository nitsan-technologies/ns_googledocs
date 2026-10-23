<?php

defined('TYPO3') || die();

$languageFile = 'LLL:EXT:ns_googledocs/Resources/Private/Language/locallang_db.xlf:';

$temporaryColumns = [
    'tx_nsgoogledocs_client_id' => [
        'exclude' => true,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.client_id',
        'config' => [
            'type' => 'input',
            'size' => 40,
            'eval' => 'trim',
            'max' => 255,
        ],
    ],
    // Keep type=input (not password): Core hashes password fields on be_users, which would break OAuth secrets.
    'tx_nsgoogledocs_client_secret' => [
        'exclude' => true,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.client_secret',
        'config' => [
            'type' => 'input',
            'size' => 40,
            'eval' => 'trim',
            'max' => 255,
        ],
    ],
    'tx_nsgoogledocs_refresh_token' => [
        'exclude' => true,
        'label' => $languageFile . 'tx_nsgoogledocs_domain_model_userinfo.refresh_token',
        'config' => [
            'type' => 'input',
            'size' => 40,
            'eval' => 'trim',
            'max' => 255,
        ],
    ],
    'tx_nsgoogledocs_import_type' => [
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
    'tx_nsgoogledocs_client_id, tx_nsgoogledocs_client_secret, tx_nsgoogledocs_refresh_token, tx_nsgoogledocs_import_type'
);
