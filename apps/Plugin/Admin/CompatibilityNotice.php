<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use WPShop\App\Plugin\Compatibility\CompatibilityResult;

final readonly class CompatibilityNotice
{
    public function __construct(
        private CompatibilityResult $result
    ) {
    }

    public function message(): string
    {
        return sprintf(
            'WP Shop Builder is inactive. %s',
            $this->result->message()
        );
    }

    public function render(): void
    {
        $message = htmlspecialchars(
            $this->message(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        echo '<div class="notice notice-error"><p>';
        echo $message;
        echo '</p></div>';
    }
}