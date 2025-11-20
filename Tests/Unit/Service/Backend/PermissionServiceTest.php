<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Backend;

use Netresearch\TemporalCache\Service\Backend\PermissionService;
use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Backend\PermissionService
 */
final class PermissionServiceTest extends UnitTestCase
{
    private TemporalMonitorRegistry $monitorRegistry;
    private BackendUserAuthentication&MockObject $backendUser;
    private PermissionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // Use real registry - it's a simple singleton data holder
        $this->monitorRegistry = new TemporalMonitorRegistry();
        $this->backendUser = $this->createMock(BackendUserAuthentication::class);

        // Mock global backend user
        $GLOBALS['BE_USER'] = $this->backendUser;

        $this->subject = new PermissionService($this->monitorRegistry);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canModifyTemporalContentReturnsTrueForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->canModifyTemporalContent();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function canModifyTemporalContentChecksSpecificTableWhenProvided(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->expects(self::once())
            ->method('check')
            ->with('tables_modify', 'pages')
            ->willReturn(true);

        $result = $this->subject->canModifyTemporalContent('pages');

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function canModifyTemporalContentChecksAllTablesWhenNoTableProvided(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', true],
            ]);

        $result = $this->subject->canModifyTemporalContent();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function canModifyTemporalContentReturnsFalseIfAnyTableNotModifiable(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', false], // No permission for tt_content
            ]);

        $result = $this->subject->canModifyTemporalContent();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function canAccessModuleReturnsTrueForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->canAccessModule();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function canAccessModuleReturnsTrueWhenModuleNotHidden(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => '',
                ],
            ]);

        $result = $this->subject->canAccessModule();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function canAccessModuleReturnsFalseWhenModuleHidden(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => 'tools_TemporalCache',
                ],
            ]);

        $result = $this->subject->canAccessModule();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function canAccessModuleReturnsFalseWhenModuleInHiddenList(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => 'web_info, tools_TemporalCache, file_list',
                ],
            ]);

        $result = $this->subject->canAccessModule();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function getUnmodifiableTablesReturnsEmptyForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->getUnmodifiableTables();

        self::assertEmpty($result);
    }

    /**
     * @test
     */
    public function getUnmodifiableTablesReturnsTablesWithoutPermission(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', false],
            ]);

        $result = $this->subject->getUnmodifiableTables();

        self::assertCount(1, $result);
        self::assertContains('tt_content', $result);
    }

    /**
     * @test
     */
    public function isReadOnlyReturnsTrueWhenCannotModify(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturn(false); // No permission for any table

        $result = $this->subject->isReadOnly();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function isReadOnlyReturnsFalseWhenCanModify(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->isReadOnly();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function getPermissionStatusReturnsCompleteStatusForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => '',
                ],
            ]);

        $result = $this->subject->getPermissionStatus();

        self::assertTrue($result['isAdmin']);
        self::assertTrue($result['canModify']);
        self::assertTrue($result['canAccessModule']);
        self::assertEmpty($result['unmodifiableTables']);
    }

    /**
     * @test
     */
    public function getPermissionStatusReturnsCompleteStatusForNonAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', false],
            ]);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => '',
                ],
            ]);

        $result = $this->subject->getPermissionStatus();

        self::assertFalse($result['isAdmin']);
        self::assertFalse($result['canModify']); // Cannot modify all tables
        self::assertTrue($result['canAccessModule']);
        self::assertCount(1, $result['unmodifiableTables']);
        self::assertContains('tt_content', $result['unmodifiableTables']);
    }
}
