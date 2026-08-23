<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Write;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Write\SureRankWriter;

final class SureRankWriterTest extends TestCase
{
    public function testPreservesExistingSettingsAndUpdatesOnlyDescription(): void
    {
        $saved = null;
        $writer = new SureRankWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$saved): mixed {
                if ($name === 'get_post_meta') {
                    return [
                        'page_title' => 'Keep title',
                        'schema' => ['type' => 'Product'],
                        'page_description' => 'Old description',
                    ];
                }

                if ($name === 'update_post_meta') {
                    $saved = $arguments[2];

                    return true;
                }

                return null;
            }
        );

        $logs = $writer->write(5028, $this->data());

        self::assertIsArray($saved);
        self::assertSame('Keep title', $saved['page_title']);
        self::assertSame(
            ['type' => 'Product'],
            $saved['schema']
        );
        self::assertSame(
            'RU meta',
            $saved['page_description']
        );
        self::assertSame(
            ['SURERANK META DESCRIPTION = UPDATED'],
            $logs
        );
    }

    private function data(): ProductDraftData
    {
        return new ProductDraftData(
            'Aabbe – Digital Marketplace WordPress Theme',
            'aabbe',
            26350912,
            '6.2.0',
            '2025-04-20',
            'QuomodoTheme',
            '249',
            'https://themeforest.net/item/aabbe/26350912',
            'themeforest-26350912-aabbe-6.2.0.zip',
            '',
            0,
            [],
            'RU short',
            'RU long',
            'RU meta',
            'EN short',
            'EN long',
            'EN meta',
            'Pre-activated.',
            false,
            false
        );
    }
}
