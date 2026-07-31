<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use WPShop\App\Plugin\Installation\Exception\InstallationFailed;

final readonly class InstallationFailureNotice implements AdminNoticeInterface
{
    public function __construct(
        private InstallationFailed $exception
    ) {
    }

    public function message(): string
    {
        return sprintf(
            'WP Shop Builder could not complete installation or update. %s',
            $this->exception->getMessage()
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
