<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Domain\Model;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for TemporalContent value object
 *
 * @covers \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class TemporalContentTest extends UnitTestCase
{
    /**
     * @test
     */
    public function constructorCreatesImmutableObject(): void
    {
        $subject = new TemporalContent(
            uid: 123,
            tableName: 'pages',
            title: 'Test Page',
            pid: 1,
            starttime: 1609459200,
            endtime: 1612137600,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame(123, $subject->uid);
        self::assertSame('pages', $subject->tableName);
        self::assertSame('Test Page', $subject->title);
        self::assertSame(1, $subject->pid);
        self::assertSame(1609459200, $subject->starttime);
        self::assertSame(1612137600, $subject->endtime);
        self::assertSame(0, $subject->languageUid);
        self::assertSame(0, $subject->workspaceUid);
        self::assertFalse($subject->hidden);
        self::assertFalse($subject->deleted);
    }

    /**
     * @test
     */
    public function hasTemporalFieldsReturnsTrueWhenStarttimeSet(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: 1609459200,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertTrue($subject->hasTemporalFields());
    }

    /**
     * @test
     */
    public function hasTemporalFieldsReturnsTrueWhenEndtimeSet(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: 1612137600,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertTrue($subject->hasTemporalFields());
    }

    /**
     * @test
     */
    public function hasTemporalFieldsReturnsFalseWhenNoTemporalFields(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertFalse($subject->hasTemporalFields());
    }

    /**
     * @test
     */
    public function getNextTransitionReturnsNullWhenNoFutureTransitions(): void
    {
        $currentTime = 1620000000;
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: 1609459200,
            endtime: 1612137600,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertNull($subject->getNextTransition($currentTime));
    }

    /**
     * @test
     */
    public function getNextTransitionReturnsStarttimeWhenInFuture(): void
    {
        $currentTime = 1600000000;
        $futureStarttime = 1609459200;

        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $futureStarttime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame($futureStarttime, $subject->getNextTransition($currentTime));
    }

    /**
     * @test
     */
    public function getNextTransitionReturnsEndtimeWhenInFuture(): void
    {
        $currentTime = 1610000000;
        $futureEndtime = 1612137600;

        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: 1609459200,
            endtime: $futureEndtime,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame($futureEndtime, $subject->getNextTransition($currentTime));
    }

    /**
     * @test
     */
    public function getNextTransitionReturnsNearestTransition(): void
    {
        $currentTime = 1600000000;
        $nearTransition = 1605000000;
        $farTransition = 1612137600;

        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $nearTransition,
            endtime: $farTransition,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame($nearTransition, $subject->getNextTransition($currentTime));
    }

    /**
     * @test
     */
    public function getContentTypeReturnsPageForPagesTable(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame('page', $subject->getContentType());
    }

    /**
     * @test
     */
    public function getContentTypeReturnsContentForTtContentTable(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'tt_content',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame('content', $subject->getContentType());
    }

    /**
     * @test
     */
    public function isPageReturnsTrueForPagesTable(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertTrue($subject->isPage());
    }

    /**
     * @test
     */
    public function isPageReturnsFalseForTtContentTable(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'tt_content',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertFalse($subject->isPage());
    }

    /**
     * @test
     */
    public function isContentReturnsTrueForTtContentTable(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'tt_content',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertTrue($subject->isContent());
    }

    /**
     * @test
     */
    public function isContentReturnsFalseForPagesTable(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertFalse($subject->isContent());
    }

    /**
     * @test
     * @dataProvider visibilityDataProvider
     */
    public function isVisibleChecksAllConditions(
        bool $hidden,
        bool $deleted,
        ?int $starttime,
        ?int $endtime,
        int $currentTime,
        bool $expectedVisible
    ): void {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $starttime,
            endtime: $endtime,
            languageUid: 0,
            workspaceUid: 0,
            hidden: $hidden,
            deleted: $deleted
        );

        self::assertSame($expectedVisible, $subject->isVisible($currentTime));
    }

    public static function visibilityDataProvider(): array
    {
        $currentTime = 1610000000;
        $pastTime = 1600000000;
        $futureTime = 1620000000;

        return [
            'visible when no restrictions' => [false, false, null, null, $currentTime, true],
            'hidden when hidden flag set' => [true, false, null, null, $currentTime, false],
            'hidden when deleted flag set' => [false, true, null, null, $currentTime, false],
            'hidden when both flags set' => [true, true, null, null, $currentTime, false],
            'hidden when starttime in future' => [false, false, $futureTime, null, $currentTime, false],
            'visible when starttime in past' => [false, false, $pastTime, null, $currentTime, true],
            'hidden when endtime in past' => [false, false, null, $pastTime, $currentTime, false],
            'visible when endtime in future' => [false, false, null, $futureTime, $currentTime, true],
            'visible when between start and end' => [false, false, $pastTime, $futureTime, $currentTime, true],
            'hidden when before starttime' => [false, false, $futureTime, null, $pastTime, false],
            'hidden when after endtime' => [false, false, null, $pastTime, $futureTime, false],
        ];
    }

    /**
     * @test
     */
    public function getTransitionTypeReturnsStartForStarttime(): void
    {
        $starttime = 1609459200;
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $starttime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame('start', $subject->getTransitionType($starttime));
    }

    /**
     * @test
     */
    public function getTransitionTypeReturnsEndForEndtime(): void
    {
        $endtime = 1612137600;
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: $endtime,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertSame('end', $subject->getTransitionType($endtime));
    }

    /**
     * @test
     */
    public function getTransitionTypeReturnsNullForNonMatchingTimestamp(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: 1609459200,
            endtime: 1612137600,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertNull($subject->getTransitionType(1610000000));
    }

    /**
     * @test
     */
    public function getTransitionTypeReturnsNullWhenNoTemporalFields(): void
    {
        $subject = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        self::assertNull($subject->getTransitionType(1610000000));
    }
}
