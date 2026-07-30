<?php

declare(strict_types=1);

namespace WPShop\Core\Provider\Exception;

use LogicException;

final class ServiceProvidersNotRegistered extends LogicException
{
    public static function beforeBoot(): self
    {
        return new self('Service providers must be registered before they can be booted.');
    }
}
