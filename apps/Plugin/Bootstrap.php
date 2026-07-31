<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use WPShop\App\Plugin\Admin\CompatibilityNotice;
use WPShop\App\Plugin\Compatibility\CompatibilityChecker;
use WPShop\App\Plugin\Lifecycle\Activator;
use WPShop\App\Plugin\Lifecycle\Deactivator;

final readonly class Bootstrap
{
    /**
     * @param null|Closure(bool): void $flushRewriteRules
     */
    public function __construct(
        private ?CompatibilityChecker $compatibility = null,
        private ?Closure $flushRewriteRules = null
    ) {
    }

    public function activate(): void
    {
        $activator = new Activator(
            $this->compatibility(),
            $this->flushRewriteRules
        );

        $activator->activate();
    }

    public function deactivate(): void
    {
        $deactivator = new Deactivator(
            $this->flushRewriteRules
        );

        $deactivator->deactivate();
    }

    public function boot(): ?CompatibilityNotice
    {
        $result = $this->compatibility()->check();

        if (! $result->isCompatible()) {
            return new CompatibilityNotice($result);
        }

        (new Plugin())->boot();

        return null;
    }

    private function compatibility(): CompatibilityChecker
    {
        return $this->compatibility
            ?? CompatibilityChecker::fromRuntime();
    }
}