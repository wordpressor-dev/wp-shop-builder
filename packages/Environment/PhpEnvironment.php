<?php

declare(strict_types=1);

namespace WPShop\Environment;

use WPShop\Environment\Contracts\PhpEnvironmentInterface;

final class PhpEnvironment implements PhpEnvironmentInterface
{
    public function version(): string
    {
        return PHP_VERSION;
    }

    public function memoryLimit(): string
    {
        return $this->iniValue('memory_limit');
    }

    public function uploadMaxFilesize(): string
    {
        return $this->iniValue('upload_max_filesize');
    }

    public function postMaxSize(): string
    {
        return $this->iniValue('post_max_size');
    }

    public function maxExecutionTime(): int
    {
        return (int) $this->iniValue('max_execution_time', '0');
    }

    public function extensions(): array
    {
        $extensions = get_loaded_extensions();
        sort($extensions);

        return $extensions;
    }

    private function iniValue(string $option, string $fallback = 'Unavailable'): string
    {
        $value = ini_get($option);

        return $value === false ? $fallback : $value;
    }
}
