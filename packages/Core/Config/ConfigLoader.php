<?php

declare(strict_types=1);

namespace WPShop\Core\Config;

use WPShop\Core\Config\Exception\ConfigFileNotFound;
use WPShop\Core\Config\Exception\InvalidConfigFile;

final class ConfigLoader
{
    /**
     * @param list<string> $files
     */
    public function load(array $files): ConfigRepository
    {
        $repository = new ConfigRepository();

        foreach ($files as $file) {
            if (!is_file($file)) {
                throw ConfigFileNotFound::forPath($file);
            }

            $config = require $file;

            if (!is_array($config)) {
                throw InvalidConfigFile::forPath($file);
            }

            /** @var array<string, mixed> $config */
            $repository = $repository->merge($config);
        }

        return $repository;
    }
}
