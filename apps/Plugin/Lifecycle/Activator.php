<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Lifecycle;

use Closure;
use WPShop\App\Plugin\Compatibility\CompatibilityChecker;
use WPShop\App\Plugin\Exception\IncompatibleEnvironment;
use WPShop\App\Plugin\Installation\InstallationManager;

final readonly class Activator
{
    /**
     * @param null|Closure(bool): void $flushRewriteRules
     */
    public function __construct(
        private CompatibilityChecker $compatibility,
        private ?Closure $flushRewriteRules = null,
        private ?InstallationManager $installation = null
    ) {
    }

    public function activate(): void
    {
        $result = $this->compatibility->check();

        if (! $result->isCompatible()) {
            throw IncompatibleEnvironment::fromResult($result);
        }

        $this->installation?->synchronize();

        $flushRewriteRules = $this->flushRewriteRules;

        if ($flushRewriteRules !== null) {
            $flushRewriteRules(false);
        }
    }
}
