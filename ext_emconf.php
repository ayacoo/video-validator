<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Video validator',
    'description' => 'Checks online videos in TYPO3 for availability',
    'category' => 'plugin',
    'author' => 'Guido Schmechel',
    'author_email' => 'info@ayacoo.de',
    'state' => 'stable',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '4.1.1',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.3.99',
            'typo3' => '13.0.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
