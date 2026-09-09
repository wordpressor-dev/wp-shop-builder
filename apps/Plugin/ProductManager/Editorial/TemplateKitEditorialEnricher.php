<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

final class TemplateKitEditorialEnricher
{
    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $base
     * @param list<string> $sourceTags
     * @param array{
     *   templateCount:int,
     *   elementorProRequired:?bool,
     *   responsive:bool,
     *   dragDrop:bool,
     *   globalStyles:bool,
     *   templates:list<string>,
     *   requiredPlugins:list<string>,
     *   helloElementor:bool,
     *   demoImagesLicense:bool,
     *   tags:list<string>
     * } $facts
     * @return array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     */
    public function enrich(
        array $base,
        string $title,
        string $developer,
        array $sourceTags,
        array $facts
    ): array {
        if (! $this->hasUsefulFacts($facts)) {
            return $base;
        }

        $product = $this->productName($title);
        $audienceRu = $this->audience($sourceTags, 'ru');
        $audienceEn = $this->audience($sourceTags, 'en');

        $ruFeatures = [];
        $enFeatures = [];

        if ($facts['templateCount'] > 0) {
            $ruFeatures[] = 'Более ' . $facts['templateCount']
                . ' готовых шаблонов страниц и секций';
            $enFeatures[] = $facts['templateCount']
                . '+ ready-to-use page and section templates';
        }

        if ($facts['responsive']) {
            $ruFeatures[] = 'Адаптивный дизайн для компьютеров, планшетов и смартфонов';
            $enFeatures[] = 'Responsive layouts for desktop, tablet and mobile devices';
        }

        if ($facts['dragDrop']) {
            $ruFeatures[] = 'Визуальная настройка без кода с перетаскиванием элементов';
            $enFeatures[] = 'No-code visual customization with drag and drop';
        }

        if ($facts['globalStyles']) {
            $ruFeatures[] = 'Централизованная настройка шрифтов и цветов через глобальные стили';
            $enFeatures[] = 'Centralized font and color settings through global styles';
        }

        $templateSummaryRu = $this->templateSummary($facts['templates'], 'ru');
        $templateSummaryEn = $this->templateSummary($facts['templates'], 'en');

        if ($templateSummaryRu !== '') {
            $ruFeatures[] = $templateSummaryRu;
        }
        if ($templateSummaryEn !== '') {
            $enFeatures[] = $templateSummaryEn;
        }

        $ruCompat = ['WordPress', 'Elementor'];
        $enCompat = ['WordPress', 'Elementor'];

        if ($facts['elementorProRequired'] === false) {
            $ruCompat[] = 'Elementor Pro не требуется';
            $enCompat[] = 'Elementor Pro is not required';
        } elseif ($facts['elementorProRequired'] === true) {
            $ruCompat[] = 'Требуется Elementor Pro';
            $enCompat[] = 'Elementor Pro is required';
        }

        foreach ($facts['requiredPlugins'] as $plugin) {
            if (
                $plugin !== ''
                && mb_strtolower($plugin, 'UTF-8') !== 'elementor'
            ) {
                $ruCompat[] = $plugin;
                $enCompat[] = $plugin;
            }
        }

        if ($facts['helloElementor']) {
            $ruCompat[] = 'Оптимизирован для бесплатной темы Hello Elementor';
            $enCompat[] = 'Optimized for the free Hello Elementor theme';
        }

        $ruIntro = $product . ' — набор шаблонов Elementor'
            . ($developer !== '' ? ' от ' . $developer : '')
            . ($audienceRu !== '' ? ' для ' . $audienceRu : '')
            . '. Набор помогает быстрее собрать основные страницы сайта '
            . 'на готовой визуальной основе.';

        if ($facts['templateCount'] > 0) {
            $ruIntro = $product . ' — набор шаблонов Elementor'
                . ($developer !== '' ? ' от ' . $developer : '')
                . ($audienceRu !== '' ? ' для ' . $audienceRu : '')
                . '. В комплект входит более ' . $facts['templateCount']
                . ' готовых шаблонов для быстрого запуска сайта.';
        }

        $enIntro = $product . ' is an Elementor template kit'
            . ($developer !== '' ? ' by ' . $developer : '')
            . ($audienceEn !== '' ? ' for ' . $audienceEn : '')
            . '. It provides a ready visual foundation for building '
            . 'the main website pages faster.';

        if ($facts['templateCount'] > 0) {
            $enIntro = $product . ' is an Elementor template kit'
                . ($developer !== '' ? ' by ' . $developer : '')
                . ($audienceEn !== '' ? ' for ' . $audienceEn : '')
                . '. It includes ' . $facts['templateCount']
                . '+ ready-to-use templates for faster website creation.';
        }

        $ruImportant = $product . ' — это набор шаблонов Elementor, '
            . 'а не самостоятельная WordPress-тема. Шаблоны импортируются '
            . 'в существующий сайт и используются для создания страниц и секций.';
        $enImportant = $product . ' is an Elementor template kit, '
            . 'not a standalone WordPress theme. The templates are imported '
            . 'into an existing site and used to build pages and sections.';

        if ($facts['demoImagesLicense']) {
            $ruImportant .= ' Демо-изображения могут требовать отдельной '
                . 'лицензии Envato Elements или замены на собственные.';
            $enImportant .= ' Demo images may require a separate Envato Elements '
                . 'license or replacement with your own images.';
        }

        $ruShort = $product . ' — набор шаблонов Elementor'
            . ($audienceRu !== '' ? ' для ' . $audienceRu : '') . '.';
        $enShort = $product . ' is an Elementor template kit'
            . ($audienceEn !== '' ? ' for ' . $audienceEn : '') . '.';

        if ($facts['templateCount'] > 0) {
            $ruShort .= ' Включает более ' . $facts['templateCount']
                . ' готовых шаблонов';
            $enShort .= ' Includes ' . $facts['templateCount']
                . '+ ready-to-use templates';

            if ($facts['responsive']) {
                $ruShort .= ', адаптивный дизайн';
                $enShort .= ', responsive layouts';
            }

            if ($facts['elementorProRequired'] === false) {
                $ruShort .= '; Elementor Pro не требуется';
                $enShort .= '; Elementor Pro is not required';
            }

            $ruShort .= '.';
            $enShort .= '.';
        }

        $ruMeta = $this->metaDescription(
            $product,
            $sourceTags,
            $facts,
            'ru'
        );
        $enMeta = $this->metaDescription(
            $product,
            $sourceTags,
            $facts,
            'en'
        );

        return [
            'ruShort' => '<p>' . $this->text($ruShort) . '</p>',
            'ruLong' => $this->longHtml(
                $title,
                $ruIntro,
                $ruFeatures,
                $product . ($audienceRu !== ''
                    ? ' подходит для ' . $audienceRu . '.'
                    : ' подходит для бизнес-сайтов и корпоративных проектов.'),
                $ruCompat,
                $ruImportant,
                'ru'
            ),
            'ruMeta' => $ruMeta,
            'enShort' => '<p>' . $this->text($enShort) . '</p>',
            'enLong' => $this->longHtml(
                $title,
                $enIntro,
                $enFeatures,
                $product . ($audienceEn !== ''
                    ? ' is suitable for ' . $audienceEn . '.'
                    : ' is suitable for business and corporate websites.'),
                $enCompat,
                $enImportant,
                'en'
            ),
            'enMeta' => $enMeta,
        ];
    }

