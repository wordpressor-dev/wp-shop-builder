<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;

final class ProductEditorialOfficialEnrichmentTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_REQUEST['preview_id']);
        parent::tearDown();
    }

    public function testOfficialFactsEnrichLongContentWithoutReplacingEnglishSummary(): void
    {
        $_REQUEST['preview_id'] = '4561';
        $summary = '<p>Edubin – Education WordPress Theme — это современная тема '
            . 'WordPress для школ, курсов, университетов и онлайн-образования. '
            . 'Адаптивный дизайн, интеграция с LMS, расписание занятий, страницы '
            . 'преподавателей, календарь событий, галерея, отзывы и форма '
            . 'обратной связи.</p>';
        $post = [
            'ID' => 4561,
            'post_type' => 'product',
            'post_title' => 'Edubin – Education WordPress Theme 9.6.5',
            'post_excerpt' => $summary,
            'post_content' => $summary,
        ];
        $meta = [
            'attr_version_value' => '9.6.5',
            '_wp_shop_product_type' => 'theme',
            'attr_developer_value' => 'pixelcurve',
            '_wp_shop_source_update_date' => '2026-08-28',
            'sales_page' => 'https://themeforest.net/item/edubin/24037792',
            '_wp_shop_editorial_backup_v28' => [
                'ruShort' => $summary,
                'ruLong' => $summary,
                'ruMeta' => 'Old RU meta.',
                'enShort' => '',
                'enLong' => '',
                'enMeta' => '',
            ],
            'surerank_settings_general' => ['page_description' => 'Old RU meta.'],
        ];
        $envato = new class implements EnvatoClientInterface {
            public function fetch(string $itemUrl, string $token): EnvatoItem
            {
                $tags = [
                    'learnpress',
                    'learndash',
                    'elementor',
                    'woocommerce',
                    'wpml',
                    'responsive',
                ];

                return new EnvatoItem(
                    24037792,
                    'Edubin – Education WordPress Theme',
                    'edubin',
                    '9.6.5',
                    '2026-08-28',
                    'pixelcurve',
                    $itemUrl,
                    1,
                    null,
                    $tags,
                    'edubin.zip',
                    ['tags' => $tags]
                );
            }
        };
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta),
            $envato
        );

        $preview = $service->preview(4561);

        self::assertSame('READY', $preview['officialStatus']);
        self::assertGreaterThan(0, $preview['officialFacts']);
        self::assertStringContainsString(
            'Edubin – Education WordPress Theme is a WordPress theme',
            $preview['generated']['enShort']
        );
        self::assertStringNotContainsString(
            '<p>LearnPress LMS integration',
            $preview['generated']['enShort']
        );
        self::assertStringNotContainsString(
            'LearnPress LMS integration, LearnDash LMS compatibility',
            $preview['generated']['enMeta']
        );
        self::assertStringContainsString(
            '<li>Адаптивный дизайн.</li>',
            $preview['generated']['ruLong']
        );
        self::assertStringNotContainsString(
            '<li>Edubin – Education WordPress Theme',
            $preview['generated']['ruLong']
        );
        self::assertStringContainsString(
            'интеграция с LearnPress LMS',
            $preview['generated']['ruLong']
        );
        self::assertStringNotContainsString(
            'Contact Form 7',
            $preview['generated']['ruLong']
        );
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $meta
     * @return \Closure(string,mixed...): mixed
     */
    private function caller(array &$post, array &$meta): \Closure
    {
        return static function (
            string $name,
            mixed ...$arguments
        ) use (
            &$post,
            &$meta
        ): mixed {
            if ($name === 'get_post') {
                return (object) $post;
            }

            if ($name === 'get_post_meta') {
                return $meta[(string) $arguments[1]] ?? '';
            }

            if ($name === 'get_option') {
                return 'envato-token';
            }

            if ($name === 'wp_get_post_terms') {
                return ['education', 'lms', 'contact form 7', 'wordpress'];
            }

            if ($name === 'current_time') {
                return '2026-08-29 20:55:00';
            }

            return null;
        };
    }
}
