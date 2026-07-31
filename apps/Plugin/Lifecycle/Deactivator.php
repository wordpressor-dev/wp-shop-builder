<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Lifecycle;

use Closure;

final readonly class Deactivator
{
    /**
     * @param null|Closure(bool): void $flushRewriteRules
     */
    public function __construct(
        private ?Closure $flushRewriteRules = null
    ) {
    }

    public function deactivate(): void
    {
        $flushRewriteRules = $this->flushRewriteRules;

        if ($flushRewriteRules !== null) {
            $flushRewriteRules(false);
        }
    }
}