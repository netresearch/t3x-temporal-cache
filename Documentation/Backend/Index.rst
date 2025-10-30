.. include:: /Includes.rst.txt

.. _backend-module:

==============
Backend Module
==============

Visual management interface for the TYPO3 Temporal Cache extension.

Overview
========

The Temporal Cache backend module provides a user-friendly interface for:

- 📊 **Monitoring** temporal content and upcoming transitions
- ⚡ **Viewing** performance statistics and KPIs
- 🔧 **Managing** temporal content with bulk operations
- ⚙️ **Configuring** the extension with guided presets
- 🧪 **Testing** and optimizing configuration

.. _backend-module-access:

Accessing the Module
====================

Location
--------

Navigate to: **Tools → Temporal Cache**

Requirements
------------

**Permissions**: Backend users require the following:

- Access to the **Tools** module
- Permission for **Temporal Cache** module (configured in user/group settings)

**TYPO3 Versions**: Compatible with TYPO3 12.4+ and 13.0+

Module Features
===============

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 📊 Dashboard

        Comprehensive overview of temporal cache status with statistics, timeline
        visualization, and performance metrics.

        ..  card-footer:: :ref:`View Dashboard <backend-dashboard>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 📝 Content Management

        Browse and manage all temporal content (pages and content elements) with
        filtering, bulk operations, and detailed views.

        ..  card-footer:: :ref:`Manage Content <backend-content>`
            :button-style: btn btn-info stretched-link

    ..  card:: ⚙️ Configuration Wizard

        Guided configuration setup with presets for different site sizes and
        performance impact calculator.

        ..  card-footer:: :ref:`Configure <backend-wizard>`
            :button-style: btn btn-success stretched-link

    ..  card:: 💡 Tips & Best Practices

        Performance optimization recommendations, user permissions setup, and
        troubleshooting guidance.

        ..  card-footer:: :ref:`Learn More <backend-tips>`
            :button-style: btn btn-secondary stretched-link

Quick Actions
=============

Common tasks available from the module:

**Flush Cache**: Clear all page caches to force regeneration

.. code-block:: bash

   # Via module button or CLI
   php vendor/bin/typo3 cache:flush

**Refresh Statistics**: Update temporal content counters and metrics

**Export Report**: Download temporal content inventory as CSV

**Test Configuration**: Simulate cache behavior with current settings

Module Tabs Overview
=====================

Dashboard Tab
-------------

The main overview showing:

- Statistics cards (total, active, pending, expired content)
- Timeline visualization of upcoming transitions
- Performance metrics and cache impact
- Configuration summary

See :ref:`backend-dashboard` for detailed information.

Content Tab
-----------

Browse and manage temporal content:

- Filter by status (all, active, pending, expired)
- Search by page title or content
- Bulk operations (edit times, clear cache, export)
- Detailed view per item

See :ref:`backend-content` for detailed information.

Configuration Wizard Tab
-------------------------

Guided setup process:

- Site profile selection (small, medium, large)
- Preset configurations
- Performance impact calculator
- Test and validate settings

See :ref:`backend-wizard` for detailed information.

Related Documentation
=====================

- :ref:`configuration` - Detailed configuration reference
- :ref:`performance-considerations` - Performance implications
- :ref:`installation` - Installation and setup
- :ref:`phases` - Future improvements roadmap

.. Meta Menu

.. toctree::
   :hidden:
   :maxdepth: 2

   Dashboard
   Content
   Wizard
   Tips
