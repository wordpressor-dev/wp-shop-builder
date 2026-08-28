<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;
use WPShop\App\Plugin\ProductManager\Translation\TranslationDictionaryStatus;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;

final class TranslatePressProductTranslatorTest extends TestCase
{
    public function testRequiresPublishedProductBeforeTranslation(): void
    {
        $dictionary = new ProductTranslatorDictionary([]);
        $registrar = new ProductTranslatorRegistrar();
        $translator = new TranslatePressProductTranslator(
            new TranslationMapBuilder(),
            $dictionary,
            $registrar,
            static function (string $name): mixed {
                if ($name === 'get_post') {
                    return (object) [
                        'ID' => 5028,
                        'post_type' => 'product',
                        'post_status' => 'draft',
                    ];
                }

                return null;
            }
        );

        $result = $translator->translate(
            5028,
            '<p>Short</p>',
            '<p>Long</p>',
            'Meta'
        );

        self::assertFalse($result->success);
        self::assertSame(
            ['PUBLISH_FIRST; CURRENT_STATUS_DRAFT'],
            $result->logs
        );
        self::assertSame(0, $registrar->pageCalls);
    }

    public function testRetriesRegistersMissingBacksUpAndFillsSafely(): void
    {
        $missing = new TranslationDictionaryStatus(
            true,
            3,
            2,
            0,
            0,
            1,
            [
                [
                    'source' => 'Мета',
                    'target' => 'Meta',
                    'row' => null,
                    'action' => 'MISSING',
                ],
            ]
        );
        $fillable = new TranslationDictionaryStatus(
            true,
            3,
            1,
            1,
            1,
            0,
            [
                [
                    'source' => 'Коротко',
                    'target' => 'Short',
                    'row' => [
                        'id' => 7,
                        'translated' => '',
                        'status' => 0,
                    ],
                    'action' => 'FILL',
                ],
            ]
        );
        $ready = new TranslationDictionaryStatus(
            true,
            3,
            2,
            1,
            0,
            0,
            []
        );
        $dictionary = new ProductTranslatorDictionary([
            $missing,
            $missing,
            $fillable,
            $ready,
        ]);
        $registrar = new ProductTranslatorRegistrar();
        $savedMeta = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$savedMeta): mixed {
            return match ($name) {
                'get_post' => (object) [
                    'ID' => 5028,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_name' => 'aabbe',
                    'post_excerpt' => '<p>Коротко</p>',
                    'post_content' => '<p>Длинно</p>',
                ],
                'get_post_meta' => [
                    'page_description' => 'Мета',
                ],
                'wp_kses_post' => $arguments[0],
                'sanitize_textarea_field' => $arguments[0],
                'update_post_meta' => $savedMeta[
                    (string) $arguments[1]
                ] = $arguments[2],
                default => null,
            };
        };
        $translator = new TranslatePressProductTranslator(
            new TranslationMapBuilder(),
            $dictionary,
            $registrar,
            $call(...)
        );

        $result = $translator->translate(
            5028,
            '<p>Short</p>',
            '<p>Long</p>',
            'Meta'
        );

