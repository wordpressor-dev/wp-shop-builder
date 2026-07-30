<?php

declare(strict_types=1);

namespace WPShop\Core\Provider\Exception;

use LogicException;

final class ServiceProviderAlreadyRegistered extends LogicException
{
    public static function forClass(string $providerClass): self
    {
        return new self(sprintf(
            'Service provider "%s" is already registered.',
            $providerClass
        ));
    }
}
