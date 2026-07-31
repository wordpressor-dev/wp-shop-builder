<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin\Contracts;

interface AdminPageInterface
{
    public function slug(): string;

    public function title(): string;

    public function capability(): string;

    public function render(): void;
}
