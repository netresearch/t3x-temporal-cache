<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy
 */
final class SchedulerTimingStrategyTest extends UnitTestCase
{
    private ScopingStrategyInterface&MockObject $scopingStrategy;
    private CacheManager&MockObject $cacheManager;
    private Context&MockObject $context;
    private SchedulerTimingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->context = $this->createMock(Context::class);

        $this->subject = new SchedulerTimingStrategy(
            $this->scopingStrategy,
            $this->cacheManager
        );
    }

    /**
     * @test
     */
    public function handlesContentTypeReturnsAlwaysTrue(): void
    {
        self::assertTrue($this->subject->handlesContentType('page'));
        self::assertTrue($this->subject->handlesContentType('content'));
    }

    /**
     * @test
     */
    public function getCacheLifetimeReturnsNull(): void
    {
        $lifetime = $this->subject->getCacheLifetime($this->context);

        self::assertNull($lifetime);
    }

    /**
     * @test
     */
    public function processTransitionFlushesCache(): void
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: 'tt_content',
            title: 'Test',
            pid: 5,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $event = new TransitionEvent(
            content: $content,
            timestamp: \time(),
            transitionType: 'start'
        );

        $this->scopingStrategy
            ->method('getCacheTagsToFlush')
            ->willReturn(['pageId_5', 'pageId_10']);

        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())
            ->method('flushByTags')
            ->with(['pageId_5', 'pageId_10']);

        $this->cacheManager
            ->method('getCache')
            ->with('pages')
            ->willReturn($cache);

        $this->subject->processTransition($event);
    }

    /**
     * @test
     */
    public function getNameReturnsCorrectIdentifier(): void
    {
        self::assertSame('scheduler', $this->subject->getName());
    }
}
