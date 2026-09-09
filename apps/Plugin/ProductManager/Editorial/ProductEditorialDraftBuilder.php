<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductEditorialDraftBuilder
{
    /** @var list<string> */
    private const STOP_TAGS = [
        'wordpress',
        'theme',
        'plugin',
        'template',
        'template kit',
        'elementor',
        'elementor pro',
        'responsive',
        'modern',
        'clean',
        'themeforest',
        'codecanyon',
        'website',
        'web',
        'design',
        'learndash',
        'learnpress',
        'lifterlms',
        'sensei',
        'tutor',
        'tutor lms',
        'loco translate',
        'rtl',
        'wpml',
        'woocommerce',
        'translation ready',
        'retina ready',
        'bootstrap',
        'gutenberg',
    ];

    /**
     * @param list<string> $sourceTags
     * @param array{ruShort?:string,ruLong?:string,enShort?:string,enLong?:string} $legacy
     * @return array{
     *   ruShort:string,
     *   ruLong:string,
     *   ruMeta:string,
     *   enShort:string,
     *   enLong:string,
     *   enMeta:string
     * }
     */
    public function build(
        string $title,
        string $developer,
        string $productType,
        array $sourceTags = [],
        string $sourceUpdateDate = '',
        array $legacy = []
    ): array {
        $title = trim($title);
        $developer = trim($developer);
        $topicCandidates = array_merge(
            $this->titleTopics($title),
            $sourceTags
        );
        $topics = $this->topics(
            $this->filterEditionTopics($title, $topicCandidates)
        );
        $ruTopics = $this->translatedTopics($topics, 'ru');
        $enTopics = $this->translatedTopics($topics, 'en');
        $ruType = $this->ruType($productType);
        $enType = $this->enType($productType);
        $ruDeveloper = $developer !== '' ? ' от ' . $developer : '';
        $enDeveloper = $developer !== '' ? ' by ' . $developer : '';
        $product = $this->productName($title);

        $ruShort = $this->standardShort(
            $product,
            $developer,
            $productType,
            $ruTopics,
            'ru'
        );
        $enShort = $this->standardShort(
            $product,
            $developer,
            $productType,
            $enTopics,
            'en'
        );

        $ruLong = $this->baseLong(
            $title,
            $developer,
            $productType,
            $ruTopics,
            $sourceTags,
            'ru'
        );
        $enLong = $this->baseLong(
            $title,
            $developer,
            $productType,
            $enTopics,
            $sourceTags,
            'en'
        );
        $ruMeta = $title . ' — ' . $ruType . $ruDeveloper;
        $enMeta = $title . ' — ' . $enType . $enDeveloper;

        if ($ruTopics !== '') {
            $ruMeta .= '. Для проектов: ' . $ruTopics;
        }

        if ($enTopics !== '') {
            $enMeta .= '. For ' . $enTopics;
        }

        $legacyRuShort = $this->normalizeRuLegacyGrammar($this->legacyText(
    (string) ($legacy['ruShort'] ?? '')
));
$legacyRuLong = $this->normalizeRuLegacyGrammar($this->legacyText(
    (string) ($legacy['ruLong'] ?? '')
));

$ruTypeSource = $legacyRuShort !== '' ? $legacyRuShort : $legacyRuLong;
if ($this->legacyProductTypeConflict($ruTypeSource, $productType, 'ru')) {
    $legacyRuShort = $this->typeSafeLegacySummary($title, $ruTypeSource, $productType, 'ru');
    $legacyRuLong = $legacyRuShort;
}

if ($legacyRuShort !== '' || $legacyRuLong !== '') {
            $ruSource = $legacyRuShort !== '' ? $legacyRuShort : $legacyRuLong;
            $ruDetails = $legacyRuLong !== '' ? $legacyRuLong : $ruSource;
            $ruShort = $ruSource;
            $ruLong = $this->legacyLong(
                $title,
                $ruSource,
                $ruDetails,
                $productType,
                $ruTopics,
                $sourceTags,
                'ru'
            );
            $ruMeta = $this->limit($ruSource);
        }

        $legacyEnShort = $this->legacyText((string) ($legacy['enShort'] ?? ''));
$legacyEnLong = $this->legacyText((string) ($legacy['enLong'] ?? ''));

$enTypeSource = $legacyEnShort !== '' ? $legacyEnShort : $legacyEnLong;
if ($this->legacyProductTypeConflict($enTypeSource, $productType, 'en')) {
    $legacyEnShort = $this->typeSafeLegacySummary($title, $enTypeSource, $productType, 'en');
    $legacyEnLong = $legacyEnShort;
}

if ($legacyEnShort !== '' || $legacyEnLong !== '') {
            $enSource = $legacyEnShort !== '' ? $legacyEnShort : $legacyEnLong;
            $enDetails = $legacyEnLong !== '' ? $legacyEnLong : $enSource;
            $enShort = $enSource;
            $enLong = $this->legacyLong(
                $title,
                $enSource,
                $enDetails,
                $productType,
                $enTopics,
                $sourceTags,
                'en'
            );
            $enMeta = $this->limit($enSource);
        }

        return [
            'ruShort' => '<p>' . $this->text($ruShort) . '</p>',
            'ruLong' => $ruLong,
            'ruMeta' => $this->meta($ruMeta),
            'enShort' => '<p>' . $this->text($enShort) . '</p>',
            'enLong' => $enLong,
            'enMeta' => $this->meta($enMeta),
        ];
    }

    /** @param list<string> $sourceTags */
    private function legacyLong(
        string $title,
        string $summary,
        string $details,
        string $productType,
        string $topics,
        array $sourceTags,
        string $language
    ): string {
        $product = $this->productName($title);
        $features = $this->legacyFeatures(
            $details,
            $this->sameText($summary, $details)
        );
        $features = $this->standardFeatureItems(
            $features,
            $productType,
            $topics,
            $sourceTags,
            $language
        );

        return '<h2>' . $this->text(
            $this->seoHeading(
                $title,
                $product,
                $productType,
                $topics,
                $summary . ' ' . $details,
                $language
            )
        ) . '</h2>'
            . '<p>' . $this->text($summary) . '</p>'
            . $this->featureSection(
                $language === 'ru'
                    ? 'Основные возможности'
                    : 'Key features',
                $features
            )
            . $this->audienceSection(
                $product,
                $summary,
                $productType,
                $topics,
                $language
            )
            . $this->compatibilitySection(
                $title,
                $details,
                $productType,
                $sourceTags,
                $language
            )
            . $this->importantSection(
                $product,
                $productType,
                $language
            );
    }


    /** @param list<string> $sourceTags */
    private function baseLong(
        string $title,
        string $developer,
        string $productType,
        string $topics,
        array $sourceTags,
        string $language
    ): string {
        $product = $this->productName($title);
        $intro = $this->baseIntro(
            $title,
            $developer,
            $productType,
            $topics,
            $language
        );
        $features = $this->standardFeatureItems(
            [],
            $productType,
            $topics,
            $sourceTags,
            $language
        );

        return '<h2>' . $this->text(
            $this->seoHeading(
                $title,
                $product,
                $productType,
                $topics,
                '',
                $language
            )
        ) . '</h2><p>'
            . $this->text($intro) . '</p>'
            . $this->featureSection(
                $language === 'ru'
                    ? 'Основные возможности'
                    : 'Key features',
                $features
            )
            . $this->audienceSection(
                $product,
                '',
                $productType,
                $topics,
                $language
            )
            . $this->compatibilitySection(
                $title,
                '',
                $productType,
                $sourceTags,
                $language
            )
            . $this->importantSection(
                $product,
                $productType,
                $language
            );
    }


    private function standardShort(
        string $product,
        string $developer,
        string $productType,
        string $topics,
        string $language
    ): string {
        $developerPart = $developer !== ''
            ? ($language === 'ru' ? ' от ' : ' by ') . $developer
            : '';

        if ($language === 'ru') {
            $purpose = $topics !== ''
                ? ' для проектов в сфере ' . $topics
                : '';

            return match ($productType) {
                CatalogProductType::TEMPLATE_KIT =>
                    $product . ' — набор шаблонов Elementor'
                    . $developerPart . $purpose
                    . '. Помогает быстрее собрать основные страницы сайта '
                    . 'на готовой визуальной основе.',
                CatalogProductType::PLUGIN =>
                    $product . ' — плагин WordPress'
                    . $developerPart . $purpose
                    . '. Расширяет сайт специализированными функциями '
                    . 'в рамках задач продукта.',
                default =>
                    $product . ' — тема WordPress'
                    . $developerPart . $purpose
                    . '. Предоставляет готовую основу дизайна и структуры сайта.',
            };
        }

        $purpose = $topics !== ''
            ? ' for projects focused on ' . $topics
            : '';

        return match ($productType) {
            CatalogProductType::TEMPLATE_KIT =>
                $product . ' is an Elementor template kit'
                . $developerPart . $purpose
                . '. It provides a ready visual foundation for building '
                . 'the main website pages faster.',
            CatalogProductType::PLUGIN =>
                $product . ' is a WordPress plugin'
                . $developerPart . $purpose
                . '. It adds focused functionality for the product use case.',
            default =>
                $product . ' is a WordPress theme'
                . $developerPart . $purpose
                . '. It provides a ready design and site-structure foundation.',
        };
    }

    /**
     * @param list<string> $existing
     * @param list<string> $sourceTags
     * @return list<string>
     */
    private function standardFeatureItems(
        array $existing,
        string $productType,
        string $topics,
        array $sourceTags,
        string $language
    ): array {
        $features = $existing;

        if (
            $productType === CatalogProductType::TEMPLATE_KIT
            || $this->hasAnyTag($sourceTags, ['elementor', 'elementor pro'])
        ) {
            $features[] = $language === 'ru'
                ? 'Визуальная настройка страниц с помощью Elementor'
                : 'Visual page editing with Elementor';
        }

        if ($this->hasAnyTag(
            $sourceTags,
            ['responsive', 'responsive design', 'responsive layout']
        )) {
            $features[] = $language === 'ru'
                ? 'Адаптивная компоновка для компьютеров, планшетов и смартфонов'
                : 'Responsive layouts for desktop, tablet and mobile devices';
        }

        if ($this->hasAnyTag($sourceTags, ['woocommerce', 'ecommerce'])) {
            $features[] = $language === 'ru'
                ? 'Поддержка коммерческих сценариев и интернет-магазинов'
                : 'Support for commerce workflows and online stores';
        }

        if ($features === []) {
            if ($language === 'ru') {
                $features[] = match ($productType) {
                    CatalogProductType::TEMPLATE_KIT =>
                        'Готовые шаблоны страниц и секций для ускорения сборки сайта',
                    CatalogProductType::PLUGIN =>
                        'Расширение стандартных возможностей WordPress '
                        . 'специализированными функциями продукта',
                    default =>
                        'Готовая визуальная основа и структура для WordPress-сайта',
                };
            } else {
                $features[] = match ($productType) {
                    CatalogProductType::TEMPLATE_KIT =>
                        'Ready page and section templates for faster site building',
                    CatalogProductType::PLUGIN =>
                        'Focused functionality that extends standard WordPress capabilities',
                    default =>
                        'A ready visual and structural foundation for a WordPress site',
                };
            }
        }

        if ($topics !== '' && count($features) < 2) {
            $features[] = $language === 'ru'
                ? 'Структура и оформление ориентированы на проекты в сфере '
                    . $topics
                : 'The structure and presentation are oriented toward '
                    . $topics . ' projects';
        }

        return array_values(array_unique(array_slice($features, 0, 12)));
    }

    /**
     * @param list<string> $sourceTags
     */
    private function compatibilitySection(
        string $title,
        string $details,
        string $productType,
        array $sourceTags,
        string $language
    ): string {
        unset($details);

        $items = [];
        $items[] = $language === 'ru'
            ? 'WordPress'
            : 'WordPress';

        if (
            $productType === CatalogProductType::TEMPLATE_KIT
            || $this->hasAnyTag($sourceTags, ['elementor', 'elementor pro'])
            || $this->matches($title, '/\belementor\b/ui')
        ) {
            $items[] = 'Elementor';
        }

        if (
            $this->hasAnyTag($sourceTags, ['elementor pro'])
            || $this->matches($title, '/\belementor\s+pro\b/ui')
        ) {
            $items[] = $language === 'ru'
                ? 'Для заявленных Pro-виджетов требуется Elementor Pro'
                : 'Elementor Pro is required for the stated Pro widgets';
        }

        if ($this->hasAnyTag($sourceTags, ['woocommerce', 'ecommerce'])) {
            $items[] = 'WooCommerce';
        }

        $lms = $this->selectedTags(
            $sourceTags,
            ['learnpress', 'learndash', 'lifterlms', 'sensei', 'tutor', 'tutor lms']
        );
        foreach ($lms as $item) {
            $items[] = $item;
        }

        $languageTags = $this->selectedTags(
            $sourceTags,
            ['wpml', 'rtl', 'loco translate', 'translation ready']
        );
        foreach ($languageTags as $item) {
            $items[] = $item;
        }

        return $this->listSection(
            $language === 'ru'
                ? 'Совместимость и требования'
                : 'Compatibility and requirements',
            array_values(array_unique($items))
        );
    }

    private function importantSection(
        string $product,
        string $productType,
        string $language
    ): string {
        if ($language === 'ru') {
            $text = match ($productType) {
                CatalogProductType::TEMPLATE_KIT =>
                    $product . ' — это набор шаблонов Elementor, '
                    . 'а не самостоятельная WordPress-тема. Шаблоны импортируются '
                    . 'в существующий сайт и используются для создания страниц и секций.',
                CatalogProductType::PLUGIN =>
                    $product . ' устанавливается как отдельный плагин WordPress. '
                    . 'Для интеграций могут потребоваться соответствующие базовые '
                    . 'плагины, если они указаны в совместимости продукта.',
                default =>
                    $product . ' — полноценная WordPress-тема. После установки '
                    . 'могут использоваться дополнительные плагины, если они '
                    . 'предусмотрены разработчиком.',
            };

            return $this->simpleSection('Что важно знать', $text);
        }

        $text = match ($productType) {
            CatalogProductType::TEMPLATE_KIT =>
                $product . ' is an Elementor template kit, not a standalone '
                . 'WordPress theme. The templates are imported into an existing '
                . 'site and used to build pages and sections.',
            CatalogProductType::PLUGIN =>
                $product . ' is installed as a separate WordPress plugin. '
                . 'Some integrations may require their corresponding base plugins '
                . 'when listed in the product compatibility information.',
            default =>
                $product . ' is a complete WordPress theme. Additional plugins '
                . 'may be used when they are provided or recommended by the developer.',
        };

        return $this->simpleSection('What to know', $text);
    }

    /** @param list<string> $items */
    private function listSection(string $heading, array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $item = rtrim(trim($item), " .;,:!?");

            if ($item === '') {
                continue;
            }

            $html .= '<li>' . $this->text($item) . '.</li>';
        }

        if ($html === '') {
            return '';
        }

        return '<h3>' . $this->text($heading) . '</h3><ul>'
            . $html . '</ul>';
    }

    private function seoHeading(
        string $title,
        string $product,
        string $productType,
        string $topics,
        string $content,
        string $language
    ): string {
        $education = $this->isEducation($content, $topics);

        if ($language === 'ru') {
            if ($education && $productType === CatalogProductType::THEME) {
                return $product . ' — WordPress-тема для онлайн-обучения и LMS';
            }

            if ($education && $productType === CatalogProductType::PLUGIN) {
                return $product . ' — WordPress-плагин для онлайн-обучения и LMS';
            }

            if ($education && $productType === CatalogProductType::TEMPLATE_KIT) {
                return $product . ' — шаблоны Elementor для образовательных сайтов';
            }

            return $title;
        }

        if ($education && $productType === CatalogProductType::THEME) {
            return $product . ' — WordPress Theme for Education and LMS';
        }

        if ($education && $productType === CatalogProductType::PLUGIN) {
            return $product . ' — WordPress Plugin for Education and LMS';
        }

        if ($education && $productType === CatalogProductType::TEMPLATE_KIT) {
            return $product . ' — Elementor Template Kit for Education Websites';
        }

        return $title;
    }

    private function baseIntro(
        string $title,
        string $developer,
        string $productType,
        string $topics,
        string $language
    ): string {
        if ($language === 'ru') {
            $intro = $title . ' — ' . $this->ruType($productType) . '.';

            if ($developer !== '') {
                $intro .= ' Разработчик — ' . $developer . '.';
            }

            if ($topics !== '') {
                $intro .= ' Подходит для проектов, ориентированных на ' . $topics . '.';
            }

            return $intro;
        }

        $intro = $title . ' is a ' . $this->enType($productType) . '.';

        if ($developer !== '') {
            $intro .= ' The developer is ' . $developer . '.';
        }

        if ($topics !== '') {
            $intro .= ' Suitable for projects focused on ' . $topics . '.';
        }

        return $intro;
    }

    private function audienceSection(
        string $product,
        string $summary,
        string $productType,
        string $topics,
        string $language
    ): string {
        if ($language === 'ru') {
            $audience = $this->audienceFromRuSummary($summary);
            $text = $audience !== ''
                ? $product . ' подходит для ' . $audience
                    . '. Перед выбором стоит сопоставить необходимые функции '
                    . 'проекта с возможностями текущей версии продукта.'
                : $this->ruAudienceFallback($product, $productType, $topics);

            return $this->simpleSection(
                'Кому подходит',
                $text
            );
        }

        $audience = $this->audienceFromEnSummary($summary);
        $text = $audience !== ''
            ? $product . ' is suitable for ' . $audience
                . '. Before choosing it, compare the project requirements with '
                . 'the capabilities of the current product version.'
            : $this->enAudienceFallback($product, $productType, $topics);

        return $this->simpleSection(
            'Who it is for',
            $text
        );
    }

    private function ruAudienceFallback(
        string $product,
        string $productType,
        string $topics
    ): string {
        if ($this->matches($topics, '/(?:образован|обучен|lms|школ|университет)/ui')) {
            return $product
                . ' подойдёт школам, университетам, учебным центрам, '
                . 'преподавателям, тренерам и образовательным компаниям, которым '
                . 'нужен готовый WordPress-проект для онлайн-обучения.';
        }

        if ($topics !== '') {
            return $product . ' подходит для проектов в сфере '
                . $topics . '.';
        }

        return match ($productType) {
            CatalogProductType::TEMPLATE_KIT =>
                $product . ' подходит компаниям, агентствам и специалистам, '
                . 'которым нужен готовый набор шаблонов Elementor для нового сайта.',
            CatalogProductType::PLUGIN =>
                $product . ' подходит владельцам сайтов и разработчикам, '
                . 'которым нужны специализированные функции WordPress.',
            default =>
                $product . ' подходит владельцам сайтов, агентствам и разработчикам, '
                . 'которым нужна готовая визуальная основа WordPress-сайта.',
        };
    }

    private function enAudienceFallback(
        string $product,
        string $productType,
        string $topics
    ): string {
        if ($this->matches($topics, '/(?:education|learning|lms|school|universit)/ui')) {
            return $product
                . ' is suitable for schools, universities, training centers, '
                . 'instructors, coaches and education businesses that need a '
                . 'WordPress foundation for online learning.';
        }

        if ($topics !== '') {
            return $product . ' is suitable for projects focused on '
                . $topics . '.';
        }

        return match ($productType) {
            CatalogProductType::TEMPLATE_KIT =>
                $product . ' is suitable for companies, agencies and specialists '
                . 'that need a ready Elementor template kit for a new website.',
            CatalogProductType::PLUGIN =>
                $product . ' is suitable for site owners and developers '
                . 'who need focused WordPress functionality.',
            default =>
                $product . ' is suitable for site owners, agencies and developers '
                . 'who need a ready visual foundation for a WordPress website.',
        };
    }

    private function simpleSection(string $heading, string $text): string
    {
        return '<h3>' . $this->text($heading) . '</h3><p>'
            . $this->text($text) . '</p>';
    }

    private function ruType(string $productType): string
    {
        return match ($productType) {
            CatalogProductType::PLUGIN => 'плагин WordPress',
            CatalogProductType::TEMPLATE_KIT => 'набор шаблонов Elementor',
            default => 'тема WordPress',
        };
    }

    private function enType(string $productType): string
    {
        return match ($productType) {
            CatalogProductType::PLUGIN => 'WordPress plugin',
            CatalogProductType::TEMPLATE_KIT => 'Elementor template kit',
            default => 'WordPress theme',
        };
    }

    private function isEducation(string $content, string $topics): bool
    {
        return $this->matches(
            $content . ' ' . $topics,
            '/(?:образован|обучен|lms|курс|школ|университет|education|learning|course|school|university)/ui'
        );
    }

    /**
     * @param list<string> $topics
     * @return list<string>
     */
    private function filterEditionTopics(string $title, array $topics): array
    {
        $rules = [
            'business' => '/\bbusiness\s*[–—-]\s*/ui',
            'premium' => '/\bpremium\s*[–—-]\s*/ui',
            'pro' => '/\bpro\s*[–—-]\s*/ui',
        ];
        $editionTopics = [];
        foreach ($rules as $topic => $pattern) {
            if ($this->matches($title, $pattern)) {
                $editionTopics[] = $topic;
            }
        }
        if ($editionTopics === []) {
            return $topics;
        }
        return array_values(array_filter(
            $topics,
            static fn (string $topic): bool => ! in_array(
                strtolower(trim($topic)),
                $editionTopics,
                true
            )
        ));
    }

    /** @return list<string> */
    private function titleTopics(string $title): array
    {
        $title = strtolower($title);
        $rules = [
            'education' => ['education'],
            'school' => ['school'],
            'university' => ['university'],
            ' lms' => ['lms'],
            'hotel' => ['hotel'],
            'resort' => ['resort'],
            'villa' => ['villa'],
            'travel' => ['travel'],
            'tourism' => ['tourism'],
            'booking' => ['booking'],
            'business' => preg_match('/\bbusiness\s*[–—-]\s*/u', $title) === 1 ? [] : ['business'],
            'corporate' => ['corporate'],
            'consulting' => ['consulting'],
            'agency' => ['agency'],
            'marketing' => ['marketing'],
            'ecommerce' => ['ecommerce'],
            'e-commerce' => ['ecommerce'],
            'woocommerce' => ['ecommerce'],
            ' shop' => ['shop'],
            ' store' => ['store'],
            'blog' => ['blog'],
            'portfolio' => ['portfolio'],
            'restaurant' => ['restaurant'],
            'real estate' => ['real estate'],
            'medical' => ['medical'],
            'health' => ['health'],
            'fitness' => ['fitness'],
            ' gym' => ['gym'],
            'construction' => ['construction'],
            'renovation' => ['renovation'],
            'remodeling' => ['remodeling'],
            'remodelling' => ['remodeling'],
            'finance' => ['finance'],
            'technology' => ['technology'],
            ' saas' => ['saas'],
        ];
        $topics = [];

        foreach ($rules as $needle => $values) {
            if (! str_contains($title, $needle)) {
                continue;
            }

            foreach ($values as $value) {
                $topics[] = $value;
            }
        }

        return array_values(array_unique($topics));
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private function topics(array $tags): array
    {
        $result = [];

        foreach ($tags as $tag) {
            $tag = strtolower(trim((string) preg_replace('/\s+/u', ' ', $tag)));

            if (
                $tag === ''
                || in_array($tag, self::STOP_TAGS, true)
                || strlen($tag) > 40
            ) {
                continue;
            }

            $result[] = $tag;
        }

        return array_values(array_unique(array_slice($result, 0, 6)));
    }

    /** @param list<string> $topics */
    private function translatedTopics(array $topics, string $language): string
    {
        $ruMap = [
            'hotel' => 'отели',
            'hotels' => 'отели',
            'resort' => 'курорты',
            'villa' => 'виллы',
            'travel' => 'путешествия',
            'tourism' => 'туризм',
            'booking' => 'бронирование',
            'business' => 'бизнес',
            'corporate' => 'корпоративные сайты',
            'consulting' => 'консалтинг',
            'agency' => 'агентства',
            'marketing' => 'маркетинг',
            'education' => ['образование', 'онлайн-обучение'],
            'school' => 'школы',
            'university' => 'университеты',
            'lms' => 'LMS',
            'ecommerce' => 'интернет-магазины',
            'shop' => 'магазины',
            'store' => 'магазины',
            'blog' => 'блоги',
            'portfolio' => 'портфолио',
            'restaurant' => 'рестораны',
            'real estate' => 'недвижимость',
            'medical' => 'медицина',
            'health' => 'здоровье',
            'fitness' => 'фитнес',
            'gym' => 'спортзалы',
            'construction' => 'строительство',
            'renovation' => 'ремонт и реконструкция',
            'remodeling' => 'ремоделирование',
            'finance' => 'финансы',
            'technology' => 'технологии',
            'saas' => 'SaaS',
        ];
        $enMap = [
            'hotel' => 'hotels',
            'hotels' => 'hotels',
            'resort' => 'resorts',
            'villa' => 'villas',
            'travel' => 'travel',
            'tourism' => 'tourism',
            'booking' => 'booking',
            'business' => 'business',
            'corporate' => 'corporate websites',
            'consulting' => 'consulting',
            'agency' => 'agencies',
            'marketing' => 'marketing',
            'education' => ['education', 'online learning'],
            'school' => 'schools',
            'university' => 'universities',
            'lms' => 'LMS',
            'ecommerce' => 'e-commerce',
            'shop' => 'online stores',
            'store' => 'online stores',
            'blog' => 'blogs',
            'portfolio' => 'portfolios',
            'restaurant' => 'restaurants',
            'real estate' => 'real estate',
            'medical' => 'medical websites',
            'health' => 'healthcare',
            'fitness' => 'fitness',
            'gym' => 'gyms',
            'construction' => 'construction',
            'renovation' => 'home renovation',
            'remodeling' => 'remodeling',
            'finance' => 'finance',
            'technology' => 'technology',
            'saas' => 'SaaS',
        ];
        $map = $language === 'ru' ? $ruMap : $enMap;
        $translated = [];

        foreach ($topics as $topic) {
            if (! isset($map[$topic])) {
                continue;
            }

            foreach ((array) $map[$topic] as $value) {
                $translated[] = $value;
            }
        }

        return $this->humanList(
            array_values(array_unique($translated)),
            $language
        );
    }

    /** @param list<string> $values */
    private function humanList(array $values, string $language): string
    {
        $values = array_values(array_filter(
            $values,
            static fn (string $value): bool => trim($value) !== ''
        ));

        if ($values === []) {
            return '';
        }

        if (count($values) === 1) {
            return $values[0];
        }

        $last = array_pop($values);
        $joiner = $language === 'ru' ? ' и ' : ' and ';

        return implode(', ', $values) . $joiner . $last;
    }

    private function normalizeRuLegacyGrammar(string $value): string
    {
        $normalized = preg_replace(
            '/\bс\s+помощью\s+расширенн(?:ой|ых)\s+структурированн(?:ой|ых)\s+данных\b/ui',
            'с помощью расширенных структурированных данных',
            $value
        );
        return is_string($normalized) ? $normalized : $value;
    }

    private function legacyProductTypeConflict(string $value, string $productType, string $language): bool
    {
        if (trim($value) === '') {
            return false;
        }
        $head = mb_substr($value, 0, 220, 'UTF-8');
        if ($language === 'ru') {
            $hasTheme = $this->matches($head, '/\b(?:тема|шаблон)\b/ui');
            $hasPlugin = $this->matches($head, '/\b(?:плагин|расширение)\b/ui');
        } else {
            $hasTheme = $this->matches($head, '/\btheme\b/ui');
            $hasPlugin = $this->matches($head, '/\b(?:plugin|add-on|addon|extension)\b/ui');
        }
        if ($productType === CatalogProductType::PLUGIN) {
            return $hasTheme && ! $hasPlugin;
        }
        if ($productType === CatalogProductType::THEME) {
            return $hasPlugin && ! $hasTheme;
        }
        return false;
    }

    private function typeSafeLegacySummary(string $title, string $value, string $productType, string $language): string
    {
        $product = $this->productName($title);
        $tail = trim($value);
        if ($productType === CatalogProductType::PLUGIN) {
            if ($language === 'ru') {
                $tail = (string) preg_replace('/^.*?\b(?:тема|шаблон)\s*(?:WordPress)?\b/ui', '', $tail, 1);
                $base = preg_match('/^(.+?)\s+Premium$/ui', $product, $match) === 1
                    ? trim((string) $match[1])
                    : '';
                $type = $base !== '' ? 'премиум-плагин для ' . $base : 'плагин WordPress';
            } else {
                $tail = (string) preg_replace('/^.*?\btheme\b/ui', '', $tail, 1);
                $base = preg_match('/^(.+?)\s+Premium$/ui', $product, $match) === 1
                    ? trim((string) $match[1])
                    : '';
                $type = $base !== '' ? 'premium plugin for ' . $base : 'WordPress plugin';
            }
        } else {
            if ($language === 'ru') {
                $tail = (string) preg_replace('/^.*?\b(?:плагин|расширение)\s*(?:WordPress)?\b/ui', '', $tail, 1);
                $type = 'тема WordPress';
            } else {
                $tail = (string) preg_replace('/^.*?\b(?:plugin|add-on|addon|extension)\b/ui', '', $tail, 1);
                $type = 'WordPress theme';
            }
        }
        $tail = ltrim(trim($tail), "—-:,. ");
        return $product . ' — ' . $type . ($tail !== '' ? ' ' . $tail : '.');
    }

    /** @return list<string> */
    private function legacyFeatures(string $value, bool $skipFirstSentence): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($value)) ?: [];

        if ($skipFirstSentence && count($sentences) > 1) {
            array_shift($sentences);
        }

        $features = [];

        foreach ($sentences as $sentence) {
            $sentence = trim((string) $sentence, " \t\n\r\0\x0B.!?");

            if ($this->isAudienceSentence($sentence)) {
                continue;
            }

            $explicitList = $this->matches(
                $sentence,
                '/^(?:включает|включены|созда[её]т|добавляет|поддерживает|предлагает|содержит|'
                    . 'интеграция\s+с|совместимость\s+с|поддержка(?:\s+для)?|'
                    . 'includes|creates|generates|adds|supports|offers|contains|features|'
                    . 'integration\s+with|compatibility\s+with|support\s+for)\b/ui'
            );
            $denseCommaList = substr_count($sentence, ',') >= 3
                && ! $this->matches(
                    $sentence,
                    '/\b(?:является|представляет|позволяет|помогает|подходит|ориентирован|'
                        . 'is|allows|helps|suitable|designed)\b/ui'
                );
            $separator = ($explicitList || $denseCommaList)
                ? '/\s*[,;]\s*/u'
                : '/\s*;\s*/u';
            $parts = preg_split($separator, $sentence) ?: [];

            if (count($parts) < 2) {
                continue;
            }

            foreach ($parts as $part) {
                $part = trim((string) $part, " \t\n\r\0\x0B.!?");

                if (
                    $part !== ''
                    && $this->textLength($part) >= 3
                    && ! $this->isFeatureFragment($part)
                ) {
                    $features[] = $part;
                }
            }
        }

        return array_values(array_unique(array_slice($features, 0, 12)));
    }

    private function isAudienceSentence(string $sentence): bool
    {
        return $this->matches(
            trim($sentence),
            '/^(?:(?:идеально\s+)?подходит|подойд[её]т|предназначен(?:а|о|ы)?)\s+для\b'
                . '|^(?:ideal|suitable|designed)\s+for\b/ui'
        );
    }

    private function isFeatureFragment(string $part): bool
    {
        $part = trim($part);

        return $this->matches(
            $part,
            '/^(?:котор(?:ый|ая|ое|ые)|что|включая|с\s+|где|при\s+этом|поэтому)\b'
                . '|^(?:which|that|including|with|where|while|therefore|so\s+that)\b/ui'
        ) || $this->matches(
            $part,
            '/(?:—|-)\s*это\s+.*\b(?:плагин|тема|шаблон)\b'
                . '|\bis\s+(?:a|an)\s+.*\b(?:plugin|theme|template(?:\s+kit)?)\b/ui'
        );
    }

    /** @param list<string> $features */
    private function featureSection(string $heading, array $features): string
    {
        if ($features === []) {
            return '';
        }

        $items = '';

        foreach ($features as $feature) {
            $feature = rtrim($feature, " .;,:!?");
            $items .= '<li>' . $this->text($feature) . '.</li>';
        }

        return '<h3>' . $this->text($heading) . '</h3><ul>' . $items . '</ul>';
    }

    /**
     * @param list<string> $tags
     * @param list<string> $needles
     */
    private function hasAnyTag(array $tags, array $needles): bool
    {
        return $this->selectedTags($tags, $needles) !== [];
    }

    /**
     * @param list<string> $tags
     * @param list<string> $needles
     * @return list<string>
     */
    private function selectedTags(array $tags, array $needles): array
    {
        $normalizedNeedles = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            $needles
        );
        $result = [];

        foreach ($tags as $tag) {
            $normalized = strtolower(trim((string) $tag));

            if (in_array($normalized, $normalizedNeedles, true)) {
                $result[] = trim((string) $tag);
            }
        }

        return array_values(array_unique($result));
    }

    private function audienceFromRuSummary(string $summary): string
    {
        $explicit = [
            '/\b(?:идеал(?:ен|ьна|ьно|ьны)|подходит|подойд[её]т|предназначен(?:а|о|ы)?|ориентирован(?:а|о|ы)?)\s+для\s+([^.!?]+)/ui',
        ];

        foreach ($explicit as $pattern) {
            if (preg_match($pattern, $summary, $matches) !== 1) {
                continue;
            }

            $candidate = trim((string) $matches[1]);
            if ($this->isRuAudienceCandidate($candidate)) {
                return $candidate;
            }
        }

        if (preg_match_all('/\bдля\s+([^.!?]+)/ui', $summary, $matches) !== false) {
            foreach ($matches[1] as $match) {
                $candidate = trim((string) $match);
                if ($this->isRuAudienceCandidate($candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function audienceFromEnSummary(string $summary): string
    {
        $explicit = [
            '/\b(?:ideal|suitable|designed|built|intended|best\s+suited)\s+for\s+([^.!?]+)/ui',
        ];

        foreach ($explicit as $pattern) {
            if (preg_match($pattern, $summary, $matches) !== 1) {
                continue;
            }

            $candidate = trim((string) $matches[1]);
            if ($this->isEnAudienceCandidate($candidate)) {
                return $candidate;
            }
        }

        if (preg_match_all('/\bfor\s+([^.!?]+)/ui', $summary, $matches) !== false) {
            foreach ($matches[1] as $match) {
                $candidate = trim((string) $match);
                if ($this->isEnAudienceCandidate($candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function isRuAudienceCandidate(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        if ($this->matches(
            $candidate,
            '/^(?:wordpress|woocommerce|elementor|wpml|rtl)\b'
                . '|^(?:создани|оптимизац|продвижени|сброс|настройк|управлен|защит|перевод|интеграц|работ|улучшени|добавлен|отображен|построен|восстановлен|сканирован|автоматизац)[а-яё]*\b/ui'
        )) {
            return false;
        }

        return $this->matches(
            $candidate,
            '/(?:пользовател|разработчик|тестировщик|дизайнер|маркетолог|агентств|компан|команд|бизнес|бренд|магазин|салон|спа|spa|ресторан|кафе|отел|гостиниц|школ|университет|преподавател|тренер|студи|фриланс|блогер|фотограф|клиент)/ui'
        );
    }

    private function isEnAudienceCandidate(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        if ($this->matches(
            $candidate,
            '/^(?:wordpress|woocommerce|elementor|wpml|rtl)\b'
                . '|^(?:creating|building|optimizing|optimization|promoting|promotion|resetting|reset|configuring|configuration|managing|management|protecting|protection|translating|translation|integrating|integration|improving|adding|displaying|restoring|scanning|automation)\b/ui'
        )) {
            return false;
        }

        return $this->matches(
            $candidate,
            '/(?:users?|developers?|testers?|designers?|marketers?|agencies|companies|teams|business(?:es)?|brands?|stores?|shops?|salons?|spas?|restaurants?|cafes?|hotels?|schools?|universities|instructors?|coaches|studios?|freelancers?|bloggers?|photographers?|clients?)/ui'
        );
    }

    private function productName(string $title): string
    {
        $parts = preg_split('/\s+[–—-]\s+/u', $title, 2) ?: [];
        $name = trim((string) ($parts[0] ?? $title));

        return $name !== '' ? $name : $title;
    }

    private function matches(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    private function sameText(string $left, string $right): bool
    {
        $normalize = static fn (string $value): string => trim(
            (string) preg_replace('/\s+/u', ' ', $value)
        );

        return $normalize($left) === $normalize($right);
    }

    private function textLength(string $value): int
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($chars) ? count($chars) : strlen($value);
    }

    private function legacyText(string $value): string
    {
        $value = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function meta(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $value = rtrim($value, " .;,:!?");
        $limited = $this->limit($value);

        if ($limited === '' || str_ends_with($limited, '…')) {
            return $limited;
        }

        return rtrim($limited, " .;,:!?") . '.';
    }

    private function limit(string $value, int $max = 155): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($chars) || count($chars) <= $max) {
            return $value;
        }

        $prefix = implode('', array_slice($chars, 0, $max - 1));
        $space = strrpos($prefix, ' ');

        if ($space !== false && $space > (int) ($max * 0.65)) {
            $prefix = substr($prefix, 0, $space);
        }

        return rtrim($prefix, " ,.;:-") . '…';
    }

    private function text(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
