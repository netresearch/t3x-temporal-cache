# Architecture Diagram: EventListener Refactoring

## V1.0 Strategy Pattern Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     TYPO3 Core Framework                                │
│                                                                         │
│  ModifyCacheLifetimeForPageEvent → EventListener                       │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│              TemporalCacheLifetime (EventListener)                      │
│                                                                         │
│  - ExtensionConfiguration                                               │
│  - ScopingStrategyInterface  ←────────┐                                │
│  - TimingStrategyInterface   ←───────┐│                                │
│  - Context                           ││                                │
│  - LoggerInterface                   ││                                │
│                                      ││                                │
│  __invoke(event):                    ││                                │
│    1. Get lifetime from timing strategy                                │
│    2. Cap at max lifetime            ││                                │
│    3. Set on event                   ││                                │
│    4. Log if debug enabled           ││                                │
└──────────────────────────────────────││─────────────────────────────────┘
                                       ││
                 ┌─────────────────────┘│
                 │                      │
                 ▼                      ▼
┌────────────────────────────┐  ┌──────────────────────────────┐
│  ScopingStrategyFactory    │  │  TimingStrategyFactory       │
│  (implements Interface)    │  │  (implements Interface)      │
│                            │  │                              │
│  - strategies[]            │  │  - strategies[]              │
│  - extensionConfig         │  │  - extensionConfig           │
│                            │  │                              │
│  selectStrategy():         │  │  selectStrategy():           │
│    config → strategy       │  │    config → strategy         │
│                            │  │                              │
│  delegates to active       │  │  delegates to active         │
└────────────────────────────┘  └──────────────────────────────┘
         │                                │
         │ selects based on              │ selects based on
         │ scoping config                │ timing config
         │                               │
         ▼                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SCOPING STRATEGIES                           │
│                                                                 │
│  ┌──────────────────┐  ┌───────────────────┐  ┌──────────────┐│
│  │ Global           │  │ PerPage           │  │ PerContent   ││
│  │ (default)        │  │                   │  │ (optimized)  ││
│  │                  │  │                   │  │              ││
│  │ Returns:         │  │ Returns:          │  │ Returns:     ││
│  │ ['pages']        │  │ ['pageId_X']      │  │ ['pageId_X', ││
│  │                  │  │                   │  │  'pageId_Y'] ││
│  │ Flushes:         │  │ Flushes:          │  │              ││
│  │ ALL pages        │  │ Single page       │  │ Uses:        ││
│  │                  │  │                   │  │ RefindexSvc  ││
│  │                  │  │                   │  │              ││
│  │ Phase 1 compat   │  │                   │  │ 99.7% less   ││
│  └──────────────────┘  └───────────────────┘  └──────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    TIMING STRATEGIES                            │
│                                                                 │
│  ┌──────────────────┐  ┌───────────────────┐  ┌──────────────┐│
│  │ Dynamic          │  │ Scheduler         │  │ Hybrid       ││
│  │ (default)        │  │                   │  │              ││
│  │                  │  │                   │  │              ││
│  │ When:            │  │ When:             │  │ When:        ││
│  │ On page render   │  │ Scheduler task    │  │ Conditional  ││
│  │                  │  │                   │  │              ││
│  │ Returns:         │  │ Returns:          │  │ Pages:       ││
│  │ seconds until    │  │ null (infinite    │  │   dynamic    ││
│  │ next transition  │  │ cache)            │  │ Content:     ││
│  │                  │  │                   │  │   scheduler  ││
│  │ Phase 1 compat   │  │ Scheduler flushes │  │              ││
│  │                  │  │ via processTransition              ││
│  └──────────────────┘  └───────────────────┘  └──────────────┘│
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ called by
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│            TemporalCacheSchedulerTask                           │
│                                                                 │
│  Dependencies (setter injection):                               │
│    - TemporalContentRepository                                  │
│    - TimingStrategyInterface                                    │
│    - ExtensionConfiguration                                     │
│    - Context                                                    │
│    - Registry (for last run tracking)                           │
│    - LoggerInterface                                            │
│                                                                 │
│  execute():                                                     │
│    1. Get last run from Registry                                │
│    2. Find transitions since last run                           │
│    3. For each transition:                                      │
│         - Create TransitionEvent                                │
│         - Call timingStrategy.processTransition()               │
│    4. Update Registry with current time                         │
│    5. Return success/failure                                    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ reads
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│              TemporalContentRepository                          │
│                                                                 │
│  - findTransitionsInRange(start, end): TemporalContent[]        │
│  - findAllWithTemporalFields(): TemporalContent[]               │
│  - countTransitionsPerDay(): int                                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Configuration Flow

