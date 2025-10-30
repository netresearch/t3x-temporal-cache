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
        $this->perPageStrategy = $this->createMock(PerPageScopingStrategy::class);
        $this->perContentStrategy = $this->createMock(PerContentScopingStrategy::class);

        $this->subject = new ScopingStrategyFactory(
            $this->configuration,
            $this->globalStrategy,
            $this->perPageStrategy,
            $this->perContentStrategy
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

        $result = $this->subject->get();

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

        $result = $this->subject->get();

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

        $result = $this->subject->get();

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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown scoping strategy: invalid');

        $this->subject->get();
    }
}
