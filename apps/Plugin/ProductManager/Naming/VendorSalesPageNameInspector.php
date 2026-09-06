<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;
use DOMDocument;

final class VendorSalesPageNameInspector
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    /**
     * @return array{status:string,h1:string,title:string}
     */
    public function inspect(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return [
                'status' => 'NO_SALES_PAGE',
                'h1' => '',
                'title' => '',
            ];
        }

        $response = ($this->call)(
            'wp_remote_get',
            $url,
            [
                'timeout' => 8,
                'redirection' => 3,
                'user-agent' => 'WP Shop Builder Vendor Naming Review',
            ]
        );

        if (
            is_object($response)
            && is_a($response, 'WP_Error')
        ) {
            return [
                'status' => 'FETCH_ERROR',
                'h1' => '',
                'title' => '',
            ];
        }

        $code = (int) ($this->call)(
            'wp_remote_retrieve_response_code',
            $response
        );

        if ($code < 200 || $code >= 400) {
            return [
                'status' => 'HTTP_' . $code,
                'h1' => '',
                'title' => '',
            ];
        }

        $body = (string) ($this->call)(
            'wp_remote_retrieve_body',
            $response
        );

        if (trim($body) === '') {
            return [
                'status' => 'EMPTY_BODY',
                'h1' => '',
                'title' => '',
            ];
        }

        [$h1, $title] = $this->extract($body);

        return [
            'status' => ($h1 !== '' || $title !== '')
                ? 'OK'
                : 'NO_NAME_EVIDENCE',
            'h1' => $h1,
            'title' => $title,
        ];
    }

    /**
     * @return array{string,string}
     */
    private function extract(string $html): array
    {
        if (! class_exists(DOMDocument::class)) {
            return ['', ''];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $h1 = '';
        $title = '';

        if ($loaded) {
            $h1Nodes = $dom->getElementsByTagName('h1');
            if ($h1Nodes->length > 0) {
                $h1 = $this->normalize(
                    (string) $h1Nodes->item(0)?->textContent
                );
            }

            $titleNodes = $dom->getElementsByTagName('title');
            if ($titleNodes->length > 0) {
                $title = $this->normalize(
                    (string) $titleNodes->item(0)?->textContent
                );
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$h1, $title];
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $normalized = preg_replace(
            '/\s+/u',
            ' ',
            trim($value)
        );

        return is_string($normalized)
            ? $normalized
            : '';
    }
}
