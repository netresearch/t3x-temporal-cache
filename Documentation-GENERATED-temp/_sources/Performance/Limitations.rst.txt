.. include:: /Includes.rst.txt

.. _performance-limitations:

================================
Phase 1 Architectural Limitations
================================

Understanding Phase 1 Constraints
==================================

.. note::
   This section describes the global scoping strategy and its architectural constraints.
   The extension also provides per-page and per-content scoping strategies that mitigate
   these limitations. See :ref:`performance-strategies` for optimization options.

**Key Architectural Constraint**:

The ``ModifyCacheLifetimeForPageEvent`` (TYPO3 core) does not provide information about which
page is being cached or its dependencies. Therefore, global scoping must use **global queries**
across all pages and content within the current workspace and language.

**This is a known Phase 1 constraint, not a bug.**

See :ref:`phases` for how Phase 2/3 (core integration) will eliminate these limitations.

Critical Impact: Site-Wide Cache Synchronization
=================================================

Behavior
--------

With **global scoping** strategy:

**ALL pages** in a workspace/language expire at the **same timestamp** - the earliest future
starttime/endtime across the entire site.

Example Scenario
~~~~~~~~~~~~~~~~

::

   Site with 10,000 pages:
   - Page A (pid=123): Has future starttime = 10:00 AM
   - Pages B-J (pid=1-9999): No temporal restrictions

   Result:
   - ALL 10,000 pages cache expires at 10:00 AM
   - ALL pages regenerate simultaneously when first accessed after 10:00 AM

Why This Happens
----------------

The extension queries globally:

.. code-block:: php

   // Queries ALL pages in workspace/language
   ->from('pages')
   ->where(
       $qb->expr()->eq('hidden', 0),
       $qb->expr()->gt('starttime', $now),
       $qb->expr()->eq('sys_language_uid', $languageId)
   )

**No filtering by**:

- Page tree (no rootline check)
- Current page being cached
- Page dependencies or relationships
- Content on specific pages

**Mitigation**: Use per-page or per-content scoping strategies to reduce impact.

Performance Impacts
===================

1. Reduced Cache Hit Ratio
---------------------------

**Severity**: MEDIUM-HIGH (global scoping only)

**Impact**: Frequent temporal transitions = frequent site-wide cache expirations

**Without Extension**:

- Default cache lifetime: 24 hours (typical)
- Cache hit ratio: 90-95%
- Stable, predictable cache behavior

**With Extension (global scoping, temporal-heavy site)**:

- Dynamic cache lifetime: Minutes to hours
- Cache hit ratio: May drop to 40-70%
- More cache regenerations = higher server load

**Example**: News site with hourly scheduled articles = hourly site-wide cache flush

**Mitigation**:

✅ Use per-page or per-content scoping (95-99.7% reduction)
✅ Enable time harmonization (98%+ reduction in transitions)
✅ Consider scheduler timing for non-critical content

2. Cache Miss Storms (Thundering Herd)
--------------------------------------

**Severity**: HIGH (global scoping + high traffic)

**Impact**: All pages expire simultaneously, causing request spike to origin server

Scenario
~~~~~~~~

::

   T+0:   10,000 pages cached, expires at T+60 minutes
   T+60:  ALL 10,000 page caches expire simultaneously
   T+61:  First 100 concurrent requests hit origin (cache misses)
   T+62:  Cache warming begins, but load spike occurred

**Risk Factors**:

- High-traffic sites (>1M pageviews/month)
- Large page counts (>1,000 pages)
- Using CDN/Varnish (amplifies the effect)
- Global scoping strategy

**Mitigation Strategies**:

✅ **Cache Warming**:

.. code-block:: bash

   # Proactive cache warming before expiration
   curl -s https://example.com/sitemap.xml | \
   grep -o '<loc>[^<]*' | \
   sed 's/<loc>//' | \
   xargs -P 10 -I {} curl -s {} > /dev/null

✅ **Stale-While-Revalidate**:

.. code-block:: apache
   :caption: Apache .htaccess

   <IfModule mod_headers.c>
       Header set Cache-Control "public, max-age=3600, stale-while-revalidate=300"
   </IfModule>

.. code-block:: text
   :caption: Varnish VCL

   sub vcl_backend_response {
       set beresp.grace = 5m;
   }

✅ **Origin Rate Limiting**:

.. code-block:: nginx
   :caption: Nginx

   limit_req_zone $binary_remote_addr zone=one:10m rate=10r/s;
   limit_req zone=one burst=20 nodelay;

✅ **Use per-page/per-content scoping** - Eliminates synchronized expiration

3. Database Query Overhead
---------------------------

**Severity**: MEDIUM (dynamic timing only)

**Impact**: 4 database queries per page cache generation

Queries Executed
~~~~~~~~~~~~~~~~

.. code-block:: sql

   -- Query 1: Earliest future starttime for pages
   SELECT starttime FROM pages
   WHERE hidden=0 AND starttime>? AND starttime!=0 AND sys_language_uid=?
   ORDER BY starttime ASC LIMIT 1

   -- Query 2: Earliest future endtime for pages
   SELECT endtime FROM pages
   WHERE hidden=0 AND endtime>? AND endtime!=0 AND sys_language_uid=?
   ORDER BY endtime ASC LIMIT 1

   -- Query 3: Earliest future starttime for content
   SELECT starttime FROM tt_content
   WHERE hidden=0 AND starttime>? AND starttime!=0 AND sys_language_uid=?
   ORDER BY starttime ASC LIMIT 1

   -- Query 4: Earliest future endtime for content
   SELECT endtime FROM tt_content
   WHERE hidden=0 AND endtime>? AND endtime!=0 AND sys_language_uid=?
   ORDER BY endtime ASC LIMIT 1

