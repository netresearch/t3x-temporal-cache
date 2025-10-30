<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as Typo3ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Central configuration management for temporal cache extension.
 *
 * Provides type-safe access to all extension configuration with sensible defaults.
 */
class ExtensionConfiguration implements SingletonInterface
{
    private const EXT_KEY = 'nr_temporal_cache';

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly Typo3ExtensionConfiguration $extensionConfiguration
    ) {
        $this->config = $this->extensionConfiguration->get(self::EXT_KEY) ?? [];
    }

    // Scoping Configuration

    public function getScopingStrategy(): string
    {
        return $this->config['scoping']['strategy'] ?? 'global';
    }

    public function useRefindex(): bool
    {
        return (bool)($this->config['scoping']['use_refindex'] ?? true);
    }

    // Timing Configuration

    public function getTimingStrategy(): string
    {
        return $this->config['timing']['strategy'] ?? 'dynamic';
    }

    public function getSchedulerInterval(): int
    {
        return \max(60, (int)($this->config['timing']['scheduler_interval'] ?? 60));
    }

    /**
     * @return array<string, string>
     */
    public function getTimingRules(): array
    {
        return [
            'pages' => $this->config['timing']['hybrid']['pages'] ?? 'dynamic',
            'content' => $this->config['timing']['hybrid']['content'] ?? 'scheduler',
        ];
    }

    // Harmonization Configuration

    public function isHarmonizationEnabled(): bool
    {
        return (bool)($this->config['harmonization']['enabled'] ?? false);
    }

    /**
     * @return string[]
     */
    public function getHarmonizationSlots(): array
    {
        $slots = $this->config['harmonization']['slots'] ?? '00:00,06:00,12:00,18:00';
        return \array_map('trim', \explode(',', $slots));
    }

    public function getHarmonizationTolerance(): int
    {
        return (int)($this->config['harmonization']['tolerance'] ?? 3600);
    }

    public function isAutoRoundEnabled(): bool
    {
        return (bool)($this->config['harmonization']['auto_round'] ?? false);
    }

    // Advanced Configuration

    public function getDefaultMaxLifetime(): int
    {
        return (int)($this->config['advanced']['default_max_lifetime'] ?? 86400);
    }

    public function isDebugLoggingEnabled(): bool
    {
        return (bool)($this->config['advanced']['debug_logging'] ?? false);
    }

    // Convenience Methods

    public function isPerContentScoping(): bool
    {
        return $this->getScopingStrategy() === 'per-content';
    }

    public function isSchedulerTiming(): bool
    {
        return $this->getTimingStrategy() === 'scheduler';
    }

    public function isHybridTiming(): bool
    {
        return $this->getTimingStrategy() === 'hybrid';
    }

    public function isDynamicTiming(): bool
    {
        return $this->getTimingStrategy() === 'dynamic';
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->config;
    }
}
