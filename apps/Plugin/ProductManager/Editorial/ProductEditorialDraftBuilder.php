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
        $topics = $this->topics(array_merge(
            $this->titleTopics($title),
            $sourceTags
        ));
        $ruTopics = $this->translatedTopics($topics, 'ru');
        $enTopics = $this->translatedTopics($topics, 'en');
        $ruType = $this->ruType($productType);
        $enType = $this->enType($productType);
        $ruDeveloper = $developer !== '' ? ' от ' . $developer : '';
        $enDeveloper = $developer !== '' ? ' by ' . $developer : '';

        $ruShort = $title . ' — ' . $ruType . $ruDeveloper . '.';
        $enShort = $title . ' is a ' . $enType . $enDeveloper . '.';

        if ($ruTopics !== '') {
            $ruShort .= ' Подходит для проектов в тематике: ' . $ruTopics . '.';
        }

        if ($enTopics !== '') {
            $enShort .= ' Suitable for projects focused on ' . $enTopics . '.';
        }

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

        $legacyRuShort = $this->legacyText(
            (string) ($legacy['ruShort'] ?? '')
        );
        $legacyRuLong = $this->legacyText(
            (string) ($legacy['ruLong'] ?? '')
        );

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

        $legacyEnShort = $this->legacyText(
            (string) ($legacy['enShort'] ?? '')
        );
        $legacyEnLong = $this->legacyText(
            (string) ($legacy['enLong'] ?? '')
        );

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
            'ruMeta' => $this->limit($ruMeta . '.'),
            'enShort' => '<p>' . $this->text($enShort) . '</p>',
            'enLong' => $enLong,
            'enMeta' => $this->limit($enMeta . '.'),
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
        $featureHeading = $language === 'ru'
            ? 'Основные возможности ' . $product
            : 'Key features of ' . $product;
        $featuresSection = $this->featureSection(
            $featureHeading,
            $features
        );
        $fallback = $featuresSection === ''
            && ! $this->sameText($summary, $details)
                ? $this->fallbackDetails($details, $language)
                : '';

        return '<h2>' . $this->text($title) . '</h2>'
            . '<p>' . $this->text($summary) . '</p>'
            . $this->leadParagraph(
                $product,
                $summary . ' ' . $details,
                $productType,
                $topics,
                $language
            )
            . $featuresSection
            . $fallback
            . $this->editorialSections(
                $product,
                $details,
                $productType,
                $topics,
                $sourceTags,
                $language
            )
            . $this->audienceSection(
                $product,
                $summary,
                $productType,
                $topics,
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
        $type = $language === 'ru'
            ? $this->ruType($productType)
            : $this->enType($productType);
        $intro = $this->baseIntro(
            $title,
            $developer,
            $productType,
            $topics,
            $language
        );

        return '<h2>' . $this->text($title) . '</h2><p>'
            . $this->text($intro) . '</p>'
            . $this->editorialSections(
                $product,
                '',
                $productType,
                $topics,
                $sourceTags,
                $language
            )
            . $this->audienceSection(
                $product,
                '',
                $productType,
                $topics,
                $language
            )
            . '<p>' . $this->text(
                $language === 'ru'
                    ? 'Перед публикацией рекомендуется сверить функции '
                        . $type . ' с актуальной документацией разработчика.'
                    : 'Before publishing, verify the ' . $type
                        . ' features against the current developer documentation.'
            ) . '</p>';
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
                $intro .= ' Подходит для проектов в тематике ' . $topics . '.';
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

    private function leadParagraph(
        string $product,
        string $content,
        string $productType,
        string $topics,
        string $language
    ): string {
        if ($language === 'ru') {
            if ($this->matches($content, '/(?:образован|lms|курс|школ)/ui')) {
                return '<p>' . $this->text(
                    $product
                    . ' ориентирован на образовательные проекты. '
                    . 'Карточка товара указывает на сценарии онлайн-обучения, '
                    . 'работу с учебным контентом и организацию образовательного сайта.'
                ) . '</p>';
            }

            if ($topics !== '') {
                return '<p>' . $this->text(
                    $product . ' можно использовать в проектах по тематике '
                    . $topics . '. Ниже сохранены и структурированы ключевые '
                    . 'возможности из существующего описания товара.'
                ) . '</p>';
            }

            return '<p>' . $this->text(
                $product . ' — ' . $this->ruType($productType)
                . '. Ниже собраны ключевые возможности из существующего описания.'
            ) . '</p>';
        }

        if ($this->matches($content, '/(?:education|lms|course|school)/ui')) {
            return '<p>' . $this->text(
                $product
                . ' is aimed at education projects. The product information '
                . 'points to online learning, course content and education-site workflows.'
            ) . '</p>';
        }

        if ($topics !== '') {
            return '<p>' . $this->text(
                $product . ' can be used for projects focused on '
                . $topics . '. The existing product facts are preserved and '
                . 'structured below.'
            ) . '</p>';
        }

        return '<p>' . $this->text(
            $product . ' is a ' . $this->enType($productType)
            . '. The existing product capabilities are preserved below.'
        ) . '</p>';
    }

    /** @param list<string> $sourceTags */
    private function editorialSections(
        string $product,
        string $details,
        string $productType,
        string $topics,
        array $sourceTags,
        string $language
    ): string {
        $html = '';
        $isEducation = $this->matches(
            $details . ' ' . $topics,
            '/(?:lms|курс|обучен|преподавател|education|course|learning)/ui'
        ) || $this->hasAnyTag(
            $sourceTags,
            ['learndash', 'learnpress', 'lifterlms', 'sensei', 'tutor', 'tutor lms']
        );

        if ($isEducation) {
            $html .= $this->educationSection(
                $product,
                $details,
                $language
            );
        }

        if ($this->hasAnyTag($sourceTags, ['elementor', 'elementor pro'])) {
            $html .= $this->simpleSection(
                $language === 'ru'
                    ? 'Elementor и настройка страниц'
                    : 'Elementor and page building',
                $language === 'ru'
                    ? 'Elementor указан среди связанных технологий продукта. '
                        . 'Его можно учитывать при визуальной настройке страниц '
                        . 'и подготовке структуры сайта.'
                    : 'Elementor is listed among the product-related technologies. '
                        . 'It can be considered when visually building and '
                        . 'adjusting the site page structure.'
            );
        }

        if ($this->hasAnyTag($sourceTags, ['woocommerce'])) {
            $html .= $this->simpleSection(
                $language === 'ru'
                    ? 'WooCommerce и коммерческие сценарии'
                    : 'WooCommerce and commerce workflows',
                $language === 'ru'
                    ? 'WooCommerce отмечен среди интеграций продукта. '
                        . $product . ' можно рассматривать для проектов, где '
                        . 'основной сайт дополняется возможностями интернет-магазина.'
                    : 'WooCommerce is listed among the product integrations. '
                        . $product . ' can be considered for projects that '
                        . 'combine the main site with online-store functionality.'
            );
        }

        $languageTags = $this->selectedTags(
            $sourceTags,
            ['wpml', 'rtl', 'loco translate', 'translation ready']
        );

        if ($languageTags !== []) {
            $html .= $this->simpleSection(
                $language === 'ru'
                    ? 'Многоязычные проекты'
                    : 'Multilingual projects',
                $language === 'ru'
                    ? 'В данных товара отмечены возможности, связанные с '
                        . $this->humanList($languageTags, 'ru')
                        . '. Это полезно для многоязычных сайтов и '
                        . 'международной аудитории.'
                    : 'The product data includes '
                        . $this->humanList($languageTags, 'en')
                        . '. This is relevant to multilingual sites and '
                        . 'international audiences.'
            );
        }

        if ($html === '' && $topics !== '') {
            $html .= $this->simpleSection(
                $language === 'ru' ? 'Сценарии использования' : 'Use cases',
                $language === 'ru'
                    ? $product . ' подходит для проектов в тематике '
                        . $topics . '. Конкретные сценарии зависят от '
                        . 'возможностей, описанных в карточке товара.'
                    : $product . ' is suitable for projects focused on '
                        . $topics . '. The exact workflows depend on the '
                        . 'capabilities described in the product information.'
            );
        }

        if ($html === '' && $productType === CatalogProductType::PLUGIN) {
            $html .= $this->simpleSection(
                $language === 'ru' ? 'Сценарии использования' : 'Use cases',
                $language === 'ru'
                    ? $product . ' расширяет WordPress специализированной '
                        . 'функциональностью. Практические сценарии зависят от '
                        . 'возможностей, перечисленных в описании плагина.'
                    : $product . ' extends WordPress with focused functionality. '
                        . 'Practical workflows depend on the capabilities '
                        . 'listed in the plugin description.'
            );
        }

        return $html;
    }

    private function educationSection(
        string $product,
        string $details,
        string $language
    ): string {
        $pattern = $language === 'ru'
            ? '/(?:lms|курс|расписан|преподавател|учеб)/ui'
            : '/(?:lms|course|class|schedule|instructor|learning)/ui';
        $facts = $this->matchingFacts($details, $pattern);

        if ($language === 'ru') {
            $factSentence = $facts !== []
                ? 'В существующем описании отмечены '
                    . $this->humanList($facts, 'ru') . '.'
                : 'В данных товара отмечена направленность на LMS '
                    . 'и онлайн-обучение.';

            return $this->simpleSection(
                'Онлайн-курсы и LMS',
                $factSentence . ' Это делает ' . $product
                . ' подходящей основой для образовательных сайтов, где '
                . 'важны учебный контент и связанные страницы.'
            );
        }

        $factSentence = $facts !== []
            ? 'The existing description highlights '
                . $this->humanList($facts, 'en') . '.'
            : 'The product data indicates an LMS and online-learning focus.';

        return $this->simpleSection(
            'Online courses and LMS',
            $factSentence . ' This makes ' . $product
            . ' relevant to education websites that need structured learning '
            . 'content and related pages.'
        );
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
                    . '. Перед использованием стоит сверить требования проекта '
                    . 'с актуальной документацией продукта.'
                : $this->ruAudienceFallback($product, $productType, $topics);

            return $this->simpleSection(
                'Кому подходит ' . $product . '?',
                $text
            );
        }

        $audience = $this->audienceFromEnSummary($summary);
        $text = $audience !== ''
            ? $product . ' is suitable for ' . $audience
                . '. Check the current documentation against the project '
                . 'requirements before use.'
            : $this->enAudienceFallback($product, $productType, $topics);

        return $this->simpleSection(
            'Who is ' . $product . ' for?',
            $text
        );
    }

    private function ruAudienceFallback(
        string $product,
        string $productType,
        string $topics
    ): string {
        if ($topics !== '') {
            return $product . ' можно рассматривать для проектов в тематике '
                . $topics . '. Выбор зависит от требуемых функций и структуры сайта.';
        }

        return $product . ' подойдёт пользователям, которым нужен '
            . $this->ruType($productType)
            . ' с возможностями, перечисленными в описании товара.';
    }

    private function enAudienceFallback(
        string $product,
        string $productType,
        string $topics
    ): string {
        if ($topics !== '') {
            return $product . ' can be considered for projects focused on '
                . $topics . '. The final choice depends on the required '
                . 'site structure and features.';
        }

        return $product . ' is suitable for users who need a '
            . $this->enType($productType)
            . ' with the capabilities listed in the product description.';
    }

    private function fallbackDetails(string $details, string $language): string
    {
        return '<h3>'
            . $this->text(
                $language === 'ru'
                    ? 'Описание и возможности'
                    : 'Description and features'
            )
            . '</h3><p>' . $this->text($details) . '</p>';
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
            'business' => ['business'],
            'corporate' => ['corporate'],
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
            $parts = preg_split('/\s*[,;]\s*/u', $sentence) ?: [];

            if (count($parts) < 2) {
                continue;
            }

            foreach ($parts as $part) {
                $part = trim((string) $part, " \t\n\r\0\x0B.!?");

                if ($part !== '' && $this->textLength($part) >= 3) {
                    $features[] = $part;
                }
            }
        }

        return array_values(array_unique(array_slice($features, 0, 12)));
    }

    /** @param list<string> $features */
    private function featureSection(string $heading, array $features): string
    {
        if ($features === []) {
            return '';
        }

        $items = '';

        foreach ($features as $feature) {
            $items .= '<li>' . $this->text($feature) . '.</li>';
        }

        return '<h3>' . $this->text($heading) . '</h3><ul>' . $items . '</ul>';
    }

    /** @return list<string> */
    private function matchingFacts(string $details, string $pattern): array
    {
        $facts = $this->legacyFeatures($details, false);

        return array_values(array_filter(
            $facts,
            fn (string $fact): bool => $this->matches($fact, $pattern)
        ));
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
        if (preg_match('/\bдля\s+([^.!?]+)/ui', $summary, $matches) !== 1) {
            return '';
        }

        return trim((string) $matches[1]);
    }

    private function audienceFromEnSummary(string $summary): string
    {
        if (preg_match('/\bfor\s+([^.!?]+)/ui', $summary, $matches) !== 1) {
            return '';
        }

        return trim((string) $matches[1]);
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