Performance Cost
~~~~~~~~~~~~~~~~

- Per query: ~1-5ms with proper indexing
- Per page cache: ~5-20ms total (4 queries)
- Cold cache fill (10,000 pages): 50,000-200,000ms (50-200 seconds of query time)

**Mitigation**:

✅ **Mandatory database indexing** (see :ref:`installation`):

.. code-block:: sql

   CREATE INDEX idx_starttime ON pages (starttime);
   CREATE INDEX idx_endtime ON pages (endtime);
   CREATE INDEX idx_starttime ON tt_content (starttime);
   CREATE INDEX idx_endtime ON tt_content (endtime);

✅ **Use scheduler timing** - Zero per-page overhead

✅ **Database query caching** - Reduces repeated query cost

4. CDN/Reverse Proxy Cascade
----------------------------

**Severity**: MEDIUM (global scoping + CDN)

**Impact**: Site-wide cache expiration extends to CDN layer

Architecture Flow
~~~~~~~~~~~~~~~~~

::

   Browser → CDN (Cloudflare/Varnish) → TYPO3 Origin
           ↓
   CDN respects Cache-Control headers from TYPO3
           ↓
   When TYPO3 cache expires, CDN cache also expires
           ↓
   ALL requests hit TYPO3 origin simultaneously

**Risk**: CDN cache miss storm can overwhelm origin server

**Mitigation**:

✅ **CDN stale-while-revalidate**:

.. code-block:: text
   :caption: Cloudflare Page Rule

   Cache Level: Cache Everything
   Edge Cache TTL: 1 hour
   Origin Cache Control: On

✅ **Origin rate limiting** (see above)

✅ **Use per-page/per-content scoping** - Reduces cascade effect

5. No Granular Control (v1.0.x)
--------------------------------

**Severity**: LOW-MEDIUM

**Impact**: All-or-nothing - cannot disable for specific page trees or content types

Current Limitations
~~~~~~~~~~~~~~~~~~~

- No TypoScript configuration options
- No per-page-tree enable/disable
- No content type filtering (pages vs content vs news vs events)
- Global behavior across entire workspace/language

**Future**: Configuration options planned for v1.2.0+

**Workaround**: Use custom event listeners to add filtering logic (see :ref:`architecture`)

6. Multi-Language Overhead
---------------------------

**Severity**: MEDIUM (multi-language sites)

**Impact**: Query overhead multiplies by number of languages

Behavior
~~~~~~~~

With 5 languages:

- Global scoping: 4 queries × 5 languages = 20 queries per cache generation
- Per-page scoping: 4 queries × 5 languages = 20 queries per page
- Scheduler timing: Background processing, minimal impact

**Mitigation**:

✅ Use scheduler timing (eliminates per-page overhead)
✅ Database query caching (reduces redundant queries)
✅ Per-content scoping + refindex (more efficient lookup)

When Phase 1 Limitations Matter
================================

Phase 1 limitations are most significant for:

❌ **Large Enterprise Sites**

- >50,000 pages
- >1M pageviews/month
- >100 temporal transitions/day
- Mission-critical uptime requirements

❌ **High-Traffic News Sites**

- Hourly/frequent scheduled content
- High concurrent user load
- CDN-heavy architecture
- Performance-sensitive

❌ **Multi-Tenant Platforms**

- Hundreds of independent sites
- Variable temporal content per site
- Shared infrastructure
- Resource constraints

✅ **When Phase 1 Works Well**

- Small to medium sites (<10,000 pages)
- Moderate temporal content (<50 transitions/day)
- Standard TYPO3 infrastructure
- Proper optimization strategies applied

Comparison: Phase 1 vs Phase 2/3
=================================

.. list-table::
   :header-rows: 1
   :widths: 30 35 35

   * - Aspect
     - Phase 1 (Current)
     - Phase 2/3 (Future)
   * - **Scope**
     - Global by default
       (per-page/content available)
     - Per-page native
   * - **Query Overhead**
     - 4 queries per cache
       (dynamic timing)
     - Zero queries
   * - **Cache Synchronization**
     - Site-wide possible
       (with global scoping)
     - Per-page always
   * - **Configuration**
     - Extension settings
     - Core API
   * - **Cross-page Dependencies**
     - Not detected
     - Automatic detection
   * - **Timeline**
     - Available today
     - 2026-2027+ (estimated)

See :ref:`phases` for complete roadmap and migration path.

Mitigation Summary
==================

For each limitation, mitigation strategies are available:

.. list-table::
   :header-rows: 1
   :widths: 30 40 30

   * - Limitation
     - Mitigation Strategy
     - Effectiveness
   * - Cache hit ratio drop
     - Per-page/per-content scoping
     - 95-99.7% improvement
   * - Cache miss storms
     - Stale-while-revalidate + warming
     - High
   * - Query overhead
     - Scheduler timing + indexing
     - 100% (zero overhead)
   * - CDN cascade
     - CDN grace period + rate limiting
     - Medium-High
   * - No granular control
     - Custom event listeners
     - Medium (requires code)
   * - Multi-language overhead
     - Scheduler timing + query caching
     - High

Next Steps
==========

- :ref:`performance-strategies` - Optimization approaches
- :ref:`decision-guide` - Site-specific recommendations
- :ref:`phases` - Future improvements roadmap
- :ref:`configuration` - Detailed configuration options
