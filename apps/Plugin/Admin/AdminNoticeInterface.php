<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

interface AdminNoticeInterface
{
    public function message(): string;

    public function render(): void;
}
