<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

/**
 * Icon configuration for nr_temporal_cache extension
 *
 * TYPO3 13 LTS standard: Icons are registered via Configuration/Icons.php
 * instead of ext_localconf.php to avoid deprecation warnings.
 */
return [
    'temporal-cache-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:nr_temporal_cache/Resources/Public/Icons/Extension.svg',
    ],
];
