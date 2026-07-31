<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation;

use Closure;
use WPShop\App\Plugin\Installation\Contracts\InstalledVersionStoreInterface;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;

final readonly class OptionInstalledVersionStore implements
    InstalledVersionStoreInterface
{
    public const OPTION_NAME = 'wp_shop_builder_installed_version';

    /**
     * @param Closure(string, mixed): mixed $getOption
     * @param Closure(string, mixed, bool): bool $updateOption
     */
    public function __construct(
        private Closure $getOption,
        private Closure $updateOption
    ) {
    }

    public function installedVersion(): ?string
    {
        $value = ($this->getOption)(
            self::OPTION_NAME,
            null
        );

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    public function saveInstalledVersion(string $version): void
    {
        $updated = ($this->updateOption)(
            self::OPTION_NAME,
            $version,
            false
        );

        if ($updated) {
            return;
        }

        if ($this->installedVersion() === $version) {
            return;
        }

        throw InstallationFailed::stateWrite($version);
    }
}
