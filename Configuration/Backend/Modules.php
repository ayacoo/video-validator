<?php

declare(strict_types=1);

use Ayacoo\VideoValidator\Controller\Backend\VideoOverviewController;

return [
    'file_videovalidator' => [
        'parent' => 'file',
        'access' => 'user',
        'path' => '/module/file/videovalidator',
        'iconIdentifier' => 'module-video-validator',
        'labels' => 'LLL:EXT:video_validator/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'VideoValidator',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponentId' => '',
        'routes' => [
            '_default' => [
                'target' => VideoOverviewController::class . '::handleRequest',
            ],
        ],
    ],
];