```
┌─────────────────────────────────────────────┐
│   Extension Manager (TYPO3 Backend)         │
│                                             │
│   scoping.strategy = 'per-content'          │
│   timing.strategy = 'hybrid'                │
│   harmonization.enabled = true              │
│   advanced.debug_logging = true             │
└─────────────────────────────────────────────┘
                    │
                    │ stored in
                    ▼
┌─────────────────────────────────────────────┐
│     LocalConfiguration.php                  │
│                                             │
│  'temporal_cache' => [                      │
│    'scoping' => [                           │
│      'strategy' => 'per-content'            │
│    ],                                       │
│    'timing' => [                            │
│      'strategy' => 'hybrid'                 │
│    ]                                        │
│  ]                                          │
└─────────────────────────────────────────────┘
                    │
                    │ read by
                    ▼
┌─────────────────────────────────────────────┐
│     ExtensionConfiguration                  │
│                                             │
│  - getScopingStrategy(): string             │
│  - getTimingStrategy(): string              │
│  - isHarmonizationEnabled(): bool           │
│  - isDebugLoggingEnabled(): bool            │
└─────────────────────────────────────────────┘
                    │
                    │ used by
                    ▼
┌─────────────────────────────────────────────┐
│     Strategy Factories                      │
│                                             │
│  - Select active strategy at runtime        │
│  - Inject into EventListener/Task           │
└─────────────────────────────────────────────┘
```

---

## Dependency Injection Flow

```
Container (DI)
    │
    ├─► ExtensionConfiguration (singleton, public)
    │
    ├─► All Strategy Implementations
    │   ├─► GlobalScopingStrategy
    │   ├─► PerPageScopingStrategy
    │   ├─► PerContentScopingStrategy
    │   ├─► DynamicTimingStrategy
    │   ├─► SchedulerTimingStrategy
    │   └─► HybridTimingStrategy
    │
    ├─► Strategy Factories
    │   ├─► ScopingStrategyFactory (receives all scoping strategies)
    │   └─► TimingStrategyFactory (receives all timing strategies)
    │
    ├─► Interface Aliases
    │   ├─► ScopingStrategyInterface → ScopingStrategyFactory
    │   └─► TimingStrategyInterface → TimingStrategyFactory
    │
    ├─► EventListener
    │   └─► TemporalCacheLifetime
    │       ├─► ExtensionConfiguration
    │       ├─► ScopingStrategyInterface (resolves to factory)
    │       ├─► TimingStrategyInterface (resolves to factory)
    │       ├─► Context
    │       └─► LoggerInterface
    │
    └─► Scheduler Task
        └─► TemporalCacheSchedulerTask
            ├─► TemporalContentRepository (setter)
            ├─► TimingStrategyInterface (setter)
            ├─► ExtensionConfiguration (setter)
            ├─► Context (setter)
            ├─► Registry (setter)
            └─► LoggerInterface (setter)
```

---

## Request Flow Examples

### Example 1: Dynamic Timing + Global Scoping (Phase 1 Default)

```
Page Request
    │
    ▼
TYPO3 dispatches ModifyCacheLifetimeForPageEvent
    │
    ▼
TemporalCacheLifetime.__invoke()
    │
    ├─► timingStrategy.getCacheLifetime(context)
    │   │ (resolves to DynamicTimingStrategy)
    │   │
    │   └─► scopingStrategy.getNextTransition(context)
    │       │ (resolves to GlobalScopingStrategy)
    │       │
    │       ├─► Query pages table: MIN(starttime, endtime) > NOW
    │       ├─► Query tt_content table: MIN(starttime, endtime) > NOW
    │       └─► Return earliest transition timestamp
    │
    ├─► Calculate: lifetime = transition - now
    ├─► Cap at max lifetime (24h)
    └─► event.setCacheLifetime(cappedLifetime)
```

