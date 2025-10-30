<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Temporal Cache Management',
    'description' => 'Solves TYPO3 Forge #14277 with flexible cache strategies. Automatic cache invalidation for time-based content. Features: 10-50× optimized queries, CLI commands, Reports module integration, enhanced security. Three scoping strategies (global/per-page/per-content) and timing options (dynamic/scheduler/hybrid) for optimal performance.',
    'category' => 'fe',
    'author' => 'Netresearch',
    'author_email' => 'typo3@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state' => 'beta',
    'version' => '0.9.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.9.99',
            'php' => '8.1.0-8.3.99',
            'scheduler' => '12.4.0-13.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Netresearch\\TemporalCache\\' => 'Classes/',
        ],
    ],
];
