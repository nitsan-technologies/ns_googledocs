<?php

$EM_CONF['ns_googledocs'] = [
    'title' => '[NITSAN] Google Docs',
    'description' => 'Import your Google Docs to your TYPO3 site',
    'category' => 'module',
    'author' => 'T3: Keval Pandya, QA: Siddharth Sheth',
    'author_email' => 'sanjay@nitsan.in',
    'author_company' => 'NITSAN Technologies Pvt Ltd',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-10.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
