<?php

declare(strict_types=1);

use Netresearch\TemporalCache\Controller\Backend\TemporalCacheController;

/**
 * Backend routes configuration for Temporal Cache module.
 */
return [
    'nr_temporal_cache_dashboard' => [
        'path' => '/temporal-cache/dashboard',
        'target' => TemporalCacheController::class . '::dashboardAction',
    ],
    'nr_temporal_cache_content' => [
        'path' => '/temporal-cache/content',
        'target' => TemporalCacheController::class . '::contentAction',
    ],
    'nr_temporal_cache_wizard' => [
        'path' => '/temporal-cache/wizard',
        'target' => TemporalCacheController::class . '::wizardAction',
    ],
    'nr_temporal_cache_harmonize' => [
        'path' => '/temporal-cache/harmonize',
        'target' => TemporalCacheController::class . '::harmonizeAction',
    ],
];
