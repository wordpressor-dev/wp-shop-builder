<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;

final class ProductManagerControllerTest extends TestCase
{
    public function testAutofillsMappedEnvatoFieldsAndExistingTags(): void
    {
        $controller = new ProductManagerController(
            new ProductManagerEnvatoClient(),
            new ExistingTagSelector(
                new ProductManagerCatalogTagRepository()
            )
        );

        $result = $controller->autofill(
            ' https://themeforest.net/item/aabbe/26350912 ',
            ' token '
        );

        self::assertTrue($result->success);
        self::assertSame(
            'Aabbe – Digital Marketplace WordPress Theme',
            $result->fields['base_title']
        );
        self::assertSame('aabbe', $result->fields['slug']);
        self::assertSame('26350912', $result->fields['item_id']);
        self::assertSame('6.2.0', $result->fields['version']);
        self::assertSame(
            '2025-04-20',
            $result->fields['source_update_date']
        );
        self::assertSame(
            "elementor|elementor\nторговая площадка|marketplace",
            $result->fields['tags']
        );
        self::assertContains(
            'ENVATO AUTOFILL = READY',
            $result->logs
        );
    }

    public function testReturnsSafeLogWhenEnvatoFails(): void
    {
        $client = $this->createMock(
            EnvatoClientInterface::class
        );
        $client->method('fetch')->willThrowException(
            new RuntimeException('Envato unavailable.')
        );
        $repository = $this->createMock(
            CatalogTagRepositoryInterface::class
        );
        $controller = new ProductManagerController(
            $client,
            new ExistingTagSelector($repository)
        );

        $result = $controller->autofill('url', 'token');

        self::assertFalse($result->success);
        self::assertSame([], $result->fields);
        self::assertContains(
            'ERROR MESSAGE: Envato unavailable.',
            $result->logs
        );
    }
}

final class ProductManagerEnvatoClient implements
    EnvatoClientInterface
{
    public function fetch(
        string $itemUrl,
        string $token
    ): EnvatoItem {
        TestCase::assertSame(
            'https://themeforest.net/item/aabbe/26350912',
            $itemUrl
        );
        TestCase::assertSame('token', $token);

        return new EnvatoItem(
            26350912,
            'Aabbe – Digital Marketplace WordPress Theme',
            'aabbe',
            '6.2.0',
            '2025-04-20',
            'QuomodoTheme',
            'https://themeforest.net/item/aabbe/26350912',
            100,
            '2020-04-20T00:00:00+00:00',
            ['elementor', 'marketplace', 'unknown-envato-tag'],
            'themeforest-26350912-aabbe-6.2.0.zip',
            [
                'name' => 'Aabbe Digital Marketplace',
                'tags' => [
                    'elementor',
                    'marketplace',
                    'unknown-envato-tag',
                ],
            ]
        );
    }
}

final class ProductManagerCatalogTagRepository implements
    CatalogTagRepositoryInterface
{
    public function existsInBoth(
        string $name,
        string $slug
    ): bool {
        return in_array(
            $slug,
            ['elementor', 'marketplace'],
            true
        );
    }
}
