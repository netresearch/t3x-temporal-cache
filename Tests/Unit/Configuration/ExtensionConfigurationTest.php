<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Configuration;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as Typo3ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ExtensionConfiguration
 *
 * @covers \Netresearch\TemporalCache\Configuration\ExtensionConfiguration
 */
final class ExtensionConfigurationTest extends UnitTestCase
{
    private Typo3ExtensionConfiguration&MockObject $typo3ExtensionConfiguration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typo3ExtensionConfiguration = $this->createMock(Typo3ExtensionConfiguration::class);
    }

    /**
     * @test
     */
    public function constructorLoadsConfiguration(): void
    {
        $config = [
            'scoping' => ['strategy' => 'per-content'],
            'timing' => ['strategy' => 'scheduler'],
        ];

        $this->typo3ExtensionConfiguration
            ->expects(self::once())
            ->method('get')
            ->with('nr_temporal_cache')
            ->willReturn($config);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame('per-content', $subject->getScopingStrategy());
        self::assertSame('scheduler', $subject->getTimingStrategy());
    }

    /**
     * @test
     */
    public function constructorHandlesEmptyConfiguration(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(null);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        // Should return defaults
        self::assertSame('global', $subject->getScopingStrategy());
        self::assertSame('dynamic', $subject->getTimingStrategy());
    }

    /**
     * @test
     * @dataProvider scopingStrategyDataProvider
     */
    public function getScopingStrategyReturnsConfiguredValue(string $strategy): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['scoping' => ['strategy' => $strategy]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($strategy, $subject->getScopingStrategy());
    }

    public static function scopingStrategyDataProvider(): array
    {
        return [
            'global' => ['global'],
            'per-page' => ['per-page'],
            'per-content' => ['per-content'],
        ];
    }

    /**
     * @test
     */
    public function getScopingStrategyReturnsDefaultWhenNotConfigured(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame('global', $subject->getScopingStrategy());
    }

    /**
     * @test
     * @dataProvider booleanDataProvider
     */
    public function useRefindexReturnsBooleanValue(mixed $value, bool $expected): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['scoping' => ['use_refindex' => $value]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($expected, $subject->useRefindex());
    }

    public static function booleanDataProvider(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            '1' => [1, true],
            '0' => [0, false],
            'string true' => ['1', true],
            'string false' => ['0', false],
        ];
    }

    /**
     * @test
     */
    public function useRefindexReturnsTrueByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->useRefindex());
    }

    /**
     * @test
     * @dataProvider timingStrategyDataProvider
     */
    public function getTimingStrategyReturnsConfiguredValue(string $strategy): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['timing' => ['strategy' => $strategy]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($strategy, $subject->getTimingStrategy());
    }

    public static function timingStrategyDataProvider(): array
    {
        return [
            'dynamic' => ['dynamic'],
            'scheduler' => ['scheduler'],
            'hybrid' => ['hybrid'],
        ];
    }

    /**
     * @test
     */
    public function getTimingStrategyReturnsDefaultWhenNotConfigured(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame('dynamic', $subject->getTimingStrategy());
    }

    /**
     * @test
     * @dataProvider schedulerIntervalDataProvider
     */
    public function getSchedulerIntervalReturnsConfiguredValue(int $interval, int $expected): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['timing' => ['scheduler_interval' => $interval]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($expected, $subject->getSchedulerInterval());
    }

    public static function schedulerIntervalDataProvider(): array
    {
        return [
            'minimum enforced' => [30, 60],
            'valid value' => [120, 120],
            'large value' => [3600, 3600],
        ];
    }

    /**
     * @test
     */
    public function getSchedulerIntervalReturnsDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(60, $subject->getSchedulerInterval());
    }

    /**
     * @test
     */
    public function getTimingRulesReturnsConfiguredValues(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([
                'timing' => [
                    'hybrid' => [
                        'pages' => 'dynamic',
                        'content' => 'scheduler',
                    ],
                ],
            ]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $rules = $subject->getTimingRules();

        self::assertSame('dynamic', $rules['pages']);
        self::assertSame('scheduler', $rules['content']);
    }

    /**
     * @test
     */
    public function getTimingRulesReturnsDefaults(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $rules = $subject->getTimingRules();

        self::assertSame('dynamic', $rules['pages']);
        self::assertSame('scheduler', $rules['content']);
    }

    /**
     * @test
     */
    public function isHarmonizationEnabledReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['enabled' => true]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->isHarmonizationEnabled());
    }

    /**
     * @test
     */
    public function isHarmonizationEnabledReturnsFalseByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertFalse($subject->isHarmonizationEnabled());
    }

    /**
     * @test
     */
    public function getHarmonizationSlotsReturnsConfiguredValues(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['slots' => '00:00,08:00,16:00']]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $slots = $subject->getHarmonizationSlots();

        self::assertSame(['00:00', '08:00', '16:00'], $slots);
    }

    /**
     * @test
     */
    public function getHarmonizationSlotsReturnsDefaults(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $slots = $subject->getHarmonizationSlots();

        self::assertSame(['00:00', '06:00', '12:00', '18:00'], $slots);
    }

    /**
     * @test
     */
    public function getHarmonizationSlotsTrimsWhitespace(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['slots' => ' 00:00 , 12:00 , 18:00 ']]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $slots = $subject->getHarmonizationSlots();

        self::assertSame(['00:00', '12:00', '18:00'], $slots);
    }

    /**
     * @test
     */
    public function getHarmonizationToleranceReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['tolerance' => 7200]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(7200, $subject->getHarmonizationTolerance());
    }

    /**
     * @test
     */
    public function getHarmonizationToleranceReturnsDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(3600, $subject->getHarmonizationTolerance());
    }

    /**
     * @test
     */
    public function isAutoRoundEnabledReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['auto_round' => true]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->isAutoRoundEnabled());
    }

    /**
     * @test
     */
    public function isAutoRoundEnabledReturnsFalseByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertFalse($subject->isAutoRoundEnabled());
    }

    /**
     * @test
     */
    public function getDefaultMaxLifetimeReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['advanced' => ['default_max_lifetime' => 172800]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(172800, $subject->getDefaultMaxLifetime());
    }

    /**
     * @test
     */
    public function getDefaultMaxLifetimeReturnsDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(86400, $subject->getDefaultMaxLifetime());
    }

    /**
     * @test
     */
    public function isDebugLoggingEnabledReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['advanced' => ['debug_logging' => true]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->isDebugLoggingEnabled());
    }

    /**
     * @test
     */
    public function isDebugLoggingEnabledReturnsFalseByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertFalse($subject->isDebugLoggingEnabled());
    }

    /**
     * @test
     * @dataProvider convenienceMethodDataProvider
     */
    public function convenienceMethodsWorkCorrectly(string $method, string $configKey, string $configValue, bool $expected): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([$configKey => ['strategy' => $configValue]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($expected, $subject->$method());
    }

    public static function convenienceMethodDataProvider(): array
    {
        return [
            'isPerContentScoping true' => ['isPerContentScoping', 'scoping', 'per-content', true],
            'isPerContentScoping false' => ['isPerContentScoping', 'scoping', 'global', false],
            'isSchedulerTiming true' => ['isSchedulerTiming', 'timing', 'scheduler', true],
            'isSchedulerTiming false' => ['isSchedulerTiming', 'timing', 'dynamic', false],
            'isHybridTiming true' => ['isHybridTiming', 'timing', 'hybrid', true],
            'isHybridTiming false' => ['isHybridTiming', 'timing', 'scheduler', false],
            'isDynamicTiming true' => ['isDynamicTiming', 'timing', 'dynamic', true],
            'isDynamicTiming false' => ['isDynamicTiming', 'timing', 'hybrid', false],
        ];
    }

    /**
     * @test
     */
    public function getAllReturnsCompleteConfiguration(): void
    {
        $config = [
            'scoping' => ['strategy' => 'per-content'],
            'timing' => ['strategy' => 'scheduler'],
            'harmonization' => ['enabled' => true],
        ];

        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn($config);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($config, $subject->getAll());
    }
}
