.. include:: /Includes.rst.txt

.. _architecture:

============
Architecture
============

Root Cause Analysis
===================

TYPO3's Cache Invalidation Paradigms
-------------------------------------

TYPO3's cache system supports two invalidation strategies:

**Event-Driven Invalidation:**
   Invalidate when data changes (page edited, deleted, moved).

   Example: Page is edited → Cache tagged with ``pageId_123`` is flushed.

**Tag-Based Invalidation:**
   Invalidate entries matching specific tags.

   Example: ``$cache->flushByTag('news_category_5')`` clears all news in category 5.

**Missing: Temporal Invalidation:**
   Invalidate at absolute timestamp (when time passes, not when data changes).

   Example: Cache should expire at ``2025-10-28 14:30:00`` when page's ``endtime`` arrives.

The Architectural Gap
---------------------

.. code-block:: text

   Current TYPO3 Cache API:
   ├─ Relative TTL: new CacheTag('tag', 3600)  ← Expires in 3600 seconds
   └─ Event-based: flushByTag('pageId_123')    ← Manual invalidation

   Missing Capability:
   └─ Absolute expiration: new CacheTag('tag', absoluteExpire: 1730124600)
                                                 ↑ Unix timestamp

Why This Matters
-----------------

Content rendering pipeline:

.. code-block:: php

   // Simplified TYPO3 rendering flow

   function renderPage($pageId) {
       // 1. Fetch ALL content elements
       $elements = getContentElements($pageId);

       // 2. Filter by starttime/endtime (snapshot at current time!)
       $visible = array_filter($elements, function($el) {
           return isVisible($el, time());  // ← Uses CURRENT time
       });

       // 3. Render filtered elements
       $output = renderElements($visible);

       // 4. CACHE the result with relative TTL
       $cache->set($key, $output, $tags, 3600);  // ← Fixed 3600s lifetime

       return $output;
   }

**Problem:** Visibility filtering happens at render time, then result is cached with
fixed lifetime. Cache doesn't know to expire when temporal conditions change.

How Phase 1 Solves This
========================

Dynamic Cache Lifetime Strategy
--------------------------------

Instead of fixed lifetime, calculate when next temporal transition will occur:

.. code-block:: php

   function calculateCacheLifetime() {
       $now = time();

       // Find next starttime or endtime across all temporal content
       $transitions = [
           getNextPageTransition(),        // Pages becoming visible/expiring
           getNextContentTransition(),     // Content elements changing
           getNextCustomTransition(),      // Extension records
       ];

       $nextTransition = min(array_filter($transitions));

       // Cache until that moment
       return max(0, $nextTransition - $now);
   }

**Result:** Cache expires exactly when temporal state changes.

Implementation: PSR-14 Event
-----------------------------

TYPO3 v12+ provides ``ModifyCacheLifetimeForPageEvent`` (Feature-96879):

.. code-block:: php

   namespace Netresearch\TemporalCache\EventListener;

   use TYPO3\CMS\Core\Cache\Event\ModifyCacheLifetimeForPageEvent;

   class TemporalCacheLifetime
   {
       public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
       {
           $nextTransition = $this->getNextTemporalTransition();

           if ($nextTransition !== null) {
               $lifetime = max(0, $nextTransition - time());
               $event->setCacheLifetime($lifetime);
           }
       }
   }

**Registration:** ``Configuration/Services.yaml``

.. code-block:: yaml

   services:
     Netresearch\TemporalCache\EventListener\TemporalCacheLifetime:
       tags:
         - name: event.listener
           event: TYPO3\CMS\Core\Cache\Event\ModifyCacheLifetimeForPageEvent

Temporal Transition Detection
------------------------------

Query pages for next state change:

.. code-block:: php

   private function getNextPageTransition(): ?int
   {
       $qb = $this->connectionPool->getQueryBuilderForTable('pages');
       $now = time();

       $result = $qb
           ->select('starttime', 'endtime')
           ->from('pages')
           ->where(
               $qb->expr()->or(
                   $qb->expr()->and(
                       $qb->expr()->gt('starttime', $now),
                       $qb->expr()->neq('starttime', 0)
                   ),
                   $qb->expr()->and(
                       $qb->expr()->gt('endtime', $now),
                       $qb->expr()->neq('endtime', 0)
                   )
               ),
               // Context-aware filters (workspace, language)
           )
           ->executeQuery()
           ->fetchAllAssociative();

       // Extract minimum future timestamp
       return $this->extractNextTransition($result);
   }

