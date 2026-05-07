# Biblio Plugin Refactoring & Testing Documentation

## Overview

The Biblio WordPress plugin has been refactored to improve maintainability and testability through modern software engineering practices. This documentation covers the refactoring changes and the new PHPUnit testing infrastructure.

## Major Changes

### 1. Dependency Injection & Path Provider Pattern

**Problem:** The `BaseController` was tightly coupled to WordPress functions (`plugin_dir_path()`, `plugin_dir_url()`) making it difficult to test.

**Solution:** Introduced `PluginPathProviderInterface` with two implementations:
- `WordPressPluginPathProvider`: Uses actual WordPress functions (production)
- Mock implementations for testing

**Files:**
- `inc/Base/PluginPathProviderInterface.php` - Interface definition
- `inc/Base/WordPressPluginPathProvider.php` - WordPress implementation
- `inc/Base/BaseController.php` - Refactored to accept provider via constructor

**Example Usage:**
```php
// Production code (auto-detects WordPress paths)
$controller = new BaseController();

// Test code (uses mock paths)
$mock_provider = $this->createMock(PluginPathProviderInterface::class);
$controller = new BaseController($mock_provider);
```

### 2. Logger Abstraction

**Problem:** The `BiblioApi::debug_log()` method directly used file operations, making it difficult to test and substitute with different logging backends.

**Solution:** Introduced `LoggerInterface` with implementations:
- `FileLogger`: Logs to files (current implementation)
- Easy to add: `DatabaseLogger`, `SyslogLogger`, etc.

**Files:**
- `inc/Api/LoggerInterface.php` - Interface definition
- `inc/Api/FileLogger.php` - File-based logger implementation
- `inc/Api/BiblioApi.php` - Refactored to use logger abstraction

**Example Usage:**
```php
// Create logger instance
$logger = new FileLogger('/path/to/logs');

// Use in BiblioApi
$api = new BiblioApi($logger);
$api->debug_log('Title', 'Message');

// Or use directly
$logger->debug('Title', 'Message');
$logger->error('Error', 'Something went wrong');
$logger->info('Info', 'Operation completed');
```

### 3. Service Container

**Problem:** Services were instantiated directly, making it difficult to manage dependencies and swap implementations for testing.

**Solution:** Created a lightweight `ServiceContainer` for managing service registration and resolution.

**Files:**
- `inc/Base/ServiceContainer.php` - Service container implementation

**Example Usage:**
```php
$container = new ServiceContainer();

// Register a factory (new instance each time)
$container->register('logger', function ($c) {
    return new FileLogger('/var/www/html/wp-content/plugins/biblio/logs');
});

// Register a singleton (same instance always)
$container->singleton('api', new BiblioApi());

// Resolve services
$logger = $container->resolve('logger');
$api = $container->resolve('api');

// Check if registered
if ($container->has('logger')) {
    // ...
}
```

## Testing Infrastructure

### PHPUnit Setup

**Configuration Files:**
- `phpunit.xml` - PHPUnit configuration with code coverage settings
- `tests/bootstrap.php` - Test environment initialization
- `tests/UnitTestCase.php` - Base test case with common utilities

### Running Tests

#### Install Dependencies

```bash
cd /var/www/html/larepartidora/wp-content/plugins/biblio
composer install
```

#### Run All Tests

```bash
./vendor/bin/phpunit
```

#### Run Specific Test Suite

```bash
# Unit tests only
./vendor/bin/phpunit tests/Unit

# Integration tests only
./vendor/bin/phpunit tests/Integration

# Specific test file
./vendor/bin/phpunit tests/Unit/Base/BaseControllerTest.php
```

#### Run Tests with Code Coverage

```bash
./vendor/bin/phpunit --coverage-html ./coverage
```

Coverage report will be generated in `./coverage/` directory.

### Test Organization

```
tests/
├── bootstrap.php              # PHPUnit bootstrap
├── UnitTestCase.php           # Base test case class
├── Unit/
│   ├── Base/
│   │   ├── BaseControllerTest.php
│   │   └── ServiceContainerTest.php
│   ├── Api/
│   │   ├── BiblioApiTest.php
│   │   └── FileLoggerTest.php
│   └── InitTest.php
└── Integration/
    └── (Integration tests coming soon)
```

## New Dependencies

