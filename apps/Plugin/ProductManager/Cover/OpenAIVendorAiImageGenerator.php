<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use Closure;
use JsonException;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Cover\Contracts\VendorAiImageGeneratorInterface;

final class OpenAIVendorAiImageGenerator implements
    VendorAiImageGeneratorInterface
{
    public const MODEL = 'gpt-image-2';

    /**
     * @param Closure(string, mixed...): mixed $call
     * @param Closure(): string $apiKey
     */
    public function __construct(
        private readonly Closure $call,
        private readonly Closure $apiKey
    ) {
    }

    public function configured(): bool
    {
        return trim(($this->apiKey)()) !== '';
    }

    public function generate(string $prompt): VendorAiImageResult
    {
        $key = trim(($this->apiKey)());

        if ($key === '') {
            throw new RuntimeException(
                'OpenAI API key is not configured.'
            );
        }

        try {
            $payload = json_encode(
                [
                    'model' => self::MODEL,
                    'prompt' => trim($prompt),
                    'size' => '1536x1024',
                    'quality' => 'medium',
                    'n' => 1,
                ],
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Unable to encode OpenAI image request.',
                0,
                $exception
            );
        }

        $response = ($this->call)(
            'wp_remote_post',
            'https://api.openai.com/v1/images/generations',
            [
                'timeout' => 180,
                'redirection' => 2,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'body' => $payload,
            ]
        );

        if (
            (bool) ($this->call)(
                'is_wp_error',
                $response
            )
        ) {
            throw new RuntimeException(
                'OpenAI image request failed: '
                . $this->errorMessage($response)
            );
        }

        $status = (int) ($this->call)(
            'wp_remote_retrieve_response_code',
            $response
        );
        $body = (string) ($this->call)(
            'wp_remote_retrieve_body',
            $response
        );

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                'OpenAI image request returned HTTP '
                . $status
                . ': '
                . $this->apiError($body)
            );
        }

        try {
            $decoded = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'OpenAI image response is not valid JSON.',
                0,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'OpenAI image response has an invalid shape.'
            );
        }

        $data = $decoded['data'] ?? null;
        $first = is_array($data)
            ? ($data[0] ?? null)
            : null;

        if (! is_array($first)) {
            throw new RuntimeException(
                'OpenAI image response does not contain image data.'
            );
        }

        $encoded = $first['b64_json'] ?? null;

        if (is_string($encoded) && trim($encoded) !== '') {
            $bytes = base64_decode($encoded, true);

            if (is_string($bytes) && $bytes !== '') {
                return new VendorAiImageResult(
                    $bytes,
                    'image/png'
                );
            }
        }

        $url = $first['url'] ?? null;

        if (is_string($url) && trim($url) !== '') {
            return new VendorAiImageResult(
                $this->download(trim($url)),
                'image/png'
            );
        }

        throw new RuntimeException(
            'OpenAI image response contains neither base64 image data nor a URL.'
        );
    }

    private function download(string $url): string
    {
        $response = ($this->call)(
            'wp_remote_get',
            $url,
            [
                'timeout' => 120,
                'redirection' => 3,
            ]
        );

        if ((bool) ($this->call)('is_wp_error', $response)) {
            throw new RuntimeException(
                'Generated image download failed: '
                . $this->errorMessage($response)
            );
        }

        $status = (int) ($this->call)(
            'wp_remote_retrieve_response_code',
            $response
        );
        $body = (string) ($this->call)(
            'wp_remote_retrieve_body',
            $response
        );

        if ($status < 200 || $status >= 300 || $body === '') {
            throw new RuntimeException(
                'Generated image download returned HTTP '
                . $status
                . '.'
            );
        }

        return $body;
    }

    private function apiError(string $body): string
    {
        try {
            $decoded = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $this->shorten($body);
        }

        if (! is_array($decoded)) {
            return $this->shorten($body);
        }

        $error = $decoded['error'] ?? null;

        if (is_array($error)) {
            $message = $error['message'] ?? null;

            if (is_string($message) && trim($message) !== '') {
                return $this->shorten($message);
            }
        }

        return $this->shorten($body);
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

        return 'Unknown WordPress HTTP error.';
    }

    private function shorten(string $value): string
    {
        $value = trim(
            (string) preg_replace('/\s+/u', ' ', $value)
        );

        if (mb_strlen($value, 'UTF-8') <= 280) {
            return $value !== '' ? $value : 'Unknown API error.';
        }

        return mb_substr(
            $value,
            0,
            277,
            'UTF-8'
        ) . '...';
    }
}
