<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Write;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Write\AdvancedLabelWriter;

final class AdvancedLabelWriterTest extends TestCase
{
    public function testWritesOnlyEditoriallySelectedLabels(): void
    {
        $saved = null;
        $writer = new AdvancedLabelWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$saved): mixed {
                if ($name === 'update_post_meta') {
                    $saved = $arguments[2];
                }

                return true;
            }
        );

        $logs = $writer->write(
            5028,
            $this->data(hit: true, new: false)
        );

        self::assertSame(
            ['label_from_post' => ['2536']],
            $saved
        );
        self::assertSame(
            ['ADVANCED LABELS = Hit(2536)'],
            $logs
        );
    }

    public function testSupportsBothHitAndNewWhenExplicitlySelected(): void
    {
        $saved = null;
        $writer = new AdvancedLabelWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$saved): mixed {
                if ($name === 'update_post_meta') {
                    $saved = $arguments[2];
                }

                return true;
            }
        );

        $writer->write(
            5028,
            $this->data(hit: true, new: true)
        );

        self::assertSame(
            ['label_from_post' => ['2536', '2637']],
            $saved
        );
    }

    public function testDeletesLabelMetaWhenBothAreOff(): void
    {
        $calls = [];
        $writer = new AdvancedLabelWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$calls): mixed {
                $calls[] = [$name, $arguments];

                return true;
            }
        );

        $logs = $writer->write(
            5028,
            $this->data(hit: false, new: false)
        );

        self::assertSame(
            'delete_post_meta',
            $calls[0][0]
        );
        self::assertSame(
            ['ADVANCED LABELS = NONE'],
            $logs
        );
    }

    private function data(
        bool $hit,
        bool $new
    ): ProductDraftData {
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
            $hit,
            $new
        );
    }
}
