# WP Shop Builder

Digital Product Platform for WordPress.

## Development

Install dependencies:

```bash
composer install
```

Run the complete local quality gate:

```bash
composer qa
```

Individual commands:

```bash
composer test       # PHPUnit
composer stan       # PHPStan level 8
composer cs         # PSR-12 check
composer cs-fix     # automatically fix supported style violations
composer rector     # Rector dry run
composer rector-fix # apply Rector changes
```

`composer qa` runs PHPStan, PHP_CodeSniffer, and PHPUnit in that order. Rector remains an explicit command because it is intended for reviewed refactoring rather than routine test execution.

## Continuous integration

GitHub Actions runs the same local quality gate on every push and pull request:

```bash
composer qa
```

The workflow validates Composer metadata and tests the supported PHP matrix defined in `.github/workflows/quality.yml`.

## Logging

The core provides a lightweight PSR-3 logging layer with `file` and `null`
drivers. Register `LoggingServiceProvider` to resolve
`Psr\Log\LoggerInterface` from the container.

## WordPress bridge

PR-013.1 introduces the contracts and adapters used by the WordPress integration layer.
Production code can use `NativeHookAdapter`, while unit tests can use
`TestingHookAdapter` without loading WordPress.


## WordPress application lifecycle

```php
use WPShop\WordPress\Bootstrap\Bootstrap;

$application = Bootstrap::create();
$application->registerPlugin(new ShopPlugin());
$application->boot();
```

`Bootstrap::run()` is available when no plugins need to be registered before boot.
