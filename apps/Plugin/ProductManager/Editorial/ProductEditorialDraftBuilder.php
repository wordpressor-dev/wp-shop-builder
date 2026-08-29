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
            $sourceUpdateDate
        );
        $enLong = $this->enLong(
            $title,
            $developer,
            $productType,
            $enTopics,
            $sourceUpdateDate
        );

        $ruMeta = $title . ' — ' . $ruType . $ruDeveloper;
        $enMeta = $title . ' — ' . $enType . $enDeveloper;

        if ($ruTopics !== '') {
            $ruMeta .= '. Для проектов: ' . $ruTopics;
        }

        if ($enTopics !== '') {
            $enMeta .= '. For ' . $enTopics;
        }

        $legacyRuShort = $this->legacyText((string) ($legacy['ruShort'] ?? ''));
        $legacyRuLong = $this->legacyText((string) ($legacy['ruLong'] ?? ''));

        if ($legacyRuShort !== '' || $legacyRuLong !== '') {
            $ruSource = $legacyRuShort !== '' ? $legacyRuShort : $legacyRuLong;
            $ruDetails = $legacyRuLong !== '' ? $legacyRuLong : $ruSource;
            $ruShort = $ruSource;
            $ruLong = $this->ruLegacyLong(
                $title,
                $ruDetails,
                $developer,
                $productType,
                $ruTopics,
                $sourceUpdateDate
            );
            $ruMeta = $this->limit($ruSource);
        }

        $legacyEnShort = $this->legacyText((string) ($legacy['enShort'] ?? ''));
        $legacyEnLong = $this->legacyText((string) ($legacy['enLong'] ?? ''));

        if ($legacyEnShort !== '' || $legacyEnLong !== '') {
            $enSource = $legacyEnShort !== '' ? $legacyEnShort : $legacyEnLong;
            $enDetails = $legacyEnLong !== '' ? $legacyEnLong : $enSource;
            $enShort = $enSource;
            $enLong = $this->enLegacyLong(
                $title,
                $enDetails,
                $developer,
                $productType,
                $enTopics,
                $sourceUpdateDate
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

    private function ruLegacyLong(
        string $title,
        string $legacyDetails,
        string $developer,
        string $productType,
        string $topics,
        string $sourceUpdateDate
    ): string {
        $safeTitle = $this->text($title);
        $type = $this->ruType($productType);
        $scope = $topics !== ''
            ? '<li><strong>Тематика:</strong> ' . $this->text($topics) . '.</li>'
            : '';
        $developerRow = $developer !== ''
            ? '<li><strong>Разработчик:</strong> ' . $this->text($developer) . '.</li>'
            : '';
        $dateRow = $sourceUpdateDate !== ''
            ? '<li><strong>Дата обновления источника:</strong> '
                . $this->text($sourceUpdateDate) . '.</li>'
            : '';

        return '<h2>' . $safeTitle . '</h2>'
            . '<p>' . $this->text($legacyDetails) . '</p>'
            . '<h3>Основные сведения</h3>'
            . '<ul>'
            . '<li><strong>Тип продукта:</strong> ' . $this->text($type) . '.</li>'
            . $scope
            . $developerRow
            . $dateRow
            . '</ul>';
    }

    private function enLegacyLong(
        string $title,
        string $legacyDetails,
        string $developer,
        string $productType,
        string $topics,
        string $sourceUpdateDate
    ): string {
        $safeTitle = $this->text($title);
        $type = $this->enType($productType);
        $scope = $topics !== ''
            ? '<li><strong>Project focus:</strong> ' . $this->text($topics) . '.</li>'
            : '';
        $developerRow = $developer !== ''
            ? '<li><strong>Developer:</strong> ' . $this->text($developer) . '.</li>'
            : '';
        $dateRow = $sourceUpdateDate !== ''
            ? '<li><strong>Source update date:</strong> '
                . $this->text($sourceUpdateDate) . '.</li>'
            : '';

        return '<h2>' . $safeTitle . '</h2>'
            . '<p>' . $this->text($legacyDetails) . '</p>'
            . '<h3>Product details</h3>'
            . '<ul>'
            . '<li><strong>Product type:</strong> ' . $this->text($type) . '.</li>'
            . $scope
            . $developerRow
            . $dateRow
            . '</ul>';
    }

    private function ruLong(
        string $title,
        string $developer,
        string $productType,
        string $topics,
        string $sourceUpdateDate
    ): string {
        $safeTitle = $this->text($title);
        $type = $this->ruType($productType);
        $developerSentence = $developer !== ''
            ? ' Разработчик — ' . $this->text($developer) . '.'
            : '';
        $purpose = match ($productType) {
            CatalogProductType::PLUGIN =>
                'Он расширяет возможности WordPress и предназначен для добавления специализированной функциональности без разработки решения с нуля.',
            CatalogProductType::TEMPLATE_KIT =>
                'Он содержит согласованный набор шаблонов для сборки страниц в Elementor и помогает быстрее подготовить визуальную структуру сайта.',
            default =>
                'Она служит готовой основой для WordPress-сайта и помогает быстрее перейти от установки к настройке структуры, оформления и контента.',
        };
        $scope = $topics !== ''
            ? '<li><strong>Тематика:</strong> ' . $this->text($topics) . '.</li>'
            : '<li><strong>Тематика:</strong> определяется назначением продукта и официальной демонстрацией.</li>';
        $developerRow = $developer !== ''
            ? '<li><strong>Разработчик:</strong> ' . $this->text($developer) . '.</li>'
            : '';
        $dateRow = $sourceUpdateDate !== ''
            ? '<li><strong>Дата обновления источника:</strong> '
                . $this->text($sourceUpdateDate) . '.</li>'
            : '';

        return '<h2>' . $safeTitle . '</h2>'
            . '<p>' . $safeTitle . ' — ' . $this->text($type) . '.'
            . $developerSentence . ' ' . $this->text($purpose) . '</p>'
            . '<h3>Назначение и основные сведения</h3>'
            . '<ul>'
            . '<li><strong>Тип продукта:</strong> ' . $this->text($type) . '.</li>'
            . $scope
            . $developerRow
            . $dateRow
            . '</ul>'
            . '<p>Перед публикацией рекомендуется сверить системные требования, совместимость, состав и особенности установки с официальной страницей разработчика.</p>';
    }

    private function enLong(
        string $title,
        string $developer,
        string $productType,
        string $topics,
        string $sourceUpdateDate
    ): string {
        $safeTitle = $this->text($title);
        $type = $this->enType($productType);
        $developerSentence = $developer !== ''
            ? ' The developer is ' . $this->text($developer) . '.'
            : '';
        $purpose = match ($productType) {
            CatalogProductType::PLUGIN =>
                'It extends WordPress with focused functionality and can reduce the amount of custom development needed for the intended workflow.',
            CatalogProductType::TEMPLATE_KIT =>
                'It provides a coordinated set of Elementor templates that can speed up page building and establish a consistent visual structure.',
            default =>
                'It provides a ready WordPress foundation that can speed up the move from installation to site structure, styling and content setup.',
        };
        $scope = $topics !== ''
            ? '<li><strong>Project focus:</strong> ' . $this->text($topics) . '.</li>'
            : '<li><strong>Project focus:</strong> see the official product demo and documentation.</li>';
        $developerRow = $developer !== ''
            ? '<li><strong>Developer:</strong> ' . $this->text($developer) . '.</li>'
            : '';
        $dateRow = $sourceUpdateDate !== ''
            ? '<li><strong>Source update date:</strong> '
                . $this->text($sourceUpdateDate) . '.</li>'
            : '';

        return '<h2>' . $safeTitle . '</h2>'
            . '<p>' . $safeTitle . ' is a ' . $this->text($type) . '.'
            . $developerSentence . ' ' . $this->text($purpose) . '</p>'
            . '<h3>Purpose and product details</h3>'
            . '<ul>'
            . '<li><strong>Product type:</strong> ' . $this->text($type) . '.</li>'
            . $scope
            . $developerRow
            . $dateRow
            . '</ul>'
            . '<p>Before publishing, verify system requirements, compatibility, included components and installation details on the official developer page.</p>';
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
