.. include:: /Includes.rst.txt

.. _performance-considerations:

============================
Performance Considerations
============================

Critical Information for Production Deployment
==============================================

.. important::
   This extension implements **site-wide cache synchronization** to solve temporal content issues.
   Understanding the performance implications is critical before deploying to production.

   **Read this documentation completely before production deployment.**

Overview
========

The TYPO3 Temporal Cache extension addresses Forge #14277 by dynamically adjusting cache lifetime
based on temporal content transitions. The extension provides flexible optimization strategies
to match different site sizes and requirements.

**Key Performance Factors:**

✅ **Addresses 20-year-old temporal content bug** - Automatic cache invalidation
⚠️ **Site-wide cache synchronization** - Phase 1 architectural constraint
✅ **Mitigation strategies available** - Scoping, timing, harmonization
⚠️ **Performance implications** - Varies by site size and configuration
🔄 **Future improvements** - Phase 2/3 will eliminate constraints

.. important::
   For most small to medium sites (<10,000 pages, <50 transitions/day), the benefits
   (correct temporal behavior) far outweigh the costs (moderate performance impact).

   For large or high-traffic sites, **thorough testing is mandatory** before production.

Quick Decision Guide
====================

.. list-table::
   :header-rows: 1
   :widths: 30 40 30

   * - Site Profile
     - Recommendation
     - Action
   * - **Small**
       (<1,000 pages)
     - ✅ Safe to install
       Default configuration works well
     - Install and monitor
   * - **Medium**
       (1,000-10,000 pages)
     - ⚠️ Test thoroughly
       Optimize with scoping strategies
     - Test → Optimize → Deploy
   * - **Large**
       (>10,000 pages)
     - ⚠️ Evaluate carefully
       Implement all mitigations
     - Test → Mitigate → Monitor
   * - **Enterprise**
       (>50,000 pages, >1M req/day)
     - ❌ Not recommended
       Wait for Phase 2/3 or custom solution
     - Consider alternatives

See :ref:`decision-guide` for detailed decision framework.

Performance Topics
==================

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: ⚡ Optimization Strategies

        Learn the three complementary optimization approaches: scoping strategies,
        timing strategies, and time harmonization. Achieve 99%+ reduction in cache churn.

        ..  card-footer:: :ref:`Read Strategies <performance-strategies>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚠️ Phase 1 Limitations

        Understand architectural constraints, site-wide cache synchronization,
        and performance implications of the Phase 1 approach.

        ..  card-footer:: :ref:`Read Limitations <performance-limitations>`
            :button-style: btn btn-warning stretched-link

    ..  card:: 🎯 Decision Guide

        Site-specific recommendations with decision matrices for small, medium,
        large, and high-traffic sites. Make informed deployment decisions.

        ..  card-footer:: :ref:`Read Decision Guide <decision-guide>`
            :button-style: btn btn-info stretched-link

    ..  card:: 🔄 Alternative Approaches

        Explore alternatives: USER_INT menus, SSI/ESI, AJAX, scheduled cache clearing.
        Compare trade-offs and find the best solution for your needs.

        ..  card-footer:: :ref:`Read Alternatives <performance-alternatives>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: ❓ Frequently Asked Questions

        Common questions about cache synchronization, CDN compatibility, backend
        performance, and when NOT to use this extension.

        ..  card-footer:: :ref:`Read FAQ <performance-faq>`
            :button-style: btn btn-success stretched-link

Summary: Key Takeaways
======================

**Benefits:**

✅ Addresses 20-year-old temporal content bug (Forge #14277)
✅ Automatic, zero-configuration operation
✅ Multiple optimization strategies available
✅ Works today with TYPO3 v12/v13 LTS

**Constraints:**

⚠️ Site-wide cache synchronization (Phase 1 limitation)
⚠️ Performance implications for large/high-traffic sites
⚠️ Requires thorough testing before production
⚠️ Database queries on every cache generation (~5-10ms)

**Future:**

🔄 Phase 2/3 will eliminate performance concerns through core integration
🔄 Extension will become obsolete when TYPO3 core provides native support
🔄 Your production experience helps shape Phase 2/3 design

See :ref:`phases` for complete roadmap and migration path.

Related Documentation
=====================

- :ref:`installation` - Installation guide
- :ref:`configuration` - Detailed configuration options
- :ref:`architecture` - Technical implementation details
- :ref:`phases` - Three-phase solution roadmap

.. Meta Menu

.. toctree::
   :hidden:
   :maxdepth: 2

   Strategies
   Limitations
   DecisionGuide
   Alternatives
   FAQ
