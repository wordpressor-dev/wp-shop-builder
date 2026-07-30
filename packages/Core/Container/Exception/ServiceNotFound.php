<?php

declare(strict_types=1);

namespace WPShop\Core\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class ServiceNotFound extends RuntimeException implements NotFoundExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Service "%s" was not found in the container.', $id));
    }
}
