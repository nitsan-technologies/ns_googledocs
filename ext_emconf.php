<?php

$EM_CONF['ns_googledocs'] = [
    'title' => 'Google Docs to TYPO3 Importer',
    'description' => "Tired of copying content manually from Google Docs to TYPO3? This extension lets you import Google Docs into TYPO3 pages, blogs, and news with a single click—saving time and improving productivity.",
    
    'category' => 'module',
    'author' => 'Team T3Planet',
    'author_email' => 'info@t3planet.de',
    'author_company' => 'T3Planet',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '2.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-11.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
