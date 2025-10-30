# Developer Guide: Strategy Pattern Implementation

## Quick Start

### Understanding the Architecture

The refactored EventListener uses two strategy patterns:

1. **Scoping Strategy**: Determines WHICH caches to invalidate
2. **Timing Strategy**: Determines WHEN to check for transitions

Both are configured via Extension Manager and selected at runtime via factory pattern.

---

## Creating a New Strategy

### Example: Custom Scoping Strategy

#### Step 1: Create Strategy Class

```php
<?php
declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use TYPO3\CMS\Core\Context\Context;

final class CustomScopingStrategy implements ScopingStrategyInterface
{
    public function __construct(
        // Inject dependencies here
    ) {
    }

    public function getCacheTagsToFlush(
        TemporalContent $content,
        Context $context
    ): array {
        // Your custom logic to determine which caches to flush
        // Return array of cache tags
        return ['custom_tag_1', 'custom_tag_2'];
    }

    public function getNextTransition(Context $context): ?int
    {
        // Calculate next transition timestamp
        // Return unix timestamp or null
        return time() + 3600;
    }

    public function getName(): string
    {
        return 'custom';
    }
}
```

#### Step 2: Register in Services.yaml

```yaml
services:
  Netresearch\TemporalCache\Service\Scoping\CustomScopingStrategy:
    public: false
    tags:
      - { name: 'temporal_cache.scoping_strategy', identifier: 'custom' }

  # Add to factory
  Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory:
    arguments:
      $strategies:
        - '@Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy'
        - '@Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy'
        - '@Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy'
        - '@Netresearch\TemporalCache\Service\Scoping\CustomScopingStrategy'
```

#### Step 3: Update Factory Selection Logic

```php
// In ScopingStrategyFactory.php
private function selectStrategy(array $strategies): ScopingStrategyInterface
{
    $strategyMap = [
        'global' => GlobalScopingStrategy::class,
        'per-page' => PerPageScopingStrategy::class,
        'per-content' => PerContentScopingStrategy::class,
        'custom' => CustomScopingStrategy::class, // Add this
    ];
    // ... rest of logic
}
```

#### Step 4: Add Configuration Option

```
# In ext_conf_template.txt
scoping.strategy = global
# cat=Scoping/010; type=options[global,per-page,per-content,custom]; label=Custom option
```

---

## Testing Strategies

### Unit Test Template

```php
<?php
declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\Scoping\CustomScopingStrategy;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CustomScopingStrategyTest extends UnitTestCase
{
    private CustomScopingStrategy $subject;
    private Context $contextMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextMock = $this->createMock(Context::class);
        $this->subject = new CustomScopingStrategy(/* inject mocks */);
    }

    /**
     * @test
     */
    public function getCacheTagsToFlushReturnsExpectedTags(): void
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: 'pages',
            title: 'Test Page',
            pid: 0,
            starttime: time() + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $tags = $this->subject->getCacheTagsToFlush($content, $this->contextMock);

        self::assertIsArray($tags);
        self::assertNotEmpty($tags);
        self::assertContains('custom_tag_1', $tags);
    }

    /**
     * @test
     */
    public function getNextTransitionReturnsTimestamp(): void
    {
        $result = $this->subject->getNextTransition($this->contextMock);

        self::assertIsInt($result);
        self::assertGreaterThan(time(), $result);
    }

    /**
     * @test
     */
    public function getNameReturnsCustom(): void
    {
        self::assertSame('custom', $this->subject->getName());
    }
}
```

---

## Debugging

### Enable Debug Logging

```php
// Extension Manager → temporal_cache → Advanced → Debug Logging = Yes
```

This will log:
- Strategy selection
- Cache lifetime calculations
- Transition processing
- Error details

### View Logs

```bash
# TYPO3 system log
tail -f var/log/typo3_*.log | grep temporal_cache

# Check active strategies
SELECT * FROM sys_registry WHERE entry_namespace = 'tx_temporalcache';
```

### Manual Testing

```php
// In TYPO3 backend module or CLI command

$container = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \Psr\Container\ContainerInterface::class
);

$eventListener = $container->get(
    \Netresearch\TemporalCache\EventListener\TemporalCacheLifetime::class
);

// Check active strategies
$scopingStrategy = $eventListener->getScopingStrategy();
$timingStrategy = $eventListener->getTimingStrategy();

echo "Scoping: " . $scopingStrategy->getName() . "\n";
echo "Timing: " . $timingStrategy->getName() . "\n";
```

---

## Common Patterns

### Pattern 1: Conditional Strategy Selection

```php
public function getCacheTagsToFlush(
    TemporalContent $content,
    Context $context
): array {
    if ($content->isPage()) {
        return $this->handlePageTransition($content);
    }

    if ($content->isContent()) {
        return $this->handleContentTransition($content);
    }

    return [];
}
```

### Pattern 2: Delegation to Other Services

