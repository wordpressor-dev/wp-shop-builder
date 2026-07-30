<?php

declare(strict_types=1);

namespace WPShop\Core\Logging;

use Psr\Log\LoggerInterface;
use WPShop\Core\Config\ConfigInterface;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Provider\AbstractServiceProvider;

final class LoggingServiceProvider extends AbstractServiceProvider
{
    public function __construct(
        ContainerInterface $container,
        private readonly ConfigInterface $config
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $config = $this->config;

        $this->container->factory(
            LoggerInterface::class,
            static fn (): LoggerInterface => (new LoggerFactory($config))->create()
        );
    }
}
