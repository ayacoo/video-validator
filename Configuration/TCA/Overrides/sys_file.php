<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$additionalColumns = [
    'validation_date' => [
        'exclude' => true,
        'label' => 'Validation Date',
        'config' => [
            'type' => 'datetime',
            'default' => 0,
        ],
    ],
    'validation_status' => [
        'exclude' => true,
        'label' => 'Validation Status',
        'config' => [
            'type' => 'number',
            'default' => 0,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('sys_file', $additionalColumns);
ExtensionManagementUtility::addToAllTCAtypes('sys_file', 'validation_date, validation_status');