```php
public function __construct(
    private readonly RefindexService $refindexService,
    private readonly CacheManager $cacheManager
) {
}

public function getCacheTagsToFlush(
    TemporalContent $content,
    Context $context
): array {
    $pageIds = $this->refindexService->findPagesWithContent(
        $content->uid,
        $content->languageUid
    );

    return array_map(
        fn(int $pid) => 'pageId_' . $pid,
        $pageIds
    );
}
```

### Pattern 3: Context-Aware Processing

```php
public function getNextTransition(Context $context): ?int
{
    $workspaceId = $context->getPropertyFromAspect('workspace', 'id');
    $languageId = $context->getPropertyFromAspect('language', 'id');

    // Use workspace and language in queries
    return $this->repository->findNextTransition(
        $workspaceId,
        $languageId
    );
}
```

---

## Performance Optimization

### Caching Strategy Results

```php
private array $transitionCache = [];

public function getNextTransition(Context $context): ?int
{
    $cacheKey = $context->getPropertyFromAspect('language', 'id');

    if (!isset($this->transitionCache[$cacheKey])) {
        $this->transitionCache[$cacheKey] = $this->calculateNextTransition($context);
    }

    return $this->transitionCache[$cacheKey];
}
```

### Batch Processing in Scheduler

```php
public function processTransition(TransitionEvent $event): void
{
    // Collect multiple transitions
    $this->pendingTransitions[] = $event;

    // Process in batch when threshold reached
    if (count($this->pendingTransitions) >= 100) {
        $this->processBatch();
    }
}

private function processBatch(): void
{
    $allTags = [];
    foreach ($this->pendingTransitions as $transition) {
        $allTags = array_merge(
            $allTags,
            $this->getCacheTagsToFlush($transition->content)
        );
    }

    // Single cache flush for all transitions
    $this->cacheManager->flushCachesByTags(array_unique($allTags));
    $this->pendingTransitions = [];
}
```

---

## Error Handling Best Practices

### Graceful Degradation

```php
public function getCacheTagsToFlush(
    TemporalContent $content,
    Context $context
): array {
    try {
        return $this->calculateOptimalTags($content);
    } catch (\Throwable $e) {
        $this->logger->warning(
            'Failed to calculate optimal tags, falling back to global',
            ['exception' => $e->getMessage()]
        );
        // Fallback to safe default
        return ['pages'];
    }
}
```

### Validation

```php
public function __construct(
    private readonly RefindexService $refindexService
) {
    if (!$this->refindexService->isAvailable()) {
        throw new \RuntimeException(
            'RefindexService required but not available'
        );
    }
}
```

---

## Migration Guide

### From Phase 1 to V1.0

#### No Changes Required
Default configuration maintains Phase 1 behavior:
- Scoping: global
- Timing: dynamic

#### Opt-in to Optimization
Extension Manager → temporal_cache:

1. **Step 1**: Change scoping to `per-page`
   - Test thoroughly
   - Monitor cache hit rates

2. **Step 2**: Change scoping to `per-content`
   - Requires refindex to be up to date
   - Best cache efficiency

3. **Step 3**: Add scheduler task
   - TYPO3 Backend → Scheduler
   - Add "Temporal Cache Scheduler Task"
   - Set interval (e.g., 5 minutes)

4. **Step 4**: Change timing to `scheduler` or `hybrid`
   - Scheduler: All transitions via task
   - Hybrid: Pages=dynamic, Content=scheduler

---

## Troubleshooting

### Issue: Strategy Not Applied

**Symptom**: Configuration changed but old strategy still active

**Solution**:
```bash
# Clear all caches
./vendor/bin/typo3 cache:flush

# Check DI container
./vendor/bin/typo3 cache:warmup
```

### Issue: Scheduler Task Not Running

**Symptom**: Transitions not processed, caches not flushed

**Solution**:
1. Check scheduler task is enabled
2. Verify cron is running: `crontab -l`
3. Check task last run: Backend → Scheduler → Tasks
4. Enable debug logging and check logs

### Issue: Wrong Caches Flushed

**Symptom**: Too many or too few caches invalidated

**Solution**:
1. Enable debug logging
2. Check active scoping strategy: `$eventListener->getScopingStrategy()->getName()`
3. Verify refindex is up to date: `./vendor/bin/typo3 referenceindex:update`

---

## Code Review Checklist

When reviewing strategy implementations:

- [ ] Implements correct interface (ScopingStrategyInterface or TimingStrategyInterface)
- [ ] All interface methods implemented
- [ ] getName() returns unique identifier
- [ ] Constructor uses dependency injection (no GeneralUtility::makeInstance)
- [ ] Error handling with graceful degradation
- [ ] Logging at appropriate levels (debug/info/warning/error)
- [ ] Unit tests with >80% coverage
- [ ] PHPStan level 9 passing
- [ ] PHP-CS-Fixer compliant
- [ ] Documentation in class docblock

---

## Further Reading

- [TYPO3 Dependency Injection](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/DependencyInjection/)
- [Strategy Pattern](https://refactoring.guru/design-patterns/strategy)
- [TYPO3 Caching Framework](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/CachingFramework/)
- [TYPO3 Scheduler](https://docs.typo3.org/c/typo3/cms-scheduler/main/en-us/)
