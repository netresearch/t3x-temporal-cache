<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\Timing\DynamicTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy;
use PHPUnit\Framework\MockObject\Stub;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory
 */
final class TimingStrategyFactoryTest extends UnitTestCase
{
    private ExtensionConfiguration&Stub $configuration;
    private DynamicTimingStrategy&MockObject $dynamicStrategy;
    private SchedulerTimingStrategy&MockObject $schedulerStrategy;
    private HybridTimingStrategy&MockObject $hybridStrategy;
    private TimingStrategyFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->dynamicStrategy = $this->createMock(DynamicTimingStrategy::class);
        $this->schedulerStrategy = $this->createMock(SchedulerTimingStrategy::class);
        $this->hybridStrategy = $this->createMock(HybridTimingStrategy::class);

        $this->subject = new TimingStrategyFactory(
            $this->configuration,
            $this->dynamicStrategy,
            $this->schedulerStrategy,
            $this->hybridStrategy
        );
    }

    /**
     * @test
     */
    public function getReturnsDynamicStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $result = $this->subject->get();

        self::assertSame($this->dynamicStrategy, $result);
    }

    /**
     * @test
     */
    public function getReturnsSchedulerStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('scheduler');

        $result = $this->subject->get();

        self::assertSame($this->schedulerStrategy, $result);
    }

    /**
     * @test
     */
    public function getReturnsHybridStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('hybrid');

        $result = $this->subject->get();

        self::assertSame($this->hybridStrategy, $result);
    }

    /**
     * @test
     */
    public function getThrowsExceptionForUnknownStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('invalid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown timing strategy: invalid');

        $this->subject->get();
    }
}
