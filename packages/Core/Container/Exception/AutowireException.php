<?php

declare(strict_types=1);

namespace WPShop\Core\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Throwable;

final class AutowireException extends RuntimeException implements ContainerExceptionInterface
{
    public static function classDoesNotExist(string $id): self
    {
        return new self(
            sprintf('Class "%s" does not exist and cannot be autowired.', $id)
        );
    }

    public static function classIsNotInstantiable(string $id): self
    {
        return new self(
            sprintf('Class "%s" is not instantiable and cannot be autowired.', $id)
        );
    }

    public static function parameterCannotBeResolved(
        string $className,
        string $parameterName
    ): self {
        return new self(
            sprintf(
                'Parameter "$%s" of class "%s" cannot be resolved automatically.',
                $parameterName,
                $className
            )
        );
    }

    public static function reflectionFailed(
        string $id,
        Throwable $previous
    ): self {
        return new self(
            sprintf('Unable to reflect class "%s".', $id),
            0,
            $previous
        );
    }
}
