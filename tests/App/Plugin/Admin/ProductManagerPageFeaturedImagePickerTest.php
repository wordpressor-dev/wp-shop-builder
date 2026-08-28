<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Admin;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;

final class ProductManagerPageFeaturedImagePickerTest extends TestCase
{
    public function testPickerRendersSelectedAttachmentPreview(): void
    {
        $page = $this->page();

        ob_start();
        $this->invoke(
            $page,
            'renderFeaturedImagePicker',
            ['123']
        );
        $html = (string) ob_get_clean();

        self::assertStringContainsString(
            'name="featured_image_id" value="123"',
            $html
        );
        self::assertStringContainsString(
            'https://example.test/preview.jpg',
            $html
        );
        self::assertStringContainsString(
            'Заменить изображение',
            $html
        );
        self::assertStringContainsString(
            'Удалить изображение',
            $html
        );
    }

    public function testPickerScriptUsesWordPressMediaLibrary(): void
    {
        $page = $this->page();

        ob_start();
        $this->invoke(
            $page,
            'renderFeaturedImagePickerScript'
        );
        $script = (string) ob_get_clean();

        self::assertStringContainsString(
            'window.wp.media',
            $script
        );
        self::assertStringContainsString(
            "library: {type: 'image'}",
            $script
        );
        self::assertStringContainsString(
            'idInput.value = String(attachment.id)',
            $script
        );
        self::assertStringContainsString(
            "idInput.value = ''",
            $script
        );
    }

    private function page(): ProductManagerPage
    {
        $envato = new class implements EnvatoClientInterface {
            public function fetch(
                string $itemUrl,
                string $token
            ): EnvatoItem {
                throw new LogicException('Not used by this test.');
            }
        };
        $repository = new class implements CatalogTagRepositoryInterface {
            public function existsInBoth(
                string $name,
                string $slug
            ): bool {
                return false;
            }
        };
        $controller = new ProductManagerController(
            $envato,
            new ExistingTagSelector($repository)
        );

        return new ProductManagerPage(
            $controller,
            static function (string $name, mixed ...$arguments): mixed {
                if ($name === 'wp_get_attachment_image_url') {
                    return 'https://example.test/preview.jpg';
                }

                if (
                    in_array(
                        $name,
                        ['esc_html', 'esc_attr', 'esc_url'],
                        true
                    )
                ) {
                    return htmlspecialchars(
                        (string) ($arguments[0] ?? ''),
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );
                }

                return null;
            }
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    private function invoke(
        ProductManagerPage $page,
        string $method,
        array $arguments = []
    ): mixed {
        return (new ReflectionMethod($page, $method))->invokeArgs(
            $page,
            $arguments
        );
    }
}
