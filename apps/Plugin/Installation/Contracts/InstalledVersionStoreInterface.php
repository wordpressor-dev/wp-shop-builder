<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation\Contracts;

interface InstalledVersionStoreInterface
{
    public function installedVersion(): ?string;

    public function saveInstalledVersion(string $version): void;
}
