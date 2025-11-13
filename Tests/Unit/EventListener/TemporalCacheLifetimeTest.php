<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\EventListener;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\EventListener\TemporalCacheLifetime;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Event\ModifyCacheLifetimeForPageEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for TemporalCacheLifetime event listener
 *
 * @covers \Netresearch\TemporalCache\EventListener\TemporalCacheLifetime
 */
final class TemporalCacheLifetimeTest extends UnitTestCase
{
    private TemporalCacheLifetime $subject;
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private ScopingStrategyInterface&MockObject $scopingStrategy;
    private TimingStrategyInterface&MockObject $timingStrategy;
    private Context&MockObject $context;
    private LoggerInterface&MockObject $logger;
    private ModifyCacheLifetimeForPageEvent&MockObject $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->scopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $this->timingStrategy = $this->createMock(TimingStrategyInterface::class);
        $this->context = $this->createMock(Context::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->event = $this->createMock(ModifyCacheLifetimeForPageEvent::class);

        // Configure default mock behaviors
        $this->extensionConfiguration
            ->method('getDefaultMaxLifetime')
            ->willReturn(86400);
        $this->extensionConfiguration
            ->method('isDebugLoggingEnabled')
            ->willReturn(false);
        $this->scopingStrategy
            ->method('getName')
            ->willReturn('test-scoping');
        $this->timingStrategy
            ->method('getName')
            ->willReturn('test-timing');

        $this->subject = new TemporalCacheLifetime(
            $this->extensionConfiguration,
            $this->scopingStrategy,
            $this->timingStrategy,
            $this->context,
            $this->logger
        );
    }

    /**
     * @test
     */
    public function invokeDoesNotModifyCacheLifetimeWhenTimingStrategyReturnsNull(): void
    {
        // Arrange: Timing strategy returns null (scheduler mode)
        $this->timingStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context)
            ->willReturn(null);

        // Assert: Event should not be modified
        $this->event
            ->expects(self::never())
            ->method('setCacheLifetime');

        // Act
        ($this->subject)($this->event);
    }

    /**
     * @test
     */
    public function invokeSetsLifetimeWhenTimingStrategyReturnsValue(): void
    {
        $lifetime = 3600;

        // Arrange
        $this->timingStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context)
            ->willReturn($lifetime);

        $this->event
            ->method('getRenderingInstructions')
            ->willReturn([]);

        // Assert: Lifetime should be set
        $this->event
            ->expects(self::once())
            ->method('setCacheLifetime')
            ->with($lifetime);

        // Act
        ($this->subject)($this->event);
    }

    /**
     * @test
     */
    public function invokeCapsLifetimeAtDefaultMaximum(): void
    {
        $requestedLifetime = 100000; // More than default 86400
        $expectedLifetime = 86400;

        // Arrange
        $this->timingStrategy
            ->method('getCacheLifetime')
            ->willReturn($requestedLifetime);

        $this->event
            ->method('getRenderingInstructions')
            ->willReturn([]);

        // Assert: Lifetime should be capped
        $this->event
            ->expects(self::once())
            ->method('setCacheLifetime')
            ->with($expectedLifetime);

        // Act
        ($this->subject)($this->event);
    }

    /**
     * @test
     */
    public function invokeRespectsTypoScriptCachePeriod(): void
    {
        $typoScriptMaxLifetime = 7200;
        $requestedLifetime = 10000;

        // Arrange
        $this->timingStrategy
            ->method('getCacheLifetime')
            ->willReturn($requestedLifetime);

        $this->event
            ->method('getRenderingInstructions')
            ->willReturn(['cache_period' => $typoScriptMaxLifetime]);

        // Assert: Should use TypoScript cache_period as max
        $this->event
            ->expects(self::once())
            ->method('setCacheLifetime')
            ->with($typoScriptMaxLifetime);

        // Act
        ($this->subject)($this->event);
    }

    /**
     * @test
     */
    public function invokeHandlesExceptionsGracefully(): void
    {
        // Arrange: Timing strategy throws exception
        $this->timingStrategy
            ->method('getCacheLifetime')
            ->willThrowException(new \RuntimeException('Test exception'));

        // Assert: Should log error but not throw
        $this->logger
            ->expects(self::once())
            ->method('error');

        $this->event
            ->expects(self::never())
            ->method('setCacheLifetime');

        // Act: Should not throw
        ($this->subject)($this->event);

        self::assertTrue(true); // Reached here without exception
    }

    /**
     * @test
     */
    public function invokeLogsDebugInfoWhenDebugEnabled(): void
    {
        $lifetime = 3600;

        // Arrange: Need fresh subject with debug enabled
        $debugConfig = $this->createMock(ExtensionConfiguration::class);
        $debugConfig->method('getDefaultMaxLifetime')->willReturn(86400);
        $debugConfig->method('isDebugLoggingEnabled')->willReturn(true);

        $debugLogger = $this->createMock(LoggerInterface::class);
        $debugTimingStrategy = $this->createMock(TimingStrategyInterface::class);
        $debugTimingStrategy->method('getName')->willReturn('test-timing');
        $debugTimingStrategy->method('getCacheLifetime')->willReturn($lifetime);

        $debugScopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $debugScopingStrategy->method('getName')->willReturn('test-scoping');

        $subject = new TemporalCacheLifetime(
            $debugConfig,
            $debugScopingStrategy,
            $debugTimingStrategy,
            $this->context,
            $debugLogger
        );

        $this->event
            ->method('getRenderingInstructions')
            ->willReturn([]);

        // Assert: Should log debug info
        $debugLogger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Temporal cache lifetime set',
                self::isType('array')
            );

        // Act
        $subject($this->event);
    }

    /**
     * @test
     */
    public function getScopingStrategyReturnsInjectedStrategy(): void
    {
        $result = $this->subject->getScopingStrategy();

        self::assertSame($this->scopingStrategy, $result);
    }

    /**
     * @test
     */
    public function getTimingStrategyReturnsInjectedStrategy(): void
    {
        $result = $this->subject->getTimingStrategy();

        self::assertSame($this->timingStrategy, $result);
    }
}
