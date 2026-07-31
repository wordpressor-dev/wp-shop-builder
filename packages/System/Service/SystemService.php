<?php

declare(strict_types=1);

namespace WPShop\System\Service;

use WPShop\Environment\Contracts\PhpEnvironmentInterface;
use WPShop\Environment\Contracts\ServerEnvironmentInterface;
use WPShop\Environment\Contracts\WordPressEnvironmentInterface;
use WPShop\System\Contracts\SystemServiceInterface;
use WPShop\System\DTO\PhpInformation;
use WPShop\System\DTO\ServerInformation;
use WPShop\System\DTO\SystemInformation;
use WPShop\System\DTO\WordPressInformation;
use WPShop\Version\Contracts\VersionServiceInterface;

final readonly class SystemService implements SystemServiceInterface
{
    public function __construct(
        private VersionServiceInterface $versions,
        private PhpEnvironmentInterface $php,
        private ServerEnvironmentInterface $server,
        private WordPressEnvironmentInterface $wordpress
    ) {
    }

    public function information(): SystemInformation
    {
        return new SystemInformation(
            versions: $this->versions->information(),
            php: new PhpInformation(
                version: $this->php->version(),
                memoryLimit: $this->php->memoryLimit(),
                uploadMaxFilesize: $this->php->uploadMaxFilesize(),
                postMaxSize: $this->php->postMaxSize(),
                maxExecutionTime: $this->php->maxExecutionTime(),
                extensions: $this->php->extensions()
            ),
            server: new ServerInformation(
                software: $this->server->software(),
                phpSapi: $this->server->phpSapi(),
                operatingSystem: $this->server->operatingSystem()
            ),
            wordpress: new WordPressInformation(
                version: $this->wordpress->version(),
                locale: $this->wordpress->locale(),
                timezone: $this->wordpress->timezone(),
                multisite: $this->wordpress->isMultisite(),
                debug: $this->wordpress->isDebug()
            )
        );
    }
}
