<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use Closure;
use RuntimeException;
use Throwable;

final class VendorAiCoverMediaService
{
    public const WIDTH = 590;
    public const HEIGHT = 300;

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function install(
        int $productId,
        string $title,
        VendorAiImageResult $image,
        string $inputHash
    ): int {
        if ($productId <= 0 || $image->bytes === '') {
            throw new RuntimeException(
                'Vendor AI cover install received invalid input.'
            );
        }

        $previousAttachmentId = max(
            0,
            (int) ($this->call)(
                'get_post_thumbnail_id',
                $productId
            )
        );
        $sourceFilename = 'wp-shop-vendor-ai-cover-source-'
            . $productId
            . '.png';
        $upload = ($this->call)(
            'wp_upload_bits',
            $sourceFilename,
            null,
            $image->bytes
        );

        if (! is_array($upload)) {
            throw new RuntimeException(
                'WordPress could not create the AI cover source file.'
            );
        }

        $uploadError = trim((string) ($upload['error'] ?? ''));

        if ($uploadError !== '') {
            throw new RuntimeException(
                'WordPress AI cover upload failed: '
                . $uploadError
            );
        }

        $sourcePath = trim((string) ($upload['file'] ?? ''));

        if ($sourcePath === '' || ! is_file($sourcePath)) {
            throw new RuntimeException(
                'AI cover source file is missing after upload.'
            );
        }

        $directory = dirname($sourcePath);
        $slug = ($this->call)(
            'sanitize_title',
            trim($title)
        );
        $slug = is_string($slug) && trim($slug) !== ''
            ? trim($slug)
            : 'vendor-product-' . $productId;
        $finalPath = $directory
            . DIRECTORY_SEPARATOR
            . $slug
            . '-wp-shop-cover-'
            . $productId
            . '.webp';
        $attachmentId = 0;

        try {
            $this->ensureImageFunctions();
            $editor = ($this->call)(
                'wp_get_image_editor',
                $sourcePath
            );

            if ((bool) ($this->call)('is_wp_error', $editor)) {
                throw new RuntimeException(
                    'WordPress image editor could not open the AI cover: '
                    . $this->errorMessage($editor)
                );
            }

            if (! is_object($editor)) {
                throw new RuntimeException(
                    'WordPress returned an invalid image editor.'
                );
            }

            $resize = $this->objectCall(
                $editor,
                'resize',
                self::WIDTH,
                self::HEIGHT,
                true
            );

            if ((bool) ($this->call)('is_wp_error', $resize)) {
                throw new RuntimeException(
                    'AI cover resize failed: '
                    . $this->errorMessage($resize)
                );
            }

            $saved = $this->objectCall(
                $editor,
                'save',
                $finalPath,
                'image/webp'
            );

            if ((bool) ($this->call)('is_wp_error', $saved)) {
                throw new RuntimeException(
                    'AI cover WebP save failed: '
                    . $this->errorMessage($saved)
                );
            }

            if (! is_array($saved)) {
                throw new RuntimeException(
                    'AI cover WebP save returned invalid data.'
                );
            }

            $savedPath = trim((string) ($saved['path'] ?? ''));

            if ($savedPath === '' || ! is_file($savedPath)) {
                throw new RuntimeException(
                    'Final 590×300 WebP cover was not created.'
                );
            }

            $attachment = ($this->call)(
                'wp_insert_attachment',
                [
                    'post_mime_type' => 'image/webp',
                    'post_title' => trim($title) . ' — WP Shop cover',
                    'post_status' => 'inherit',
                    'post_parent' => $productId,
                ],
                $savedPath,
                $productId,
                true
            );

            if ((bool) ($this->call)('is_wp_error', $attachment)) {
                throw new RuntimeException(
                    'AI cover Media Library insert failed: '
                    . $this->errorMessage($attachment)
                );
            }

            $attachmentId = (int) $attachment;

            if ($attachmentId <= 0) {
                throw new RuntimeException(
                    'AI cover Media Library returned an invalid attachment ID.'
                );
            }

            $metadata = ($this->call)(
                'wp_generate_attachment_metadata',
                $attachmentId,
                $savedPath
            );

            if (is_array($metadata)) {
                ($this->call)(
                    'wp_update_attachment_metadata',
                    $attachmentId,
                    $metadata
                );
            }

            ($this->call)(
                'update_post_meta',
                $attachmentId,
                '_wp_attachment_image_alt',
                trim($title)
            );

            $this->applyWatermark($savedPath);

            $this->writeCandidateMeta(
                $productId,
                $previousAttachmentId,
                $attachmentId,
                $inputHash
            );
        } catch (Throwable $exception) {
            if ($attachmentId > 0) {
                ($this->call)(
                    'wp_delete_attachment',
                    $attachmentId,
                    true
                );
            }

            throw $exception;
        } finally {
            @unlink($sourcePath);
        }

        return $attachmentId;
    }

