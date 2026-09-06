<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;

final class VendorCanonicalNamingMigrationV2Service
{
    /**
     * @var array<int, array{
     *   from:string,
     *   to:string,
     *   sales_page:string,
     *   expected_old_en?:string
     * }>
     */
    private const TARGETS = [
        2995 => [
            'from' => 'WPML Multilingual CMS (including Add-on Plugins)',
            'to' => 'WPML Multilingual CMS',
            'sales_page' => 'https://wpml.org/',
        ],
        3171 => [
            'from' => 'WP Ghost (Hide My WP Ghost) – Security & Firewall',
            'to' => 'WP Ghost',
            'sales_page' => 'https://hidemywpghost.com/',
        ],
        3204 => [
            'from' => 'Stackable Premium – Page Builder Gutenberg Blocks',
            'to' => 'Stackable Premium',
            'sales_page' => 'https://wpstackable.com/',
        ],
        3208 => [
            'from' => 'Dokan Pro – AI Powered WooCommerce Multivendor Marketplace Solution',
            'to' => 'Dokan Pro',
            'sales_page' => 'https://dokan.co/wordpress/',
        ],
        3492 => [
            'from' => 'JetTabs – WordPress Tab Plugin for Adding Toggles, Accordions, and Nested Tab Structures',
            'to' => 'JetTabs',
            'sales_page' => 'https://crocoblock.com/plugins/jettabs/',
        ],
        3496 => [
            'from' => 'JetWooBuilder – WordPress Plugin for Shop Page, Product, Cart & Checkout for WooCommerce',
            'to' => 'JetWooBuilder',
            'sales_page' => 'https://crocoblock.com/plugins/jetwoobuilder/',
            'expected_old_en' => 'is a WooCommerce page builder for Elementor. Visually design custom shop, single product, cart, and checkout pages with pre-made templates and widgets.',
        ],
        3585 => [
            'from' => 'Elementor Pro Website Builder – More Than Just a Page Builder',
            'to' => 'Elementor Pro',
            'sales_page' => 'https://elementor.com/',
        ],
        3691 => [
            'from' => 'Gutenberg Essential Blocks Pro – Page Builder for Gutenberg Blocks & Patterns',
            'to' => 'Essential Blocks Pro',
            'sales_page' => 'https://essential-blocks.com/',
        ],
        4678 => [
            'from' => 'YOOtheme Pro WordPress Theme',
            'to' => 'YOOtheme Pro',
            'sales_page' => 'https://yootheme.com/page-builder',
            'expected_old_en' => 'is a powerful theme and page builder for WordPress, combining a visual editor with a dynamic system. Enables creating complex layouts, managing styles globally, using dynamic content from custom fields, and optimizing site performance without coding.',
        ],
        4813 => [
            'from' => 'SEO Engine Pro — Smart SEO with AI, Schema & Redirection for WordPress',
            'to' => 'SEO Engine Pro',
            'sales_page' => 'https://meowapps.com/seo-engine/',
        ],
        4864 => [
            'from' => 'WooCommerce Product Filters Plugin - Fast AJAX Filtering',
            'to' => 'WooCommerce Product Filters',
            'sales_page' => 'https://barn2.com/wordpress-plugins/woocommerce-product-filters/',
        ],
    ];

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorProductNamingAuditService $audit,
        private readonly TranslatePressTitleInspector $translationInspector,
        private readonly TranslationDictionaryInterface $dictionary,
        private readonly TranslationRegistrarInterface $registrar,
        private readonly Closure $call
    ) {
    }

    /**
     * @return array<int, array{from:string,to:string,sales_page:string,expected_old_en?:string}>
     */
    public function targets(): array
    {
        return self::TARGETS;
    }

    /**
     * @return array{
     *   productId:int,
     *   status:string,
     *   oldTitle:string,
     *   newTitle:string,
     *   slug:string,
     *   translation:string,
     *   reason:string
     * }
     */
    public function apply(int $productId): array
    {
        $target = self::TARGETS[$productId] ?? null;

        if ($target === null) {
            return $this->result(
                $productId,
                'SKIP',
                '',
                '',
                '',
                '',
                'Product is not in the approved canonical Vendor naming manifest.'
            );
        }

        $post = ($this->call)('get_post', $productId);

        if (! is_object($post)) {
            return $this->result(
                $productId,
                'SKIP',
                '',
                '',
                '',
                '',
                'Product no longer exists.'
            );
        }

        $postType = trim((string) ($post->post_type ?? ''));
        $postStatus = trim((string) ($post->post_status ?? ''));
        $currentTitle = trim((string) ($post->post_title ?? ''));
        $slug = trim((string) ($post->post_name ?? ''));

        if ($postType !== 'product' || $postStatus !== 'publish') {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                '',
                'Product is no longer a published WooCommerce product.'
            );
        }

        if ($currentTitle !== $target['from']) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                '',
                'Current H1 changed after the approved post-migration V5 review snapshot.'
            );
        }

        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));

        if ($salesPage !== $target['sales_page']) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                '',
                'Sales Page changed after the approved post-migration V5 review snapshot.'
            );
        }

        if ($this->audit->auditCurrentVendorProduct($productId) === null) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                '',
                'Product is no longer classified as Vendor.'
            );
        }

        $oldTranslation = $this->translationInspector->inspect(
            $currentTitle
        );

        if (
            ! in_array(
                $oldTranslation->state,
                ['TRANSLATED', 'UNFINISHED'],
                true
            )
        ) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                $oldTranslation->state,
                'TranslatePress title state is not safe for automatic remap.'
            );
        }

        if ($oldTranslation->state === 'TRANSLATED') {
            $expectedOldEn = trim(
                (string) ($target['expected_old_en'] ?? $currentTitle)
            );

            if (
                count($oldTranslation->translations) !== 1
                || trim($oldTranslation->translations[0]) !== $expectedOldEn
            ) {
                return $this->result(
                    $productId,
                    'SKIP',
                    $currentTitle,
                    $currentTitle,
                    $slug,
                    $oldTranslation->state,
                    'Existing EN title changed after the approved V5 review snapshot.'
                );
            }
        }

        $updated = ($this->call)(
            'wp_update_post',
            [
                'ID' => $productId,
                'post_title' => $target['to'],
                'post_name' => $slug,
            ],
            true
        );

        if (
            is_object($updated)
            && is_a($updated, 'WP_Error')
        ) {
            return $this->result(
                $productId,
                'ERROR',
                $currentTitle,
                $currentTitle,
                $slug,
                $oldTranslation->state,
                'WordPress rejected the canonical Vendor title update.'
            );
        }

        if ((int) $updated !== $productId) {
            return $this->result(
                $productId,
                'ERROR',
                $currentTitle,
                $currentTitle,
                $slug,
                $oldTranslation->state,
                'Unexpected WordPress title update result.'
            );
        }

        try {
            $this->ensureExactEnglishTitle(
                $productId,
                $slug,
                $target['to']
            );
        } catch (Throwable $exception) {
            $rollback = ($this->call)(
                'wp_update_post',
                [
                    'ID' => $productId,
                    'post_title' => $currentTitle,
                    'post_name' => $slug,
                ],
                true
            );

            $rollbackOk = (int) $rollback === $productId;

            return $this->result(
                $productId,
                'ERROR',
                $currentTitle,
                $rollbackOk
                    ? $currentTitle
                    : $target['to'],
                $slug,
                'TRP_ROLLBACK_' . ($rollbackOk ? 'OK' : 'FAILED'),
                'TranslatePress remap failed: '
                    . $exception->getMessage()
            );
        }

        $verified = ($this->call)('get_post', $productId);

        if (! is_object($verified)) {
            throw new RuntimeException(
                'Product could not be verified after canonical rename.'
            );
        }

        $verifiedTitle = trim((string) ($verified->post_title ?? ''));
        $verifiedSlug = trim((string) ($verified->post_name ?? ''));

        if (
            $verifiedTitle !== $target['to']
            || $verifiedSlug !== $slug
        ) {
            throw new RuntimeException(
                'Post-write verification failed for product #'
                . $productId
                . '.'
            );
        }

        return $this->result(
            $productId,
            'UPDATED',
            $currentTitle,
            $target['to'],
            $slug,
            'TRP_EXACT',
            'Canonical Vendor H1 updated, slug preserved, and EN title registered exactly in TranslatePress.'
        );
    }

    private function ensureExactEnglishTitle(
        int $productId,
        string $slug,
        string $title
    ): void {
        $this->registrar->registerPage($slug);
        $status = $this->dictionary->status([
            $title => $title,
        ]);

        if ($status->missing > 0) {
            $this->registrar->registerMissing($status);
            $this->registrar->registerPage($slug);
            $status = $this->dictionary->status([
                $title => $title,
            ]);
        }

        if ($status->fill > 0) {
            $this->dictionary->backup(
                $productId,
                $slug,
                $status
            );
            $this->dictionary->fill($status);
            $status = $this->dictionary->status([
                $title => $title,
            ]);
        }

        if (
            ! $status->tableOk
            || $status->total !== 1
            || $status->exact !== 1
            || $status->keep !== 0
            || $status->fill !== 0
            || $status->missing !== 0
        ) {
            throw new RuntimeException(
                'New canonical title was not registered as an exact EN translation.'
            );
        }
    }

    /**
     * @return array{
     *   productId:int,
     *   status:string,
     *   oldTitle:string,
     *   newTitle:string,
     *   slug:string,
     *   translation:string,
     *   reason:string
     * }
     */
    private function result(
        int $productId,
        string $status,
        string $oldTitle,
        string $newTitle,
        string $slug,
        string $translation,
        string $reason
    ): array {
        return [
            'productId' => $productId,
            'status' => $status,
            'oldTitle' => $oldTitle,
            'newTitle' => $newTitle,
            'slug' => $slug,
            'translation' => $translation,
            'reason' => $reason,
        ];
    }
}
