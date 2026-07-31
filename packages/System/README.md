# System Package

The System package exposes a single immutable snapshot of the current WP Shop Builder runtime.

It composes existing Version and Environment services and does not read global state directly.

## Public API

```php
use WPShop\System\Contracts\SystemServiceInterface;

$information = $container->get(SystemServiceInterface::class)->information();

$information->versions;
$information->php;
$information->server;
$information->wordpress;
```

`SystemInformation` and its nested DTOs are immutable value objects intended for diagnostics, dashboards, CLI output, and support exports.
