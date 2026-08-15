<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;

final class TranslationMapBuilderTest extends TestCase
{
    public function testBuildsShortLongAndMetaMap(): void
    {
        $map = (new TranslationMapBuilder())->build(
            '<p><strong>Тема</strong> для магазина.</p>',
            '<h2>Возможности</h2><p>Быстрый сайт</p>',
            'Описание товара & функции',
            '<p><strong>Theme</strong> for a store.</p>',
            '<h2>Features</h2><p>Fast website</p>',
            'Product description & features'
        );

        self::assertSame('Theme', $map['Тема']);
        self::assertSame(
            ' for a store.',
            $map['для магазина.'] ?? null
        );
        self::assertSame('Features', $map['Возможности']);
        self::assertSame('Fast website', $map['Быстрый сайт']);
        self::assertSame(
            'Product description & features',
            $map['Описание товара & функции']
        );
    }

    public function testIgnoresPunctuationOnlyTextNodes(): void
    {
        $map = (new TranslationMapBuilder())->build(
            '<p>Текст <strong>важный</strong>.</p>',
            '<p>Описание</p>',
            'Мета',
            '<p>Text <strong>important</strong>.</p>',
            '<p>Description</p>',
            'Meta'
        );

        self::assertArrayNotHasKey('.', $map);
        self::assertSame('important', $map['важный']);
    }

    public function testRejectsDifferentHtmlTextSegmentCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'LONG: RU segments=2, EN segments=1.'
        );

        (new TranslationMapBuilder())->build(
            '<p>Коротко</p>',
            '<p>Первый</p><p>Второй</p>',
            'Мета',
            '<p>Short</p>',
            '<p>First</p>',
            'Meta'
        );
    }

    public function testRejectsConflictingTranslationForSameSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Conflicting EN translation for RU segment'
        );

        (new TranslationMapBuilder())->build(
            '<p>Одинаково</p>',
            '<p>Одинаково</p>',
            'Мета',
            '<p>First</p>',
            '<p>Second</p>',
            'Meta'
        );
    }
}
