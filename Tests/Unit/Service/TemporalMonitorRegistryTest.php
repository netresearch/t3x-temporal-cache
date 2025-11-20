<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service;

use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\TemporalMonitorRegistry
 */
final class TemporalMonitorRegistryTest extends UnitTestCase
{
    private TemporalMonitorRegistry $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TemporalMonitorRegistry();
    }

    /**
     * @test
     */
    public function defaultTablesAreRegistered(): void
    {
        self::assertTrue($this->subject->isRegistered('pages'));
        self::assertTrue($this->subject->isRegistered('tt_content'));
    }

    /**
     * @test
     */
    public function getAllTablesIncludesDefaultTables(): void
    {
        $tables = $this->subject->getAllTables();

        self::assertArrayHasKey('pages', $tables);
        self::assertArrayHasKey('tt_content', $tables);
        self::assertSame(2, \count($tables)); // Only defaults initially
    }

    /**
     * @test
     */
    public function getTotalTableCountIncludesDefaults(): void
    {
        self::assertSame(2, $this->subject->getTotalTableCount());
    }

    /**
     * @test
     */
    public function getCustomTableCountIsZeroInitially(): void
    {
        self::assertSame(0, $this->subject->getCustomTableCount());
    }

    /**
     * @test
     */
    public function getCustomTablesIsEmptyInitially(): void
    {
        self::assertEmpty($this->subject->getCustomTables());
    }

    /**
     * @test
     */
    public function registerTableAddsCustomTable(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');

        self::assertTrue($this->subject->isRegistered('tx_news_domain_model_news'));
        self::assertSame(1, $this->subject->getCustomTableCount());
        self::assertSame(3, $this->subject->getTotalTableCount());
    }

    /**
     * @test
     */
    public function registerTableWithDefaultFieldsUsesDefaults(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');

        $fields = $this->subject->getTableFields('tx_news_domain_model_news');

        self::assertIsArray($fields);
        self::assertContains('uid', $fields);
        self::assertContains('starttime', $fields);
        self::assertContains('endtime', $fields);
    }

    /**
     * @test
     */
    public function registerTableWithCustomFieldsUsesCustom(): void
    {
        $customFields = ['uid', 'starttime', 'endtime', 'custom_field'];

        $this->subject->registerTable('tx_news_domain_model_news', $customFields);

        $fields = $this->subject->getTableFields('tx_news_domain_model_news');

        self::assertSame($customFields, $fields);
    }

    /**
     * @test
     */
    public function registerTableThrowsExceptionForEmptyTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289600);

        $this->subject->registerTable('');
    }

    /**
     * @test
     */
    public function registerTableThrowsExceptionForDefaultTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289601);

        $this->subject->registerTable('pages');
    }

    /**
     * @test
     */
    public function registerTableThrowsExceptionWhenMissingUid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289602);

        $this->subject->registerTable('tx_news_domain_model_news', ['starttime', 'endtime']);
    }

    /**
     * @test
     */
    public function registerTableThrowsExceptionWhenMissingStarttime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289602);

        $this->subject->registerTable('tx_news_domain_model_news', ['uid', 'endtime']);
    }

    /**
     * @test
     */
    public function registerTableThrowsExceptionWhenMissingEndtime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289602);

        $this->subject->registerTable('tx_news_domain_model_news', ['uid', 'starttime']);
    }

    /**
     * @test
     */
    public function unregisterTableRemovesCustomTable(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');

        self::assertTrue($this->subject->isRegistered('tx_news_domain_model_news'));

        $this->subject->unregisterTable('tx_news_domain_model_news');

        self::assertFalse($this->subject->isRegistered('tx_news_domain_model_news'));
        self::assertSame(0, $this->subject->getCustomTableCount());
    }

    /**
     * @test
     */
    public function unregisterTableDoesNotAffectDefaultTables(): void
    {
        // Attempting to unregister default table should not cause error
        $this->subject->unregisterTable('pages');

        // Default table still registered
        self::assertTrue($this->subject->isRegistered('pages'));
    }

    /**
     * @test
     */
    public function unregisterNonExistentTableDoesNotCauseError(): void
    {
        $this->subject->unregisterTable('non_existent_table');

        self::assertFalse($this->subject->isRegistered('non_existent_table'));
    }

    /**
     * @test
     */
    public function isRegisteredReturnsFalseForUnregisteredTable(): void
    {
        self::assertFalse($this->subject->isRegistered('tx_news_domain_model_news'));
    }

    /**
     * @test
     */
    public function getAllTablesIncludesCustomTables(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');

        $tables = $this->subject->getAllTables();

        self::assertCount(4, $tables); // 2 defaults + 2 custom
        self::assertArrayHasKey('pages', $tables);
        self::assertArrayHasKey('tt_content', $tables);
        self::assertArrayHasKey('tx_news_domain_model_news', $tables);
        self::assertArrayHasKey('tx_events_domain_model_event', $tables);
    }

    /**
     * @test
     */
    public function getCustomTablesReturnsOnlyCustomTables(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');

        $customTables = $this->subject->getCustomTables();

        self::assertCount(2, $customTables);
        self::assertArrayHasKey('tx_news_domain_model_news', $customTables);
        self::assertArrayHasKey('tx_events_domain_model_event', $customTables);
        self::assertArrayNotHasKey('pages', $customTables);
        self::assertArrayNotHasKey('tt_content', $customTables);
    }

    /**
     * @test
     */
    public function getTableFieldsReturnsDefaultFields(): void
    {
        $fields = $this->subject->getTableFields('pages');

        self::assertIsArray($fields);
        self::assertContains('uid', $fields);
        self::assertContains('title', $fields);
        self::assertContains('starttime', $fields);
        self::assertContains('endtime', $fields);
    }

    /**
     * @test
     */
    public function getTableFieldsReturnsCustomFields(): void
    {
        $customFields = ['uid', 'starttime', 'endtime', 'custom_field'];

        $this->subject->registerTable('tx_news_domain_model_news', $customFields);

        $fields = $this->subject->getTableFields('tx_news_domain_model_news');

        self::assertSame($customFields, $fields);
    }

    /**
     * @test
     */
    public function getTableFieldsReturnsNullForUnregisteredTable(): void
    {
        $fields = $this->subject->getTableFields('non_existent_table');

        self::assertNull($fields);
    }

    /**
     * @test
     */
    public function clearCustomTablesClearsOnlyCustomTables(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');

        self::assertSame(2, $this->subject->getCustomTableCount());

        $this->subject->clearCustomTables();

        self::assertSame(0, $this->subject->getCustomTableCount());
        self::assertTrue($this->subject->isRegistered('pages')); // Defaults still there
        self::assertTrue($this->subject->isRegistered('tt_content'));
        self::assertFalse($this->subject->isRegistered('tx_news_domain_model_news'));
        self::assertFalse($this->subject->isRegistered('tx_events_domain_model_event'));
    }

    /**
     * @test
     */
    public function multipleRegistrationsCombineCorrectly(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');
        $this->subject->registerTable('tx_blog_domain_model_post');

        self::assertSame(3, $this->subject->getCustomTableCount());
        self::assertSame(5, $this->subject->getTotalTableCount());

        $allTables = $this->subject->getAllTables();
        self::assertCount(5, $allTables);
    }
}
