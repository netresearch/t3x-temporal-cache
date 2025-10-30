.. include:: /Includes.rst.txt

.. _backend-dashboard:

=============
Dashboard Tab
=============

The Dashboard provides a comprehensive overview of temporal cache status and performance.

Interface Overview
==================

The Dashboard displays:

1. **Header Section**

   - Current date and time
   - Active configuration summary
   - Quick action buttons (Flush Cache, Refresh Stats)

2. **Statistics Cards** (4 cards in a row)

   - **Total Temporal Content**: Count of pages and content elements with starttime/endtime
   - **Active Transitions**: Content currently visible based on temporal settings
   - **Pending Transitions**: Upcoming starttime/endtime events
   - **Expired Content**: Content that has passed its endtime

3. **Timeline Visualization**

   - Horizontal timeline showing next 24 hours
   - Markers for each upcoming transition
   - Color-coded by type (starttime in green, endtime in red)
   - Hover shows details (page/content title, exact time)

4. **Performance Metrics**

   - Cache invalidation rate (per day/hour)
   - Estimated cache hit ratio
   - Average database query time
   - Impact of current configuration

5. **Configuration Summary**

   - Active scoping strategy
   - Active timing strategy
   - Harmonization status
   - Quick links to change configuration

Statistics Cards
================

Total Temporal Content
----------------------

Shows count of database records with temporal fields:

.. code-block:: text

   Pages:          45 with starttime/endtime
   Content:        123 content elements with temporal fields
   Total:          168 temporal items

**What it means**:

- More items = More potential cache invalidations
- Use to assess impact of temporal content

Active Transitions
------------------

Currently visible content based on temporal rules:

.. code-block:: text

   Active:         89 items currently visible
   Hidden:         79 items currently hidden

**What it means**:

- Content visible between starttime and endtime
- Excludes items with future starttime or past endtime

Pending Transitions
-------------------

Upcoming temporal events:

.. code-block:: text

   Next Hour:      3 transitions
   Next 24 Hours:  12 transitions
   Next 7 Days:    45 transitions

**What it means**:

- Each transition triggers cache invalidation (scope depends on strategy)
- Use with harmonization to reduce transitions

Expired Content
---------------

Content that has passed its endtime:

.. code-block:: text

   Expired:        23 items past endtime
   Still visible:  2 (cache not updated)

**What it means**:

- Items past their endtime
- "Still visible" indicates cached pages not yet regenerated

Timeline Visualization
======================

Visual representation of upcoming temporal transitions.

How to Read
-----------

- **Time Axis**: Horizontal timeline spanning next 24 hours
- **Green Markers**: Content becoming visible (starttime)
- **Red Markers**: Content expiring (endtime)
- **Marker Size**: Relative to number of items in transition
- **Hover Details**: Shows page/content title, exact timestamp

Example Interpretation
----------------------

.. code-block:: text

   09:00  |●     Green marker: 3 pages start (homepage, news, events)
   12:00  |●●    Green marker: 5 content elements start
   14:00  |○     Red marker: 2 pages expire
   18:00  |●●●   Green marker: 8 scheduled articles start

**Actions**:

- Click marker → View affected items
- Plan cache warming around major transitions
- Identify harmonization opportunities (many close markers)

Performance Metrics
===================

Cache Invalidation Rate
-----------------------

Frequency of cache invalidations:

.. code-block:: text

   Per Hour:    2.5 invalidations/hour (avg over 24h)
   Per Day:     60 invalidations/day
   Per Week:    420 invalidations/week

**Interpretation**:

- Low (<10/day): Minimal impact
- Medium (10-50/day): Moderate impact, consider harmonization
- High (>50/day): Significant impact, optimize configuration

**Actions**:

- High rate → Enable harmonization
- Constant rate → Check for misconfiguration

Estimated Cache Hit Ratio
--------------------------

Projected cache effectiveness:

.. code-block:: text

   Current:     65% (65 hits / 100 requests)
   Baseline:    90% (without extension)
   Impact:      -25% cache hit ratio

**Interpretation**:

- >80%: Good
- 60-80%: Acceptable with monitoring
- <60%: Performance concern, review configuration

**Actions**:

- Low ratio → Implement per-page/per-content scoping
- Monitor actual vs estimated

Database Query Performance
--------------------------

Query execution times:

.. code-block:: text

   Pages Query:     2.3ms avg
   Content Query:   3.1ms avg
   Total:           5.4ms per cache generation

**Interpretation**:

- <5ms: Excellent (properly indexed)
- 5-20ms: Good
- >20ms: Indexes missing or queries inefficient

**Actions**:

- Slow queries → Verify database indexes
- See :ref:`installation` for index setup

Configuration Impact
--------------------

Assessment of current settings:

.. code-block:: text

   Strategy:        Global Scoping + Dynamic Timing
   Impact:          MEDIUM - Site-wide cache synchronization
   Recommendation:  Consider per-page scoping for this site size

**Levels**:

- **LOW**: Minimal performance impact
- **MEDIUM**: Noticeable but manageable
- **HIGH**: Significant impact, mitigation recommended

Configuration Summary
=====================

Quick view of active settings:

**Scoping Strategy**:

- Global (all pages)
- Per-Page (affected page only)
- Per-Content (pages containing temporal content)

**Timing Strategy**:

- Dynamic (checks on every cache generation)
- Scheduler (background processing)
- Hybrid (mixed per table)

**Harmonization**:

- Enabled/Disabled
- Time slots if enabled

**Quick Actions**:

- Change Configuration → Opens :ref:`backend-wizard`
- View Details → Opens :ref:`configuration`

Next Steps
==========

- :ref:`backend-content` - Manage temporal content
- :ref:`backend-wizard` - Configure strategies
- :ref:`performance-considerations` - Understand implications
- :ref:`backend-tips` - Optimization recommendations
