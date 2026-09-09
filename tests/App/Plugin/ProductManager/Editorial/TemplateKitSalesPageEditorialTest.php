<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\EnvatoTemplateKitSalesPageExtractor;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;
use WPShop\App\Plugin\ProductManager\Editorial\TemplateKitEditorialEnricher;

final class TemplateKitSalesPageEditorialTest extends TestCase
{
    public function testBuildsFactRichProbizEditorialFromSalesPage(): void
    {
        $html = <<<'HTML'
<div class="item-description">
<p>Probiz is an Elementor Template Kit specially designed for Business Consulting websites.</p>
<p>Features :</p>
<ul>
<li>Compatible with WordPress – Elementor</li>
<li>Using Free Plugins (Elementor Pro is not required)</li>
<li>True no-code customization with drag and drop</li>
<li>100% Fully Responsive & mobile-friendly</li>
<li>Modern and Professional design</li>
<li>11+ pre-built templates ready to use</li>
<li>Customize fonts and colors in one place (Global Theme Kit Style)</li>
</ul>
<p>Templates in Zip :</p>
<ul>
<li>Homepage</li>
<li>About Us</li>
<li>Our Team</li>
<li>Services</li>
<li>Service Detail</li>
<li>Pricing Plan</li>
<li>FAQs</li>
<li>404</li>
<li>Latest Article</li>
<li>Single Blog</li>
<li>Contact Us</li>
<li>Contact Us Form</li>
<li>Subscribe Form</li>
<li>Header</li>
<li>Footer</li>
</ul>
<p>Required Plugins :</p>
<ul>
<li>Elementor</li>
<li>RomethemeForm</li>
<li>RomethemeKit For Elementor</li>
</ul>
<p>This Template Kit contains page content for creating Elementor pages.
This kit has been optimized for use with the free "Hello Elementor" theme.</p>
<p>Images This Template Kit uses demo images from Envato Elements.
You will need to license these images from Envato Elements to use them.</p>
<div class="tags">
<a href="https://themeforest.net/search/agency">agency</a>
<a href="/search/business">business</a>
<a href="https://themeforest.net/search/company">company</a>
<a href="https://themeforest.net/search/consulting">consulting</a>
<a href="https://themeforest.net/search/corporate">corporate</a>
<a href="https://themeforest.net/search/finance">finance</a>
<a href="https://themeforest.net/search/landingpage">landingpage</a>
<a href="https://themeforest.net/search/marketing">marketing</a>
<a href="https://themeforest.net/search/startup">startup</a>
</div>
</div>
HTML;

        $facts = (new EnvatoTemplateKitSalesPageExtractor())->extract($html);

        self::assertSame(11, $facts['templateCount']);
        self::assertFalse($facts['elementorProRequired']);
        self::assertTrue($facts['responsive']);
        self::assertTrue($facts['dragDrop']);
        self::assertTrue($facts['globalStyles']);
        self::assertTrue($facts['helloElementor']);
        self::assertTrue($facts['demoImagesLicense']);
        self::assertContains('Homepage', $facts['templates']);
        self::assertContains('RomethemeForm', $facts['requiredPlugins']);
        self::assertSame(
            [
                'agency',
                'business',
                'company',
                'consulting',
                'corporate',
                'finance',
                'landingpage',
                'marketing',
                'startup',
            ],
            $facts['tags']
        );

        $base = (new ProductEditorialDraftBuilder())->build(
            'Probiz – Business Consulting Elementor Template Kit',
            'Rometheme',
            CatalogProductType::TEMPLATE_KIT,
            [
                'business',
                'consulting',
                'agency',
                'corporate',
                'finance',
                'marketing',
                'startup',
            ]
        );

        $editorial = (new TemplateKitEditorialEnricher())->enrich(
            $base,
            'Probiz – Business Consulting Elementor Template Kit',
            'Rometheme',
            [
                'business',
                'consulting',
                'agency',
                'corporate',
                'finance',
                'marketing',
                'startup',
            ],
            $facts
        );

        self::assertStringContainsString(
            'более 11 готовых шаблонов',
            mb_strtolower($editorial['ruShort'], 'UTF-8')
        );
        self::assertStringContainsString(
            'Elementor Pro не требуется',
            $editorial['ruShort']
        );
        self::assertStringContainsString(
            '<h3>Основные возможности</h3>',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            'Адаптивный дизайн для компьютеров, планшетов и смартфонов',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            'страница «О компании»',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Кому подходит</h3>',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Совместимость и требования</h3>',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            'RomethemeForm',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            'Hello Elementor',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Что важно знать</h3>',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            'не самостоятельная WordPress-тема',
            $editorial['ruLong']
        );
        self::assertStringContainsString(
            'консалтинговых компаний, финансовых консультантов, агентств и корпоративных проектов',
            $editorial['ruShort']
        );
        self::assertStringContainsString(
            'консалтинговых компаний, финансовых консультантов, агентств и корпоративных проектов',
            $editorial['ruLong']
        );
        self::assertStringNotContainsString(
            'бизнес-сайтов, сайтов компаний',
            $editorial['ruLong']
        );
        self::assertSame(
            'Probiz — набор шаблонов Elementor для консалтинговых '
                . 'и финансовых сайтов. 11+ готовых шаблонов. '
                . 'Elementor Pro не требуется.',
            $editorial['ruMeta']
        );
        self::assertSame(
            'Probiz — Elementor template kit for consulting and finance '
                . 'websites. 11+ ready templates. Elementor Pro is not required.',
            $editorial['enMeta']
        );
        self::assertStringNotContainsString('…', $editorial['ruMeta']);
        self::assertStringNotContainsString('…', $editorial['enMeta']);
        self::assertStringContainsString(
            'Демо-изображения могут требовать отдельной лицензии Envato Elements',
            $editorial['ruLong']
        );
        self::assertStringNotContainsString(
            '<h3>Elementor и настройка страниц</h3>',
            $editorial['ruLong']
        );
        self::assertStringNotContainsString(
            '<h3>Многоязычные проекты</h3>',
            $editorial['ruLong']
        );
    }
}
