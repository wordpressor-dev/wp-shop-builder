<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation\Contracts;

interface MigrationInterface
{
    public function version(): string;

    public function up(): void;
}
