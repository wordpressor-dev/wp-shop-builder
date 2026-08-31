<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class ProductEditorialAudienceExtractionTest extends TestCase
{
    public function testUsesExplicitDeveloperAudienceInsteadOfFunctionalForPhrase(): void
    {
        $builder = new ProductEditorialDraftBuilder();
        $ru = 'WP Reset PRO — профессиональный плагин для быстрого сброса базы данных '
            . 'WordPress к исходным настройкам. Идеален для разработчиков и тестировщиков.';
        $en = 'WP Reset PRO is a professional plugin for quickly resetting the WordPress '
            . 'database. Ideal for developers and testers.';

        $content = $builder->build(
            'WP Reset PRO - WordPress Development Tool for Non-Devs',
            'WebFactory Ltd',
            CatalogProductType::PLUGIN,
            [],
            '',
            ['ruShort' => $ru, 'ruLong' => $ru, 'enShort' => $en, 'enLong' => $en]
        );

        self::assertStringContainsString(
            'WP Reset PRO подходит для разработчиков и тестировщиков.',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'WP Reset PRO is suitable for developers and testers.',
            $content['enLong']
        );
        self::assertStringNotContainsString(
            'подходит для быстрого сброса базы данных',
            $content['ruLong']
        );
    }

    public function testRejectsPlatformAndFunctionalPurposeAsAudience(): void
    {
        $builder = new ProductEditorialDraftBuilder();

        foreach ([
            [
                'SecuPress Pro with Simple SSL – Simple and Performance Security',
                'SecuPress Pro — комплексный плагин безопасности для WordPress.',
                'SecuPress Pro is a security plugin for WordPress.',
            ],
            [
                'Advanced Custom Fields PRO',
                'Advanced Custom Fields PRO — плагин для создания произвольных полей в WordPress.',
                'Advanced Custom Fields PRO is a plugin for creating custom fields in WordPress.',
            ],
            [
                'Yoast News SEO',
                'Yoast News SEO — плагин для оптимизации новостного сайта под Google News.',
                'Yoast News SEO is a plugin for optimizing news sites for Google News.',
            ],
        ] as [$title, $ru, $en]) {
            $content = $builder->build(
                $title,
                '',
                CatalogProductType::PLUGIN,
                [],
                '',
                ['ruShort' => $ru, 'ruLong' => $ru, 'enShort' => $en, 'enLong' => $en]
            );

            self::assertStringContainsString(
                'подойдёт пользователям, которым нужен плагин WordPress',
                $content['ruLong']
            );
            self::assertStringContainsString(
                'is suitable for users who need a WordPress plugin',
                $content['enLong']
            );
            self::assertStringNotContainsString(
                'подходит для WordPress',
                $content['ruLong']
            );
            self::assertStringNotContainsString(
                'подходит для создания',
                $content['ruLong']
            );
            self::assertStringNotContainsString(
                'is suitable for WordPress',
                $content['enLong']
            );
            self::assertStringNotContainsString(
                'is suitable for creating',
                $content['enLong']
            );
        }
    }

    public function testKeepsRealAudienceFromThemeSummary(): void
    {
        $builder = new ProductEditorialDraftBuilder();
        $ru = 'Lirena — элегантная тема WordPress для салонов красоты и спа.';
        $en = 'Lirena is an elegant WordPress theme for beauty salons and spas.';

        $content = $builder->build(
            'Lirena - Beauty and Spa Salon WordPress Theme',
            'GT3themes',
            CatalogProductType::THEME,
            [],
            '',
            ['ruShort' => $ru, 'ruLong' => $ru, 'enShort' => $en, 'enLong' => $en]
        );

        self::assertStringContainsString(
            'Lirena подходит для салонов красоты и спа.',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'Lirena is suitable for beauty salons and spas.',
            $content['enLong']
        );
    }
}