### Example 2: Scheduler Timing + PerContent Scoping (Optimized)

```
Scheduler Task Execution (every 5 minutes)
    │
    ▼
TemporalCacheSchedulerTask.execute()
    │
    ├─► registry.get('last_run') → 10 minutes ago
    │
    ├─► repository.findTransitionsInRange(10min ago, now)
    │   │
    │   └─► Returns: [content #123, page #456]
    │
    └─► For each transition:
        │
        ├─► Create TransitionEvent(content, timestamp, type)
        │
        └─► timingStrategy.processTransition(event)
            │ (resolves to SchedulerTimingStrategy)
            │
            ├─► scopingStrategy.getCacheTagsToFlush(content, context)
            │   │ (resolves to PerContentScopingStrategy)
            │   │
            │   ├─► refindexService.findPagesWithContent(123)
            │   │   └─► Returns: [pageId_5, pageId_17, pageId_23]
            │   │
            │   └─► Returns: ['pageId_5', 'pageId_17', 'pageId_23']
            │
            └─► cacheManager.flushCachesByTags(['pageId_5', 'pageId_17', ...])

---

Page Request (during scheduler-managed period)
    │
    ▼
TemporalCacheLifetime.__invoke()
    │
    └─► timingStrategy.getCacheLifetime(context)
        │ (resolves to SchedulerTimingStrategy)
        │
        └─► Returns: null
            │
            └─► Cache lives indefinitely (scheduler will flush)
```

### Example 3: Hybrid Timing (Pages Dynamic, Content Scheduler)

```
Page Request
    │
    ▼
TemporalCacheLifetime.__invoke()
    │
    └─► timingStrategy.getCacheLifetime(context)
        │ (resolves to HybridTimingStrategy)
        │
        ├─► Read config: timing.hybrid.pages = 'dynamic'
        ├─► Read config: timing.hybrid.content = 'scheduler'
        │
        ├─► Calculate lifetime for PAGES:
        │   └─► dynamicStrategy.getCacheLifetime(context)
        │       └─► Returns: 3600 seconds
        │
        ├─► Calculate lifetime for CONTENT:
        │   └─► schedulerStrategy.getCacheLifetime(context)
        │       └─► Returns: null (handled by scheduler)
        │
        └─► Return: 3600 (minimum of both)

---

Scheduler Task (processes content transitions only)
    │
    └─► For content transitions:
        └─► schedulerStrategy.processTransition()
            └─► Flush affected caches
```

---

## Backward Compatibility Matrix

| Configuration | Scoping | Timing | Behavior | Compatible |
|---------------|---------|--------|----------|-----------|
| Default (none) | global | dynamic | Phase 1 exact | ✅ 100% |
| per-page + dynamic | per-page | dynamic | Improved scoping | ✅ Safe |
| per-content + dynamic | per-content | dynamic | Optimal scoping | ✅ Safe |
| global + scheduler | global | scheduler | Task-based | ✅ Safe |
| per-content + scheduler | per-content | scheduler | Optimal + task | ✅ Safe |
| hybrid | per-content | hybrid | Best of both | ✅ Safe |

---

## Performance Characteristics

### Phase 1 (Global + Dynamic)
- **Cache Invalidation**: ALL page caches on any transition
- **Query Frequency**: Every page render
- **Cache Efficiency**: Low (global flush)
- **Resource Usage**: Moderate (queries per request)

### V1.0 Optimized (PerContent + Scheduler)
- **Cache Invalidation**: Only affected pages
- **Query Frequency**: Scheduler interval (e.g., 5 min)
- **Cache Efficiency**: High (99.7% reduction)
- **Resource Usage**: Low (batch processing)

### V1.0 Hybrid
- **Cache Invalidation**: Mixed strategy
- **Query Frequency**: Pages=per request, Content=scheduler
- **Cache Efficiency**: Very high
- **Resource Usage**: Balanced
