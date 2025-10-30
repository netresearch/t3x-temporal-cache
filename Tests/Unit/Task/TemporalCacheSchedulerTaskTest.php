<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Task;

use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask
 */
final class TemporalCacheSchedulerTaskTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&Stub $repository;
    private TimingStrategyInterface&MockObject $timingStrategy;
    private TemporalCacheSchedulerTask $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->timingStrategy = $this->createMock(TimingStrategyInterface::class);

        $this->subject = new TemporalCacheSchedulerTask(
            $this->repository,
            $this->timingStrategy
        );
    }

    /**
     * @test
     */
    public function executeReturnsTrue(): void
    {
        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $result = $this->subject->execute();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function executeProcessesTransitions(): void
    {
        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn([
                ['uid' => 1, 'pid' => 0, 'title' => 'Test', 'starttime' => \time(), 'endtime' => null,
                 'sys_language_uid' => 0, 't3ver_wsid' => 0, 'hidden' => 0, 'deleted' => 0, 'tablename' => 'pages'],
            ]);

        $this->timingStrategy
            ->expects(self::once())
            ->method('processTransition');

        $result = $this->subject->execute();

        self::assertTrue($result);
    }
}
