<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation;

use WPShop\App\Plugin\Installation\Contracts\InstalledVersionStoreInterface;

final readonly class InstallationManager
{
    public function __construct(
        private InstalledVersionStoreInterface $versionStore,
        private MigrationRunner $migrations,
        private string $currentVersion
    ) {
    }

    public function synchronize(): void
    {
        $installedVersion = $this->versionStore->installedVersion();

        if (
            $installedVersion !== null
            && version_compare(
                $installedVersion,
                $this->currentVersion,
                '>='
            )
        ) {
            return;
        }

        $this->migrations->run(
            $installedVersion,
            $this->currentVersion
        );

        $this->versionStore->saveInstalledVersion(
            $this->currentVersion
        );
    }
}
