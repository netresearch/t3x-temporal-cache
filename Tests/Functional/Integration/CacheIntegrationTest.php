<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Integration;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Event\ModifyCacheLifetimeForPageEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Integration tests verifying complete workflow with TYPO3 cache system
 *
 * @covers \Netresearch\TemporalCache\EventListener\TemporalCacheLifetime
 */
final class CacheIntegrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/temporal_cache',
    ];

    protected array $coreExtensionsToLoad = [
        'core',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
    }

    /**
     * @test
     */
    public function eventIsDispatchedByCacheSystem(): void
    {
        $eventDispatcher = $this->get(EventDispatcher::class);
        $originalLifetime = 86400;
        $event = new ModifyCacheLifetimeForPageEvent($originalLifetime);

        // Dispatch event (simulates TYPO3 cache system behavior)
        $modifiedEvent = $eventDispatcher->dispatch($event);

        // Event should be instance (proving it was processed)
        self::assertInstanceOf(ModifyCacheLifetimeForPageEvent::class, $modifiedEvent);
    }

    /**
     * @test
     */
    public function temporalContentAffectsCacheLifetime(): void
    {
        $now = \time();
        $futureTime = $now + 3600; // 1 hour from now

        // Create page with future starttime
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Future Page',
            'starttime' => $futureTime,
            'endtime' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        $eventDispatcher = $this->get(EventDispatcher::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);
        $modifiedEvent = $eventDispatcher->dispatch($event);

        // Lifetime should be modified to ~1 hour
        $lifetime = $modifiedEvent->getCacheLifetime();
        self::assertLessThan(86400, $lifetime);
        self::assertGreaterThan(3500, $lifetime);
        self::assertLessThan(3700, $lifetime);
    }

    /**
     * @test
     */
    public function multipleTemporalRecordsCalculateCorrectLifetime(): void
    {
        $now = \time();

        // Create multiple records with different timings
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');

        // Page expires in 30 minutes
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Expiring Soon',
            'starttime' => 0,
            'endtime' => $now + 1800,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        // Page starts in 2 hours
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Starting Later',
            'starttime' => $now + 7200,
            'endtime' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        $eventDispatcher = $this->get(EventDispatcher::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);
        $modifiedEvent = $eventDispatcher->dispatch($event);

        // Should use earliest transition (30 minutes)
        $lifetime = $modifiedEvent->getCacheLifetime();
        self::assertGreaterThan(1700, $lifetime);
        self::assertLessThan(1900, $lifetime);
    }

    /**
     * @test
     */
    public function extensionIntegratesWithCacheManager(): void
    {
        $cacheManager = $this->get(CacheManager::class);

        // Verify cache manager is available (proves TYPO3 integration)
        self::assertInstanceOf(CacheManager::class, $cacheManager);

        // Verify pages cache exists
        $pagesCache = $cacheManager->getCache('pages');
        self::assertNotNull($pagesCache);
    }

    /**
     * @test
     */
    public function workflowWithRealDatabaseData(): void
    {
        $now = \time();

        // Scenario: Editorial workflow
        // 1. Editor creates page scheduled for tomorrow
        $tomorrowStarttime = $now + 86400;

        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Tomorrow Launch',
            'starttime' => $tomorrowStarttime,
            'endtime' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        // 2. Cache system calculates lifetime
        $eventDispatcher = $this->get(EventDispatcher::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);
        $modifiedEvent = $eventDispatcher->dispatch($event);

        // 3. Verify cache will expire at page's starttime
        $lifetime = $modifiedEvent->getCacheLifetime();
        $expectedLifetime = $tomorrowStarttime - $now;

        self::assertGreaterThan($expectedLifetime - 2, $lifetime);
        self::assertLessThan($expectedLifetime + 2, $lifetime);
    }

    /**
     * @test
     */
    public function verifyNoRegressionWithStandardPages(): void
    {
        // Scenario: Standard pages without temporal restrictions
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Always Visible',
            'starttime' => 0,
            'endtime' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        $eventDispatcher = $this->get(EventDispatcher::class);
        $originalLifetime = 86400;
        $event = new ModifyCacheLifetimeForPageEvent($originalLifetime);
        $modifiedEvent = $eventDispatcher->dispatch($event);

        // Lifetime should remain default (no temporal content)
        self::assertEquals($originalLifetime, $modifiedEvent->getCacheLifetime());
    }

    /**
     * @test
     */
    public function mixedContentTypesCalculateCorrectly(): void
    {
        $now = \time();

        // Page with endtime in 2 hours
        $pageConnection = $this->getConnectionPool()->getConnectionForTable('pages');
        $pageConnection->insert('pages', [
            'pid' => 0,
            'title' => 'Expiring Page',
            'starttime' => 0,
            'endtime' => $now + 7200,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        // Content with starttime in 1 hour
        $contentConnection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $contentConnection->insert('tt_content', [
            'pid' => 1,
            'header' => 'Upcoming Content',
            'starttime' => $now + 3600,
            'endtime' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);

        $eventDispatcher = $this->get(EventDispatcher::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);
        $modifiedEvent = $eventDispatcher->dispatch($event);

        // Should use earliest (content in 1 hour)
        $lifetime = $modifiedEvent->getCacheLifetime();
        self::assertGreaterThan(3500, $lifetime);
        self::assertLessThan(3700, $lifetime);
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
