# Development Guide

## Quick Start

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
# All tests (unit + functional + integration)
composer test

# Unit tests only (10 tests)
composer test:unit

# Functional tests only (11 tests + 7 integration tests)
composer test:functional

# With coverage
composer test:coverage
```

### Test Types

**Unit Tests** (`Tests/Unit/`)
- 10 tests with mocked dependencies
- Fast execution (<1s)
- ~90% code coverage
- Tests business logic in isolation

**Functional Tests** (`Tests/Functional/EventListener/`)
- 11 tests with real TYPO3 database integration
- Tests actual database queries
- Uses CSV fixtures for test data
- Performance testing (200 records < 50ms)

**Integration Tests** (`Tests/Functional/Integration/`)
- 7 tests verifying complete TYPO3 workflow
- EventDispatcher integration
- CacheManager verification
- Real editorial workflow scenarios

### Test Fixtures

CSV fixtures in `Tests/Functional/Fixtures/`:
- `pages.csv` - Test pages with temporal configurations
- `tt_content.csv` - Test content elements with temporal settings

Fixtures use special values:
- `FUTURE_TIME` - Replaced with future timestamp during test
- `PAST_TIME` - Replaced with past timestamp during test

See `Tests/Functional/Fixtures/README.md` for fixture documentation.

### Code Quality

```bash
# Run all checks
composer code:check

# PHPStan only
composer code:phpstan

# Coding standards check
composer code:style:check

# Fix coding standards
composer code:style:fix
```

### DDEV Environment

```bash
# Start DDEV
ddev start

# Install dependencies in DDEV
ddev composer install

# Run tests in DDEV
ddev composer test

# Access database
ddev mysql
```

## Test Coverage Target

- **Minimum**: 70% (enforced in CI)
- **Target**: 80%+
- **Current**: ~90% (exceeds target)
- **Total Tests**: 28 (10 unit + 11 functional + 7 integration)

Run `composer test:coverage` to generate coverage report.

## Continuous Integration

GitHub Actions runs automatically on push/PR:
- Code quality checks (PHPStan, PHP-CS-Fixer)
- Tests across PHP 8.1-8.3 and TYPO3 12.4-13.0
- Coverage analysis

## File Structure

```
typo3-temporal-cache/
├── .ddev/                          # DDEV configuration
├── .github/workflows/              # CI/CD pipelines
├── Build/
│   ├── .php-cs-fixer.php          # Coding standards config
│   ├── phpstan.neon               # Static analysis config
│   └── phpunit/
│       ├── Unit Tests.xml          # Unit test configuration
│       └── FunctionalTests.xml    # Functional test configuration
├── Classes/                        # Source code
├── Configuration/                  # TYPO3 configuration
├── Documentation/                  # ReST documentation
├── Tests/
│   ├── Unit/
│   │   └── EventListener/         # Unit tests (10 tests)
│   └── Functional/
│       ├── EventListener/         # Functional tests (11 tests)
│       ├── Integration/           # Integration tests (7 tests)
│       └── Fixtures/              # CSV test data
├── composer.json
├── ext_emconf.php
└── README.md
```

## Contributing

1. Create feature branch
2. Make changes
3. Run `composer ci` (code check + tests)
4. Submit PR

All code must pass CI checks before merge.
