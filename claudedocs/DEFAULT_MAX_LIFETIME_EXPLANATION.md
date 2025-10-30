# Why default_max_lifetime is Required

## The Critical Question

**"Why is `default_max_lifetime` required? Does TYPO3 not have this safety net? Or do we override this safety net somehow?"**

## The Answer: We Override TYPO3's Safety Net

### TYPO3's Built-In Behavior (Without Our Extension)

Looking at TYPO3's `CacheLifetimeCalculator.php`:

```php
// Line 44: TYPO3's default cache timeout
protected const defaultCacheTimeout = 86400; // 24 hours

// Line 110: Calculates page cache lifetime
$cacheTimeout = $defaultCacheTimoutInSeconds ?:
    (int)($renderingInstructions['cache_period'] ?? self::defaultCacheTimeout);

// Line 152: If no temporal content transitions found
return $result === PHP_INT_MAX ? PHP_INT_MAX : $result - $currentTimestamp + 1;
```

**TYPO3's behavior:**
1. ✅ Has a 24-hour default cache timeout
2. ✅ Checks starttime/endtime fields for cache lifetime calculation
3. ⚠️ BUT only for tables configured in `config.cache.all` (e.g., `tt_content:42`)
4. ⚠️ Default configuration: Only checks `tt_content:currentPage` - **NOT pages table**
5. 🚨 **If no transitions found: Returns `PHP_INT_MAX` (effectively infinite cache!)**

### The Event System: The Override Mechanism

After TYPO3 calculates cache lifetime, it dispatches `ModifyCacheLifetimeForPageEvent`:

```php
// Line 126-134 in CacheLifetimeCalculator.php
$event = new ModifyCacheLifetimeForPageEvent(
    $cacheTimeout,  // Could be PHP_INT_MAX or 86400
    $pageId,
    $pageRecord,
    $renderingInstructions,
    $context
);
$event = $this->eventDispatcher->dispatch($event);
$cacheTimeout = $event->getCacheLifetime(); // Extensions can OVERRIDE this
```

**Key Point**: Extensions listening to this event can OVERRIDE the calculated lifetime.

### Our Extension's Behavior (With Extension Active)

Our `TemporalCacheLifetime` event listener:

```php
// Lines 50-57 in Classes/EventListener/TemporalCacheLifetime.php
$lifetime = $this->timingStrategy->getCacheLifetime($this->context);

if ($lifetime !== null) {
    // Cap at configured maximum to prevent extremely long cache lifetimes
    $maxLifetime = $this->extensionConfiguration->getDefaultMaxLifetime();
    $cappedLifetime = \min($lifetime, $maxLifetime);

    $event->setCacheLifetime($cappedLifetime); // WE OVERRIDE TYPO3'S VALUE
}
```

**What we do:**
1. ✅ Check ALL temporal content (pages + tt_content + custom tables)
2. ✅ Calculate next transition time accurately
3. ⚠️ Could calculate very long lifetimes (e.g., 6 months = 15,552,000 seconds)
4. ✅ Cap it with `default_max_lifetime` (default: 86400) to prevent abuse

### The Problem Without default_max_lifetime

**Scenario**: No temporal content has transitions scheduled for 6 months

**Without our extension:**
```
TYPO3 calculates: PHP_INT_MAX (infinite)
TYPO3's 24-hour default still applies via other mechanisms
Result: Page cached for reasonable time
```

**With our extension but WITHOUT default_max_lifetime:**
```
Our extension calculates: 15,552,000 seconds (6 months)
Our extension calls: $event->setCacheLifetime(15,552,000)
TYPO3 uses: 15,552,000 seconds
Result: Page cached for 6 MONTHS! (BAD - we overrode TYPO3's safety net)
```

**With our extension AND WITH default_max_lifetime:**
```
Our extension calculates: 15,552,000 seconds (6 months)
Our extension caps it: min(15,552,000, 86400) = 86400
Our extension calls: $event->setCacheLifetime(86400)
TYPO3 uses: 86400 seconds
Result: Page cached for 24 hours (GOOD - we respected reasonable bounds)
```

## Summary Table

| Scenario | Lifetime Set | Why |
|----------|--------------|-----|
| **TYPO3 alone, no transitions** | 24 hours (or PHP_INT_MAX) | Default fallback |
| **TYPO3 alone, transitions found** | Until next transition | Calculated from temporal fields |
| **Our extension, 6-month gap, NO max** | 15,552,000 sec (6 months) | 🚨 We override TYPO3's safety! |
| **Our extension, 6-month gap, WITH max** | 86400 sec (24 hours) | ✅ Our safety cap prevents abuse |

## Key Insights

1. **TYPO3 HAS a 24-hour default safety net**
   - But it only applies to its own calculations

2. **TYPO3 checks temporal fields**
   - But only for configured tables (default: only tt_content)
   - Returns PHP_INT_MAX if no transitions found

3. **Events can OVERRIDE core behavior**
   - This is the power and danger of the event system
   - Extensions can replace TYPO3's calculated values

4. **`default_max_lifetime` is OUR safety net**
   - Prevents us from setting unreasonably long cache lifetimes
   - Ensures we don't abuse our override power
   - Keeps cache refreshes regular even when no transitions scheduled

## The "Admin Bypass for Efficiency" Clarification

In the `PermissionService`:

```php
public function canModifyTemporalContent(?string $tableName = null): bool
{
    $user = $this->getBackendUser();

    // Admin users can do everything
    if ($user->isAdmin()) {
        return true;  // Skip checking 10+ tables individually
    }

    // For non-admins, check table permissions...
}
```

**This means**: If user is admin, immediately return true without checking individual table permissions.

**Why?** Admins have all permissions anyway, so checking each table (pages, tt_content, custom tables) would be wasteful. This is standard TYPO3 practice and a performance optimization, not a security concern.

## Related Architecture

The extension's approach to handling TYPO3 core behavior:

```
TYPO3 Core
  └─> Calculates cache lifetime (24h default, PHP_INT_MAX fallback)
       └─> Dispatches ModifyCacheLifetimeForPageEvent
            └─> Our Extension Listens
                 ├─> Calculates better lifetime (checks all temporal content)
                 ├─> Caps with default_max_lifetime (prevent abuse)
                 └─> Overrides event value
                      └─> TYPO3 uses our value for actual caching
```

**The takeaway**: Extensions using events to override core behavior MUST implement their own safety nets, because they're replacing the core's safety mechanisms.

---

**Date**: 2025-10-30
**Context**: Documentation review and architecture explanation
**Related**: TYPO3 Forge Issue #14277