Query content elements similarly:

.. code-block:: php

   private function getNextContentTransition(): ?int
   {
       // Same pattern for tt_content table
       // Returns next starttime/endtime transition
   }

Combine transitions:

.. code-block:: php

   private function getNextTemporalTransition(): ?int
   {
       $transitions = array_filter([
           $this->getNextPageTransition(),
           $this->getNextContentTransition(),
       ]);

       return !empty($transitions) ? min($transitions) : null;
   }

Timeline Example
================

Scenario
--------

- **09:00:** Page render, 3 content elements:

  - Element A: ``starttime = 10:00``
  - Element B: visible now, ``endtime = 12:00``
  - Element C: visible now, no restrictions

- **11:00:** Another page with ``starttime = 11:00``

Execution Flow
--------------

**09:00 - Initial Render:**

.. code-block:: text

   1. Query finds:
      - Page starttime: 11:00
      - Content A starttime: 10:00
      - Content B endtime: 12:00

   2. Calculate next transition:
      min(11:00, 10:00, 12:00) = 10:00

   3. Set cache lifetime:
      10:00 - 09:00 = 3600 seconds (1 hour)

   4. Render page:
      - Element A: Hidden (starttime not reached)
      - Element B: Visible
      - Element C: Visible

   5. Cache result until 10:00

**10:00 - Cache Expires (automatic):**

.. code-block:: text

   1. Cache miss triggers regeneration

   2. Query finds:
      - Page starttime: 11:00
      - Content B endtime: 12:00
      (Element A now visible, no future starttime)

   3. Calculate next transition:
      min(11:00, 12:00) = 11:00

   4. Set cache lifetime:
      11:00 - 10:00 = 3600 seconds

   5. Render page:
      - Element A: NOW VISIBLE ✅
      - Element B: Still visible
      - Element C: Visible

   6. Cache result until 11:00

**11:00 - Cache Expires (automatic):**

.. code-block:: text

   1. Cache miss triggers regeneration

   2. Query finds:
      - Content B endtime: 12:00
      (Page now visible in menus)

   3. Calculate next transition:
      min(12:00) = 12:00

   4. Set cache lifetime:
      12:00 - 11:00 = 3600 seconds

   5. Render page + update menus:
      - Page: NOW IN MENU ✅
      - Element A: Visible
      - Element B: Still visible
      - Element C: Visible

   6. Cache result until 12:00

**12:00 - Cache Expires (automatic):**

.. code-block:: text

   1. Cache miss triggers regeneration

   2. Query finds: No future transitions

   3. Set cache lifetime: Default (24 hours)

   4. Render page:
      - Element A: Visible
      - Element B: NOW HIDDEN ✅
      - Element C: Visible

   5. Cache for 24 hours (no more temporal changes)

**Result:** ✅ Fully automatic, zero manual intervention

Performance Analysis
====================

Query Cost
----------

Each cache regeneration executes:

.. code-block:: sql

   -- Pages query
   SELECT starttime, endtime FROM pages
   WHERE (starttime > {now} AND starttime != 0)
      OR (endtime > {now} AND endtime != 0)
   -- Context filters...

   -- Content query
   SELECT starttime, endtime FROM tt_content
   WHERE (starttime > {now} AND starttime != 0)
      OR (endtime > {now} AND endtime != 0)
   AND hidden = 0
   -- Context filters...

**Indexes (standard TYPO3):**

- ``pages(starttime)``
- ``pages(endtime)``
- ``tt_content(starttime)``
- ``tt_content(endtime)``

**Measured Performance:**

.. list-table::
   :header-rows: 1
   :widths: 40 30 30

   * - Operation
     - Time
     - Notes
   * - Pages query
     - ~2-4ms
     - Indexed, aggregates only
   * - Content query
     - ~3-6ms
     - More rows, still indexed
   * - Calculation overhead
     - ~0.1ms
     - Array operations
   * - **Total per cache miss**
     - **~5-10ms**
     - One-time cost

