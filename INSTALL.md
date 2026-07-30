# Installation

## Requirements

- PHP 8.3 or newer
- Composer 2
- PHP extensions required by PHPUnit and PHP_CodeSniffer, including `dom`, `json`, `libxml`, `mbstring`, `tokenizer`, `xml`, `xmlwriter`, and `simplexml`

## First installation

```bash
composer install
composer qa
```

## Upgrading from PR-009

PR-010 adds development dependencies. After replacing the project files, update the lock file once:

```bash
composer update phpstan/phpstan rector/rector squizlabs/php_codesniffer --with-all-dependencies
composer qa
```

Commit the resulting `composer.lock` together with the PR-010 changes. Subsequent installations can use `composer install` normally.

## Verify the development environment

Run the complete local quality gate before pushing changes:

```bash
composer qa
```

GitHub Actions executes the same command for every push and pull request.
