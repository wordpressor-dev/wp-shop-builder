<?php

declare(strict_types=1);

namespace WPShop\Core\Container;

use Closure;

final readonly class Factory
{
    /**
     * @param Closure(ContainerInterface): mixed $resolver
     */
    public function __construct(
        private Closure $resolver
    ) {
    }

    public function resolve(ContainerInterface $container): mixed
    {
        return ($this->resolver)($container);
    }
}