    private function writeCandidateMeta(
        int $productId,
        int $previousAttachmentId,
        int $attachmentId,
        string $inputHash
    ): void {
        $values = [
            '_wp_shop_vendor_cover_generated' => '0',
            '_wp_shop_vendor_cover_locked' => '0',
            '_wp_shop_vendor_cover_source' => 'vendor_ai_cover_v1',
            '_wp_shop_vendor_cover_prompt_version' => VendorAiCoverPromptBuilder::VERSION,
            '_wp_shop_vendor_cover_generated_at' => (string) ($this->call)(
                'current_time',
                'mysql'
            ),
            '_wp_shop_vendor_cover_previous_attachment_id' => (string) $previousAttachmentId,
            '_wp_shop_vendor_cover_candidate_attachment_id' => (string) $attachmentId,
            '_wp_shop_vendor_cover_current_attachment_id' => '',
            '_wp_shop_vendor_cover_status' => 'candidate',
            '_wp_shop_vendor_cover_error' => '',
            '_wp_shop_vendor_cover_input_hash' => $inputHash,
        ];

        foreach ($values as $key => $value) {
            ($this->call)(
                'update_post_meta',
                $productId,
                $key,
                $value
            );
        }
    }

    private function applyWatermark(string $path): void
    {
        if (
            ! function_exists('imagecreatefromwebp')
            || ! function_exists('imagestring')
            || ! function_exists('imagewebp')
        ) {
            return;
        }

        $image = imagecreatefromwebp($path);

        if ($image === false) {
            return;
        }

        try {
            $label = 'wp-shop.org';
            $font = 4;
            $width = imagefontwidth($font) * strlen($label);
            $height = imagefontheight($font);
            $x = max(8, self::WIDTH - $width - 12);
            $y = max(8, self::HEIGHT - $height - 10);
            $shadow = imagecolorallocatealpha(
                $image,
                0,
                0,
                0,
                58
            );
            $white = imagecolorallocatealpha(
                $image,
                255,
                255,
                255,
                26
            );

            imagestring(
                $image,
                $font,
                $x + 1,
                $y + 1,
                $label,
                $shadow
            );
            imagestring(
                $image,
                $font,
                $x,
                $y,
                $label,
                $white
            );
            imagewebp($image, $path, 90);
        } finally {
            imagedestroy($image);
        }
    }

    private function ensureImageFunctions(): void
    {
        if (! defined('ABSPATH')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    private function objectCall(
        object $object,
        string $method,
        mixed ...$arguments
    ): mixed {
        if (! method_exists($object, $method)) {
            throw new RuntimeException(
                'WordPress image editor method is unavailable: '
                . $method
            );
        }

        return $object->{$method}(...$arguments);
    }

    private function errorMessage(mixed $error): string
    {
        if (
            is_object($error)
            && method_exists($error, 'get_error_message')
        ) {
            $message = $error->get_error_message();

            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        return 'Unknown WordPress image error.';
    }
}
