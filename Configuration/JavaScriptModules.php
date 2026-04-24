<?php

declare(strict_types=1);

return [
    'dependencies' => ['backend'],
    'tags' => ['backend.module'],
    'imports' => [
        '@ayacoo/video-validator/' => 'EXT:video_validator/Resources/Public/JavaScript/',
    ],
];
