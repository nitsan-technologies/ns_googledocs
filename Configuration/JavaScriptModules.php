<?php

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.module',
    ],
    'imports' => [
        '@nitsan/ns-googledocs/' => 'EXT:ns_googledocs/Resources/Public/JavaScript/',
    ],
];
