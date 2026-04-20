<?php

use Ayacoo\VideoValidator\Controller\Backend\VideoRefreshAjaxController;

return [
    'videovalidator_refresh' => [
        'path' => '/videovalidator/refresh',
        'target' => VideoRefreshAjaxController::class . '::refresh',
        'access' => 'user',
    ],
];
