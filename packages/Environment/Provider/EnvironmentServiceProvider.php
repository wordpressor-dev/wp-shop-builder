<?php

declare(strict_types=1);

namespace WPShop\Environment\Provider;

use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Environment\Contracts\PhpEnvironmentInterface;
use WPShop\Environment\Contracts\ServerEnvironmentInterface;
use WPShop\Environment\Contracts\WordPressEnvironmentInterface;
use WPShop\Environment\PhpEnvironment;
use WPShop\Environment\ServerEnvironment;
use WPShop\Environment\WordPressEnvironment;

final class EnvironmentServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $php = new PhpEnvironment();
        $server = new ServerEnvironment();
        $wordpress = new WordPressEnvironment();

        $this->container->set(PhpEnvironmentInterface::class, $php);
        $this->container->set(PhpEnvironment::class, $php);
        $this->container->set(ServerEnvironmentInterface::class, $server);
        $this->container->set(ServerEnvironment::class, $server);
        $this->container->set(WordPressEnvironmentInterface::class, $wordpress);
        $this->container->set(WordPressEnvironment::class, $wordpress);
    }
}
