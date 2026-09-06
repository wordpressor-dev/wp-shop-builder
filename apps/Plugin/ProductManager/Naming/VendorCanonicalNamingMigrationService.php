<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;

final class VendorCanonicalNamingMigrationService
{
    /**
     * @var array<int, array{from:string,to:string,sales_page:string}>
     */
    private const TARGETS = [
        2681 => [
            'from' => 'Yoast SEO Premium – Advanced SEO With Real-Time Guidance and Built-In AI',
            'to' => 'Yoast SEO Premium',
            'sales_page' => 'https://yoast.com/product/yoast-seo-premium-wordpress/',
        ],
        3166 => [
            'from' => 'Bricks – Visual Website Builder for WordPress',
            'to' => 'Bricks',
            'sales_page' => 'https://bricksbuilder.io/',
        ],
        3223 => [
            'from' => 'Beaver Themer Add-On For Beaver Builder',
            'to' => 'Beaver Themer',
            'sales_page' => 'https://www.wpbeaverbuilder.com/beaver-themer/',
        ],
        3422 => [
            'from' => 'Envira Gallery - The Best Photo Gallery Plugin for WordPress (including Add-on Plugins)',
            'to' => 'Envira Gallery',
            'sales_page' => 'https://enviragallery.com/',
        ],
        3498 => [
            'from' => 'WP Smush Pro – The #1 Image Optimizer for WordPress',
            'to' => 'Smush Pro',
            'sales_page' => 'https://wpmudev.com/project/wp-smush-pro/',
        ],
        3500 => [
            'from' => 'Forminator Pro – Contact Form, Payment Form & Custom Form Builder',
            'to' => 'Forminator Pro',
            'sales_page' => 'https://wpmudev.com/project/forminator-pro/',
        ],
        3519 => [
            'from' => 'Branda Pro – White Label & Branding',
            'to' => 'Branda Pro',
            'sales_page' => 'https://wpmudev.com/project/ultimate-branding/',
        ],
        3671 => [
            'from' => 'AI Engine Pro – The Chatbot and AI Framework for WordPress',
            'to' => 'AI Engine Pro',
            'sales_page' => 'https://meowapps.com/products/ai-engine-pro/',
        ],
        3717 => [
            'from' => 'Media Grid Multimedia Portfolio Plugin',
            'to' => 'Media Grid',
            'sales_page' => 'https://lcweb.it/media-grid-wordpress-portfolio-plugin/',
        ],
        4114 => [
            'from' => 'Slim SEO Pro — Advanced SEO Features Without the Complexity',
            'to' => 'Slim SEO Pro',
            'sales_page' => 'https://wpslimseo.com/products/slim-seo-pro/',
        ],
        4336 => [
            'from' => 'Divi 5 (including Divi Builder)',
            'to' => 'Divi 5',
            'sales_page' => 'https://www.elegantthemes.com/gallery/divi/',
        ],
        4339 => [
            'from' => 'Extra (including Divi Builder)',
            'to' => 'Extra',
            'sales_page' => 'https://www.elegantthemes.com/gallery/extra/',
        ],
        4409 => [
            'from' => 'FiboSearch - AJAX Search for WooCommerce Pro',
            'to' => 'FiboSearch Pro',
            'sales_page' => 'https://fibosearch.com/',
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
     * @return array<int, array{from:string,to:string,sales_page:string}>
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
                'Current H1 changed after the approved V5 review snapshot.'
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
                'Sales Page changed after the approved V5 review snapshot.'
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

        if (
            $oldTranslation->state === 'TRANSLATED'
            && (
                count($oldTranslation->translations) !== 1
                || trim($oldTranslation->translations[0]) !== $currentTitle
            )
        ) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                $oldTranslation->state,
                'Existing EN title differs from the current H1; automatic remap was stopped.'
            );
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
