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
            if ($this->isEducation($content, $topics)) {
                return '<p>' . $this->text(
                    $product
                    . ' подходит для создания образовательных сайтов, '
                    . 'онлайн-курсов и LMS-платформ. Возможности продукта '
                    . 'помогают организовать учебный контент, страницы курсов '
                    . 'и другие разделы образовательного проекта.'
                ) . '</p>';
            }

            if ($topics !== '') {
                return '<p>' . $this->text(
                    $product . ' подходит для проектов в тематике '
                    . $topics . '. Ниже собраны основные возможности и сценарии '
                    . 'использования на основе существующего описания продукта.'
                ) . '</p>';
            }

            return '<p>' . $this->text(
                $product . ' — ' . $this->ruType($productType)
                . '. Ниже собраны основные возможности продукта и практические '
                . 'сценарии его использования.'
            ) . '</p>';
        }

        if ($this->isEducation($content, $topics)) {
            return '<p>' . $this->text(
                $product
                . ' is suitable for education websites, online courses and '
                . 'LMS platforms. Its features support learning content, course '
                . 'pages and other parts of an education-focused website.'
            ) . '</p>';
        }

        if ($topics !== '') {
            return '<p>' . $this->text(
                $product . ' is suitable for projects focused on '
                . $topics . '. The main capabilities and practical use cases '
                . 'are summarized below from the available product information.'
            ) . '</p>';
        }

        return '<p>' . $this->text(
            $product . ' is a ' . $this->enType($productType)
            . '. Its main capabilities and practical use cases are summarized below.'
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

        if (
            $this->isEducation($details, $topics)
            || $this->hasAnyTag(
                $sourceTags,
                [
                    'learndash',
                    'learnpress',
                    'lifterlms',
                    'sensei',
                    'tutor',
                    'tutor lms',
                ]
            )
        ) {
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
                    ? $product . ' поддерживает Elementor для визуальной '
                        . 'настройки страниц. Это позволяет редактировать структуру '
                        . 'и содержимое сайта через привычный интерфейс конструктора.'
                    : $product . ' supports Elementor for visual page building. '
                        . 'This makes it possible to adjust page structure and '
                        . 'content through the familiar builder interface.'
            );
        }

        if ($this->hasAnyTag($sourceTags, ['woocommerce'])) {
            $html .= $this->simpleSection(
                $language === 'ru'
                    ? 'WooCommerce и коммерческие сценарии'
                    : 'WooCommerce and commerce workflows',
                $language === 'ru'
                    ? 'Совместимость с WooCommerce позволяет использовать '
                        . $product . ' в интернет-магазинах и других e-commerce '
                        . 'проектах. Конкретные возможности интеграции зависят от '
                        . 'функций, заявленных разработчиком для текущей версии.'
                    : 'WooCommerce compatibility makes ' . $product
                        . ' suitable for online stores and other e-commerce '
                        . 'projects. The exact integration capabilities depend '
                        . 'on the features provided by the current version.'
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
                    ? 'Поддержка ' . $this->humanList($languageTags, 'ru')
                        . ' помогает адаптировать ' . $product
                        . ' для многоязычных сайтов и международной аудитории.'
                    : 'Support for ' . $this->humanList($languageTags, 'en')
                        . ' helps adapt ' . $product
                        . ' for multilingual websites and international audiences.'
            );
        }

        if ($html === '' && $topics !== '') {
            $html .= $this->simpleSection(
                $language === 'ru' ? 'Сценарии использования' : 'Use cases',
                $language === 'ru'
                    ? $product . ' подходит для проектов в тематике '
                        . $topics . '. Конкретный набор возможностей зависит от '
                        . 'функций, заявленных разработчиком для текущей версии.'
                    : $product . ' is suitable for projects focused on '
                        . $topics . '. The exact workflows depend on the '
                        . 'features provided by the current product version.'
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
                ? 'Среди возможностей: '
                    . $this->humanList($facts, 'ru') . '.'
                : $product . ' ориентирован на LMS и онлайн-обучение.';

            return $this->simpleSection(
                'Онлайн-курсы и LMS',
                $factSentence . ' Это делает ' . $product
                . ' подходящей основой для школ, учебных центров, '
                . 'онлайн-курсов и других образовательных проектов.'
            );
        }

        $factSentence = $facts !== []
            ? 'Key capabilities include '
                . $this->humanList($facts, 'en') . '.'
            : $product . ' is focused on LMS and online learning.';

        return $this->simpleSection(
            'Online courses and LMS',
            $factSentence . ' This makes ' . $product
            . ' a suitable foundation for schools, training centers, online '
            . 'courses and other education-focused projects.'
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
                    . '. Перед выбором стоит сопоставить необходимые функции '
                    . 'проекта с возможностями текущей версии продукта.'
                : $this->ruAudienceFallback($product, $productType, $topics);

            return $this->simpleSection(
                'Кому подходит ' . $product . '?',
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
            'Who is ' . $product . ' for?',
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
        if ($this->matches($topics, '/(?:education|learning|lms|school|universit)/ui')) {
            return $product
                . ' is suitable for schools, universities, training centers, '
                . 'instructors, coaches and education businesses that need a '
                . 'WordPress foundation for online learning.';
        }

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

    private function isEducation(string $content, string $topics): bool
    {
        return $this->matches(
            $content . ' ' . $topics,
            '/(?:образован|обучен|lms|курс|школ|университет|education|learning|course|school|university)/ui'
        );
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

            if ($this->isAudienceSentence($sentence)) {
                continue;
            }

            $parts = preg_split('/\s*[,;]\s*/u', $sentence) ?: [];

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
        if (count($features) < 2) {
            return '';
        }

        $items = '';

        foreach ($features as $feature) {
            $feature = rtrim($feature, " .;,:!?");
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
