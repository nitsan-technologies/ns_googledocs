<?php

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@nitsan/ns-googledocs/' => 'EXT:ns_googledocs/Resources/Public/JavaScript/',
    ],
];