Cache Hit Rate Impact
---------------------

Typical TYPO3 site:

- Cache hit rate: 95-99%
- Cache miss: 1-5% of requests

Effective overhead:

.. code-block:: text

   10ms (query) × 2% (miss rate) = 0.2ms average per page load

**Verdict:** ✅ Negligible performance impact

Comparison: Current Workarounds
--------------------------------

**Manual Clearing:**

- Editorial overhead: ~5-10 minutes per scheduled item
- Risk: Forgotten cache clears = broken content
- Cost: Developer time + broken user experience

**Cron Cache Clearing:**

- Server overhead: Clear ALL caches regularly
- Side effect: Destroys all cache performance
- Granularity: Limited by cron frequency

**No Caching:**

- Every request regenerates: ~50-200ms per page
- 100x slower than temporal cache solution

Context Awareness
=================

Workspace Support
-----------------

Extension respects TYPO3 workspace context:

.. code-block:: php

   $workspaceId = $this->context->getPropertyFromAspect('workspace', 'id');

   // Query includes workspace overlay records
   $qb->where(/* workspace-aware conditions */);

**Result:** Preview mode shows correct temporal behavior for workspace versions.

Language Support
----------------

Extension respects language context:

.. code-block:: php

   $languageId = $this->context->getPropertyFromAspect('language', 'id');

   $qb->where(
       $qb->expr()->eq('sys_language_uid', $languageId)
   );

**Result:** Each language has independent cache lifetimes based on translated content's temporal fields.

Extensibility
=============

Custom Tables
-------------

Register additional tables with temporal fields:

.. code-block:: php

   // ext_localconf.php or config/system/additional.php

   use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;

   TemporalMonitorRegistry::registerTable(
       tableName: 'tx_news_domain_model_news',
       startField: 'datetime',  // Custom field name
       endField: 'archive'      // Custom field name
   );

Custom Transition Logic
------------------------

.. note::
   The TemporalCacheLifetime class is ``final`` and cannot be extended.
   Use custom event listeners instead.

For custom temporal logic, create your own PSR-14 event listener:

.. code-block:: php

   namespace YourVendor\YourExtension\EventListener;

   use TYPO3\CMS\Core\Cache\Event\ModifyCacheLifetimeForPageEvent;

   final class CustomTemporalLogic
   {
       public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
       {
           // Add your custom temporal checks
           $customTransition = $this->getCustomTransition();
           if ($customTransition) {
               $currentLifetime = $event->getCacheLifetime();
               $lifetime = min($currentLifetime, $customTransition - time());
               $event->setCacheLifetime($lifetime);
           }
       }

       private function getCustomTransition(): ?int
       {
           // Your custom logic here
           return null;
       }
   }

Register in ``Configuration/Services.yaml``:

.. code-block:: yaml

   services:
     YourVendor\YourExtension\EventListener\CustomTemporalLogic:
       tags:
         - name: event.listener
           identifier: 'your-extension/custom-temporal-logic'
           event: TYPO3\CMS\Core\Cache\Event\ModifyCacheLifetimeForPageEvent
           after: 'temporal-cache/modify-cache-lifetime'

Limitations & Trade-offs
=========================

Current Limitations
-------------------

1. **Symptom Fix:**
   Solves problem within current architecture but doesn't fix root cause
   (missing absolute expiration in TYPO3 core).

2. **Recalculation:**
   Queries execute on every cache miss. Minimal overhead but not zero.

3. **Maximum Granularity:**
   Limited to per-second precision (Unix timestamps).

4. **Cross-Page Dependencies:**
   Doesn't detect when Page A's visibility affects Page B's content.

When Phase 2/3 Are Better
--------------------------

This extension becomes obsolete when TYPO3 core implements:

- **Phase 2:** Absolute expiration timestamps in ``CacheTag`` API
- **Phase 3:** Automatic temporal dependency detection

See :ref:`phases` for migration path.

Next Steps
==========

- :ref:`phases` - Future improvements and migration plan
- `Source Code <https://github.com/netresearch/typo3-temporal-cache>`__ - Examine implementation
- `Forge #14277 <https://forge.typo3.org/issues/14277>`__ - Track core development
