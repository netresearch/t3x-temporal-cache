<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Report;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\HarmonizationService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * TYPO3 Reports module status provider for Temporal Cache extension.
 *
 * This report displays comprehensive system health and configuration information
 * in the TYPO3 backend Reports module (Admin Tools > Reports > Status Report).
 *
 * Report sections:
 * - Extension Status: Configuration and operational status
 * - Database Indexes: Performance optimization verification
 * - Temporal Content: Statistics and upcoming transitions
 * - Harmonization: Configuration and potential impact
 *
 * Status levels:
 * - OK (green): Everything working properly, no action needed
 * - WARNING (yellow): Non-critical issues or recommendations for optimization
 * - ERROR (red): Critical issues requiring immediate attention
 *
 * Access: Admin Tools > Reports > Status Report > Temporal Cache
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/SystemReports/Index.html
 */
final class TemporalCacheStatusReport implements StatusProviderInterface
{
    /**
     * Required database indexes for optimal performance.
     */
    private const REQUIRED_INDEXES = [
        'pages' => ['starttime', 'endtime'],
        'tt_content' => ['starttime', 'endtime'],
    ];

    /**
     * Valid configuration values for validation.
     */
    private const VALID_SCOPING_STRATEGIES = ['global', 'per-page', 'per-content'];
    private const VALID_TIMING_STRATEGIES = ['dynamic', 'scheduler', 'hybrid'];

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly TemporalContentRepository $contentRepository,
        private readonly HarmonizationService $harmonizationService,
        private readonly ConnectionPool $connectionPool
    ) {
    }

    /**
     * Get status reports for TYPO3 Reports module.
     *
     * This method is called by TYPO3's Reports module to retrieve all status
     * information. Each status object contains:
     * - Title: Short descriptive title
     * - Value: Current value or status
     * - Message: Detailed explanation or recommendations
     * - Severity: OK, WARNING, or ERROR
     *
     * @return array<Status> Array of status objects
     */
    public function getStatus(): array
    {
        return [
            'extensionStatus' => $this->getExtensionStatus(),
            'databaseIndexes' => $this->getDatabaseIndexesStatus(),
            'temporalContent' => $this->getTemporalContentStatus(),
            'harmonizationStatus' => $this->getHarmonizationStatus(),
            'upcomingTransitions' => $this->getUpcomingTransitionsStatus(),
        ];
    }

    /**
     * Get extension configuration and operational status.
     *
     * Verifies that the extension is properly configured and reports the
     * current operational mode (scoping and timing strategies).
     */
    private function getExtensionStatus(): Status
    {
        $scopingStrategy = $this->extensionConfiguration->getScopingStrategy();
        $timingStrategy = $this->extensionConfiguration->getTimingStrategy();
        $harmonizationEnabled = $this->extensionConfiguration->isHarmonizationEnabled();
        $useRefindex = $this->extensionConfiguration->useRefindex();

        // Validate configuration
        $scopingValid = \in_array($scopingStrategy, self::VALID_SCOPING_STRATEGIES, true);
        $timingValid = \in_array($timingStrategy, self::VALID_TIMING_STRATEGIES, true);

        if (!$scopingValid || !$timingValid) {
            return new Status(
                'Extension Configuration',
                'Invalid Configuration',
                'The extension configuration contains invalid values. ' .
                'Please review your settings in Admin Tools > Settings > Extension Configuration > temporal_cache.',
                ContextualFeedbackSeverity::ERROR
            );
        }

        // Build status message
        $value = 'Enabled';
        $message = '<strong>Active Configuration:</strong>' . \chr(10);
        $message .= '• Scoping Strategy: ' . $this->formatStrategyName($scopingStrategy) . \chr(10);
        $message .= '• Timing Strategy: ' . $this->formatStrategyName($timingStrategy) . \chr(10);
        $message .= '• Harmonization: ' . ($harmonizationEnabled ? 'Enabled' : 'Disabled') . \chr(10);
        $message .= '• Reference Index: ' . ($useRefindex ? 'Enabled' : 'Disabled') . \chr(10) . \chr(10);

        $message .= $this->getStrategyRecommendations($scopingStrategy, $timingStrategy);

        return new Status(
            'Extension Configuration',
            $value,
            $message,
            ContextualFeedbackSeverity::OK
        );
    }

    /**
     * Verify database indexes for performance optimization.
     *
     * Checks that required indexes exist on starttime/endtime fields.
     * Missing indexes can cause severe performance degradation.
     */
    private function getDatabaseIndexesStatus(): Status
    {
        $missingIndexes = [];

        foreach (self::REQUIRED_INDEXES as $tableName => $fields) {
            $connection = $this->connectionPool->getConnectionForTable($tableName);
            $schemaManager = $connection->createSchemaManager();

            try {
                $tableIndexes = $schemaManager->listTableIndexes($tableName);

                foreach ($fields as $field) {
                    if (!$this->checkIndexExists($tableIndexes, [$field])) {
                        $missingIndexes[] = $tableName . '.' . $field;
                    }
                }
            } catch (\Exception $e) {
                return new Status(
                    'Database Indexes',
                    'Verification Failed',
                    'Failed to verify database indexes: ' . $e->getMessage(),
                    ContextualFeedbackSeverity::ERROR
                );
            }
        }

        if (!empty($missingIndexes)) {
            $message = '<strong>Missing Indexes:</strong>' . \chr(10);
            $message .= '• ' . \implode(\chr(10) . '• ', $missingIndexes) . \chr(10) . \chr(10);
            $message .= '<strong>Performance Impact:</strong>' . \chr(10);
            $message .= 'Missing indexes cause slow queries and can severely degrade frontend performance. ' .
                       'Each temporal content lookup will require a full table scan instead of an index lookup.' . \chr(10) . \chr(10);
            $message .= '<strong>Action Required:</strong>' . \chr(10);
            $message .= 'Run the database schema update: Admin Tools > Maintenance > Analyze Database Structure';

            return new Status(
                'Database Indexes',
                'Missing Indexes',
                $message,
                ContextualFeedbackSeverity::ERROR
            );
        }

        $message = 'All required database indexes are present. Performance optimization is active.';

        return new Status(
            'Database Indexes',
            'OK',
            $message,
            ContextualFeedbackSeverity::OK
        );
    }

    /**
     * Get temporal content statistics and overview.
     *
     * Provides insights into how much temporal content exists in the system
     * and its current visibility status.
     */
    private function getTemporalContentStatus(): Status
    {
        try {
            $stats = $this->contentRepository->getStatistics();
            $currentTime = \time();

            // Calculate next transition
            $nextTransition = $this->contentRepository->getNextTransition($currentTime);

            $message = '<strong>Temporal Content Overview:</strong>' . \chr(10);
            $message .= '• Total Items: ' . $stats['total'] . \chr(10);
            $message .= '• Pages: ' . $stats['pages'] . \chr(10);
            $message .= '• Content Elements: ' . $stats['content'] . \chr(10) . \chr(10);

            $message .= '<strong>Temporal Field Distribution:</strong>' . \chr(10);
            $message .= '• With Start Date Only: ' . $stats['withStart'] . \chr(10);
            $message .= '• With End Date Only: ' . $stats['withEnd'] . \chr(10);
            $message .= '• With Both Dates: ' . $stats['withBoth'] . \chr(10) . \chr(10);

            if ($nextTransition !== null) {
                $timeUntil = $nextTransition - $currentTime;
                $message .= '<strong>Next Transition:</strong>' . \chr(10);
                $message .= '• Time: ' . \date('Y-m-d H:i:s', $nextTransition) . \chr(10);
                $message .= '• In: ' . $this->formatDuration($timeUntil) . \chr(10);
            } else {
                $message .= '<strong>Next Transition:</strong> No upcoming transitions scheduled';
            }

            // Determine severity based on content volume
            $severity = ContextualFeedbackSeverity::OK;
            if ($stats['total'] === 0) {
                $severity = ContextualFeedbackSeverity::WARNING;
                $message .= \chr(10) . \chr(10);
                $message .= '<strong>Note:</strong> No temporal content found. ' .
                          'The extension is active but not currently managing any time-based content.';
            }

            return new Status(
                'Temporal Content Statistics',
                $stats['total'] . ' items',
                $message,
                $severity
            );
        } catch (\Exception $e) {
            return new Status(
                'Temporal Content Statistics',
                'Error',
                'Failed to retrieve temporal content statistics: ' . $e->getMessage(),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    /**
     * Get harmonization configuration and impact analysis.
     *
     * Shows harmonization settings and calculates potential cache reduction
     * benefits if harmonization is enabled.
     */
    private function getHarmonizationStatus(): Status
    {
        if (!$this->extensionConfiguration->isHarmonizationEnabled()) {
            $message = '<strong>Status:</strong> Disabled' . \chr(10) . \chr(10);
            $message .= '<strong>About Harmonization:</strong>' . \chr(10);
            $message .= 'Harmonization reduces cache churn by rounding transition times to predefined time slots. ' .
                       'This groups multiple transitions together, reducing the number of cache invalidations.' . \chr(10) . \chr(10);
            $message .= '<strong>Recommendation:</strong>' . \chr(10);
            $message .= 'Consider enabling harmonization if you have many temporal transitions (>10 per day). ' .
                       'This can significantly reduce cache invalidations and improve performance.';

            return new Status(
                'Harmonization',
                'Disabled',
                $message,
                ContextualFeedbackSeverity::INFO
            );
        }

        // Harmonization is enabled - show configuration and impact
        $slots = $this->harmonizationService->getFormattedSlots();
        $tolerance = $this->extensionConfiguration->getHarmonizationTolerance();
        $autoRound = $this->extensionConfiguration->isAutoRoundEnabled();

        $message = '<strong>Status:</strong> Enabled' . \chr(10) . \chr(10);
        $message .= '<strong>Configuration:</strong>' . \chr(10);
        $message .= '• Time Slots: ' . \implode(', ', $slots) . \chr(10);
        $message .= '• Tolerance: ' . $tolerance . ' seconds (' . \round($tolerance / 60) . ' minutes)' . \chr(10);
        $message .= '• Auto-round on Save: ' . ($autoRound ? 'Yes' : 'No') . \chr(10) . \chr(10);

        // Calculate potential impact
        try {
            $allContent = $this->contentRepository->findAllWithTemporalFields();
            $timestamps = [];

            foreach ($allContent as $content) {
                if ($content->starttime !== null) {
                    $timestamps[] = $content->starttime;
                }
                if ($content->endtime !== null) {
                    $timestamps[] = $content->endtime;
                }
            }

            if (!empty($timestamps)) {
                $impact = $this->harmonizationService->calculateHarmonizationImpact($timestamps);

                $message .= '<strong>Current Impact:</strong>' . \chr(10);
                $message .= '• Original Transitions: ' . $impact['original'] . \chr(10);
                $message .= '• After Harmonization: ' . $impact['harmonized'] . \chr(10);
                $message .= '• Cache Reduction: ' . $impact['reduction'] . '%' . \chr(10) . \chr(10);

                if ($impact['reduction'] > 30) {
                    $message .= '<strong>Status:</strong> Harmonization is providing significant cache reduction benefits.';
                } elseif ($impact['reduction'] > 10) {
                    $message .= '<strong>Status:</strong> Harmonization is providing moderate cache reduction benefits.';
                } else {
                    $message .= '<strong>Note:</strong> Current harmonization impact is low. ' .
                              'Consider adjusting time slots or tolerance for better results.';
                }
            } else {
                $message .= '<strong>Note:</strong> No temporal content with transitions found.';
            }
        } catch (\Exception $e) {
            $message .= '<strong>Note:</strong> Could not calculate harmonization impact: ' . $e->getMessage();
        }

        return new Status(
            'Harmonization',
            'Enabled',
            $message,
            ContextualFeedbackSeverity::OK
        );
    }

    /**
     * Get information about upcoming transitions.
     *
     * Shows when the next cache invalidations will occur, helping administrators
     * understand system behavior and plan maintenance windows.
     */
    private function getUpcomingTransitionsStatus(): Status
    {
        try {
            $currentTime = \time();
            $next7Days = $currentTime + (86400 * 7);

            $transitions = $this->contentRepository->findTransitionsInRange(
                $currentTime,
                $next7Days
            );

            if (empty($transitions)) {
                $message = 'No transitions scheduled in the next 7 days. ' .
                          'Page caches will remain stable with no time-based invalidations.';

                return new Status(
                    'Upcoming Transitions',
                    'None',
                    $message,
                    ContextualFeedbackSeverity::OK
                );
            }

            // Group by day
            $transitionsByDay = [];
            foreach ($transitions as $transition) {
                $day = \date('Y-m-d', $transition->transitionTime);
                if (!isset($transitionsByDay[$day])) {
                    $transitionsByDay[$day] = 0;
                }
                $transitionsByDay[$day]++;
            }

            $message = '<strong>Next 7 Days:</strong>' . \chr(10);
            $message .= '• Total Transitions: ' . \count($transitions) . \chr(10);
            $message .= '• Days with Transitions: ' . \count($transitionsByDay) . \chr(10) . \chr(10);

            $message .= '<strong>Daily Breakdown:</strong>' . \chr(10);
            $dayCount = 0;
            foreach ($transitionsByDay as $day => $count) {
                if ($dayCount >= 5) {
                    $remaining = \array_sum(\array_slice($transitionsByDay, 5));
                    if ($remaining > 0) {
                        $message .= '• ... and ' . $remaining . ' more transitions' . \chr(10);
                    }
                    break;
                }
                $dayName = \date('l', \strtotime($day));
                $message .= '• ' . $day . ' (' . $dayName . '): ' . $count . ' transition' . ($count !== 1 ? 's' : '') . \chr(10);
                $dayCount++;
            }

            $message .= \chr(10);
            $message .= '<strong>Note:</strong> Each transition may trigger cache invalidation depending on your scoping strategy.';

            // Warning if too many transitions
            $severity = ContextualFeedbackSeverity::OK;
            $avgPerDay = \count($transitions) / 7;
            if ($avgPerDay > 20) {
                $severity = ContextualFeedbackSeverity::WARNING;
                $message .= \chr(10) . \chr(10);
                $message .= '<strong>High Transition Volume:</strong> You have ' . \round($avgPerDay, 1) . ' transitions per day on average. ' .
                          'Consider enabling harmonization to reduce cache churn.';
            }

            return new Status(
                'Upcoming Transitions',
                \count($transitions) . ' in next 7 days',
                $message,
                $severity
            );
        } catch (\Exception $e) {
            return new Status(
                'Upcoming Transitions',
                'Error',
                'Failed to retrieve upcoming transitions: ' . $e->getMessage(),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    /**
     * Check if an index exists for specified columns.
     *
     * @param array<mixed> $tableIndexes
     * @param array<string> $columns
     */
    private function checkIndexExists(array $tableIndexes, array $columns): bool
    {
        foreach ($tableIndexes as $index) {
            $indexColumns = \array_map(
                fn ($col) => \strtolower($col),
                $index->getColumns()
            );

            $searchColumns = \array_map(
                fn ($col) => \strtolower($col),
                $columns
            );

            // Index covers our columns if exact match or starts with our columns
            if ($indexColumns === $searchColumns ||
                \array_slice($indexColumns, 0, \count($searchColumns)) === $searchColumns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format strategy name for display (global → Global Scoping).
     */
    private function formatStrategyName(string $strategy): string
    {
        $names = [
            'global' => 'Global Scoping',
            'per-page' => 'Per-Page Scoping',
            'per-content' => 'Per-Content Scoping',
            'dynamic' => 'Dynamic Timing',
            'scheduler' => 'Scheduler-Based Timing',
            'hybrid' => 'Hybrid Timing',
        ];

        return $names[$strategy] ?? \ucfirst($strategy);
    }

    /**
     * Get strategy recommendations based on configuration.
     */
    private function getStrategyRecommendations(string $scoping, string $timing): string
    {
        $recommendations = [];

        // Scoping recommendations
        if ($scoping === 'global') {
            $recommendations[] = 'Global scoping invalidates all page caches on transitions. ' .
                               'Consider per-page or per-content scoping for better performance on large sites.';
        }

        // Timing recommendations
        if ($timing === 'dynamic') {
            $recommendations[] = 'Dynamic timing calculates cache lifetime on every request. ' .
                               'Consider scheduler-based timing if you have many temporal items (>100).';
        }

        if (empty($recommendations)) {
            return '<strong>Configuration:</strong> Your current settings are optimized for production use.';
        }

        return '<strong>Recommendations:</strong>' . \chr(10) . '• ' . \implode(\chr(10) . '• ', $recommendations);
    }

    /**
     * Format duration in human-readable format.
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds !== 1 ? 's' : '');
        }

        if ($seconds < 3600) {
            $minutes = \floor($seconds / 60);
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = \floor($seconds / 3600);
            $minutes = \floor(($seconds % 3600) / 60);
            return $hours . ' hour' . ($hours !== 1 ? 's' : '') .
                   ($minutes > 0 ? ' ' . $minutes . ' minute' . ($minutes !== 1 ? 's' : '') : '');
        }

        $days = \floor($seconds / 86400);
        $hours = \floor(($seconds % 86400) / 3600);
        return $days . ' day' . ($days !== 1 ? 's' : '') .
               ($hours > 0 ? ' ' . $hours . ' hour' . ($hours !== 1 ? 's' : '') : '');
    }
}
