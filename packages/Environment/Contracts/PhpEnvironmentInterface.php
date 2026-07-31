<?php

declare(strict_types=1);

namespace WPShop\Environment\Contracts;

interface PhpEnvironmentInterface
{
    public function version(): string;

    public function memoryLimit(): string;

    public function uploadMaxFilesize(): string;

    public function postMaxSize(): string;

    public function maxExecutionTime(): int;

    /**
     * @return list<string>
     */
    public function extensions(): array;
}
