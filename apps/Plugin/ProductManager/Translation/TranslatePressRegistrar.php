<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;

final class TranslatePressRegistrar implements
    TranslationRegistrarInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function registerPage(string $slug): string
    {
        $enUrl = (string) ($this->call)(
            'home_url',
            '/en/product/' . $slug . '/'
        );
        $requestUrl = (string) ($this->call)(
            'add_query_arg',
            [
                'wp_shop_pm_v14_register' =>
                    time() . '-'
                    . (int) ($this->call)(
                        'wp_rand',
                        1000,
                        9999
                    ),
            ],
            $enUrl
        );
        $response = ($this->call)(
            'wp_remote_get',
            $requestUrl,
            [
                'timeout' => 30,
                'redirection' => 3,
                'headers' => [
                    'User-Agent' =>
                        'WP-Shop-Builder/ProductManager-1.4.2',
                    'Cache-Control' =>
                        'no-cache, no-store, max-age=0',
                    'Pragma' => 'no-cache',
                ],
            ]
        );

        if (($this->call)('is_wp_error', $response)) {
            throw new RuntimeException(
                'EN_REQUEST_ERROR: '
                . $this->errorMessage($response)
            );
        }

        $code = (int) ($this->call)(
            'wp_remote_retrieve_response_code',
            $response
        );

        if ($code < 200 || $code >= 400) {
            throw new RuntimeException(
                'EN_HTTP_' . $code
            );
        }

        return 'EN_HTTP_' . $code;
    }

    public function registerMissing(
        TranslationDictionaryStatus $status
    ): array {
        $available = (bool) ($this->call)(
            'function_exists',
            'trp_translate'
        );

        if (! $available) {
            return [
                'TRP_TRANSLATE_FUNCTION = NOT_AVAILABLE',
            ];
        }

        $attempted = 0;

        foreach ($status->items as $item) {
            if ($item['action'] !== 'MISSING') {
                continue;
            }

            $source = $item['source'];

            if ($source === '') {
                continue;
            }

            ($this->call)(
                'trp_translate',
                $source,
                'en_US',
                true
            );
            $attempted++;
        }

        return [
            'TRP_REGISTER_ATTEMPTED = ' . $attempted,
        ];
    }

    public function missingDebugLines(
        TranslationDictionaryStatus $status
    ): array {
        $logs = [];

        foreach ($status->items as $item) {
            if ($item['action'] !== 'MISSING') {
                continue;
            }

            $logs[] = 'MISSING RU: ' . $item['source'];
            $logs[] = 'PREPARED EN: ' . $item['target'];
        }

        return $logs;
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
