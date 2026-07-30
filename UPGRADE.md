# Upgrade Guide

## PR-009 to PR-010

PR-010 changes development tooling only and does not change runtime behavior or public application APIs.

Because new development packages were added to `composer.json`, regenerate the lock file once:

```bash
composer update phpstan/phpstan rector/rector squizlabs/php_codesniffer --with-all-dependencies
```

Then run:

```bash
composer qa
```

The expected order is PHPStan, PHP_CodeSniffer, and PHPUnit. Rector is available separately through `composer rector`.

## PR-011 — Quality Gate and GitHub Actions

No runtime migration is required. After applying the update, run:

```bash
composer dump-autoload
composer qa
```

Commit `.github/workflows/quality.yml` so GitHub can run the quality matrix.

## PR-012 — PSR-3 Logging

Update dependencies before running the quality suite:

```bash
composer update psr/log --with-all-dependencies
composer qa
```

Register `LoggingServiceProvider` with a `ConfigInterface` instance to expose
`Psr\Log\LoggerInterface` through the service container.
