<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Admin;

use LogicException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;

final class ProductManagerPageMediaEnqueueTest extends TestCase
{
    public function testRenderEnqueuesWordPressMediaLibrary(): void
    {
        $calls = [];
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
        $page = new ProductManagerPage(
            new ProductManagerController(
                $envato,
                new ExistingTagSelector($repository)
            ),
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$calls): mixed {
                $calls[] = $name;

                if ($name === 'get_option') {
                    return 0;
                }

                if ($name === 'wp_unslash') {
                    return $arguments[0] ?? '';
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
        $previousPost = $_POST;
        $_POST = [];

        ob_start();
        $page->render();
        ob_end_clean();
        $_POST = $previousPost;

        self::assertContains('wp_enqueue_media', $calls);
    }
}
