<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service;

use Netresearch\TemporalCache\Service\RefindexService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RefindexService
 *
 * @covers \Netresearch\TemporalCache\Service\RefindexService
 */
final class RefindexServiceTest extends UnitTestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private RefindexService $subject;
    /** @var array<string, QueryBuilder[]> Queue of query builders by table name */
    private array $queryBuilders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionPool = $this->createMock(ConnectionPool::class);

        // Set up callback to return query builders by table name from queue
        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) {
                // If we have a queue for this table, shift the first one off
                if (!empty($this->queryBuilders[$table])) {
                    return \array_shift($this->queryBuilders[$table]);
                }
                // Otherwise create a default mock
                return $this->createMockQueryBuilder();
            });

        $this->subject = new RefindexService($this->connectionPool);
    }

    /**
     * @test
     */
    public function findPagesWithContentReturnsDirectParentPage(): void
    {
        $contentUid = 123;
        $parentPageId = 5;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, []);
        $this->mockPagesQuery('mount_pid', [], []);
        $this->mockPagesQuery('shortcut', [], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertSame([$parentPageId], $result);
    }

    /**
     * @test
     */
    public function findPagesWithContentReturnsReferencedPages(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $referencedPageId = 10;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, [
            ['tablename' => 'pages', 'recuid' => $referencedPageId],
        ]);
        $this->mockPagesQuery('mount_pid', [$parentPageId, $referencedPageId], []);
        $this->mockPagesQuery('shortcut', [$parentPageId, $referencedPageId], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEqualsCanonicalizing([$parentPageId, $referencedPageId], $result);
    }

    /**
     * @test
     */
    public function findPagesWithContentIncludesMountPoints(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $mountPointId = 15;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, []);
        $this->mockPagesQuery('mount_pid', [$parentPageId], [
            ['uid' => $mountPointId],
        ]);
        $this->mockPagesQuery('shortcut', [$parentPageId, $mountPointId], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEqualsCanonicalizing([$parentPageId, $mountPointId], $result);
    }

    /**
     * @test
     */
    public function findPagesWithContentIncludesShortcuts(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $shortcutId = 20;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, []);
        $this->mockPagesQuery('mount_pid', [$parentPageId], []);
        $this->mockPagesQuery('shortcut', [$parentPageId], [
            ['uid' => $shortcutId],
        ]);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEqualsCanonicalizing([$parentPageId, $shortcutId], $result);
    }

    /**
     * @test
     */
    public function findPagesWithContentReturnsUniquePageIds(): void
    {
        $contentUid = 123;
        $pageId = 5;

        $this->mockTtContentQuery($contentUid, ['pid' => $pageId]);
        $this->mockRefindexQuery($contentUid, 0, [
            ['tablename' => 'pages', 'recuid' => $pageId], // Duplicate
        ]);
        $this->mockPagesQuery('mount_pid', [$pageId], []);
        $this->mockPagesQuery('shortcut', [$pageId], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertSame([$pageId], $result);
    }

    /**
     * @test
     */
    public function findPagesWithContentHandlesContentReferences(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $referencingContentUid = 456;
        $referencingContentPage = 10;

        // Queue order: tt_content (parent), sys_refindex, tt_content (referencing), pages (mount), pages (shortcut)
        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);

        // Refindex query returns a content reference
        $this->mockRefindexQuery($contentUid, 0, [
            ['tablename' => 'tt_content', 'recuid' => $referencingContentUid],
        ]);

        // Second tt_content query for the referencing content's page
        $this->mockTtContentQuery($referencingContentUid, ['pid' => $referencingContentPage]);

        $this->mockPagesQuery('mount_pid', [], []);
        $this->mockPagesQuery('shortcut', [], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertContains($parentPageId, $result);
        self::assertContains($referencingContentPage, $result);
    }

    /**
     * @test
     */
    public function findPagesWithContentReturnsEmptyArrayWhenContentNotFound(): void
    {
        $contentUid = 999;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchOne')->willReturn(false);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEmpty($result);
    }

    /**
     * @test
     */
    public function hasIndirectReferencesReturnsTrueForMountPoints(): void
    {
        $pageId = 5;

        $this->mockPagesQuery('mount_pid', [$pageId], [
            ['uid' => 10],
        ]);
        $this->mockPagesQuery('shortcut', [$pageId], []);

        $result = $this->subject->hasIndirectReferences($pageId);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function hasIndirectReferencesReturnsTrueForShortcuts(): void
    {
        $pageId = 5;

        $this->mockPagesQuery('mount_pid', [$pageId], []);
        $this->mockPagesQuery('shortcut', [$pageId], [
            ['uid' => 15],
        ]);

        $result = $this->subject->hasIndirectReferences($pageId);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function hasIndirectReferencesReturnsFalseWhenNoReferences(): void
    {
        $pageId = 5;

        $this->mockPagesQuery('mount_pid', [$pageId], []);
        $this->mockPagesQuery('shortcut', [$pageId], []);

        $result = $this->subject->hasIndirectReferences($pageId);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function getContentElementsOnPageReturnsAllContentUids(): void
    {
        $pageId = 5;
        $languageUid = 0;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['uid' => 10],
                ['uid' => 20],
                ['uid' => 30],
                false
            );
        $queryBuilder->method('executeQuery')->willReturn($result);

        // Store in queryBuilders queue so setUp() callback can return it
        $this->queryBuilders['tt_content'][] = $queryBuilder;

        $result = $this->subject->getContentElementsOnPage($pageId, $languageUid);

        self::assertSame([10, 20, 30], $result);
    }

    /**
     * @test
     */
    public function getContentElementsOnPageReturnsEmptyArrayWhenNoContent(): void
    {
        $pageId = 999;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')->willReturn(false);
        $queryBuilder->method('executeQuery')->willReturn($result);

        // Store in queryBuilders queue so setUp() callback can return it
        $this->queryBuilders['tt_content'][] = $queryBuilder;

        $result = $this->subject->getContentElementsOnPage($pageId);

        self::assertEmpty($result);
    }

    private function createMockQueryBuilder(): QueryBuilder&MockObject
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturn(':param');

        $expressionBuilder->method('eq')->willReturn('eq_expr');
        $expressionBuilder->method('in')->willReturn('in_expr');
        $restrictions->method('removeAll')->willReturnSelf();
        $restrictions->method('add')->willReturnSelf();

        return $queryBuilder;
    }

    private function mockTtContentQuery(int $contentUid, array $row): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchOne')->willReturn($row['pid'] ?? false);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->queryBuilders['tt_content'][] = $queryBuilder;
    }

    private function mockRefindexQuery(int $contentUid, int $languageUid, array $rows): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $calls = \array_map(fn ($row) => $row, $rows);
        $calls[] = false;

        $result->method('fetchAssociative')->willReturnOnConsecutiveCalls(...$calls);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->queryBuilders['sys_refindex'][] = $queryBuilder;
    }

    private function mockPagesQuery(string $field, array $pageIds, array $resultRows): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $calls = \array_map(fn ($row) => $row, $resultRows);
        $calls[] = false;

        $result->method('fetchAssociative')->willReturnOnConsecutiveCalls(...$calls);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->queryBuilders['pages'][] = $queryBuilder;
    }
}