    /**
     * @param array{
     *   templateCount:int,
     *   elementorProRequired:?bool,
     *   responsive:bool,
     *   dragDrop:bool,
     *   globalStyles:bool,
     *   templates:list<string>,
     *   requiredPlugins:list<string>,
     *   helloElementor:bool,
     *   demoImagesLicense:bool,
     *   tags:list<string>
     * } $facts
     */
    public function factCount(array $facts): int
    {
        $count = $facts['templateCount'] > 0 ? 1 : 0;
        foreach ([
            'responsive',
            'dragDrop',
            'globalStyles',
            'helloElementor',
            'demoImagesLicense',
        ] as $key) {
            if ($facts[$key]) {
                ++$count;
            }
        }
        if ($facts['elementorProRequired'] !== null) {
            ++$count;
        }
        if ($facts['templates'] !== []) {
            ++$count;
        }
        if ($facts['requiredPlugins'] !== []) {
            ++$count;
        }

        return $count;
    }

    /**
     * @param array{
     *   templateCount:int,
     *   elementorProRequired:?bool,
     *   responsive:bool,
     *   dragDrop:bool,
     *   globalStyles:bool,
     *   templates:list<string>,
     *   requiredPlugins:list<string>,
     *   helloElementor:bool,
     *   demoImagesLicense:bool,
     *   tags:list<string>
     * } $facts
     */
    private function hasUsefulFacts(array $facts): bool
    {
        return $this->factCount($facts) >= 2;
    }

