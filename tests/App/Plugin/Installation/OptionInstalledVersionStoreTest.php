<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Installation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;
use WPShop\App\Plugin\Installation\OptionInstalledVersionStore;

final class OptionInstalledVersionStoreTest extends TestCase
{
    public function testReadsInstalledVersion(): void
    {
        $store = new OptionInstalledVersionStore(
            static fn (
                string $name,
                mixed $default
            ): mixed => '0.2.0',
            static fn (
                string $name,
                mixed $value,
                bool $autoload
            ): bool => true
        );

        self::assertSame(
            '0.2.0',
            $store->installedVersion()
        );
    }

    public function testReturnsNullWhenVersionIsMissing(): void
    {
        $store = new OptionInstalledVersionStore(
            static fn (
                string $name,
                mixed $default
            ): mixed => $default,
            static fn (
                string $name,
                mixed $value,
                bool $autoload
            ): bool => true
        );

        self::assertNull(
            $store->installedVersion()
        );
    }

    public function testSavesVersionWithoutAutoload(): void
    {
        $arguments = [];

        $store = new OptionInstalledVersionStore(
            static fn (
                string $name,
                mixed $default
            ): mixed => null,
            static function (
                string $name,
                mixed $value,
                bool $autoload
            ) use (&$arguments): bool {
                $arguments = [
                    $name,
                    $value,
                    $autoload,
                ];

                return true;
            }
        );

        $store->saveInstalledVersion('0.2.0');

        self::assertSame(
            [
                OptionInstalledVersionStore::OPTION_NAME,
                '0.2.0',
                false,
            ],
            $arguments
        );
    }

    public function testUnchangedVersionIsAccepted(): void
    {
        $store = new OptionInstalledVersionStore(
            static fn (
                string $name,
                mixed $default
            ): mixed => '0.2.0',
            static fn (
                string $name,
                mixed $value,
                bool $autoload
            ): bool => false
        );

        $store->saveInstalledVersion('0.2.0');

        $this->addToAssertionCount(1);
    }

    public function testFailedVersionWriteThrowsException(): void
    {
        $store = new OptionInstalledVersionStore(
            static fn (
                string $name,
                mixed $default
            ): mixed => '0.1.0',
            static fn (
                string $name,
                mixed $value,
                bool $autoload
            ): bool => false
        );

        $this->expectException(
            InstallationFailed::class
        );

        $this->expectExceptionMessage(
            'Unable to save installed plugin version 0.2.0.'
        );

        $store->saveInstalledVersion('0.2.0');
    }
}
