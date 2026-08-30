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

            if ($rule['ru'] !== '' && $rule['en'] !== '') {
                $ruFacts[] = $rule['ru'];
                $enFacts[] = $rule['en'];
            }
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
                'signals' => ['page builder'],
                'ru' => '',
                'en' => '',
            ],
            [
                'needles' => ['woocommerce'],
                'signals' => ['ecommerce'],
                'ru' => '',
                'en' => '',
            ],
            [
                'needles' => ['wpml'],
                'signals' => ['wpml'],
                'ru' => '',
                'en' => '',
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
                'signals' => [],
                'ru' => '',
                'en' => '',
            ],
            [
                'needles' => ['responsive layout', 'responsive design', 'responsive'],
                'signals' => [],
                'ru' => '',
                'en' => '',
            ],
            [
                'needles' => ['contact form 7'],
                'signals' => [],
                'ru' => '',
                'en' => '',
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
        $skip = [
            'elementor',
            'elementor pro',
            'gutenberg',
            'contact form 7',
            'responsive',
            'responsive layout',
            'responsive design',
        ];

        foreach ($tags as $tag) {
            if (! is_scalar($tag)) {
                continue;
            }

            $value = strtolower(trim((string) $tag));

            if ($value === '' || strlen($value) > 60) {
                continue;
            }

            if ($value === 'woocommerce') {
                $result[] = 'ecommerce';

                continue;
            }

            if (in_array($value, $skip, true)) {
                continue;
            }

            $result[] = $value;
        }

        return array_values(array_unique($result));
    }
}
