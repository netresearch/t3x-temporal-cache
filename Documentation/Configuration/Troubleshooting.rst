.. include:: /Includes.rst.txt

.. _configuration-troubleshooting:

===============
Troubleshooting
===============

Diagnose and resolve common configuration issues.

Cache Not Updating
==================

**Symptoms**:

- Temporal content doesn't appear/disappear at scheduled times
- Menus show stale content

**Solutions**:

1. Verify extension is installed and active
2. Check database indexes exist (see :ref:`installation`)
3. Enable debug logging: ``advanced.debug_logging = 1``
4. Check logs for errors: ``var/log/typo3_*.log``
5. Verify scheduler task is running (if using scheduler timing)

Detailed Diagnosis Steps
-------------------------

**Step 1: Verify Extension Status**

.. code-block:: bash

   # Check if extension is installed
   ./vendor/bin/typo3 extension:list | grep temporal_cache

**Step 2: Check Database Indexes**

.. code-block:: sql

   -- Verify indexes exist
   SHOW INDEX FROM pages WHERE Key_name = 'idx_temporal_pages';
   SHOW INDEX FROM tt_content WHERE Key_name = 'idx_temporal_content';

If indexes don't exist, create them:

.. code-block:: sql

   CREATE INDEX idx_temporal_pages ON pages (starttime, endtime, sys_language_uid, hidden, deleted);
   CREATE INDEX idx_temporal_content ON tt_content (starttime, endtime, sys_language_uid, hidden, deleted);

**Step 3: Enable Debug Logging**

.. code-block:: text

   advanced.debug_logging = 1

Then check logs:

.. code-block:: bash

   # View recent temporal cache logs
   grep temporal_cache var/log/typo3_*.log | tail -50

**Step 4: Verify Scheduler Task (if applicable)**

.. code-block:: bash

   # Run scheduler manually
   ./vendor/bin/typo3 scheduler:run

Check System → Scheduler in backend for task status.

High Performance Impact
=======================

**Symptoms**:

- Slow page generation
- High database load
- Frequent cache misses

**Solutions**:

1. **Add database indexes** (most common issue):

   .. code-block:: sql

      CREATE INDEX idx_temporal_pages ON pages (starttime, endtime, sys_language_uid, hidden, deleted);
      CREATE INDEX idx_temporal_content ON tt_content (starttime, endtime, sys_language_uid, hidden, deleted);

2. **Switch to more efficient scoping**:

   .. code-block:: text

      scoping.strategy = per-content

3. **Enable scheduler timing**:

   .. code-block:: text

      timing.strategy = scheduler

4. **Enable harmonization**:

   .. code-block:: text

      harmonization.enabled = 1

Performance Analysis
--------------------

**Measure Query Performance**:

.. code-block:: sql

   -- Check query execution time
   EXPLAIN SELECT uid, starttime, endtime FROM pages
   WHERE (starttime > 0 OR endtime > 0)
   AND sys_language_uid = 0
   AND hidden = 0
   AND deleted = 0;

Expected: Query should use `idx_temporal_pages` index and complete in <5ms.

**Monitor Cache Hit Ratio**:

1. Go to Backend Module → Dashboard
2. Check "Cache Hit Ratio" metric
3. Target: >70% for acceptable performance

**Identify Bottlenecks**:

.. code-block:: text

   If cache hit ratio <70%:
   → Enable per-content scoping
   → Enable harmonization
   → Switch to scheduler timing

   If query time >20ms:
   → Verify indexes exist
   → Check database performance
   → Consider database optimization

Harmonization Not Working
==========================

**Symptoms**:

- Cache still flushes at every transition
- Harmonization suggestions not appearing

**Solutions**:

1. Verify harmonization is enabled:

   .. code-block:: text

      harmonization.enabled = 1

2. Check time slots are configured:

   .. code-block:: text

      harmonization.slots = 00:00,06:00,12:00,18:00

3. Verify tolerance allows harmonization:

   .. code-block:: text

      harmonization.tolerance = 3600

4. Check if transitions are within tolerance of slots

Harmonization Diagnosis
------------------------

**Test Harmonization Logic**:

.. code-block:: text

   Example with slots = 00:00,12:00 and tolerance = 3600 (1 hour):

   Starttime 11:30:
   - Nearest slot: 12:00
   - Time shift: 30 minutes
   - Within tolerance: YES → Harmonized to 12:00

   Starttime 10:30:
   - Nearest slot: 12:00
   - Time shift: 90 minutes
   - Within tolerance: NO → NOT harmonized

**Check Backend Module**:

1. Go to Tools → Temporal Cache → Content
2. Check "Harmonization Suggestion" column
3. If no suggestions appear:
   - Verify harmonization.enabled = 1
   - Check tolerance setting
   - Ensure content is within tolerance of slots

Scheduler Task Not Running
===========================

**Symptoms**:

- Content not updating when using scheduler timing
- Last execution timestamp not updating

**Solutions**:

1. Verify system cron is configured:

   .. code-block:: bash

      crontab -l | grep scheduler

   Should show:

   .. code-block:: bash

      * * * * * /usr/bin/php /path/to/typo3/vendor/bin/typo3 scheduler:run

2. Manually run scheduler:

   .. code-block:: bash

      ./vendor/bin/typo3 scheduler:run

3. Check scheduler task is enabled in backend
4. Verify no errors in scheduler log

Scheduler Configuration Verification
-------------------------------------

**Step 1: Check Cron Setup**

.. code-block:: bash

   # View crontab
   crontab -l

   # For DDEV
   ddev ssh
   crontab -l

**Step 2: Test Manual Execution**

.. code-block:: bash

   # Run scheduler manually
   ./vendor/bin/typo3 scheduler:run

   # Check output for errors
   # Should show: "Executed X tasks"

**Step 3: Verify Task Configuration**

1. Go to System → Scheduler
2. Find "Temporal Cache: Process Transitions" task
3. Check:
   - Task is enabled (checkbox checked)
   - Next execution time is in the future
   - Last execution was recent (within interval)

**Step 4: Check Logs**

.. code-block:: bash

   # View scheduler logs
   grep scheduler var/log/typo3_*.log | tail -20

   # View temporal cache logs
   grep temporal_cache var/log/typo3_*.log | tail -20

Configuration Validation
=========================

Invalid Configuration Values
----------------------------

**Symptom**: Extension ignores configuration

**Check for**:

.. code-block:: text

   1. Typos in configuration keys
   2. Invalid values (e.g., timing.scheduler_interval < 60)
   3. Missing required values (e.g., harmonization.slots when harmonization.enabled = 1)

**Solution**: Enable debug logging to see configuration parsing errors.

Extension Manager vs PHP Configuration
---------------------------------------

**Issue**: Configuration not taking effect

**Check**: Configuration method consistency

.. code-block:: text

   Priority order (highest to lowest):
   1. PHP configuration (config/system/additional.php)
   2. Extension Manager settings
   3. Default values

**Solution**: Check both locations and ensure no conflicts.

Getting Help
============

If issues persist after troubleshooting:

1. **Enable debug logging**:

   .. code-block:: text

      advanced.debug_logging = 1

2. **Gather diagnostic information**:

   .. code-block:: bash

      # Extension version
      ./vendor/bin/typo3 extension:list | grep temporal_cache

      # Database indexes
      SHOW INDEX FROM pages WHERE Key_name = 'idx_temporal_pages';
      SHOW INDEX FROM tt_content WHERE Key_name = 'idx_temporal_content';

      # Recent logs
      grep temporal_cache var/log/typo3_*.log | tail -50

3. **Check documentation**:
   - :ref:`performance-considerations` - Performance impact analysis
   - :ref:`configuration-strategies` - Strategy configuration details
   - :ref:`backend-module` - Visual monitoring and diagnostics

4. **Report issues**:
   - TYPO3 Forge: https://forge.typo3.org/
   - Include: TYPO3 version, extension version, configuration, logs

Next Steps
==========

- :ref:`configuration-strategies` - Review optimization strategies
- :ref:`configuration-examples` - See working configuration examples
- :ref:`backend-dashboard` - Monitor performance metrics
- :ref:`performance-considerations` - Understand performance implications
