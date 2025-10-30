.. include:: /Includes.rst.txt

.. _performance-alternatives:

================================
Alternative Approaches
================================

Overview of Alternatives
========================

If this extension's performance implications are unsuitable for your site, several
alternative approaches exist to handle temporal content in TYPO3:

1. USER_INT menus (uncached navigation)
2. SSI/ESI (Server/Edge Side Includes)
3. AJAX menus (client-side fetching)
4. Scheduled cache clearing
5. Manual cache management

Each has different trade-offs between accuracy, performance, and complexity.

Comparison Matrix
=================

.. list-table::
   :header-rows: 1
   :widths: 20 16 16 16 16 16

   * - Approach
     - Setup Complexity
     - Temporal Accuracy
     - Performance Impact
     - Cache Hit Ratio
     - Best Use Case
   * - **Extension**
     - ✅ Simple
     - ✅✅ Perfect
     - ⚠️ Burst load
     - ⚠️ Variable
     - Rare transitions
   * - **USER_INT**
     - ✅ Simple
     - ✅✅ Perfect
     - ⚠️ Steady load
     - ✅ High (pages)
     - High traffic
   * - **SSI/ESI**
     - ⚠️ Complex
     - ✅✅ Perfect
     - ✅ Optimized
     - ✅ High
     - Edge caching
   * - **AJAX**
     - ⚠️ Complex
     - ✅✅ Perfect
     - ✅ Client-side
     - ✅✅ Very high
     - Modern SPAs
   * - **Scheduled**
     - ⚠️ Medium
     - ❌ Delayed
     - ✅ Predictable
     - ✅ High
     - Low accuracy needs

Alternative 1: USER_INT Menus
==============================

Make menus uncached (regenerated on every request).

How It Works
------------

.. code-block:: typoscript
   :caption: TypoScript Setup

   lib.mainMenu = USER_INT
   lib.mainMenu {
       userFunc = MyVendor\MyExtension\Menu\MenuProcessor->render
   }

**Advantages**:

✅ Simple TypoScript configuration
✅ Perfect temporal accuracy for menus
✅ Page content remains cached
✅ Steady, predictable performance

**Disadvantages**:

❌ Menu regenerated on EVERY request
❌ Moderate per-request overhead (~10-50ms)
❌ Doesn't solve temporal content in page body
❌ CDN cannot cache dynamic menus

**Performance Impact**:

.. code-block:: text

   Cost_per_request = menu_generation_time
   Daily_cost = requests_per_day × menu_generation_time

   Example:
   100,000 req/day × 20ms = 2,000 seconds/day of menu generation time

**When to Use**:

✅ Temporal content is ONLY in menus
✅ High-traffic sites where steady load is preferable to spikes
✅ Site can tolerate 10-50ms per-request overhead

See :ref:`decision-guide` for comparison formula.

Alternative 2: SSI/ESI (Server/Edge Side Includes)
===================================================

Split menus into uncached fragments included at serving time.

SSI (Server Side Includes)
---------------------------

.. code-block:: apache
   :caption: Apache .htaccess

   <IfModule mod_include.c>
       Options +Includes
       AddOutputFilter INCLUDES .html
   </IfModule>

.. code-block:: html
   :caption: Template

   <div class="navigation">
       <!--#include virtual="/menu-fragment.php" -->
   </div>

ESI (Edge Side Includes)
-------------------------

.. code-block:: text
   :caption: Varnish VCL

   sub vcl_recv {
       if (req.url ~ "^/menu-fragment") {
           return (pass); // Don't cache menu fragments
       }
   }

.. code-block:: html
   :caption: Template

   <div class="navigation">
       <esi:include src="/menu-fragment.php" />
   </div>

**Advantages**:

✅ Page body remains fully cached
✅ Menu freshness without full page regeneration
✅ Works with CDN (ESI supported by most CDNs)
✅ Optimal performance characteristics

**Disadvantages**:

❌ Complex server/CDN configuration
❌ Requires SSI/ESI support in infrastructure
❌ Debugging is more difficult
❌ Not all CDNs support ESI

**When to Use**:

✅ Using CDN with ESI support (Fastly, Akamai, Varnish)
✅ High-traffic sites needing optimal performance
✅ Team has infrastructure expertise

Alternative 3: AJAX Menus
==========================

Load menus via JavaScript after page load.

Implementation
--------------

.. code-block:: javascript
   :caption: Frontend JavaScript

   // Fetch fresh menu data
   fetch('/api/menu')
       .then(response => response.json())
       .then(data => {
           document.querySelector('.navigation').innerHTML = renderMenu(data);
       });

