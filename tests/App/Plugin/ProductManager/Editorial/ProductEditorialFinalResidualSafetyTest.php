<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class ProductEditorialFinalResidualSafetyTest extends TestCase
{
    public function testBusinessEditionCatalogTagIsNotAProjectTopic(): void
    {
        $draft = (new ProductEditorialDraftBuilder())->build(
            'TranslatePress Business – Translate Multilingual sites with AI Translation',
            'Cozmoslabs',
            CatalogProductType::PLUGIN,
            ['business'],
            '',
            [
                'ruShort' => 'TranslatePress Business — профессиональный плагин '
                    . 'для создания многоязычных сайтов.',
            ]
        );

        self::assertStringNotContainsString(
            'ориентированных на бизнес',
            $draft['ruLong']
        );
        self::assertStringNotContainsString(
            'focused on business',
            $draft['enLong']
        );
    }

    public function testSingleIdentitySummaryDoesNotBecomeCommaFeatures(): void
    {
        $summary = 'GeneratePress Premium — лёгкая premium-тема WordPress '
            . 'с сотнями настроек, Starter Sites и block-based theme building '
            . 'для быстрых бизнес-сайтов, блогов и магазинов.';

        $draft = (new ProductEditorialDraftBuilder())->build(
            'GeneratePress Premium 2.5.6',
            'Tom Usborne',
            CatalogProductType::PLUGIN,
            [],
            '',
            [
                'ruShort' => $summary,
                'ruLong' => $summary,
            ]
        );

        self::assertStringContainsString(
            'премиум-плагин для GeneratePress',
            $draft['ruShort']
        );
        self::assertStringNotContainsString(
            '<li>блогов и магазинов.</li>',
            $draft['ruLong']
        );
        self::assertStringNotContainsString(
            '<h3>Основные возможности GeneratePress Premium</h3>',
            $draft['ruLong']
        );
    }
}
