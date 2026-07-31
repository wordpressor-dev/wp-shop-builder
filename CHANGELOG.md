# Changelog

## PR-017 — System Package

- Added immutable PHP, server, WordPress, and aggregate system information DTOs.
- Added `SystemServiceInterface` and `SystemService` to compose Version and Environment data.
- Added `SystemServiceProvider` with shared container bindings.
- Registered the System package through `WordPressServiceProvider`.
- Added unit, provider, integration coverage, and package documentation.

## PR-016 — Version Package

- Added immutable DTOs for framework, PHP, WordPress, and WooCommerce versions.
- Added `VersionServiceInterface` and `VersionService` as the package public API.
- Added `VersionServiceProvider` with shared container bindings.
- Added a central `Framework::VERSION` source used by the WordPress application and version service.
- Registered the version package through `WordPressServiceProvider`.
- Added unit and integration coverage plus package documentation.

## PR-014.2.1 — Environment Layer

- Added typed PHP, server, and WordPress environment contracts.
- Added native environment implementations with safe non-WordPress fallbacks.
- Registered environment services through `EnvironmentServiceProvider`.
- Added environment unit and integration tests.

## [Unreleased]

### Added

- Minimal WordPress admin framework and WP Shop Builder dashboard.


### Added

- WordPress application lifecycle with `Application`, `Bootstrap`, and `PluginManager`.
- Base `Plugin` class for WordPress extensions.
- `WordPressServiceProvider` bindings for application, plugins, and native hooks.

## PR-013.1 — WordPress Contracts and Hook Adapters

### Added

- WordPress bridge contracts for hook adapters, hook registrars, and plugins.
- Native WordPress hook adapter for `add_action()` and `add_filter()`.
- In-memory testing hook adapter with action dispatching and filter application.
- Explicit exception when native WordPress hook functions are unavailable.
- PHPUnit coverage for registration, priorities, accepted arguments, and non-WordPress execution.

## PR-011 — Quality Gate and GitHub Actions

### Added

- GitHub Actions quality workflow for pushes and pull requests.
- CI matrix for PHP 8.3, 8.4, and 8.5.
- Composer validation, dependency caching, and the unified `composer qa` quality gate.

### Changed

- PHPCS keeps strict PSR-12 checks for production code while allowing test-local fixture classes.
- Normalized PHP source files to end with exactly one newline.
- Corrected formatting in `ModuleRegistryTest`.

## PR-010.1 — PHPStan Cleanup

### Changed

- Added precise array value types to `ConfigRepository`.
- Made configuration lookup state explicitly boolean.
- Removed an unreachable `ReflectionException` catch from container autowiring.
- Runtime behavior and public APIs remain unchanged.

## PR-010 — Developer Tooling

### Added

- PHPStan static analysis at level 8.
- PHP_CodeSniffer with the PSR-12 coding standard.
- Rector configuration targeting PHP 8.3.
- `.editorconfig` for consistent editor behavior.
- Composer scripts for tests, static analysis, coding style, Rector, and a unified `composer qa` quality gate.
- Development and upgrade instructions for the new tooling.

### Changed

- Development dependencies now include PHPStan, PHP_CodeSniffer, and Rector.
- Runtime behavior and public application APIs remain unchanged.

## PR-009 — Service Providers

### Added

- `ServiceProviderInterface` lifecycle contract with `register()` and `boot()` stages.
- `ProviderRegistryInterface` for kernels that support service providers.
- `AbstractServiceProvider` with container access and a no-op default `boot()` implementation.
- `ServiceProviderRepository` with ordered registration and booting.
- Duplicate-provider and invalid-lifecycle exceptions.
- Kernel integration using the lifecycle order: providers register, modules boot, providers boot.
- PHPUnit coverage for provider registration, ordering, idempotency, duplicate protection, and Kernel integration.

### Changed

- `Kernel` now implements `ProviderRegistryInterface` while `KernelInterface` remains unchanged.

## PR-008 — Configuration Repository

### Added

- Immutable configuration repository.
- Dot-notation access to nested values.
- Default values and null-safe presence checks.
- Recursive configuration merging.
- PHP configuration file loader.
- Exceptions for missing and invalid configuration files.
- PHPUnit coverage for repository and loader behavior.

## PR-012 — PSR-3 Logging

- Added `psr/log` 3.x as the logging contract.
- Added file and null logger implementations.
- Added configurable log levels, PSR-3 message interpolation and context serialization.
- Added `LoggerFactory` and `LoggingServiceProvider`.
- Added unit tests for logging, configuration and container registration.
