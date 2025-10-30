.. include:: /Includes.rst.txt

.. _configuration-examples:

=====================
Examples & Presets
=====================

Pre-configured presets for common site profiles and real-world configuration scenarios.

.. _configuration-presets:

Configuration Presets
=====================

Small Site Preset
-----------------

**Profile**:

- Pages: <1,000
- Traffic: <100,000 pageviews/month
- Temporal changes: <10 per day

**Configuration**:

.. code-block:: text

   scoping.strategy = global
   scoping.use_refindex = 1
   timing.strategy = dynamic
   harmonization.enabled = 0
   advanced.default_max_lifetime = 86400
   advanced.debug_logging = 0

**Benefits**:

- Zero configuration required (all defaults)
- Simple and reliable
- Minimal overhead for small sites

Medium Site Preset
------------------

**Profile**:

- Pages: 1,000-10,000
- Traffic: 100,000-1,000,000 pageviews/month
- Temporal changes: 10-50 per day

**Configuration**:

.. code-block:: text

   scoping.strategy = per-page
   scoping.use_refindex = 1
   timing.strategy = dynamic
   harmonization.enabled = 1
   harmonization.slots = 00:00,06:00,12:00,18:00
   harmonization.tolerance = 3600
   harmonization.auto_round = 0
   advanced.default_max_lifetime = 86400
   advanced.debug_logging = 0

**Benefits**:

- 95%+ reduction in cache invalidations
- 98%+ reduction with harmonization
- Minimal per-page overhead
- Automatic updates at slot times

Large Site Preset
-----------------

**Profile**:

- Pages: >10,000
- Traffic: >1,000,000 pageviews/month
- Temporal changes: >50 per day

**Configuration**:

.. code-block:: text

   scoping.strategy = per-content
   scoping.use_refindex = 1
   timing.strategy = scheduler
   timing.scheduler_interval = 60
   harmonization.enabled = 1
   harmonization.slots = 00:00,06:00,12:00,18:00
   harmonization.tolerance = 3600
   harmonization.auto_round = 0
   advanced.default_max_lifetime = 86400
   advanced.debug_logging = 0

**Benefits**:

- 99.7% reduction in cache invalidations
- Zero per-page overhead
- Background processing
- Maximum efficiency

High-Traffic Site Preset
-------------------------

**Profile**:

- Traffic: >10,000,000 pageviews/month
- Performance SLA: <100ms response time
- Real-time requirements: Immediate menu updates

**Configuration**:

.. code-block:: text

   scoping.strategy = per-content
   scoping.use_refindex = 1
   timing.strategy = hybrid
   timing.hybrid.pages = dynamic
   timing.hybrid.content = scheduler
   timing.scheduler_interval = 60
   harmonization.enabled = 1
   harmonization.slots = 00:00,04:00,08:00,12:00,16:00,20:00
   harmonization.tolerance = 1800
   harmonization.auto_round = 0
   advanced.default_max_lifetime = 86400
   advanced.debug_logging = 0

**Benefits**:

- Real-time menu updates (dynamic for pages)
- Zero overhead for content (scheduler for content elements)
- Maximum cache efficiency
- Frequent harmonization slots (every 4 hours)

.. _configuration-scenarios:

Common Scenarios
================

Scenario 1: News Site with Hourly Articles
-------------------------------------------

**Requirements**:

- New articles published every hour
- Menus must update immediately
- High traffic (5M pageviews/month)

**Configuration**:

.. code-block:: text

   scoping.strategy = per-content
   timing.strategy = hybrid
   timing.hybrid.pages = dynamic
   timing.hybrid.content = scheduler
   harmonization.enabled = 1
   harmonization.slots = 00:00,01:00,02:00,03:00,04:00,05:00,06:00,07:00,08:00,09:00,10:00,11:00,12:00,13:00,14:00,15:00,16:00,17:00,18:00,19:00,20:00,21:00,22:00,23:00

**Result**:

- Menus update immediately (dynamic for pages)
- Content updates every hour (scheduler + hourly slots)
- Minimal overhead (per-content scoping)

