.. include:: /Includes.rst.txt

.. _backend-tips:

============================
Tips and Best Practices
============================

Optimize your temporal cache implementation with proven strategies and troubleshooting guidance.

Optimizing Performance
======================

1. **Enable Database Indexes**

   Verify indexes exist before enabling extension:

   .. code-block:: sql

      CREATE INDEX idx_temporal_pages ON pages (starttime, endtime, sys_language_uid, hidden, deleted);
      CREATE INDEX idx_temporal_content ON tt_content (starttime, endtime, sys_language_uid, hidden, deleted);

2. **Start with Conservative Settings**

   Begin with default (global) scoping, then optimize based on performance data

3. **Use Wizard Presets**

   Let the wizard recommend settings based on your site profile

4. **Monitor Dashboard Metrics**

   Review performance metrics regularly to identify issues

Using Harmonization Effectively
================================

1. **Align Slots with Content Schedule**

   If articles publish at 09:00, 13:00, 17:00, use those as slots:

   .. code-block:: text

      harmonization.slots = 09:00,13:00,17:00

2. **Consider Business Hours**

   For corporate sites, use business hours only:

   .. code-block:: text

      harmonization.slots = 08:00,12:00,17:00

3. **Bulk Harmonize Existing Content**

   Use Content tab → Select All → Harmonize Selected to update existing content

4. **Review Suggestions**

   Check Harmonization Suggestion column for items with large time shifts

Managing Temporal Content
==========================

1. **Regular Cleanup**

   Filter by "Expired" and consider hiding/deleting old content

2. **Plan Ahead**

   Use timeline visualization to see upcoming transitions and avoid clustering

3. **Export for Analysis**

   Export to CSV for external analysis or reporting

4. **Use Preview Mode**

   Before harmonizing, preview affected pages to understand impact

Troubleshooting with Module
============================

Cache Not Updating
------------------

1. Check **Dashboard → Performance Metrics**
2. Verify database query time <20ms (indexes working)
3. Check **Content Tab** for affected items
4. If using scheduler, verify task runs regularly

High Performance Impact
-----------------------

1. Review **Dashboard → Performance Metrics**
2. If cache hit ratio <70%, consider:

   - Switch to per-content scoping
   - Enable scheduler timing
   - Enable harmonization

3. Use **Wizard** to test alternative configurations

Harmonization Issues
--------------------

1. Check **Content Tab → Harmonization Suggestion**
2. If no suggestions appear:

   - Verify harmonization.enabled = 1
   - Check tolerance setting
   - Verify items are within tolerance of slots

3. Review tolerance setting if too many items not harmonizing

.. _backend-permissions:

User Permissions
================

Required Permissions
--------------------

Backend users need the following to access the module:

**Module Access**:

.. code-block:: php

   # User TSconfig or Group TSconfig
   options.hideModules := removeFromList(tools_TemporalCache)

**Read Permissions**:

- Read access to `pages` table
- Read access to `tt_content` table

**Write Permissions** (for harmonization):

- Write access to `pages` table
- Write access to `tt_content` table

Restricting Access
------------------

To hide module from specific users/groups:

.. code-block:: php

   # User TSconfig
   options.hideModules := addToList(tools_TemporalCache)

To limit harmonization features:

The extension checks backend user permissions before allowing harmonization operations:

**Automatic Permission Checks:**

- Users must have write permissions to all monitored tables (pages, tt_content, custom tables)
- Admin users bypass permission checks
- Users without write access receive a specific error message

**Permission Levels:**

- **Read-only access**: Users without write permissions can view content but cannot harmonize
- **Write access**: Users with table write permissions can harmonize temporal content
- **Module access**: Control via TSconfig using ``options.hideModules`` (see above)

**Custom Tables:**

When custom tables are registered via ``TemporalMonitorRegistry``, permission checks
automatically include those tables. Users must have write access to ALL registered tables
to perform harmonization operations.

Next Steps
==========

- :ref:`configuration` - Complete configuration reference
- :ref:`performance-considerations` - Performance impact analysis
- :ref:`backend-dashboard` - Monitor your temporal cache