Added to `composer.json` (dev dependencies):
- `phpunit/phpunit: ^9.5 || ^10.0` - Testing framework
- `brain/monkey: ^2.6` - WordPress function mocking
- `mockery/mockery: ^1.5` - PHP mocking library

## Test Coverage

Current test coverage includes:

### BaseController (BaseControllerTest.php)
- ✓ Initialization with custom path provider
- ✓ Managers array initialization
- ✓ `activated()` method with options
- ✓ Path provider getter/setter methods

### FileLogger (FileLoggerTest.php)
- ✓ Log file creation
- ✓ Log message formatting
- ✓ Debug, error, and info logging levels
- ✓ Custom plugin types
- ✓ Multiple log entries appending

### BiblioApi (BiblioApiTest.php)
- ✓ Custom logger initialization
- ✓ `debug_log()` method delegation
- ✓ Logger getter/setter methods

### ServiceContainer (ServiceContainerTest.php)
- ✓ Service registration with factory functions
- ✓ Service registration with class names
- ✓ Singleton service registration
- ✓ Service resolution
- ✓ Exception handling for unregistered services
- ✓ Dependency injection within factories

### Init (InitTest.php)
- ✓ Services list retrieval
- ✓ Service class validation
- ✓ Expected services presence

## Backward Compatibility

All refactoring maintains backward compatibility:

1. **BaseController** - Existing code continues to work with automatic WordPress detection
2. **BiblioApi::debug_log()** - Legacy method preserved, internally uses new logger
3. **Init class** - Service registration unchanged

## Migration Guide for Existing Code

### If Using BaseController

**Before:**
```php
class MyController extends BaseController {
    // Hard-coded paths via BaseController
}
```

**After (No change needed, but can inject for testing):**
```php
class MyController extends BaseController {
    public function __construct(?PluginPathProviderInterface $provider = null) {
        parent::__construct($provider);
    }
}
```

### If Creating New Services

**New Pattern (Recommended):**
```php
// Use constructor injection
class MyService {
    private PluginPathProviderInterface $paths;
    private LoggerInterface $logger;

    public function __construct(
        PluginPathProviderInterface $paths,
        LoggerInterface $logger
    ) {
        $this->paths = $paths;
        $this->logger = $logger;
    }
}
```

## Best Practices

1. **Always inject dependencies** - Avoid creating dependencies inside classes
2. **Use interfaces** - Program to interfaces, not implementations
3. **Keep services small** - Single responsibility principle
4. **Mock WordPress functions** - Use Brain\Monkey for WP function testing
5. **Write tests for public methods** - Especially those interacting with options/transients

## Debugging Tests

### Enable Verbose Output

```bash
./vendor/bin/phpunit --verbose
```

### Run Single Test

```bash
./vendor/bin/phpunit --filter "testActivatedReturnsTrueWhenOptionIsSet"
```

### Stop on First Failure

```bash
./vendor/bin/phpunit --stop-on-failure
```

## Future Improvements

1. **Add Integration Tests** - Test actual WordPress interactions
2. **Add E2E Tests** - Test complete user workflows
3. **GitHub Actions** - Automated testing on commits
4. **Code Standards** - PHPCS configuration for PSR-12
5. **Static Analysis** - PHPStan for type checking

## Files Modified/Created

### Created Files
- `phpunit.xml` - Test configuration
- `tests/bootstrap.php` - Test bootstrap
- `tests/UnitTestCase.php` - Base test class
- `tests/Unit/Base/BaseControllerTest.php`
- `tests/Unit/Base/ServiceContainerTest.php`
- `tests/Unit/Api/BiblioApiTest.php`
- `tests/Unit/Api/FileLoggerTest.php`
- `tests/Unit/InitTest.php`
- `inc/Base/PluginPathProviderInterface.php`
- `inc/Base/WordPressPluginPathProvider.php`
- `inc/Base/ServiceContainer.php`
- `inc/Api/LoggerInterface.php`
- `inc/Api/FileLogger.php`

### Modified Files
- `composer.json` - Added dev dependencies
- `inc/Base/BaseController.php` - Refactored for dependency injection
- `inc/Api/BiblioApi.php` - Refactored to use logger interface

## Support & Questions

For issues or questions about the testing setup:
1. Check existing test files for examples
2. Refer to [PHPUnit documentation](https://phpunit.de/documentation.html)
3. Refer to [Brain/Monkey documentation](https://brain-wp.github.io/BrainMonkey/)
