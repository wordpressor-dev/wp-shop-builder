<?php

declare(strict_types=1);

namespace WPShop\Core\Container\Exception;

use LogicException;
use Psr\Container\ContainerExceptionInterface;

final class ServiceAlreadyRegistered extends LogicException implements ContainerExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Service "%s" is already registered.', $id));
    }
}