.. code-block:: php
   :caption: TYPO3 Menu API Endpoint

   <?php
   namespace MyVendor\MyExtension\Controller;

   class MenuController {
       public function apiAction(): ResponseInterface {
           $menu = $this->menuRepository->findVisible(time());
           return $this->jsonResponse($menu);
       }
   }

**Advantages**:

✅ Page HTML fully cached
✅ Perfect temporal accuracy
✅ No server-side rendering overhead
✅ Client-side caching possible (Service Workers)

**Disadvantages**:

❌ Requires JavaScript (accessibility concern)
❌ Flash of empty navigation (FOUC)
❌ SEO implications (search engines may not see menu)
❌ More complex frontend development

**When to Use**:

✅ Modern SPA or JavaScript-heavy sites
✅ Acceptable UX trade-offs
✅ SEO not critical for menu items

Alternative 4: Scheduled Cache Clearing
========================================

Clear cache on fixed schedule (e.g., every hour).

Implementation
--------------

.. code-block:: bash
   :caption: Crontab

   # Clear TYPO3 cache every hour
   0 * * * * /path/to/typo3/vendor/bin/typo3 cache:flush

Or with warming:

.. code-block:: bash
   :caption: Crontab with warming

   # Clear cache and warm critical pages
   0 * * * * /path/to/typo3/vendor/bin/typo3 cache:flush && \
             /path/to/scripts/warm-cache.sh

**Advantages**:

✅ Extremely simple
✅ Zero code changes
✅ Predictable resource usage

**Disadvantages**:

❌ Temporal content can be stale (up to 1 hour)
❌ Doesn't solve Forge #14277 (menus show wrong content)
❌ Manual configuration required
❌ Cache warming creates load spikes

**When to Use**:

⚠️ **Only suitable for**:

- Sites that can tolerate temporal inaccuracy
- Regular content schedules (e.g., always publish at 09:00, 12:00, 17:00)
- Low temporal content frequency

Alternative 5: Manual Cache Management
=======================================

Editors manually clear cache after scheduling content.

Implementation
--------------

.. code-block:: text

   Editor workflow:
   1. Edit page, set starttime = 10:00 AM
   2. Save page
   3. Wait until 10:00 AM
   4. Go to Admin Tools → Clear Cache
   5. Clear "Pages" cache

**Advantages**:

✅ Zero technical implementation
✅ Complete editorial control

**Disadvantages**:

❌ Error-prone (editors forget)
❌ High editorial overhead
❌ Doesn't scale with multiple editors
❌ Doesn't solve the core problem

**When to Use**:

❌ **Not recommended** - defeats the purpose of automatic scheduling

Decision Framework
==================

**Step 1: Identify Temporal Content Location**

.. code-block:: text

   If temporal content is ONLY in menus:
       → Consider USER_INT menu (simplest solution)

   If temporal content is in main page body:
       → Extension or AJAX required

**Step 2: Calculate Your Metrics**

.. code-block:: text

   A = Requests_per_day
   B = Temporal_transitions_per_day
   C = Number_of_pages
   D = Menu_render_time (ms)
   E = Page_regeneration_time (ms)

**Step 3: Apply Decision Formula**

.. code-block:: text

   USER_INT cost = A × D
   Extension cost = B × C × E

   If (A × D) < (B × C × E):
       → Use USER_INT
   Else:
       → Use Extension

**Step 4: Consider Infrastructure**

.. code-block:: text

   If has_CDN && complex_menus:
       → Consider SSI/ESI

   If SPA || modern_frontend:
       → Consider AJAX

Recommendation by Site Profile
===============================

**Profile 1: Traditional Corporate Site**

- Pages: <500
- Traffic: <50,000 req/day
- Temporal: <5 transitions/day

→ **Recommendation**: Extension (simple, effective)

**Profile 2: News/Magazine Site**

- Pages: 500-5,000
- Traffic: 100,000-1M req/day
- Temporal: 10-50 transitions/day

→ **Recommendation**: USER_INT menu or SSI (better performance)

**Profile 3: Enterprise Portal**

- Pages: >10,000
- Traffic: >1M req/day
- Temporal: >50 transitions/day

→ **Recommendation**: Wait for Phase 2/3 or custom solution

**Profile 4: E-commerce/SPA**

- Pages: Variable
- Traffic: High
- Modern frontend: Yes

→ **Recommendation**: AJAX menu with client-side caching

Next Steps
==========

- :ref:`decision-guide` - Site-specific decision matrix
- :ref:`phases` - Future Phase 2/3 improvements
- :ref:`installation` - Install this extension
- :ref:`configuration` - Configure optimization strategies
