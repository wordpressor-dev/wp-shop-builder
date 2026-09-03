<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

use Closure;
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
        if ($this->editorialBatchRequest()) {
            throw new RuntimeException(
                'Envato live enrichment is disabled during Editorial Migration batch requests.'
            );
        }

        $remoteGet = $this->wordpressCallable(
            'wp_remote_get'
        );
        $isWpError = $this->wordpressCallable(
            'is_wp_error'
        );
        $responseCode = $this->wordpressCallable(
            'wp_remote_retrieve_response_code'
        );
        $responseBody = $this->wordpressCallable(
            'wp_remote_retrieve_body'
        );
        $responseHeader = $this->wordpressCallable(
            'wp_remote_retrieve_header'
        );

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

    private function editorialBatchRequest(): bool
    {
        $page = $_REQUEST['page'] ?? '';
        $action = $_POST['wp_shop_pm_editorial_action'] ?? '';

        if (! is_scalar($page) || ! is_scalar($action)) {
            return false;
        }

        if ((string) $page !== 'wp-shop-builder-product-editorial-migration') {
            return false;
        }

        return in_array((string) $action, [
            'prepare_en_pack',
            'import_en_pack',
            'apply_selected',
        ], true);
    }

    private function wordpressCallable(
        string $name
    ): Closure {
        if (! is_callable($name)) {
            throw new RuntimeException(
                'WordPress HTTP API is unavailable: ' . $name
            );
        }

        return Closure::fromCallable($name);
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
