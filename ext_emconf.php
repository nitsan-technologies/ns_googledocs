<?php

$EM_CONF['ns_googledocs'] = [
    'title' => 'Google Docs to TYPO3 Importer',
    'description' => 'Tired of copying content manually from Google Docs to TYPO3? This extension lets you import Google Docs into TYPO3 pages with a single click—saving time and improving productivity. Compatible with TYPO3 v12, v13 and v14.',
    'category' => 'module',
    'author' => 'Team T3Planet',
    'author_email' => 'info@t3planet.de',
    'author_company' => 'T3Planet',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'version' => '14.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'php' => '8.1.0-8.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'NITSAN\\NsGoogledocs\\' => 'Classes/',
        ],
    ],
];
