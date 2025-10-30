# Improvement Proposal: Respect TYPO3's config.cache_period

## Current Implementation Problem

We define our own `default_max_lifetime` (86400) which duplicates TYPO3's `defaultCacheTimeout` constant.

**Current code:**
```php
// ext_conf_template.txt
advanced.default_max_lifetime = 86400

// ExtensionConfiguration.php
public function getDefaultMaxLifetime(): int
{
    return (int)($this->config['advanced']['default_max_lifetime'] ?? 86400);
}

// TemporalCacheLifetime.php
$maxLifetime = $this->extensionConfiguration->getDefaultMaxLifetime();
$cappedLifetime = \min($lifetime, $maxLifetime);
```

**Problems:**
1. Duplicates TYPO3's magic number (86400)
2. Ignores site-wide cache configuration (`config.cache_period`)
3. Creates two separate "maximum cache lifetime" settings

## Better Approach: Respect TYPO3's Configuration

**We have access to `$event->getRenderingInstructions()`** which contains TypoScript `config.cache_period`!

**Improved implementation:**
```php
// TemporalCacheLifetime.php
public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
{
    try {
        $lifetime = $this->timingStrategy->getCacheLifetime($this->context);

        if ($lifetime !== null) {
            // Respect TYPO3's cache configuration hierarchy:
            // 1. TypoScript config.cache_period (site-wide setting)
            // 2. Extension's default_max_lifetime (fallback)
            // 3. TYPO3's default (86400) (final fallback)
            $renderingInstructions = $event->getRenderingInstructions();
            $configuredMaxLifetime = (int)($renderingInstructions['cache_period'] ??
                $this->extensionConfiguration->getDefaultMaxLifetime());

            $cappedLifetime = \min($lifetime, $configuredMaxLifetime);
            $event->setCacheLifetime($cappedLifetime);

            if ($this->extensionConfiguration->isDebugLoggingEnabled()) {
                $this->logger->debug(
                    'Temporal cache lifetime set',
                    [
                        'lifetime' => $cappedLifetime,
                        'uncapped_lifetime' => $lifetime,
                        'max_from_typoscript' => $renderingInstructions['cache_period'] ?? null,
                        'max_from_extension_config' => $this->extensionConfiguration->getDefaultMaxLifetime(),
                        'timing_strategy' => $this->timingStrategy->getName(),
                    ]
                );
            }
        }
    } catch (\Throwable $e) {
        // ... error handling
    }
}
```

## Benefits

1. ✅ **Respects site-wide cache configuration**
   - If admin sets `config.cache_period = 43200` (12h), we respect it
   - If admin sets `config.cache_period = 3600` (1h), we respect it

2. ✅ **Single source of truth**
   - TYPO3's TypoScript configuration controls cache behavior
   - Our extension respects that hierarchy

3. ✅ **Backward compatible**
   - Falls back to extension config if TypoScript not set
   - Falls back to 86400 if extension config not set

4. ✅ **No magic number duplication**
   - We use what TYPO3 already calculated
   - Aligns with TYPO3's architecture

## Configuration Hierarchy (Improved)

```
Page-specific:
  └─> Page record 'cache_timeout' field [TYPO3 handles this]

Site-wide:
  └─> TypoScript config.cache_period [WE READ THIS]
       └─> Extension config 'default_max_lifetime' [Our fallback]
            └─> 86400 hardcoded [Final fallback]
```

## Migration

**For existing users:**
- No breaking changes
- Extension config still works as fallback
- TypoScript config takes precedence if set

**Documentation update needed:**
```rst
.. confval:: advanced.default_max_lifetime

   :type: integer
   :Default: ``86400``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['temporal_cache']['advanced']['default_max_lifetime']

   Maximum cache lifetime in seconds when no temporal content exists.

   **Configuration Priority:**

   1. TypoScript ``config.cache_period`` (if configured)
   2. This extension setting (fallback)
   3. 86400 seconds / 24 hours (final fallback)

   **Why this exists:**

   TYPO3 has a default cache timeout (24 hours), but our extension can override
   it when calculating temporal transitions. This setting caps our calculated
   lifetime to prevent extremely long cache durations.

   **Best Practice:**

   Configure site-wide cache via TypoScript instead:

   .. code-block:: typoscript

      config.cache_period = 43200  # 12 hours site-wide

   This setting automatically applies to temporal cache calculations.
```

## Alternative: Remove Extension Config Entirely

**Even simpler approach:**

```php
// Just use TYPO3's value directly, no extension config needed
$maxLifetime = (int)($renderingInstructions['cache_period'] ?? 86400);
$cappedLifetime = \min($lifetime, $maxLifetime);
```

**Pros:**
- One less configuration option
- Pure alignment with TYPO3
- Simpler code

**Cons:**
- Less flexibility (can't override TYPO3's setting for temporal cache specifically)
- Removes user control

## Recommendation

**Implement the improved hierarchy approach:**
1. Read `config.cache_period` from TypoScript first
2. Fall back to extension config
3. Final fallback to 86400

**Rationale:**
- Respects TYPO3's architecture
- Provides flexibility when needed
- Maintains backward compatibility
- Educates users about TYPO3's cache configuration

---

**Date**: 2025-10-30
**Context**: Code review question about duplicate configuration
**Impact**: Non-breaking improvement, better TYPO3 integration
**Effort**: ~1 hour (code + tests + documentation)
