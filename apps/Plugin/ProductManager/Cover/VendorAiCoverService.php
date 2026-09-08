<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Cover\Contracts\VendorAiImageGeneratorInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\ProductSourceType;

final class VendorAiCoverService
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorAiImageGeneratorInterface $generator,
        private readonly VendorAiCoverPromptBuilder $promptBuilder,
        private readonly VendorAiCoverMediaService $media,
        private readonly Closure $call
    ) {
    }

    /**
     * @return list<string>
     */
    public function generateForNewProduct(
        int $productId,
        ProductDraftData $data
    ): array {
        if (
            ProductSourceType::fromSalesPage(
                $data->salesPage
            ) !== ProductSourceType::VENDOR
        ) {
            return [
                'VENDOR AI COVER = SKIP_MARKETPLACE',
            ];
        }

        if (! $this->enabled()) {
            return [
                'VENDOR AI COVER = SKIP_DISABLED',
            ];
        }

        if (! $this->generator->configured()) {
            ($this->call)(
                'update_post_meta',
                $productId,
                '_wp_shop_vendor_cover_status',
                'not_configured'
            );

            return [
                'VENDOR AI COVER = SKIP_NOT_CONFIGURED',
                'ACTION: configure OpenAI API key in Product Manager AI Cover settings.',
            ];
        }

        $locked = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_vendor_cover_locked',
            true
        ));

        if (in_array(
            strtolower($locked),
            ['1', 'yes', 'true', 'on', 'locked'],
            true
        )) {
            return [
                'VENDOR AI COVER = SKIP_LOCKED',
            ];
        }

        $prompt = $this->promptBuilder->build($data);
        $purpose = $this->promptBuilder->purposeFor($data);
        $inputHash = hash(
            'sha256',
            implode(
                '|',
                [
                    VendorAiCoverPromptBuilder::VERSION,
                    trim($data->title()),
                    $purpose,
                    trim($data->productType),
                    trim($data->developer),
                ]
            )
        );

        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_status',
            'generating'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_error',
            ''
        );

        try {
            $image = $this->generator->generate($prompt);
            $attachmentId = $this->media->install(
                $productId,
                $data->title(),
                $image,
                $inputHash
            );

            return [
                'VENDOR AI COVER = CANDIDATE',
                'VENDOR AI COVER MODEL = '
                    . OpenAIVendorAiImageGenerator::MODEL,
                'VENDOR AI COVER PURPOSE = ' . $purpose,
                'VENDOR AI COVER SIZE = 590x300 WEBP',
                'VENDOR AI COVER WATERMARK = wp-shop.org',
                'VENDOR AI COVER CANDIDATE ATTACHMENT ID = '
                    . $attachmentId,
                'FEATURED IMAGE = UNCHANGED',
                'ACTION: open the Draft and approve or discard the AI Cover candidate.',
            ];
        } catch (Throwable $exception) {
            ($this->call)(
                'update_post_meta',
                $productId,
                '_wp_shop_vendor_cover_status',
                'error'
            );
            ($this->call)(
                'update_post_meta',
                $productId,
                '_wp_shop_vendor_cover_error',
                $this->shorten($exception->getMessage())
            );

            return [
                'VENDOR AI COVER = ERROR',
                'VENDOR AI COVER ERROR = '
                    . $this->shorten($exception->getMessage()),
                'DRAFT / ORIGINAL FEATURED IMAGE = PRESERVED',
                'ACTION: keep Draft for review; AI cover can be retried later.',
            ];
        }
    }

    private function enabled(): bool
    {
        $value = (string) ($this->call)(
            'get_option',
            'wp_shop_vendor_ai_cover_enabled',
            '1'
        );

        return ! in_array(
            strtolower(trim($value)),
            ['0', 'no', 'false', 'off'],
            true
        );
    }

    private function shorten(string $value): string
    {
        $value = trim(
            (string) preg_replace('/\s+/u', ' ', $value)
        );

        if (mb_strlen($value, 'UTF-8') <= 320) {
            return $value;
        }

        return mb_substr(
            $value,
            0,
            317,
            'UTF-8'
        ) . '...';
    }
}
