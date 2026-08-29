<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductEditorialDraftBuilder
{
    /**
     * @param list<string> $sourceTags
     * @param array{ruShort?:string,ruLong?:string,enShort?:string,enLong?:string} $legacy
     * @return array{
     *   ruShort: string,
     *   ruLong: string,
     *   ruMeta: string,
     *   enShort: string,
     *   enLong: string,
     *   enMeta: string
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
        $sourceUpdateDate = trim($sourceUpdateDate);
        $topics = $this->topics(array_merge(
            $this->titleTopics($title),
            $sourceTags
        ));
        $ruTopics = $this->ruTopics($topics);
        $enTopics = $this->enTopics($topics);
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

        $ruLong = $this->ruLong(
            $title,
            $developer,
            $productType,
            $ruTopics,
            $sourceTags
        );
        $enLong = $this->enLong(
            $title,
            $developer,
            $productType,
            $enTopics,
            $sourceTags
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
            $ruLong = $this->ruLegacyLong(
                $title,
                $ruSource,
                $ruDetails,
                $productType,
                $ruTopics,
                $sourceTags
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
            $enLong = $this->enLegacyLong(
                $title,
                $enSource,
                $enDetails,
                $productType,
                $enTopics,
                $sourceTags
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
    private function ruLegacyLong(
        string $title,
        string $legacySummary,
        string $legacyDetails,
        string $productType,
        string $topics,
        array $sourceTags
    ): string {
        $safeTitle = $this->text($title);
        $product = $this->productName($title);
        $features = $this->legacyFeatures(
            $legacyDetails,
            $this->sameText($legacySummary, $legacyDetails)
        );
        $featuresSection = $this->featureSection(
            'Основные возможности ' . $product,
            $features
        );
        $detailsFallback = $featuresSection === ''
            && ! $this->sameText($legacySummary, $legacyDetails)
                ? '<h3>Описание и возможности</h3><p>'
                    . $this->text($legacyDetails) . '</p>'
                : '';

        return '<h2>' . $safeTitle . '</h2>'
            . '<p>' . $this->text($legacySummary) . '</p>'
            . $this->ruLeadParagraph(
                $product,
                $legacySummary,
                $legacyDetails,
                $productType,
                $topics
            )
            . $featuresSection
            . $detailsFallback
            . $this->ruEditorialSections(
                $product,
                $legacySummary,
                $legacyDetails,
                $productType,
                $topics,
                $sourceTags
            )
            . $this->ruAudienceSection(
                $product,
                $legacySummary,
                $productType,
                $topics
            );
    }

    /** @param list<string> $sourceTags */
    private function enLegacyLong(
        string $title,
        string $legacySummary,
        string $legacyDetails,
        string $productType,
        string $topics,
        array $sourceTags
    ): string {
        $safeTitle = $this->text($title);
        $product = $this->productName($title);
        $features = $this->legacyFeatures(
            $legacyDetails,
            $this->sameText($legacySummary, $legacyDetails)
        );
        $featuresSection = $this->featureSection(
            'Key features of ' . $product,
            $features
        );
        $detailsFallback = $featuresSection === ''
            && ! $this->sameText($legacySummary, $legacyDetails)
                ? '<h3>Description and features</h3><p>'
                    . $this->text($legacyDetails) . '</p>'
                : '';

        return '<h2>' . $safeTitle . '</h2>'
            . '<p>' . $this->text($legacySummary) . '</p>'
            . $this->enLeadParagraph(
                $product,
                $legacySummary,
                $legacyDetails,
                $productType,
                $topics
            )
            . $featuresSection
            . $detailsFallback
            . $this->enEditorialSections(
                $product,
                $legacySummary,
                $legacyDetails,
                $productType,
                $topics,
                $sourceTags
            )
            . $this->enAudienceSection(
                $product,
                $legacySummary,
                $productType,
                $topics
            );
    }

    private function ruLeadParagraph(
        string $product,
        string $summary,
        string $details,
        string $productType,
        string $topics
    ): string {
        $combined = $summary . ' ' . $details;

        if ($this->matches($combined, '/(?:образован|lms|курс|школ)/ui')) {
            return '<p>' . $this->text(
                $product
                . ' ориентирован на образовательные проекты. '
                . 'Сведения в карточке указывают на сценарии онлайн-обучения, '
                . 'работу с учебным контентом и организацию образовательного сайта.'
            ) . '</p>';
        }

        if ($topics !== '') {
            return '<p>' . $this->text(
                $product . ' можно рассматривать для проектов в тематике '
                . $topics . '. Основные возможности перечислены ниже на основе '
                . 'имеющегося описания товара.'
            ) . '</p>';
        }

        $type = $this->ruType($productType);

        return '<p>' . $this->text(
            $product . ' — ' . $type
            . '. Ниже собраны основные возможности из существующего описания товара.'
        ) . '</p>';
    }

    private function enLeadParagraph(
        string $product,
        string $summary,
        string $details,
        string $productType,
        string $topics
    ): string {
        $combined = $summary . ' ' . $details;

        if ($this->matches($combined, '/(?:education|lms|course|school)/ui')) {
            return '<p>' . $this->text(
                $product
                . ' is aimed at education projects. The available product '
                . 'information points to online learning, course content and '
                . 'education-site workflows.'
            ) . '</p>';
        }

        if ($topics !== '') {
            return '<p>' . $this->text(
                $product . ' can be considered for projects focused on '
                . $topics . '. The main capabilities below are based on the '
                . 'existing product description.'
            ) . '</p>';
        }

        return '<p>' . $this->text(
            $product . ' is a ' . $this->enType($productType)
            . '. The main capabilities below are preserved from the existing '
            . 'product description.'
        ) . '</p>';
    }

    /** @param list<string> $sourceTags */
    private function ruEditorialSections(
        string $product,
        string $summary,
        string $details,
        string $productType,
        string $topics,
        array $sourceTags
    ): string {
        $html = '';
        $combined = $summary . ' ' . $details;

        if (
            $this->matches($combined, '/(?:lms|курс|обучен|преподавател)/ui')
            || $this->hasAnyTag(
                $sourceTags,
                ['learndash', 'learnpress', 'lifterlms', 'sensei', 'tutor', 'tutor lms']
            )
        ) {
            $facts = $this->matchingFacts(
                $details,
                '/(?:lms|курс|расписан|преподавател|учеб)/ui'
            );
            $sentence = $facts !== []
                ? 'В существующем описании отмечены ' . $this->humanList($facts, 'ru') . '.'
                : 'В данных товара отмечена направленность на LMS и онлайн-обучение.';
            $html .= '<h3>Онлайн-курсы и LMS</h3><p>'
                . $this->text($sentence)
                . ' Это делает ' . $this->text($product)
                . ' подходящей основой для образовательных сайтов, где важна '
                . 'структура учебного контента и связанных страниц.</p>';
        }

        if ($this->hasAnyTag($sourceTags, ['elementor', 'elementor pro'])) {
            $html .= '<h3>Elementor и настройка страниц</h3><p>'
                . 'Elementor указан среди связанных технологий продукта. '
                . 'Это позволяет учитывать визуальный редактор при настройке '
                . 'страниц и подготовке структуры сайта.</p>';
        }

        if ($this->hasAnyTag($sourceTags, ['woocommerce'])) {
            $html .= '<h3>WooCommerce и коммерческие сценарии</h3><p>'
                . 'WooCommerce отмечен среди интеграций продукта. Поэтому '
                . $this->text($product)
                . ' можно рассматривать для проектов, где вместе с основным '
                . 'сайтом используются стандартные возможности интернет-магазина.</p>';
        }

        $languageTags = $this->selectedTags(
            $sourceTags,
            ['wpml', 'rtl', 'loco translate', 'translation ready']
        );

        if ($languageTags !== []) {
            $html .= '<h3>Многоязычные проекты</h3><p>'
                . 'В данных товара отмечены возможности, связанные с '
                . $this->text($this->humanList($languageTags, 'ru'))
                . '. Это полезно при подготовке сайтов для нескольких языков '
                . 'или международной аудитории.</p>';
        }

        if ($html === '' && $topics !== '') {
            $html .= '<h3>Сценарии использования</h3><p>'
                . $this->text($product)
                . ' подходит для проектов в тематике '
                . $this->text($topics)
                . '. Конкретный набор сценариев определяется возможностями, '
                . 'зафиксированными в описании товара.</p>';
        }

        if ($html === '' && $productType === CatalogProductType::PLUGIN) {
            $html .= '<h3>Сценарии использования</h3><p>'
                . $this->text($product)
                . ' расширяет WordPress специализированной функциональностью. '
                . 'Практические сценарии зависят от возможностей, перечисленных '
                . 'в описании плагина.</p>';
        }

        return $html;
    }

    /** @param list<string> $sourceTags */
    private function enEditorialSections(
        string $product,
        string $summary,
        string $details,
        string $productType,
        string $topics,
        array $sourceTags
    ): string {
        $html = '';
        $combined = $summary . ' ' . $details;

        if (
            $this->matches($combined, '/(?:lms|course|learning|instructor)/ui')
            || $this->hasAnyTag(
                $sourceTags,
                ['learndash', 'learnpress', 'lifterlms', 'sensei', 'tutor', 'tutor lms']
            )
        ) {
            $facts = $this->matchingFacts(
                $details,
                '/(?:lms|course|class|schedule|instructor|learning)/ui'
            );
            $sentence = $facts !== []
                ? 'The existing description highlights '
                    . $this->humanList($facts, 'en') . '.'
                : 'The product data indicates an LMS and online-learning focus.';
            $html .= '<h3>Online courses and LMS</h3><p>'
                . $this->text($sentence)
                . ' This makes ' . $this->text($product)
                . ' relevant to education websites that need structured '
                . 'learning content and related pages.</p>';
        }

        if ($this->hasAnyTag($sourceTags, ['elementor', 'elementor pro'])) {
            $html .= '<h3>Elementor and page building</h3><p>'
                . 'Elementor is listed among the product-related technologies. '
                . 'It can therefore be considered when building and adjusting '
                . 'the site page structure.</p>';
        }

        if ($this->hasAnyTag($sourceTags, ['woocommerce'])) {
            $html .= '<h3>WooCommerce and commerce workflows</h3><p>'
                . 'WooCommerce is listed among the product integrations. '
                . $this->text($product)
                . ' can therefore be considered for projects that combine the '
                . 'main site with standard online-store functionality.</p>';
        }

        $languageTags = $this->selectedTags(
            $sourceTags,
            ['wpml', 'rtl', 'loco translate', 'translation ready']
        );

        if ($languageTags !== []) {
            $html .= '<h3>Multilingual projects</h3><p>'
                . 'The product data includes '
                . $this->text($this->humanList($languageTags, 'en'))
                . '. This is relevant when preparing sites for multiple '
                . 'languages or an international audience.</p>';
        }

        if ($html === '' && $topics !== '') {
            $html .= '<h3>Use cases</h3><p>'
                . $this->text($product)
                . ' is suitable for projects focused on '
                . $this->text($topics)
                . '. The exact workflows depend on the capabilities preserved '
                . 'from the product description.</p>';
        }

        if ($html === '' && $productType === CatalogProductType::PLUGIN) {
            $html .= '<h3>Use cases</h3><p>'
                . $this->text($product)
                . ' extends WordPress with focused functionality. Practical '
                . 'workflows depend on the capabilities listed in the plugin '
                . 'description.</p>';
        }

        return $html;
    }

    private function ruAudienceSection(
        string $product,
        string $summary,
        string $productType,
        string $topics
    ): string {
        $audience = $this->audienceFromRuSummary($summary);

        if ($audience !== '') {
            return '<h3>Кому подходит ' . $this->text($product) . '?</h3><p>'
                . $this->text($product) . ' подходит для ' . $this->text($audience)
                . '. Перед использованием стоит сверить конкретные требования '
                . 'проекта с актуальной документацией продукта.</p>';
        }

        if ($topics !== '') {
            return '<h3>Кому подходит ' . $this->text($product) . '?</h3><p>'
                . $this->text($product)
                . ' можно рассматривать для проектов в тематике '
                . $this->text($topics)
                . '. Выбор зависит от требуемых функций и структуры сайта.</p>';
        }

        return '<h3>Кому подходит ' . $this->text($product) . '?</h3><p>'
            . $this->text($product) . ' подойдёт пользователям, которым нужен '
            . $this->text($this->ruType($productType))
            . ' с возможностями, перечисленными в описании товара.</p>';
    }

    private function enAudienceSection(
        string $product,
        string $summary,
        string $productType,
        string $topics
    ): string {
        $audience = $this->audienceFromEnSummary($summary);

        if ($audience !== '') {
            return '<h3>Who is ' . $this->text($product) . ' for?</h3><p>'
                . $this->text($product) . ' is suitable for '
                . $this->text($audience)
                . '. Check the current product documentation against the '
                . 'specific requirements of the project before use.</p>';
        }

        if ($topics !== '') {
            return '<h3>Who is ' . $this->text($product) . ' for?</h3><p>'
                . $this->text($product) . ' can be considered for projects '
                . 'focused on ' . $this->text($topics)
                . '. The final choice depends on the required site structure '
                . 'and features.</p>';
        }

        return '<h3>Who is ' . $this->text($product) . ' for?</h3><p>'
            . $this->text($product) . ' is suitable for users who need a '
            . $this->text($this->enType($productType))
            . ' with the capabilities listed in the product description.</p>';
    }

    /** @param list<string> $sourceTags */
    private function ruLong(
        string $title,
        string $developer,
        string $productType,
        string $topics,
        array $sourceTags
    ): string {
        $safeTitle = $this->text($title);
        $product = $this->productName($title);
        $type = $this->ruType($productType);
        $developerSentence = $developer !== ''
            ? ' Разработчик — ' . $this->text($developer) . '.'
            : '';
        $purpose = match ($productType) {
            CatalogProductType::PLUGIN =>
                'Он расширяет возможности WordPress специализированной функциональностью.',
            CatalogProductType::TEMPLATE_KIT =>
                'Он содержит согласованный набор шаблонов для сборки страниц в Elementor.',
            default =>
                'Она служит готовой основой для WordPress-сайта.',
        };
        $topicSentence = $topics !== ''
            ? ' Подходит для проектов в тематике ' . $this->text($topics) . '.'
            : '';

        return '<h2>' . $safeTitle . '</h2><p>'
            . $safeTitle . ' — ' . $this->text($type) . '.'
            . $developerSentence . ' ' . $this->text($purpose)
            . $topicSentence . '</p>'
            . $this->ruEditorialSections(
                $product,
                '',
                '',
                $productType,
                $topics,
                $sourceTags
            )
            . $this->ruAudienceSection($product, '', $productType, $topics);
    }

    /** @param list<string> $sourceTags */
    private function enLong(
        string $title,
        string $developer,
        string $productType,
        string $topics,
        array $sourceTags
    ): string {
        $safeTitle = $this->text($title);
        $product = $this->productName($title);
        $type = $this->enType($productType);
        $developerSentence = $developer !== ''
            ? ' The developer is ' . $this->text($developer) . '.'
            : '';
        $purpose = match ($productType) {
            CatalogProductType::PLUGIN =>
                'It extends WordPress with focused functionality.',
            CatalogProductType::TEMPLATE_KIT =>
                'It provides a coordinated set of Elementor page templates.',
            default =>
                'It provides a ready foundation for a WordPress site.',
        };
        $topicSentence = $topics !== ''
            ? ' Suitable for projects focused on ' . $this->text($topics) . '.'
            : '';

        return '<h2>' . $safeTitle . '</h2><p>'
            . $safeTitle . ' is a ' . $this->text($type) . '.'
            . $developerSentence . ' ' . $this->text($purpose)
            . $topicSentence . '</p>'
            . $this->enEditorialSections(
                $product,
                '',
                '',
                $productType,
                $topics,
                $sourceTags
            )
            . $this->enAudienceSection($product, '', $productType, $topics);
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
        $stop = [
            'wordpress', 'theme', 'plugin', 'template', 'template kit',
            'elementor', 'elementor pro', 'responsive', 'modern', 'clean',
            'themeforest', 'codecanyon', 'website', 'web', 'design',
            'learndash', 'learnpress', 'lifterlms', 'sensei', 'tutor',
            'tutor lms', 'loco translate', 'rtl', 'wpml', 'woocommerce',
            'translation ready', 'retina ready', 'bootstrap', 'gutenberg',
        ];

        foreach ($tags as $tag) {
            $tag = strtolower(trim((string) preg_replace('/\s+/u', ' ', $tag)));

            if (
                $tag === ''
                || in_array($tag, $stop, true)
                || strlen($tag) > 40
            ) {
                continue;
            }

            $result[] = $tag;
        }

        return array_values(array_unique(array_slice($result, 0, 6)));
    }

    /** @param list<string> $topics */
    private function ruTopics(array $topics): string
    {
        $map = [
            'hotel' => 'отели',
            'hotels' => 'отели',
            'resort' => 'курорты',
            'villa' => 'виллы',
            'travel' => 'путешествия',
            'tourism' => 'туризм',
            'booking' => 'бронирование',
            'vacation' => 'отдых',
            'business' => 'бизнес',
            'corporate' => 'корпоративные сайты',
            'agency' => 'агентства',
            'marketing' => 'маркетинг',
            'education' => ['образование', 'онлайн-обучение'],
            'school' => 'школы',
            'university' => 'университеты',
            'lms' => 'LMS',
            'ecommerce' => 'интернет-магазины',
            'e-commerce' => 'интернет-магазины',
            'shop' => 'магазины',
            'store' => 'магазины',
            'blog' => 'блоги',
            'portfolio' => 'портфолио',
            'restaurant' => 'рестораны',
            'food' => 'рестораны и питание',
            'real estate' => 'недвижимость',
            'realestate' => 'недвижимость',
            'medical' => 'медицина',
            'health' => 'здоровье',
            'fitness' => 'фитнес',
            'gym' => 'спортзалы',
            'construction' => 'строительство',
            'renovation' => 'ремонт и реконструкция',
            'remodeling' => 'ремоделирование',
            'seo' => 'SEO',
            'finance' => 'финансы',
            'technology' => 'технологии',
            'saas' => 'SaaS',
        ];
        $translated = [];

        foreach ($topics as $topic) {
            if (! isset($map[$topic])) {
                continue;
            }

            foreach ((array) $map[$topic] as $value) {
                $translated[] = $value;
            }
        }

        return $this->humanList(array_values(array_unique($translated)), 'ru');
    }

    /** @param list<string> $topics */
    private function enTopics(array $topics): string
    {
        $map = [
            'hotel' => 'hotels',
            'hotels' => 'hotels',
            'resort' => 'resorts',
            'villa' => 'villas',
            'travel' => 'travel',
            'tourism' => 'tourism',
            'booking' => 'booking',
            'vacation' => 'vacation',
            'business' => 'business',
            'corporate' => 'corporate websites',
            'agency' => 'agencies',
            'marketing' => 'marketing',
            'education' => ['education', 'online learning'],
            'school' => 'schools',
            'university' => 'universities',
            'lms' => 'LMS',
            'ecommerce' => 'e-commerce',
            'e-commerce' => 'e-commerce',
            'shop' => 'online stores',
            'store' => 'online stores',
            'blog' => 'blogs',
            'portfolio' => 'portfolios',
            'restaurant' => 'restaurants',
            'food' => 'food businesses',
            'real estate' => 'real estate',
            'realestate' => 'real estate',
            'medical' => 'medical websites',
            'health' => 'healthcare',
            'fitness' => 'fitness',
            'gym' => 'gyms',
            'construction' => 'construction',
            'renovation' => 'home renovation',
            'remodeling' => 'remodeling',
            'seo' => 'SEO',
            'finance' => 'finance',
            'technology' => 'technology',
            'saas' => 'SaaS',
        ];
        $translated = [];

        foreach ($topics as $topic) {
            if (! isset($map[$topic])) {
                continue;
            }

            foreach ((array) $map[$topic] as $value) {
                $translated[] = $value;
            }
        }

        return $this->humanList(array_values(array_unique($translated)), 'en');
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

            if ($sentence === '') {
                continue;
            }

            $parts = preg_split('/\s*[,;]\s*/u', $sentence) ?: [];

            if (count($parts) < 2) {
                continue;
            }

            foreach ($parts as $part) {
                $part = trim((string) $part, " \t\n\r\0\x0B.!?");

                if ($part === '' || $this->textLength($part) < 3) {
                    continue;
                }

                $features[] = $part;
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

    /** @param list<string> $tags */
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

        return trim((string) ($matches[1] ?? ''));
    }

    private function audienceFromEnSummary(string $summary): string
    {
        if (preg_match('/\bfor\s+([^.!?]+)/ui', $summary, $matches) !== 1) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
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
