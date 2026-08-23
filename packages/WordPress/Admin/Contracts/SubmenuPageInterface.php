<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin\Contracts;

interface SubmenuPageInterface extends AdminPageInterface
{
    public function parentSlug(): string;
}
