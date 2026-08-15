<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductManagerPage implements SubmenuPageInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductManagerController $controller,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-manager';
    }

    public function title(): string
    {
        return 'Product Manager';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $fields = $this->draftDefaults();
        $envatoUrl = $this->posted('envato_url');
        $logs = [];
        $success = null;
        $lastProductId = (int) ($this->call)(
            'get_option',
            'wp_shop_pm_last_created_product_id',
            0
        );
        $action = $this->posted('wp_shop_pm_action');
        $translationProductId = $lastProductId;
        $translationEnglish = $this->translationDefaults(
            $translationProductId
        );

        if ($action === 'autofill') {
            $this->verifyNonce('wp_shop_pm_autofill');
            $postedToken = trim($this->posted('envato_token'));

            if ($postedToken !== '') {
                $this->saveToken($postedToken);
            }

            $result = $this->controller->autofill(
                $envatoUrl,
                $this->token()
            );
            $logs = $result->logs;
            $success = $result->success;

            if ($result->success) {
                $fields = array_replace(
                    $fields,
                    $result->fields
                );
            }
        } elseif (
            $action === 'preflight_draft'
            || $action === 'create_draft'
        ) {
            $this->verifyNonce('wp_shop_pm_create_draft');
            $fields = $this->postedDraftFields($fields);

            try {
                $tags = $this->controller->parseExistingTags(
                    $fields['tags']
                );
                $data = $this->draftData($fields, $tags);
                $result = $action === 'preflight_draft'
                    ? $this->controller->preflightDraft($data)
                    : $this->controller->createDraft($data);
                $logs = $result->logs;
                $success = $result->success;

                if (
                    $action === 'create_draft'
                    && $result->productId !== null
                ) {
                    $lastProductId = $result->productId;
                    $translationProductId = $result->productId;
                    $translationEnglish = [
                        'short' => $fields['en_short_description'],
                        'long' => $fields['en_long_description'],
                        'meta' => $fields['en_meta_description'],
                    ];
                    ($this->call)(
                        'update_option',
                        'wp_shop_pm_last_created_product_id',
                        $result->productId,
                        false
                    );
                }
            } catch (Throwable $exception) {
                $success = false;
                $logs = [
                    $action === 'preflight_draft'
                        ? 'PREFLIGHT REQUEST = RECEIVED'
                        : 'CREATE REQUEST = RECEIVED',
                    'STOP: DRAFT NOT CREATED.',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ];
            }
        } elseif ($action === 'translate') {
            $this->verifyNonce('wp_shop_pm_translate');
            $translationProductId = (int) $this->posted(
                'translation_product_id'
            );
            $translationEnglish = [
                'short' => (string) ($this->call)(
                    'wp_kses_post',
                    $this->posted('translation_en_short')
                ),
                'long' => (string) ($this->call)(
                    'wp_kses_post',
                    $this->posted('translation_en_long')
                ),
                'meta' => (string) ($this->call)(
                    'sanitize_textarea_field',
                    $this->posted('translation_en_meta')
                ),
            ];
            $result = $this->controller->translate(
                $translationProductId,
                $translationEnglish['short'],
                $translationEnglish['long'],
                $translationEnglish['meta']
            );
            $logs = array_merge(
                ['TRANSLATION REQUEST = RECEIVED'],
                $result->logs
            );
            $success = $result->success;
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager</h1>';
        echo '<p>Permanent admin workflow migrated from the validated Product Manager v1.4.2 prototype.</p>';
        $this->renderLogs($logs, $success);
        $this->renderAutofillForm($envatoUrl);
        $this->renderDraftForm($fields);
        $this->renderTranslationForm(
            $translationProductId,
            $translationEnglish
        );
        echo '</div>';
    }

    /**
     * @return array<string, string>
     */
    private function draftDefaults(): array
    {
        return [
            'base_title' => '',
            'slug' => '',
            'item_id' => '',
            'version' => '',
            'source_update_date' => '',
            'developer' => '',
            'price' => '249',
            'sales_page' => '',
            'sku_filename' => '',
            'download_url' => '',
            'featured_image_id' => '',
            'tags' => '',
            'short_description' => '',
            'long_description' => '',
            'meta_description' => '',
            'en_short_description' => '',
            'en_long_description' => '',
            'en_meta_description' => '',
            'notes' => '⚠ Продукт предварительно активирован.',
            'label_hit' => '0',
            'label_new' => '0',
        ];
    }

    /**
     * @param array<string, string> $defaults
     * @return array<string, string>
     */
    private function postedDraftFields(array $defaults): array
    {
        $fields = [];

        foreach (array_keys($defaults) as $key) {
            if ($key === 'label_hit' || $key === 'label_new') {
                $fields[$key] = $this->posted($key) === '1'
                    ? '1'
                    : '0';
                continue;
            }

            $fields[$key] = $this->posted($key);
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     * @param list<\WPShop\App\Plugin\ProductManager\Tags\CatalogTag> $tags
     */
    private function draftData(
        array $fields,
        array $tags
    ): ProductDraftData {
        return new ProductDraftData(
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['base_title']
            ),
            (string) ($this->call)(
                'sanitize_title',
                $fields['slug']
            ),
            (int) $fields['item_id'],
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['version']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['source_update_date']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['developer']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['price']
            ),
            (string) ($this->call)(
                'esc_url_raw',
                $fields['sales_page']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['sku_filename']
            ),
            (string) ($this->call)(
                'esc_url_raw',
                $fields['download_url']
            ),
            (int) $fields['featured_image_id'],
            $tags,
            (string) ($this->call)(
                'wp_kses_post',
                $fields['short_description']
            ),
            (string) ($this->call)(
                'wp_kses_post',
                $fields['long_description']
            ),
            (string) ($this->call)(
                'sanitize_textarea_field',
                $fields['meta_description']
            ),
            (string) ($this->call)(
                'wp_kses_post',
                $fields['en_short_description']
            ),
            (string) ($this->call)(
                'wp_kses_post',
                $fields['en_long_description']
            ),
            (string) ($this->call)(
                'sanitize_textarea_field',
                $fields['en_meta_description']
            ),
            (string) ($this->call)(
                'wp_kses_post',
                $fields['notes']
            ),
            $fields['label_hit'] === '1',
            $fields['label_new'] === '1'
        );
    }

    private function renderAutofillForm(string $envatoUrl): void
    {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">1. Envato Autofill</h2>';
        echo '<p>ThemeForest facts are loaded through the Envato API. The token is stored only on this WordPress installation.</p>';
        echo '<form method="post">';
        $this->nonceField('wp_shop_pm_autofill');
        $this->hiddenAction('autofill');
        $this->input(
            'ThemeForest URL',
            'envato_url',
            $envatoUrl,
            'url',
            'https://themeforest.net/item/.../12345678'
        );
        $this->input(
            'Envato Personal Token',
            'envato_token',
            '',
            'password',
            $this->token() !== ''
                ? 'Token already saved — leave empty'
                : 'Paste token once'
        );
        $this->submit('Получить данные Envato', 'secondary');
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param array<string, string> $fields
     */
    private function renderDraftForm(array $fields): void
    {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">2. Review & Create Draft</h2>';
        echo '<p><strong>Safety:</strong> RU Short + Long + SureRank Meta are required. Tags must already exist in both <code>product_tag</code> and <code>pa_tags</code>. Hit/New are editorial only.</p>';
        echo '<p><strong>Version check:</strong> before creating the Draft, compare the Version field with the ThemeForest changelog. Envato machine-readable version metadata can lag behind the author changelog.</p>';
        echo '<p><strong>Preflight:</strong> checks Version → SKU, required fields, slug, SKU conflicts, Item ID and existing tags without writing a WooCommerce product.</p>';
        echo '<form method="post">';
        $this->nonceField('wp_shop_pm_create_draft');
        $this->hiddenAction('create_draft');

        foreach (
            [
                ['Base title', 'base_title', 'text'],
                ['Slug', 'slug', 'text'],
                ['ThemeForest Item ID', 'item_id', 'number'],
                ['Version', 'version', 'text'],
                ['Official update date', 'source_update_date', 'date'],
                ['Developer', 'developer', 'text'],
                ['Price', 'price', 'text'],
                ['Sales Page', 'sales_page', 'url'],
                ['SKU / ZIP filename', 'sku_filename', 'text'],
                ['Download URL (optional)', 'download_url', 'url'],
                ['Featured Image ID (optional)', 'featured_image_id', 'number'],
            ] as $field
        ) {
            $this->input(
                $field[0],
                $field[1],
                $fields[$field[1]],
                $field[2]
            );
        }

        $this->textarea(
            'Existing Tags — Name|slug',
            'tags',
            $fields['tags'],
            10
        );
        $this->textarea(
            'RU Short Description HTML',
            'short_description',
            $fields['short_description'],
            6
        );
        $this->textarea(
            'RU Long Description HTML',
            'long_description',
            $fields['long_description'],
            24
        );
        $this->textarea(
            'SureRank Meta Description RU',
            'meta_description',
            $fields['meta_description'],
            4
        );
        $this->textarea(
            'EN Short Description HTML',
            'en_short_description',
            $fields['en_short_description'],
            6
        );
        $this->textarea(
            'EN Long Description HTML',
            'en_long_description',
            $fields['en_long_description'],
            24
        );
        $this->textarea(
            'SureRank Meta Description EN',
            'en_meta_description',
            $fields['en_meta_description'],
            4
        );
        $this->textarea(
            'Notes',
            'notes',
            $fields['notes'],
            3
        );
        $this->checkbox(
            'Hit — popular product',
            'label_hit',
            $fields['label_hit'] === '1'
        );
        $this->checkbox(
            'New — product itself is newly released by developer',
            'label_new',
            $fields['label_new'] === '1'
        );
        echo '<p style="display:flex;gap:10px;align-items:center;">';
        echo '<button type="submit" class="button button-secondary" onclick="this.form.elements[\'wp_shop_pm_action\'].value=\'preflight_draft\';">Проверить Draft без создания</button>';
        echo '<button type="submit" class="button button-primary" onclick="this.form.elements[\'wp_shop_pm_action\'].value=\'create_draft\';">Создать новый товар как Draft</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param array{short: string, long: string, meta: string} $english
     */
    private function renderTranslationForm(
        int $productId,
        array $english
    ): void {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">3. Universal EN Translation</h2>';
        echo '<p>Run only after the product is published. Existing finished TranslatePress EN strings are preserved.</p>';
        echo '<form method="post">';
        $this->nonceField('wp_shop_pm_translate');
        $this->hiddenAction('translate');
        $this->input(
            'Product ID',
            'translation_product_id',
            $productId > 0 ? (string) $productId : '',
            'number'
        );
        $this->textarea(
            'EN Short Description HTML',
            'translation_en_short',
            $english['short'],
            6
        );
        $this->textarea(
            'EN Long Description HTML',
            'translation_en_long',
            $english['long'],
            24
        );
        $this->textarea(
            'SureRank Meta Description EN',
            'translation_en_meta',
            $english['meta'],
            4
        );
        $this->submit(
            'Создать / проверить EN перевод',
            'primary'
        );
        echo '</form>';
        echo '</div>';
    }

    /**
     * @return array{short: string, long: string, meta: string}
     */
    private function translationDefaults(int $productId): array
    {
        if ($productId <= 0) {
            return [
                'short' => '',
                'long' => '',
                'meta' => '',
            ];
        }

        return [
            'short' => (string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_en_short_description',
                true
            ),
            'long' => (string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_en_long_description',
                true
            ),
            'meta' => (string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_en_meta_description',
                true
            ),
        ];
    }

    /**
     * @param list<string> $logs
     */
    private function renderLogs(
        array $logs,
        ?bool $success
    ): void {
        if ($logs === []) {
            return;
        }

        $color = $success === true
            ? '#00a32a'
            : '#d63638';
        echo '<div style="max-width:1100px;background:#fff;border-left:4px solid '
            . $this->escape($color)
            . ';padding:12px 16px;margin:15px 0 20px;">';
        echo '<strong>PRODUCT MANAGER LOG</strong>';
        echo '<pre style="white-space:pre-wrap;margin-bottom:0;">'
            . $this->escape(implode("\n", $logs))
            . '</pre>';
        echo '</div>';
    }

    private function input(
        string $label,
        string $name,
        string $value,
        string $type = 'text',
        string $placeholder = ''
    ): void {
        echo '<p><label><strong>'
            . $this->escape($label)
            . '</strong><br><input style="width:900px;max-width:100%;" type="'
            . $this->escape($type)
            . '" name="'
            . $this->escape($name)
            . '" value="'
            . $this->escape($value)
            . '" placeholder="'
            . $this->escape($placeholder)
            . '"></label></p>';
    }

    private function textarea(
        string $label,
        string $name,
        string $value,
        int $rows
    ): void {
        echo '<p><label><strong>'
            . $this->escape($label)
            . '</strong><br><textarea style="width:1000px;max-width:100%;font-family:monospace;" rows="'
            . $rows
            . '" name="'
            . $this->escape($name)
            . '">'
            . $this->escape($value)
            . '</textarea></label></p>';
    }

    private function checkbox(
        string $label,
        string $name,
        bool $checked
    ): void {
        echo '<p><label><input type="checkbox" name="'
            . $this->escape($name)
            . '" value="1" '
            . ($checked ? 'checked' : '')
            . '> '
            . $this->escape($label)
            . '</label></p>';
    }

    private function submit(
        string $label,
        string $class
    ): void {
        ($this->call)(
            'submit_button',
            $label,
            $class,
            'submit',
            true
        );
    }

    private function hiddenAction(string $action): void
    {
        echo '<input type="hidden" name="wp_shop_pm_action" value="'
            . $this->escape($action)
            . '">';
    }

    private function nonceField(string $action): void
    {
        ($this->call)(
            'wp_nonce_field',
            $action,
            '_wpnonce',
            true,
            true
        );
    }

    private function verifyNonce(string $action): void
    {
        ($this->call)(
            'check_admin_referer',
            $action,
            '_wpnonce'
        );
    }

    private function token(): string
    {
        if (defined('WP_SHOP_ENVATO_TOKEN')) {
            $configured = constant('WP_SHOP_ENVATO_TOKEN');

            if (is_string($configured) && trim($configured) !== '') {
                return trim($configured);
            }
        }

        return trim(
            (string) ($this->call)(
                'get_option',
                'wp_shop_envato_personal_token',
                ''
            )
        );
    }

    private function saveToken(string $token): void
    {
        ($this->call)(
            'update_option',
            'wp_shop_envato_personal_token',
            trim($token),
            false
        );
    }

    private function posted(string $key): string
    {
        $value = $_POST[$key] ?? '';

        if (! is_string($value)) {
            return '';
        }

        return (string) ($this->call)(
            'wp_unslash',
            $value
        );
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)(
            'esc_html',
            $value
        );
    }
}