Scenario 2: Corporate Site with Scheduled Pages
------------------------------------------------

**Requirements**:

- 5-10 scheduled pages per month
- Low traffic (50,000 pageviews/month)
- Simple setup

**Configuration**:

.. code-block:: text

   # Use all defaults
   scoping.strategy = global
   timing.strategy = dynamic
   harmonization.enabled = 0

**Result**:

- Zero configuration required
- Simple and reliable
- Minimal impact due to rare transitions

Scenario 3: Event Calendar with Daily Updates
----------------------------------------------

**Requirements**:

- Events with starttime/endtime
- Daily content updates
- Medium traffic (500,000 pageviews/month)

**Configuration**:

.. code-block:: text

   scoping.strategy = per-page
   timing.strategy = dynamic
   harmonization.enabled = 1
   harmonization.slots = 00:00,12:00
   harmonization.tolerance = 3600

**Result**:

- Targeted invalidation (per-page)
- Updates at midnight and noon (harmonization)
- Real-time accuracy (dynamic timing)

Scenario 4: Multi-Language Portal
----------------------------------

**Requirements**:

- 10 languages
- 5,000 pages per language
- Frequent temporal content

**Configuration**:

.. code-block:: text

   scoping.strategy = per-content
   timing.strategy = scheduler
   timing.scheduler_interval = 60
   harmonization.enabled = 1
   harmonization.slots = 00:00,06:00,12:00,18:00
   harmonization.tolerance = 3600

**Result**:

- Per-language isolation (automatic)
- Maximum efficiency (per-content + scheduler)
- Reduced churn (harmonization)

Scenario 5: E-Commerce with Flash Sales
----------------------------------------

**Requirements**:

- Flash sales start at specific times
- Product availability changes frequently
- Critical: Exact timing required

**Configuration**:

.. code-block:: text

   scoping.strategy = per-content
   timing.strategy = dynamic
   harmonization.enabled = 0
   # No harmonization: exact timing critical for sales

**Result**:

- Precise transition timing (no harmonization)
- Minimal scope impact (per-content)
- Real-time updates (dynamic timing)

Scenario 6: Blog with Weekly Posts
-----------------------------------

**Requirements**:

- New posts every Monday at 09:00
- Low temporal activity
- Small site (500 pages)

**Configuration**:

.. code-block:: text

   scoping.strategy = global
   timing.strategy = dynamic
   harmonization.enabled = 1
   harmonization.slots = 09:00
   harmonization.tolerance = 3600

**Result**:

- Simple configuration
- All posts publish at 09:00 (harmonization)
- Minimal overhead (few transitions)

PHP Configuration Examples
==========================

Complete Extension Configuration
---------------------------------

Add to ``config/system/additional.php``:

.. code-block:: php

   <?php
   // Large site optimized configuration
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => [
           'strategy' => 'per-content',
           'use_refindex' => true,
       ],
       'timing' => [
           'strategy' => 'scheduler',
           'scheduler_interval' => 60,
       ],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 3600,
           'auto_round' => false,
       ],
       'advanced' => [
           'default_max_lifetime' => 86400,
           'debug_logging' => false,
       ],
   ];

Environment-Specific Configuration
-----------------------------------

Different settings per environment:

.. code-block:: php

   <?php
   // Development: Enable debug logging
   if (getenv('TYPO3_CONTEXT') === 'Development') {
       $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['debug_logging'] = true;
   }

   // Production: Optimized for performance
   if (getenv('TYPO3_CONTEXT') === 'Production') {
       $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
           'scoping' => ['strategy' => 'per-content'],
           'timing' => ['strategy' => 'scheduler', 'scheduler_interval' => 60],
           'harmonization' => ['enabled' => true, 'slots' => '00:00,06:00,12:00,18:00'],
       ];
   }

Next Steps
==========

- :ref:`configuration-strategies` - Understand optimization strategies
- :ref:`configuration-troubleshooting` - Diagnose configuration issues
- :ref:`backend-wizard` - Use guided configuration wizard
- :ref:`decision-guide` - Choose the right configuration
