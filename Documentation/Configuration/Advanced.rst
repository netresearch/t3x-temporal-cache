.. include:: /Includes.rst.txt

.. _configuration-advanced:

================
Advanced Options
================

Fine-tune cache lifetime limits, enable debug logging, and configure scheduler tasks.

Advanced Settings
=================

.. confval:: advanced.default_max_lifetime

   :type: integer
   :Default: ``86400``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['default_max_lifetime']

   Maximum cache lifetime in seconds when no temporal content exists.

   **Configuration Priority:**

   The extension respects TYPO3's cache configuration hierarchy:

   1. **TypoScript** ``config.cache_period`` (if configured) - Takes precedence
   2. **This extension setting** (fallback when TypoScript not set)
   3. **TYPO3's default** 86400 seconds / 24 hours (final fallback)

   **Purpose:**

   Safety cap to prevent extremely long cache lifetimes if no temporal content is scheduled for months.
   Even with no temporal content, cache refreshes at least once per configured period.

   **Why this exists:**

   TYPO3 has a default cache timeout (24 hours), but our extension can override
   it when calculating temporal transitions. This setting caps our calculated
   lifetime to prevent extremely long cache durations (e.g., 6 months if no
   transitions are scheduled).

   **Best Practice - Use TypoScript:**

   Configure site-wide cache via TypoScript instead of this extension setting:

   .. code-block:: typoscript
      :caption: setup.typoscript

      config.cache_period = 43200  # 12 hours site-wide

   This automatically applies to temporal cache calculations and all other
   TYPO3 cache operations, providing consistent cache behavior.

   **Extension Config Examples:**

   Only configure this setting if you need temporal cache to have a different
   maximum than your site-wide cache configuration.

   .. code-block:: text

      # Default: 24 hours (aligns with TYPO3 default)
      advanced.default_max_lifetime = 86400

      # Shorter: 12 hours
      advanced.default_max_lifetime = 43200

      # Longer: 48 hours
      advanced.default_max_lifetime = 172800

   **Debug Logging:**

   Enable ``advanced.debug_logging`` to see which configuration source is used:

   .. code-block:: text

      # Log shows:
      max_from_typoscript: 43200       # If config.cache_period set
      max_from_extension_config: 86400 # This setting
      max_lifetime: 43200              # Actual value used (TypoScript wins)

.. confval:: advanced.debug_logging

   :type: boolean
   :Default: ``false``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['debug_logging']

   Enable detailed logging for debugging.

   **When enabled:**

   - Logs strategy selection decisions
   - Logs cache lifetime calculations
   - Logs transition processing
   - Logs error details

   **Log location:**

   .. code-block:: bash

      # TYPO3 system log
      var/log/typo3_*.log

      # Filter for temporal cache
      grep temporal_cache var/log/typo3_*.log

   .. warning::
      Enable only for debugging. Generates significant log volume on high-traffic sites.

   Example
   -------

   .. code-block:: text

      # Enable for debugging
      advanced.debug_logging = 1

.. _scheduler-setup:

Scheduler Task Setup
====================

Required for ``scheduler`` and ``hybrid`` timing strategies.

Step 1: Create Scheduler Task
------------------------------

1. Navigate to **System → Scheduler**
2. Click **Create new task** (+ icon)
3. Select **Temporal Cache: Process Transitions**
4. Configure:

   - **Type**: Single task
   - **Start**: Now
   - **Frequency**: Every 1 minute (or as configured)
   - **Description**: Process temporal cache transitions

5. Click **Save**

Step 2: Verify Execution
-------------------------

1. Go to **System → Scheduler**
2. Find your task in the list
3. Check **Last execution** and **Next execution** timestamps
4. Verify **Status** shows success

Troubleshooting
---------------

**Task not running**:

- Verify TYPO3 Scheduler cron is configured
- Check system cron: ``crontab -l | grep scheduler``
- Should have: ``* * * * * /path/to/typo3 scheduler:run``

**Task fails**:

- Check logs: ``var/log/typo3_*.log``
- Enable debug logging: ``advanced.debug_logging = 1``
- Verify database indexes are created

Example Scheduler Cron
----------------------

Add to system crontab (``crontab -e``):

.. code-block:: bash

   # Run TYPO3 Scheduler every minute
   * * * * * /usr/bin/php /var/www/html/vendor/bin/typo3 scheduler:run

For DDEV:

.. code-block:: bash

   # Run inside DDEV container
   * * * * * ddev exec vendor/bin/typo3 scheduler:run

Scheduler Configuration Best Practices
---------------------------------------

**Interval Recommendations**:

.. code-block:: text

   Small sites (<1,000 pages):
   - Not needed (use dynamic timing)

   Medium sites (1,000-10,000 pages):
   - Consider scheduler for content only (hybrid mode)
   - Interval: 60-120 seconds

   Large sites (>10,000 pages):
   - Use scheduler for both pages and content
   - Interval: 60 seconds

   High-traffic sites (>1M pageviews/month):
   - Use scheduler or hybrid mode
   - Interval: 60 seconds
   - Monitor scheduler execution time

**Monitoring**:

.. code-block:: text

   1. Check execution frequency in System → Scheduler
   2. Review execution time (should be <1 second)
   3. Monitor for task failures
   4. Check logs for errors

Next Steps
==========

- :ref:`configuration-strategies` - Configure optimization strategies
- :ref:`configuration-examples` - See complete configuration examples
- :ref:`configuration-troubleshooting` - Diagnose configuration issues
