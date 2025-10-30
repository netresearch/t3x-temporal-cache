# Testing Quick Start Guide

## Test Suite Summary

**Total Test Files**: 23 (16 unit + 7 functional)
**Expected Coverage**: 90%+
**Test Framework**: PHPUnit 10.5+, TYPO3 Testing Framework

---

## Quick Commands

### Run All Tests
```bash
composer test
```

### Run Unit Tests Only
```bash
composer test:unit
```

### Run Functional Tests Only
```bash
composer test:functional
```

### Generate Coverage Report
```bash
composer test:coverage
# Report saved to: .Build/coverage/index.html
```

### Check Coverage Threshold
```bash
composer test:coverage:check
# Requires minimum 70% coverage
```

### Run Quality Checks
```bash
# PHPStan static analysis
composer code:phpstan

# PHP-CS-Fixer code style
composer code:style:check

# Fix code style automatically
composer code:style:fix

# Run all checks (tests + quality)
composer ci
```

---

## Detailed Test Execution

### Run Specific Test File
```bash
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Configuration/ExtensionConfigurationTest.php
```

### Run Tests with Verbose Output
```bash
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml --testdox
```

### Run Tests with Coverage
```bash
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml --coverage-text
```

### Run Single Test Method
```bash
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml --filter testMethodName
```

---

## Test Categories

### Unit Tests (16 files)

#### Configuration (1 file)
- `Tests/Unit/Configuration/ExtensionConfigurationTest.php`

#### Domain Models (2 files)
- `Tests/Unit/Domain/Model/TemporalContentTest.php`
- `Tests/Unit/Domain/Model/TransitionEventTest.php`

#### Domain Repository (1 file)
- `Tests/Unit/Domain/Repository/TemporalContentRepositoryTest.php`

#### Core Services (2 files)
- `Tests/Unit/Service/RefindexServiceTest.php`
- `Tests/Unit/Service/HarmonizationServiceTest.php`

#### Scoping Strategies (4 files)
- `Tests/Unit/Service/Scoping/GlobalScopingStrategyTest.php`
- `Tests/Unit/Service/Scoping/PerPageScopingStrategyTest.php`
- `Tests/Unit/Service/Scoping/PerContentScopingStrategyTest.php`
- `Tests/Unit/Service/Scoping/ScopingStrategyFactoryTest.php`

#### Timing Strategies (4 files)
- `Tests/Unit/Service/Timing/DynamicTimingStrategyTest.php`
- `Tests/Unit/Service/Timing/SchedulerTimingStrategyTest.php`
- `Tests/Unit/Service/Timing/HybridTimingStrategyTest.php`
- `Tests/Unit/Service/Timing/TimingStrategyFactoryTest.php`

#### Integration (2 files)
- `Tests/Unit/EventListener/TemporalCacheLifetimeTest.php`
- `Tests/Unit/Task/TemporalCacheSchedulerTaskTest.php`

### Functional Tests (7 files)

#### Service Integration (2 files)
- `Tests/Functional/Service/HarmonizationIntegrationTest.php`
- `Tests/Functional/Service/Scoping/PerContentScopingIntegrationTest.php`

#### Task Integration (1 file)
- `Tests/Functional/Task/TemporalCacheSchedulerTaskTest.php`

#### Backend Tests (1 file)
- `Tests/Functional/Backend/TemporalCacheControllerTest.php`

#### EventListener Tests (1 file)
- `Tests/Functional/EventListener/TemporalCacheLifetimeTest.php`

#### Complete Integration (2 files)
- `Tests/Functional/Integration/CacheIntegrationTest.php`
- `Tests/Functional/Integration/CompleteWorkflowTest.php`

---

## Test Fixtures

Functional tests use CSV fixtures located in `Tests/Functional/Fixtures/`:
- `pages.csv` - Test pages with various states
- `tt_content.csv` - Test content elements
- `sys_refindex.csv` - Reference index data
- `be_users.csv` - Backend user for controller tests

---

## Troubleshooting

### Tests Fail with Database Errors
Ensure functional test database is configured:
```bash
export typo3DatabaseDriver=pdo_sqlite
```

### Coverage Not Generated
Install Xdebug or PCOV:
```bash
# For Xdebug
pecl install xdebug

# For PCOV (faster)
pecl install pcov
```

### Composer Commands Not Found
Install dev dependencies:
```bash
composer install --dev
```

### PHPUnit Version Issues
Check PHPUnit version:
```bash
.Build/bin/phpunit --version
# Should be 10.5+
```

---

## Expected Test Results

### Unit Tests
- **16 test files**
- **~150 test methods**
- **Expected time**: < 5 seconds
- **Expected coverage**: 90%+

### Functional Tests
- **7 test files**
- **~30 test methods**
- **Expected time**: < 30 seconds
- **Expected coverage**: Critical paths

---

## Continuous Integration

### GitHub Actions Example
```yaml
- name: Run Unit Tests
  run: composer test:unit

- name: Run Functional Tests
  run: composer test:functional

- name: Check Code Coverage
  run: composer test:coverage:check

- name: Run PHPStan
  run: composer code:phpstan

- name: Check Code Style
  run: composer code:style:check
```

---

## Test Development Guidelines

### Writing New Tests

1. **Unit Tests**: Place in `Tests/Unit/` matching class structure
2. **Functional Tests**: Place in `Tests/Functional/` by feature area
3. **Naming**: `*Test.php` suffix required
4. **Coverage**: Use `@covers` annotation
5. **Data Providers**: Use for parametrized tests
6. **Mocking**: Mock all dependencies in unit tests

### Test Method Naming
```php
// Good
public function harmonizeTimestampReturnsOriginalWhenDisabled(): void

// Bad
public function test1(): void
```

### Test Structure (AAA Pattern)
```php
public function exampleTest(): void
{
    // Arrange: Set up test data and mocks
    $subject = new MyClass($dependency);

    // Act: Execute the method being tested
    $result = $subject->myMethod();

    // Assert: Verify the result
    self::assertSame('expected', $result);
}
```

---

## Documentation

- **Full Test Suite Documentation**: `claudedocs/TEST-SUITE-SUMMARY.md`
- **Implementation Status**: `claudedocs/V1.0-STATUS-SUMMARY.md`
- **Developer Guide**: `claudedocs/DEVELOPER-GUIDE.md`

---

## Support

For issues or questions:
1. Check test output for error messages
2. Review `TEST-SUITE-SUMMARY.md` for detailed coverage info
3. Ensure all dependencies are installed: `composer install`
4. Verify PHP version: PHP 8.1+ required