    /**
     * @param list<string> $features
     * @param list<string> $compatibility
     */
    private function longHtml(
        string $title,
        string $intro,
        array $features,
        string $audience,
        array $compatibility,
        string $important,
        string $language
    ): string {
        $featureHeading = $language === 'ru'
            ? 'Основные возможности'
            : 'Key features';
        $audienceHeading = $language === 'ru'
            ? 'Кому подходит'
            : 'Who it is for';
        $compatibilityHeading = $language === 'ru'
            ? 'Совместимость и требования'
            : 'Compatibility and requirements';
        $importantHeading = $language === 'ru'
            ? 'Что важно знать'
            : 'What to know';

        return '<h2>' . $this->text($title) . '</h2>'
            . '<p>' . $this->text($intro) . '</p>'
            . $this->listSection($featureHeading, $features)
            . '<h3>' . $this->text($audienceHeading) . '</h3>'
            . '<p>' . $this->text($audience) . '</p>'
            . $this->listSection($compatibilityHeading, $compatibility)
            . '<h3>' . $this->text($importantHeading) . '</h3>'
            . '<p>' . $this->text($important) . '</p>';
    }

    /** @param list<string> $items */
    private function listSection(string $heading, array $items): string
    {
        $html = '';
        foreach (array_values(array_unique($items)) as $item) {
            $item = rtrim(trim($item), " .;,:!?");
            if ($item !== '') {
                $html .= '<li>' . $this->text($item) . '.</li>';
            }
        }

        return '<h3>' . $this->text($heading) . '</h3><ul>'
            . $html . '</ul>';
    }

    /** @param list<string> $templates */
    private function templateSummary(array $templates, string $language): string
    {
        if ($templates === []) {
            return '';
        }

        if ($language === 'en') {
            $items = array_slice($templates, 0, 15);
            return 'Included layouts: ' . implode(', ', $items);
        }

        $map = [
            'homepage' => 'главная страница',
            'home' => 'главная страница',
            'about us' => 'страница «О компании»',
            'about' => 'страница «О компании»',
            'our team' => 'команда',
            'team' => 'команда',
            'services' => 'услуги',
            'service detail' => 'страница отдельной услуги',
            'pricing plan' => 'тарифы',
            'pricing' => 'тарифы',
            'faqs' => 'часто задаваемые вопросы',
            'faq' => 'часто задаваемые вопросы',
            '404' => 'страница 404',
            'latest article' => 'последние статьи',
            'blogs' => 'блог',
            'blog' => 'блог',
            'single blog' => 'страница записи блога',
            'contact us' => 'контакты',
            'contact' => 'контакты',
            'contact us form' => 'контактная форма',
            'subscribe form' => 'форма подписки',
            'header' => 'шапка сайта',
            'footer' => 'подвал сайта',
        ];

        $translated = [];
        foreach ($templates as $template) {
            $key = mb_strtolower(trim($template), 'UTF-8');
            if (isset($map[$key])) {
                $translated[] = $map[$key];
            }
        }

        $translated = array_values(array_unique($translated));
        if ($translated === []) {
            return '';
        }

        return 'В комплект входят: '
            . implode(', ', array_slice($translated, 0, 15));
    }

    /** @param list<string> $tags */
    private function audience(array $tags, string $language): string
    {
        $normalized = array_values(array_unique(array_map(
            static fn(string $tag): string =>
                mb_strtolower(trim($tag), 'UTF-8'),
            $tags
        )));
        $has = static fn(string ...$needles): bool =>
            array_intersect($needles, $normalized) !== [];

        $values = [];

        if ($has('consulting')) {
            $values[] = $language === 'ru'
                ? 'консалтинговых компаний'
                : 'consulting companies';
        }

        if ($has('advisor') && $has('finance', 'accounting')) {
            $values[] = $language === 'ru'
                ? 'финансовых консультантов'
                : 'financial advisors';
        } elseif ($has('accounting', 'finance')) {
            $values[] = $language === 'ru'
                ? 'финансовых компаний'
                : 'finance companies';
        } elseif ($has('advisor')) {
            $values[] = $language === 'ru'
                ? 'консультантов'
                : 'advisors';
        }

        if ($has('agency', 'marketing')) {
            $values[] = $language === 'ru'
                ? 'агентств'
                : 'agencies';
        }

        if ($has('corporate')) {
            $values[] = $language === 'ru'
                ? 'корпоративных проектов'
                : 'corporate projects';
        }

        if (count($values) < 4 && $has('startup')) {
            $values[] = $language === 'ru'
                ? 'стартапов'
                : 'startups';
        }

        if (
            $values === []
            && $has('business', 'company', 'service')
        ) {
            $values[] = $language === 'ru'
                ? 'бизнес-сайтов'
                : 'business websites';
        }

        return $this->joinNatural(
            array_values(array_unique(array_slice($values, 0, 4))),
            $language
        );
    }

