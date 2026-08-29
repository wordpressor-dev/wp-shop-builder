<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

final class EnvatoOfficialFactsExtractor
{
    /**
     * @param array<string, mixed> $source
     * @return array{signals:list<string>,ruFacts:list<string>,enFacts:list<string>}
     */
    public function extract(array $source): array
    {
        $blob = strtolower(
            json_encode(
                $source,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: ''
        );
        $signals = [];
        $ruFacts = [];
        $enFacts = [];

        foreach ($this->rules() as $rule) {
            if (! $this->containsAny($blob, $rule['needles'])) {
                continue;
            }

            foreach ($rule['signals'] as $signal) {
                $signals[] = $signal;
            }

            $ruFacts[] = $rule['ru'];
            $enFacts[] = $rule['en'];
        }

        foreach ($this->sourceTags($source) as $tag) {
            $signals[] = $tag;
        }

        return [
            'signals' => array_values(array_unique($signals)),
            'ruFacts' => array_values(array_unique($ruFacts)),
            'enFacts' => array_values(array_unique($enFacts)),
        ];
    }

    /**
     * @return list<array{needles:list<string>,signals:list<string>,ru:string,en:string}>
     */
    private function rules(): array
    {
        return [
            [
                'needles' => ['learnpress'],
                'signals' => ['learnpress', 'lms'],
                'ru' => 'интеграция с LearnPress LMS',
                'en' => 'LearnPress LMS integration',
            ],
            [
                'needles' => ['learndash'],
                'signals' => ['learndash', 'lms'],
                'ru' => 'совместимость с LearnDash LMS',
                'en' => 'LearnDash LMS compatibility',
            ],
            [
                'needles' => ['tutor lms', '"tutor"'],
                'signals' => ['tutor lms', 'lms'],
                'ru' => 'совместимость с Tutor LMS',
                'en' => 'Tutor LMS compatibility',
            ],
            [
                'needles' => ['lifterlms'],
                'signals' => ['lifterlms', 'lms'],
                'ru' => 'совместимость с LifterLMS',
                'en' => 'LifterLMS compatibility',
            ],
            [
                'needles' => ['sensei lms', '"sensei"'],
                'signals' => ['sensei', 'lms'],
                'ru' => 'совместимость с Sensei LMS',
                'en' => 'Sensei LMS compatibility',
            ],
            [
                'needles' => ['elementor'],
                'signals' => ['elementor', 'page builder'],
                'ru' => 'поддержка Elementor для визуальной настройки страниц',
                'en' => 'Elementor support for visual page building',
            ],
            [
                'needles' => ['woocommerce'],
                'signals' => ['woocommerce', 'ecommerce'],
                'ru' => 'совместимость с WooCommerce',
                'en' => 'WooCommerce compatibility',
            ],
            [
                'needles' => ['wpml'],
                'signals' => ['wpml'],
                'ru' => 'поддержка WPML для многоязычных сайтов',
                'en' => 'WPML support for multilingual sites',
            ],
            [
                'needles' => ['"rtl"', 'rtl ready', 'rtl-ready'],
                'signals' => ['rtl'],
                'ru' => 'поддержка RTL-языков',
                'en' => 'RTL language support',
            ],
            [
                'needles' => ['bbpress'],
                'signals' => ['bbpress'],
                'ru' => 'совместимость с bbPress',
                'en' => 'bbPress compatibility',
            ],
            [
                'needles' => ['buddypress'],
                'signals' => ['buddypress'],
                'ru' => 'совместимость с BuddyPress',
                'en' => 'BuddyPress compatibility',
            ],
            [
                'needles' => ['gutenberg optimized', 'gutenberg_optimized'],
                'signals' => ['gutenberg'],
                'ru' => 'оптимизация для редактора Gutenberg',
                'en' => 'Gutenberg editor optimization',
            ],
            [
                'needles' => ['responsive layout', 'responsive design', 'responsive'],
                'signals' => ['responsive'],
                'ru' => 'адаптивный дизайн для разных размеров экрана',
                'en' => 'responsive design for different screen sizes',
            ],
            [
                'needles' => ['contact form 7'],
                'signals' => ['contact form 7'],
                'ru' => 'совместимость с Contact Form 7',
                'en' => 'Contact Form 7 compatibility',
            ],
        ];
    }

    /** @param list<string> $needles */
    private function containsAny(string $blob, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($blob, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     * @return list<string>
     */
    private function sourceTags(array $source): array
    {
        $tags = $source['tags'] ?? [];

        if (! is_array($tags)) {
            return [];
        }

        $result = [];

        foreach ($tags as $tag) {
            if (! is_scalar($tag)) {
                continue;
            }

            $value = strtolower(trim((string) $tag));

            if ($value !== '' && strlen($value) <= 60) {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }
}
