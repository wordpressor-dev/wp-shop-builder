<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Config;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Config\ConfigLoader;
use WPShop\Core\Config\Exception\ConfigFileNotFound;
use WPShop\Core\Config\Exception\InvalidConfigFile;

final class ConfigLoaderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testConfigurationCanBeLoadedFromPhpFile(): void
    {
        $file = $this->createConfigFile(<<<'PHP'
<?php

return [
    'app' => [
        'name' => 'WP Shop Builder',
    ],
];
PHP);

        $config = (new ConfigLoader())->load([$file]);

        self::assertSame(
            'WP Shop Builder',
            $config->get('app.name')
        );
    }

    public function testLaterFilesOverrideEarlierFiles(): void
    {
        $base = $this->createConfigFile(<<<'PHP'
<?php

return [
    'app' => [
        'name' => 'WP Shop Builder',
        'debug' => false,
    ],
];
PHP);

        $environment = $this->createConfigFile(<<<'PHP'
<?php

return [
    'app' => [
        'debug' => true,
    ],
];
PHP);

        $config = (new ConfigLoader())->load([
            $base,
            $environment,
        ]);

        self::assertSame(
            'WP Shop Builder',
            $config->get('app.name')
        );
        self::assertTrue($config->get('app.debug'));
    }

    public function testMissingFileThrowsException(): void
    {
        $file = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-missing-config.php';

        $this->expectException(ConfigFileNotFound::class);
        $this->expectExceptionMessage(
            sprintf('Configuration file "%s" was not found.', $file)
        );

        (new ConfigLoader())->load([$file]);
    }

    public function testFileMustReturnArray(): void
    {
        $file = $this->createConfigFile(<<<'PHP'
<?php

return 'invalid';
PHP);

        $this->expectException(InvalidConfigFile::class);
        $this->expectExceptionMessage(
            sprintf(
                'Configuration file "%s" must return an array.',
                $file
            )
        );

        (new ConfigLoader())->load([$file]);
    }

    private function createConfigFile(string $contents): string
    {
        $file = tempnam(
            sys_get_temp_dir(),
            'wp-shop-config-'
        );

        if ($file === false) {
            self::fail('Unable to create temporary configuration file.');
        }

        file_put_contents($file, $contents);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
