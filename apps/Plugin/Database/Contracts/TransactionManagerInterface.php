<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database\Contracts;

interface TransactionManagerInterface
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
