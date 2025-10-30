.. include:: /Includes.rst.txt

.. _backend-content:

===========
Content Tab
===========

Browse and manage all temporal content (pages and content elements).

Interface Overview
==================

The Content tab displays:

1. **Filter Bar**

   - Filter dropdown (All / Active / Pending / Expired)
   - Search field (by title)
   - Bulk action buttons (Harmonize Selected, Export)

2. **Content Table**

   - Columns: Type, Title, Start Time, End Time, Status, Harmonization Suggestion, Actions
   - Sortable by any column
   - Pagination (50 items per page)

3. **Item Actions**

   - Edit (opens page/content record)
   - Apply Harmonization (one-click harmonize)
   - View Details (shows affected pages for content elements)

Filter Options
==============

All Content
-----------

Shows every page and content element with starttime or endtime set.

.. code-block:: text

   Total: 168 items
   - 45 pages
   - 123 content elements

Active Content
--------------

Content currently visible (between starttime and endtime).

.. code-block:: text

   Active: 89 items currently visible
   - Starttime in past or 0
   - Endtime in future or 0
   - Current timestamp within visibility window

Pending Content
---------------

Content scheduled for future activation.

.. code-block:: text

   Pending: 56 items with future starttime
   - Not yet visible
   - Will become active when starttime arrives

Expired Content
---------------

Content past its endtime.

.. code-block:: text

   Expired: 23 items past endtime
   - Endtime in the past
   - Should no longer be visible

Content Table Columns
=====================

Type
----

Icon and label indicating record type:

- 📄 Page
- 📝 Content Element
- 📰 News Article (if ext:news installed)
- 📅 Event (if ext:events installed)

Title
-----

Page title or content element header.

**Actions**:

- Click title → Edit record
- Hover → Show full path (for pages) or parent page (for content)

Start Time
----------

Timestamp when content becomes visible.

**Format**: YYYY-MM-DD HH:MM:SS

**Special values**:

- `0` or empty → No start restriction
- Past time → Currently active (if no endtime or endtime in future)
- Future time → Pending activation

End Time
--------

Timestamp when content expires.

**Format**: YYYY-MM-DD HH:MM:SS

**Special values**:

- `0` or empty → No end restriction
- Past time → Expired
- Future time → Will expire at this time

Status
------

Current visibility state with color coding:

- 🟢 **Active**: Currently visible
- 🟡 **Pending**: Future starttime
- 🔴 **Expired**: Past endtime
- ⚪ **Always**: No temporal restrictions (shown if "All" filter active)

Harmonization Suggestion
-------------------------

Recommended time adjustment for harmonization:

.. code-block:: text

   Original:     2025-10-30 09:17:42
   Suggested:    2025-10-30 09:00:00
   Shift:        -17 minutes 42 seconds
   Benefit:      Groups with 3 other items

**Interpretation**:

- Green badge: Minor shift (<10 minutes)
- Yellow badge: Moderate shift (10-30 minutes)
- Red badge: Large shift (>30 minutes, review manually)
- Gray badge: Already harmonized or outside tolerance

Actions Column
==============

Per-Item Actions
----------------

**Edit** (pencil icon)
   Opens the record for editing in TYPO3 backend

**Apply Harmonization** (clock icon)
   One-click apply suggested harmonization

   - Updates starttime/endtime to nearest slot
   - Respects tolerance setting
   - Shows confirmation before applying

**View Details** (info icon)
   Shows additional information:

   - For pages: Child pages, content elements count
   - For content: Parent page, containing column
   - Affected pages (if per-content scoping)

**Clear Cache** (refresh icon)
   Force cache regeneration for this item

   - Pages: Clears page cache
   - Content: Clears parent page cache

Bulk Operations
===============

Select Multiple Items
---------------------

- Checkbox in first column
- "Select All" checkbox in table header
- Selection count displayed

Available Bulk Actions
----------------------

**Harmonize Selected**
   Apply harmonization to all selected items

   **Process**:

   1. Validates each item's harmonization suggestion
   2. Shows preview of changes
   3. Applies on confirmation
   4. Reports success/failures

**Export Selected**
   Download CSV with temporal content details

   **CSV Columns**:

   - Type, UID, Title, Start Time, End Time, Status
   - Page Path (for pages)
   - Parent Page (for content elements)

**Clear Cache for Selected**
   Force cache regeneration for all selected items

Search and Filter
=================

Search Field
------------

Search by title (case-insensitive):

.. code-block:: text

   Search: "news"
   Results: All items with "news" in title
   - News Landing Page
   - Latest News Content Element
   - News Article 1, 2, 3...

**Tips**:

- Partial matching supported
- Searches both page titles and content element headers
- Combine with filters for precise results

Combined Filtering
------------------

Apply both status filter and search:

.. code-block:: text

   Filter: Pending
   Search: "event"
   Result: All future events not yet visible

Detailed View
=============

Click "View Details" to see comprehensive information:

Page Details
------------

.. code-block:: text

   Page Information:
   - UID: 123
   - Title: "Summer Campaign Landing Page"
   - Path: /campaigns/summer-2025
   - Start: 2025-06-01 00:00:00
   - End: 2025-08-31 23:59:59

   Child Content:
   - 12 content elements (5 with temporal restrictions)
   - 3 child pages

   Cache Impact:
   - Scoping: This page only (per-page strategy)
   - Transitions: 2 (start + end)

Content Element Details
-----------------------

.. code-block:: text

   Content Element Information:
   - UID: 456
   - Type: Text & Media
   - Header: "Limited Time Offer"
   - Parent Page: /homepage (UID: 1)
   - Column: Main Content (colPos: 0)
   - Start: 2025-10-30 09:00:00
   - End: 2025-11-05 23:59:59

   Cache Impact:
   - Affected Pages: 1 (homepage only)
   - Scoping: Per-content strategy
   - Transitions: 2 (start + end)

Use Cases
=========

Audit Temporal Content
----------------------

**Goal**: Understand what content has temporal restrictions

**Steps**:

1. Select "All" filter
2. Sort by "Type"
3. Export to CSV for documentation

Review Expired Content
----------------------

**Goal**: Find content that should be removed or renewed

**Steps**:

1. Select "Expired" filter
2. Review list
3. Edit or delete expired items

Plan Cache Warming
------------------

**Goal**: Identify major upcoming transitions

**Steps**:

1. Select "Pending" filter
2. Sort by "Start Time"
3. Note times with many simultaneous items
4. Schedule cache warming 5 minutes before

Apply Harmonization
-------------------

**Goal**: Reduce cache churn by grouping transitions

**Steps**:

1. Select "All" or "Pending" filter
2. Review "Harmonization Suggestion" column
3. Select items with green/yellow badges
4. Click "Harmonize Selected"
5. Confirm changes

Next Steps
==========

- :ref:`backend-dashboard` - View performance metrics
- :ref:`backend-wizard` - Configure harmonization slots
- :ref:`performance-strategies` - Understand optimization
- :ref:`backend-tips` - Best practices
