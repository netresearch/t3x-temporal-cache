<?php

declare(strict_types=1);

namespace TYPO3\CMS\Core\Cache\Event;

/**
 * Test stub for TYPO3 13 ModifyCacheLifetimeForPageEvent.
 *
 * This stub allows unit tests to run without requiring full TYPO3 installation.
 * Mimics the essential interface used by TemporalCacheLifetime event listener.
 *
 * @internal For testing purposes only
 */
class ModifyCacheLifetimeForPageEvent
{
    private ?int $cacheLifetime = null;

    /**
     * @param array<string, mixed> $renderingInstructions
     */
    public function __construct(
        private array $renderingInstructions = []
    ) {
    }

    /**
     * Get rendering instructions (TypoScript configuration).
     *
     * @return array<string, mixed>
     */
    public function getRenderingInstructions(): array
    {
        return $this->renderingInstructions;
    }

    /**
     * Set cache lifetime in seconds.
     */
    public function setCacheLifetime(int $lifetime): void
    {
        $this->cacheLifetime = $lifetime;
    }

    /**
     * Get cache lifetime (for testing assertions).
     */
    public function getCacheLifetime(): ?int
    {
        return $this->cacheLifetime;
    }
}
