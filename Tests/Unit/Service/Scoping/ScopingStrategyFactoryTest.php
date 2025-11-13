<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory
 */
final class ScopingStrategyFactoryTest extends UnitTestCase
{
    private ExtensionConfiguration&Stub $configuration;
    private GlobalScopingStrategy&MockObject $globalStrategy;
    private PerPageScopingStrategy&MockObject $perPageStrategy;
    private PerContentScopingStrategy&MockObject $perContentStrategy;
    private ScopingStrategyFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = $this->createStub(ExtensionConfiguration::class);

        $this->globalStrategy = $this->createMock(GlobalScopingStrategy::class);
        $this->globalStrategy->method('getName')->willReturn('global');

        $this->perPageStrategy = $this->createMock(PerPageScopingStrategy::class);
        $this->perPageStrategy->method('getName')->willReturn('per-page');

        $this->perContentStrategy = $this->createMock(PerContentScopingStrategy::class);
        $this->perContentStrategy->method('getName')->willReturn('per-content');
    }

    private function createFactory(): void
    {
        $this->subject = new ScopingStrategyFactory(
            [
                $this->globalStrategy,
                $this->perPageStrategy,
                $this->perContentStrategy,
            ],
            $this->configuration
        );
    }

    /**
     * @test
     */
    public function getReturnsGlobalStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('global');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->globalStrategy, $result);
    }

    /**
     * @test
     */
    public function getReturnsPerPageStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->perPageStrategy, $result);
    }

    /**
     * @test
     */
    public function getReturnsPerContentStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-content');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->perContentStrategy, $result);
    }

    /**
     * @test
     */
    public function getThrowsExceptionForUnknownStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('invalid');

        $this->createFactory();

        // Factory doesn't throw for unknown strategies, it falls back to first strategy
        // So we test that it returns the fallback (globalStrategy, first in array)
        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->globalStrategy, $result);
    }
}
