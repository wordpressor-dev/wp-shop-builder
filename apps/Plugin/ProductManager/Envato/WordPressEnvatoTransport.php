<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

use JsonException;
use RuntimeException;

final class WordPressEnvatoTransport
{
    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function __invoke(
        string $url,
        array $headers
    ): array {
        $this->assertWordPressHttpApi();

        /** @var callable-string $remoteGet */
        $remoteGet = 'wp_remote_get';

        /** @var callable-string $isWpError */
        $isWpError = 'is_wp_error';

        /** @var callable-string $responseCode */
        $responseCode = 'wp_remote_retrieve_response_code';

        /** @var callable-string $responseBody */
        $responseBody = 'wp_remote_retrieve_body';

        /** @var callable-string $responseHeader */
        $responseHeader = 'wp_remote_retrieve_header';

        $response = $remoteGet(
            $url,
            [
                'timeout' => 30,
                'redirection' => 3,
                'headers' => $headers,
            ]
        );

        if ($isWpError($response)) {
            throw new RuntimeException(
                $this->errorMessage($response)
            );
        }

        $code = (int) $responseCode($response);

        if ($code === 429) {
            $retryAfter = (int) $responseHeader(
                $response,
                'retry-after'
            );

            if ($retryAfter > 0 && $retryAfter <= 10) {
                sleep($retryAfter);

                $response = $remoteGet(
                    $url,
                    [
                        'timeout' => 30,
                        'redirection' => 3,
                        'headers' => $headers,
                    ]
                );

                if ($isWpError($response)) {
                    throw new RuntimeException(
                        $this->errorMessage($response)
                    );
                }

                $code = (int) $responseCode($response);
            }
        }

        if ($code < 200 || $code >= 300) {
            throw new RuntimeException(
                'Envato API HTTP ' . $code . '.'
            );
        }

        $body = (string) $responseBody($response);

        try {
            $decoded = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Envato API returned invalid JSON.',
                0,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'Envato API JSON payload is not an object.'
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function assertWordPressHttpApi(): void
    {
        foreach (
            [
                'wp_remote_get',
                'is_wp_error',
                'wp_remote_retrieve_response_code',
                'wp_remote_retrieve_body',
                'wp_remote_retrieve_header',
            ] as $function
        ) {
            if (! function_exists($function)) {
                throw new RuntimeException(
                    'WordPress HTTP API is unavailable: ' . $function
                );
            }
        }
    }

    private function errorMessage(mixed $error): string
    {
        if (
            is_object($error)
            && method_exists($error, 'get_error_message')
        ) {
            $message = $error->get_error_message();

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'WordPress HTTP request failed.';
    }
}
