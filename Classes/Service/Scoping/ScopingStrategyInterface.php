<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use TYPO3\CMS\Core\Context\Context;

/**
 * Interface for scoping strategies that determine which caches to invalidate.
 *
 * Strategy Pattern: Allows switching between global, per-page, and per-content scoping.
 */
interface ScopingStrategyInterface
{
    /**
     * Get cache tags to flush when temporal content transitions.
     *
     * @param TemporalContent $content The content that transitioned
     * @param Context $context TYPO3 context (workspace, language)
     * @return array<string> Array of cache tags to flush
     */
    public function getCacheTagsToFlush(TemporalContent $content, Context $context): array;

    /**
     * Get next temporal transition timestamp for cache lifetime calculation.
     *
     * @param Context $context TYPO3 context
     * @return int|null Timestamp of next transition or null if none
     */
    public function getNextTransition(Context $context): ?int;

    /**
     * Get strategy name for logging and debugging.
     */
    public function getName(): string;
}
