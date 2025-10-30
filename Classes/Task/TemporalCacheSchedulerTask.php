<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Task;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task for processing temporal content transitions.
 *
 * This task:
 * 1. Finds all transitions that occurred since last run
 * 2. Creates TransitionEvent objects for each transition
 * 3. Delegates to timing strategy for cache invalidation
 *
 * Required for scheduler and hybrid timing strategies.
 * Not needed for dynamic timing strategy (event-based).
 */
final class TemporalCacheSchedulerTask extends AbstractTask
{
    private const REGISTRY_NAMESPACE = 'tx_temporalcache';
    private const REGISTRY_KEY_LAST_RUN = 'scheduler_last_run';

    private ?TemporalContentRepository $repository = null;
    private ?TimingStrategyInterface $timingStrategy = null;
    private ?ExtensionConfiguration $extensionConfiguration = null;
    private ?Context $context = null;
    private ?Registry $registry = null;
    private ?LoggerInterface $logger = null;

    /**
     * Inject dependencies via setter methods for scheduler compatibility.
     * TYPO3 scheduler requires tasks to be serializable, so we use lazy injection.
     */
    public function injectTemporalContentRepository(TemporalContentRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function injectTimingStrategy(TimingStrategyInterface $timingStrategy): void
    {
        $this->timingStrategy = $timingStrategy;
    }

    public function injectExtensionConfiguration(ExtensionConfiguration $extensionConfiguration): void
    {
        $this->extensionConfiguration = $extensionConfiguration;
    }

    public function injectContext(Context $context): void
    {
        $this->context = $context;
    }

    public function injectRegistry(Registry $registry): void
    {
        $this->registry = $registry;
    }

    public function injectLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Execute the scheduler task.
     *
     * @return bool TRUE if task executed successfully, FALSE on failure
     */
    public function execute(): bool
    {
        // Validate dependencies were injected
        if (!$this->validateDependencies()) {
            $this->logError('Missing required dependencies - check DI configuration');
            return false;
        }

        try {
            $lastRun = $this->getLastRunTimestamp();
            $now = \time();

            $this->logDebug('Scheduler task started', [
                'last_run' => $lastRun ? \date('Y-m-d H:i:s', $lastRun) : 'never',
                'current_time' => \date('Y-m-d H:i:s', $now),
                'timing_strategy' => $this->timingStrategy->getName(),
            ]);

            // Find all transitions that occurred since last run
            $transitions = $this->repository->findTransitionsInRange($lastRun ?? 0, $now);

            if (empty($transitions)) {
                $this->logDebug('No transitions found in time range');
                $this->setLastRunTimestamp($now);
                return true;
            }

            // Process each transition through timing strategy
            $processedCount = 0;
            $errorCount = 0;

            foreach ($transitions as $temporalContent) {
                try {
                    $event = new TransitionEvent(
                        content: $temporalContent,
                        timestamp: $now, // Simplified - actual transition time may vary
                        transitionType: $temporalContent->getTransitionType($now) ?? 'unknown',
                        workspaceId: $this->context->getPropertyFromAspect('workspace', 'id'),
                        languageId: $this->context->getPropertyFromAspect('language', 'id')
                    );

                    $this->timingStrategy->processTransition($event);
                    $processedCount++;
                } catch (\Throwable $e) {
                    $errorCount++;
                    $this->logError('Failed to process transition', [
                        'content_uid' => $temporalContent->uid,
                        'table' => $temporalContent->tableName,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $this->logInfo('Scheduler task completed', [
                'transitions_found' => \count($transitions),
                'transitions_processed' => $processedCount,
                'errors' => $errorCount,
            ]);

            // Update last run timestamp on success
            $this->setLastRunTimestamp($now);

            // Return true if at least some transitions processed
            return $errorCount === 0 || $processedCount > 0;
        } catch (\Throwable $e) {
            $this->logError('Scheduler task failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Validate all dependencies are available.
     */
    private function validateDependencies(): bool
    {
        return $this->repository !== null
            && $this->timingStrategy !== null
            && $this->extensionConfiguration !== null
            && $this->context !== null
            && $this->registry !== null
            && $this->logger !== null;
    }

    /**
     * Get timestamp of last successful run.
     */
    private function getLastRunTimestamp(): ?int
    {
        $lastRun = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY_LAST_RUN);
        return \is_int($lastRun) ? $lastRun : null;
    }

    /**
     * Store timestamp of current run.
     */
    private function setLastRunTimestamp(int $timestamp): void
    {
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY_LAST_RUN, $timestamp);
    }

    /**
     * Get additional information for scheduler module display.
     *
     * @return string Human-readable task information
     */
    public function getAdditionalInformation(): string
    {
        if (!$this->validateDependencies()) {
            return 'Dependencies not initialized';
        }

        $lastRun = $this->getLastRunTimestamp();
        $strategy = $this->timingStrategy->getName();

        $info = [
            'Timing Strategy: ' . $strategy,
        ];

        if ($lastRun !== null) {
            $info[] = 'Last Run: ' . \date('Y-m-d H:i:s', $lastRun);
        } else {
            $info[] = 'Last Run: Never';
        }

        return \implode(' | ', $info);
    }

    /**
     * Log debug message if debug logging enabled.
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->extensionConfiguration->isDebugLoggingEnabled()) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Log info message.
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log error message.
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
