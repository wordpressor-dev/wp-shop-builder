<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Logging;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Config\ConfigRepository;
use WPShop\Core\Logging\FileLogger;
use WPShop\Core\Logging\LoggerFactory;
use WPShop\Core\Logging\NullLogger;

final class LoggerFactoryTest extends TestCase
{
    public function testCreatesNullLoggerByDefault(): void
    {
        $logger = (new LoggerFactory(new ConfigRepository()))->create();

        self::assertInstanceOf(NullLogger::class, $logger);
    }

    public function testCreatesConfiguredFileLogger(): void
    {
        $config = new ConfigRepository([
            'logging' => [
                'default' => 'file',
                'channels' => [
                    'file' => [
                        'path' => sys_get_temp_dir() . '/wp-shop-builder.log',
                        'level' => 'notice',
                    ],
                ],
            ],
        ]);

        $logger = (new LoggerFactory($config))->create();

        self::assertInstanceOf(FileLogger::class, $logger);
    }

    public function testRejectsUnknownDriver(): void
    {
        $config = new ConfigRepository([
            'logging' => ['default' => 'remote'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported logging driver');

        (new LoggerFactory($config))->create();
    }
}