        self::assertTrue($result->success);
        self::assertSame(2, $registrar->pageCalls);
        self::assertSame(1, $registrar->missingCalls);
        self::assertSame(1, $dictionary->backupCalls);
        self::assertSame(1, $dictionary->fillCalls);
        self::assertContains('RETRY_EN_HTTP_200', $result->logs);
        self::assertContains('TRP_REGISTER_ATTEMPTED = 1', $result->logs);
        self::assertContains('EN FILLED = 1', $result->logs);
        self::assertContains('EXACT = 2', $result->logs);
        self::assertContains('KEPT = 1', $result->logs);
        self::assertContains('OVERALL = READY', $result->logs);
        self::assertSame(
            '<p>Short</p>',
            $savedMeta['_wp_shop_en_short_description']
        );
        self::assertSame(
            '<p>Long</p>',
            $savedMeta['_wp_shop_en_long_description']
        );
        self::assertSame(
            'Meta',
            $savedMeta['_wp_shop_en_meta_description']
        );
    }

    public function testAddsTemplateKitCategoryTranslationFromProductType(): void
    {
        $dictionary = new ProductTranslatorDictionary([]);
        $registrar = new ProductTranslatorRegistrar();
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_post') {
                return (object) [
                    'ID' => 5156,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_name' => 'estateroof',
                    'post_excerpt' => '<p>Коротко</p>',
                    'post_content' => '<p>Длинно</p>',
                ];
            }

            if ($name === 'get_post_meta') {
                $key = (string) ($arguments[1] ?? '');

                return match ($key) {
                    'surerank_settings_general' => [
                        'page_description' => 'Мета',
                    ],
                    '_wp_shop_product_type' => 'template_kit',
                    default => '',
                };
            }

            return match ($name) {
                'wp_kses_post' => $arguments[0],
                'sanitize_textarea_field' => $arguments[0],
                'update_post_meta' => true,
                default => null,
            };
        };
        $translator = new TranslatePressProductTranslator(
            new TranslationMapBuilder(),
            $dictionary,
            $registrar,
            $call(...)
        );

        $result = $translator->translate(
            5156,
            '<p>Short</p>',
            '<p>Long</p>',
            'Meta'
        );

        self::assertTrue($result->success);
        self::assertSame(
            'Templates',
            $dictionary->lastMap['Шаблоны'] ?? null
        );
        self::assertContains(
            'CATALOG DISPLAY TRANSLATION = Шаблоны -> Templates',
            $result->logs
        );
        self::assertContains(
            'TRANSLATION SEGMENTS = 4',
            $result->logs
        );
    }

    public function testStopsWithoutFillWhenSourcesRemainMissing(): void
    {
        $missing = new TranslationDictionaryStatus(
            true,
            3,
            2,
            0,
            0,
            1,
            [
                [
                    'source' => 'Мета',
                    'target' => 'Meta',
                    'row' => null,
                    'action' => 'MISSING',
                ],
            ]
        );
        $dictionary = new ProductTranslatorDictionary([
            $missing,
            $missing,
            $missing,
        ]);
        $registrar = new ProductTranslatorRegistrar();
        $translator = new TranslatePressProductTranslator(
            new TranslationMapBuilder(),
            $dictionary,
            $registrar,
            $this->publishedProductCall()
        );

        $result = $translator->translate(
            5028,
            '<p>Short</p>',
            '<p>Long</p>',
            'Meta'
        );

        self::assertFalse($result->success);
        self::assertContains('REVIEW_MISSING_1', $result->logs);
        self::assertContains('MISSING RU: Мета', $result->logs);
        self::assertSame(0, $dictionary->fillCalls);
        self::assertSame(0, $dictionary->backupCalls);
    }

    private function publishedProductCall(): \Closure
    {
        return static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            return match ($name) {
                'get_post' => (object) [
                    'ID' => 5028,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_name' => 'aabbe',
                    'post_excerpt' => '<p>Коротко</p>',
                    'post_content' => '<p>Длинно</p>',
                ],
                'get_post_meta' => [
                    'page_description' => 'Мета',
                ],
                'wp_kses_post' => $arguments[0],
                'sanitize_textarea_field' => $arguments[0],
                'update_post_meta' => true,
                default => null,
            };
        };
    }
}

final class ProductTranslatorDictionary implements
    TranslationDictionaryInterface
{
    /** @var list<TranslationDictionaryStatus> */
    private array $statuses;

    /** @var array<string, string> */
    public array $lastMap = [];

    public int $backupCalls = 0;
    public int $fillCalls = 0;

    /**
     * @param list<TranslationDictionaryStatus> $statuses
     */
    public function __construct(array $statuses)
    {
        $this->statuses = $statuses;
    }

    public function status(array $map): TranslationDictionaryStatus
    {
        $this->lastMap = $map;
        $status = array_shift($this->statuses);

        if (! $status instanceof TranslationDictionaryStatus) {
            return new TranslationDictionaryStatus(
                true,
                count($map),
                count($map),
                0,
                0,
                0,
                []
            );
        }

        return $status;
    }

    public function backup(
        int $productId,
        string $slug,
        TranslationDictionaryStatus $status
    ): void {
        $this->backupCalls++;
    }

    public function fill(
        TranslationDictionaryStatus $status
    ): int {
        $this->fillCalls++;

        return $status->fill;
    }
}

final class ProductTranslatorRegistrar implements
    TranslationRegistrarInterface
{
    public int $pageCalls = 0;
    public int $missingCalls = 0;

    public function registerPage(string $slug): string
    {
        $this->pageCalls++;

        return 'EN_HTTP_200';
    }

    public function registerMissing(
        TranslationDictionaryStatus $status
    ): array {
        $this->missingCalls++;

        return [
            'TRP_REGISTER_ATTEMPTED = ' . $status->missing,
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
}
