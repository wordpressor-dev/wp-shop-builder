<?php

declare(strict_types=1);

namespace WPShop\Core\Logging;

use DateTimeImmutable;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;
use WPShop\Core\Logging\Exception\InvalidLogLevel;

final class FileLogger extends AbstractLogger
{
    /**
     * @var array<string, int>
     */
    private const LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    private readonly int $minimumLevel;

    public function __construct(
        private readonly string $path,
        string $level = LogLevel::DEBUG
    ) {
        $this->minimumLevel = $this->levelValue($level);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        mixed $level,
        string|Stringable $message,
        array $context = []
    ): void {
        if (!is_string($level)) {
            throw InvalidLogLevel::forLevel(get_debug_type($level));
        }

        $levelValue = $this->levelValue($level);

        if ($levelValue < $this->minimumLevel) {
            return;
        }

        $line = sprintf(
            "%s [%s] %s%s%s",
            (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate((string) $message, $context),
            $this->formatContext($context),
            PHP_EOL
        );

        $this->write($line);
    }

    private function levelValue(string $level): int
    {
        if (!array_key_exists($level, self::LEVELS)) {
            throw InvalidLogLevel::forLevel($level);
        }

        return self::LEVELS[$level];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (
                is_scalar($value)
                || $value === null
                || $value instanceof Stringable
            ) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = $value instanceof Throwable
                ? [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                ]
                : $value;
        }

        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $json === false ? '' : ' ' . $json;
    }

    private function write(string $line): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create log directory "%s".', $directory));
        }

        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write to log file "%s".', $this->path));
        }
    }
}
