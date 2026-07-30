<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Logging;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use WPShop\Core\Logging\Exception\InvalidLogLevel;
use WPShop\Core\Logging\FileLogger;

final class FileLoggerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wp-shop-logger-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $path = $this->directory . '/app.log';

        if (is_file($path)) {
            unlink($path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testWritesFormattedMessageAndCreatesDirectory(): void
    {
        $path = $this->directory . '/app.log';
        $logger = new FileLogger($path);

        $logger->info('Product {id} indexed', ['id' => 42]);

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[INFO\] Product 42 indexed/',
            $contents
        );
        self::assertStringContainsString('"id":42', $contents);
    }

    public function testMessagesBelowMinimumLevelAreIgnored(): void
    {
        $path = $this->directory . '/app.log';
        $logger = new FileLogger($path, LogLevel::WARNING);

        $logger->info('Ignored');
        $logger->error('Written');

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringNotContainsString('Ignored', $contents);
        self::assertStringContainsString('[ERROR] Written', $contents);
    }

    public function testInvalidLevelIsRejected(): void
    {
        $logger = new FileLogger($this->directory . '/app.log');

        $this->expectException(InvalidLogLevel::class);

        $logger->log('verbose', 'Unsupported');
    }
}
