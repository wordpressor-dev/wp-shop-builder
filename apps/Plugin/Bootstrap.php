<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use WPShop\App\Plugin\Admin\AdminNoticeInterface;
use WPShop\App\Plugin\Admin\CompatibilityNotice;
use WPShop\App\Plugin\Admin\InstallationFailureNotice;
use WPShop\App\Plugin\Compatibility\CompatibilityChecker;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;
use WPShop\App\Plugin\Installation\InstallationManager;
use WPShop\App\Plugin\Lifecycle\Activator;
use WPShop\App\Plugin\Lifecycle\Deactivator;

final readonly class Bootstrap
{
    /**
     * @param null|Closure(bool): void $flushRewriteRules
     */
    public function __construct(
        private ?CompatibilityChecker $compatibility = null,
        private ?Closure $flushRewriteRules = null,
        private ?InstallationManager $installation = null
    ) {
    }

    public function activate(): void
    {
        $activator = new Activator(
            $this->compatibility(),
            $this->flushRewriteRules,
            $this->installation
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

    public function boot(): ?AdminNoticeInterface
    {
        $result = $this->compatibility()->check();

        if (! $result->isCompatible()) {
            return new CompatibilityNotice($result);
        }

        try {
            $this->installation?->synchronize();
        } catch (InstallationFailed $exception) {
            return new InstallationFailureNotice(
                $exception
            );
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
