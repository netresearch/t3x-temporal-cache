<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for finding page references using sys_refindex.
 *
 * This service is the foundation for per-content scoping strategy, enabling
 * precise cache invalidation by finding all pages where content appears.
 *
 * Handles:
 * - Direct page references
 * - Mount point references
 * - Shortcut page references
 * - Multi-language scenarios
 */
class RefindexService implements SingletonInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {
    }

    /**
     * Find all page UIDs where a content element is referenced.
     *
     * This method queries sys_refindex to find all pages that reference
     * the given content element, including:
     * - The parent page (pid)
     * - Pages with mount points
     * - Pages with shortcuts
     * - Pages referencing via CONTENT/RECORDS cObjects
     *
     * @param int $contentUid UID of the content element
     * @param int $languageUid Language UID for language-specific references
     * @return array<int> Array of unique page UIDs where content appears
     */
    public function findPagesWithContent(int $contentUid, int $languageUid = 0): array
    {
        $pageIds = [];

        // 1. Get direct parent page
        $directParent = $this->getDirectParentPage($contentUid);
        if ($directParent !== null) {
            $pageIds[] = $directParent;
        }

        // 2. Find references from sys_refindex
        $referencedPages = $this->findReferencesFromRefindex($contentUid, $languageUid);
        $pageIds = \array_merge($pageIds, $referencedPages);

        // 3. Check for mount points that might display this content
        $mountPointPages = $this->findMountPointReferences($pageIds);
        $pageIds = \array_merge($pageIds, $mountPointPages);

        // 4. Check for shortcut pages pointing to these pages
        $shortcutPages = $this->findShortcutReferences($pageIds);
        $pageIds = \array_merge($pageIds, $shortcutPages);

        // Return unique page IDs
        return \array_values(\array_unique(\array_filter($pageIds)));
    }

    /**
     * Get the direct parent page of a content element.
     *
     * @param int $contentUid UID of the content element
     * @return int|null Parent page UID or null if not found
     */
    private function getDirectParentPage(int $contentUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($contentUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();

        return $result !== false ? (int)$result : null;
    }

    /**
     * Find pages that reference the content element via sys_refindex.
     *
     * Queries sys_refindex to find all page references to the content element.
     * This catches references from:
     * - CONTENT/RECORDS cObjects
     * - Plugin configurations
     * - Custom content references
     *
     * @param int $contentUid UID of the content element
     * @param int $languageUid Language UID
     * @return array<int> Array of page UIDs
     */
    private function findReferencesFromRefindex(int $contentUid, int $languageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');

        $result = $queryBuilder
            ->select('tablename', 'recuid')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq(
                    'ref_table',
                    $queryBuilder->createNamedParameter('tt_content')
                ),
                $queryBuilder->expr()->eq(
                    'ref_uid',
                    $queryBuilder->createNamedParameter($contentUid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery();

        $pageIds = [];
        while ($row = $result->fetchAssociative()) {
            // If the reference is from a page, add it directly
            if ($row['tablename'] === 'pages') {
                $pageIds[] = (int)$row['recuid'];
            }
            // If the reference is from other content, get its parent page
            elseif ($row['tablename'] === 'tt_content') {
                $parentPage = $this->getDirectParentPage((int)$row['recuid']);
                if ($parentPage !== null) {
                    $pageIds[] = $parentPage;
                }
            }
        }

        return $pageIds;
    }

    /**
     * Find mount point pages that display the given pages.
     *
     * Mount points allow pages to be "mounted" into other parts of the page tree,
     * so we need to find all mount points that reference the affected pages.
     *
     * @param array<int> $pageIds Array of page UIDs to check
     * @return array<int> Array of mount point page UIDs
     */
    private function findMountPointReferences(array $pageIds): array
    {
        if (empty($pageIds)) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'doktype',
                    $queryBuilder->createNamedParameter(7, Connection::PARAM_INT) // Mountpoint doktype
                ),
                $queryBuilder->expr()->in(
                    'mount_pid',
                    $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
                ),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->executeQuery();

        $mountPoints = [];
        while ($row = $result->fetchAssociative()) {
            $mountPoints[] = (int)$row['uid'];
        }

        return $mountPoints;
    }

    /**
     * Find shortcut pages that point to the given pages.
     *
     * Shortcut pages redirect to other pages, so we need to find all shortcuts
     * that might display content from the affected pages.
     *
     * @param array<int> $pageIds Array of page UIDs to check
     * @return array<int> Array of shortcut page UIDs
     */
    private function findShortcutReferences(array $pageIds): array
    {
        if (empty($pageIds)) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in(
                    'doktype',
                    $queryBuilder->createNamedParameter([3, 4], Connection::PARAM_INT_ARRAY) // Shortcut doktypes
                ),
                $queryBuilder->expr()->in(
                    'shortcut',
                    $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
                ),
                $queryBuilder->expr()->eq('hidden', 0)
            )
            ->executeQuery();

        $shortcuts = [];
        while ($row = $result->fetchAssociative()) {
            $shortcuts[] = (int)$row['uid'];
        }

        return $shortcuts;
    }

    /**
     * Check if a page has any mount point or shortcut references.
     *
     * Utility method to quickly check if a page is referenced by mount points
     * or shortcuts, which affects cache invalidation scope.
     *
     * @param int $pageId Page UID to check
     * @return bool True if page has mount point or shortcut references
     */
    public function hasIndirectReferences(int $pageId): bool
    {
        $mountPoints = $this->findMountPointReferences([$pageId]);
        $shortcuts = $this->findShortcutReferences([$pageId]);

        return !empty($mountPoints) || !empty($shortcuts);
    }

    /**
     * Get all content elements on a page (including nested content).
     *
     * This method finds all content elements on a page, which is useful
     * for cache tag generation and invalidation analysis.
     *
     * @param int $pageId Page UID
     * @param int $languageUid Language UID (default 0)
     * @return array<int> Array of content element UIDs
     */
    public function getContentElementsOnPage(int $pageId, int $languageUid = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery();

        $contentUids = [];
        while ($row = $result->fetchAssociative()) {
            $contentUids[] = (int)$row['uid'];
        }

        return $contentUids;
    }
}