    /** @param list<string> $tags */
    private function metaAudience(
        array $tags,
        string $language
    ): string {
        $normalized = array_values(array_unique(array_map(
            static fn(string $tag): string =>
                mb_strtolower(trim($tag), 'UTF-8'),
            $tags
        )));
        $has = static fn(string ...$needles): bool =>
            array_intersect($needles, $normalized) !== [];

        $values = [];

        if ($has('consulting')) {
            $values[] = $language === 'ru'
                ? 'консалтинговых'
                : 'consulting';
        }
        if ($has('finance', 'accounting', 'advisor')) {
            $values[] = $language === 'ru'
                ? 'финансовых'
                : 'finance';
        }
        if (count($values) < 2 && $has('agency', 'marketing')) {
            $values[] = $language === 'ru'
                ? 'агентских'
                : 'agency';
        }
        if (
            count($values) < 2
            && $has('corporate', 'business', 'company')
        ) {
            $values[] = $language === 'ru'
                ? 'корпоративных'
                : 'corporate';
        }
        if (count($values) < 2 && $has('startup')) {
            $values[] = $language === 'ru'
                ? 'стартап'
                : 'startup';
        }

        $values = array_values(array_unique(array_slice($values, 0, 2)));

        if ($values === []) {
            return $language === 'ru'
                ? 'бизнес-сайтов'
                : 'business websites';
        }

        if ($language === 'ru') {
            return implode(' и ', $values) . ' сайтов';
        }

        return implode(' and ', $values) . ' websites';
    }

    /**
     * @param list<string> $tags
     * @param array{
     *   templateCount:int,
     *   elementorProRequired:?bool,
     *   responsive:bool,
     *   dragDrop:bool,
     *   globalStyles:bool,
     *   templates:list<string>,
     *   requiredPlugins:list<string>,
     *   helloElementor:bool,
     *   demoImagesLicense:bool,
     *   tags:list<string>
     * } $facts
     */
    private function metaDescription(
        string $product,
        array $tags,
        array $facts,
        string $language
    ): string {
        $audience = $this->metaAudience(
            $tags,
            $language
        );

        if ($language === 'ru') {
            $meta = $product
                . ' — набор шаблонов Elementor для '
                . $audience . '.';

            if ($facts['templateCount'] > 0) {
                $meta .= ' ' . $facts['templateCount']
                    . '+ готовых шаблонов.';
            }

            if ($facts['elementorProRequired'] === false) {
                $meta .= ' Elementor Pro не требуется.';
            }

            return $this->limit(
                $meta,
                160
            );
        }

        $meta = $product
            . ' — Elementor template kit for '
            . $audience . '.';

        if ($facts['templateCount'] > 0) {
            $meta .= ' ' . $facts['templateCount']
                . '+ ready templates.';
        }

        if ($facts['elementorProRequired'] === false) {
            $meta .= ' Elementor Pro is not required.';
        }

        return $this->limit(
            $meta,
            160
        );
    }

    /** @param list<string> $values */
    private function joinNatural(
        array $values,
        string $language
    ): string {
        $count = count($values);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $values[0];
        }

        $last = array_pop($values);
        $conjunction = $language === 'ru'
            ? ' и '
            : ' and ';

        return implode(', ', $values)
            . $conjunction
            . $last;
    }

    private function productName(string $title): string
    {
        $parts = preg_split('/\s+[–—-]\s+/u', $title, 2) ?: [];
        $name = trim((string) ($parts[0] ?? $title));
        return $name !== '' ? $name : $title;
    }

    private function text(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function limit(string $value, int $limit): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(
            mb_substr($value, 0, $limit - 1, 'UTF-8'),
            " ,;:.-–—"
        ) . '…';
    }
}
