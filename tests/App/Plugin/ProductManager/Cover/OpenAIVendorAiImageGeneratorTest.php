<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Cover;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Cover\OpenAIVendorAiImageGenerator;

final class OpenAIVendorAiImageGeneratorTest extends TestCase
{
    public function testGeneratesImageThroughCurrentImagesEndpoint(): void
    {
        $requestUrl = '';
        $requestArgs = [];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$requestUrl, &$requestArgs): mixed {
            if ($name === 'wp_remote_post') {
                $requestUrl = (string) ($arguments[0] ?? '');
                $requestArgs = is_array($arguments[1] ?? null)
                    ? $arguments[1]
                    : [];

                return ['response' => ['code' => 200]];
            }

            if ($name === 'is_wp_error') {
                return false;
            }

            if ($name === 'wp_remote_retrieve_response_code') {
                return 200;
            }

            if ($name === 'wp_remote_retrieve_body') {
                return json_encode([
                    'data' => [
                        [
                            'b64_json' => base64_encode('PNGDATA'),
                        ],
                    ],
                ]);
            }

            return null;
        };

        $generator = new OpenAIVendorAiImageGenerator(
            $call(...),
            static fn (): string => 'sk-test'
        );

        self::assertTrue($generator->configured());

        $result = $generator->generate('Create cover');

        self::assertSame(
            'https://api.openai.com/v1/images/generations',
            $requestUrl
        );
        self::assertSame('PNGDATA', $result->bytes);

        $payload = json_decode(
            (string) ($requestArgs['body'] ?? ''),
            true
        );

        self::assertIsArray($payload);
        self::assertSame(
            'gpt-image-2',
            $payload['model'] ?? null
        );
        self::assertSame(
            '1536x1024',
            $payload['size'] ?? null
        );
        self::assertSame(
            'medium',
            $payload['quality'] ?? null
        );
    }
}
