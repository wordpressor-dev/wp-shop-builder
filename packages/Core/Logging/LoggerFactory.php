<?php

declare(strict_types=1);

namespace WPShop\Core\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use WPShop\Core\Config\ConfigInterface;

final class LoggerFactory
{
    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    public function create(): LoggerInterface
    {
        $driver = $this->config->get('logging.default', 'null');

        if (!is_string($driver)) {
            throw new \InvalidArgumentException('The logging.default value must be a string.');
        }

        return match ($driver) {
            'null' => new NullLogger(),
            'file' => $this->createFileLogger(),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported logging driver "%s".', $driver)
            ),
        };
    }

    private function createFileLogger(): FileLogger
    {
        $path = $this->config->get('logging.channels.file.path');
        $level = $this->config->get('logging.channels.file.level', LogLevel::DEBUG);

        if (!is_string($path) || $path === '') {
            throw new \InvalidArgumentException(
                'The logging.channels.file.path value must be a non-empty string.'
            );
        }

        if (!is_string($level)) {
            throw new \InvalidArgumentException(
                'The logging.channels.file.level value must be a string.'
            );
        }

        return new FileLogger($path, $level);
    }
}
