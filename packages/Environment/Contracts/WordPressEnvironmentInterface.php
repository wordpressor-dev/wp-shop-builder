<?php

declare(strict_types=1);

namespace WPShop\Environment\Contracts;

interface WordPressEnvironmentInterface
{
    public function version(): string;

    public function locale(): string;

    public function timezone(): string;

    public function isMultisite(): bool;

    public function isDebug(): bool;
}
