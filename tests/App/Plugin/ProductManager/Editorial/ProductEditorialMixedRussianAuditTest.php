<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialMixedRussianAuditTest extends TestCase
{
    public function testFlagsUntranslatedEnglishFeatureFragmentsInRussianContent(): void
    {
        $post = [
            'ID' => 5318,
            'post_type' => 'product',
            'post_title' => 'Goya – Modern WooCommerce Theme',
            'post_excerpt' => 'Современная тема WordPress для WooCommerce.',
            'post_content' => '<p>Для каталога предусмотрены AJAX Add-to-Cart, '
                . 'Quick View, sticky bar товара, Header и Mega Menu.</p>',
        ];
        $service = new ProductEditorialMigrationService(
            $this->caller($post)
        );

        $issue = $service->mixedRussianIssue(5318);

        self::assertNotSame('', $issue);
    }

    public function testAllowsRecognizablePlatformAndProductNames(): void
    {
        $post = [
            'ID' => 5319,
            'post_type' => 'product',
            'post_title' => 'Clean Product',
            'post_excerpt' => 'Плагин WordPress для WooCommerce и Elementor.',
            'post_content' => '<p>Поддерживает AJAX, API, WPML и Gutenberg.</p>',
        ];
        $service = new ProductEditorialMigrationService(
            $this->caller($post)
        );

        self::assertSame(
            '',
            $service->mixedRussianIssue(5319)
        );
    }

    /**
     * @param array<string,mixed> $post
     * @return \Closure(string,mixed...): mixed
     */
    private function caller(array &$post): \Closure
    {
        return static function (
            string $name,
            mixed ...$arguments
        ) use (&$post): mixed {
            if ($name === 'get_post') {
                return (object) $post;
            }

            return null;
        };
    }
}
