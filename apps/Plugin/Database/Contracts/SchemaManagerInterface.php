<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database\Contracts;

interface SchemaManagerInterface
{
    public function table(string $name): string;

    public function charsetCollate(): string;

    public function apply(string $sql): void;
}
