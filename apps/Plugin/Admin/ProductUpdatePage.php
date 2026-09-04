<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveUpdateCoordinator;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateCandidateClassifier;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateManualCandidateBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateSnapshot;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateSuggestion;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductUpdatePage implements SubmenuPageInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductVersionUpdater $updater,
        private readonly ProductUpdateEnvatoAdvisor $advisor,
        private readonly ProductUpdateManualCandidateBuilder $manualCandidateBuilder,
        private readonly Closure $call,
        private readonly ?ProductArchiveUpdateCoordinator $archiveCoordinator = null
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-update';
    }

    public function title(): string
    {
        return 'Update Product';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $fields = $this->defaults();
        $logs = [];
        $success = null;
        $action = $this->posted('wp_shop_pm_update_action');

        if ($action === 'load_product') {
            $this->verifyNonce('wp_shop_pm_load_product');
            $productId = (int) $this->posted('update_product_id');

            try {
                $snapshot = $this->updater->load($productId);
                $fields = $this->fieldsFromSnapshot($snapshot);
                $logs = $this->loadLogs($snapshot);
                [$fields, $envatoLogs] = $this->withEnvatoSuggestion(
                    $fields,
                    $snapshot
                );
                $logs = array_merge($logs, $envatoLogs);
                $logs[] = 'NO PRODUCT WRITTEN = YES';
                $success = true;
            } catch (Throwable $exception) {
                $logs = [
                    'UPDATE LOAD = FAILED',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                    'NO PRODUCT WRITTEN = YES',
                ];
                $success = false;
            }
        } elseif ($action === 'prepare_manual_candidate') {
            $this->verifyNonce('wp_shop_pm_update_product');
            $fields = $this->postedFields($fields);
            [$fields, $logs, $success] = $this->prepareManualCandidate(
                $fields
            );
        } elseif (
            $action === 'preflight_update'
            || $action === 'apply_update'
        ) {
            $this->verifyNonce('wp_shop_pm_update_product');
            $fields = $this->postedFields($fields);
            $data = $this->data($fields);

            if ($action === 'preflight_update') {
                $result = $this->archiveCoordinator !== null
                    ? $this->archiveCoordinator->preflight(
                        $data,
                        $this->uploadedFile('update_archive_zip')
                    )
                    : $this->updater->preflight($data);
            } elseif ($this->archiveCoordinator !== null) {
                $result = $this->archiveCoordinator->update(
                    $data,
                    $this->uploadedFile('update_archive_zip')
                );
            } else {
                $result = $this->updater->update($data);
            }

            $logs = $result->logs;
            $success = $result->success;

            if ($action === 'apply_update' && $result->success) {
                [$fields, $refreshLogs] = $this->refreshAfterApply($fields);
                $logs = array_merge($logs, $refreshLogs);
            }
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Update Product</h1>';
        echo '<p>Safe version/file update mode for an existing WooCommerce product. Editorial RU/EN content, tags, attributes, image and Advanced Labels are preserved.</p>';
        $this->renderLogs($logs, $success);
        $this->renderLoadForm($fields['product_id']);
        $this->renderUpdateForm($fields);
        echo '</div>';
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'product_id' => '',
            'status' => '',
            'base_title' => '',
            'item_id' => '',
            'current_version' => '',
            'current_update_date' => '',
            'envato_version' => '',
            'envato_update_date' => '',
            'version' => '',
            'source_update_date' => '',
            'sales_page' => '',
            'current_sku' => '',
            'expected_sku' => '',
            'current_download_url' => '',
            'download_url' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fieldsFromSnapshot(
        ProductUpdateSnapshot $snapshot
    ): array {
        return [
            'product_id' => (string) $snapshot->productId,
            'status' => $snapshot->status,
            'base_title' => $snapshot->baseTitle,
            'item_id' => (string) $snapshot->itemId,
            'current_version' => $snapshot->version,
            'current_update_date' => $snapshot->sourceUpdateDate,
            'envato_version' => '',
            'envato_update_date' => '',
            'version' => '',
            'source_update_date' => $snapshot->sourceUpdateDate,
            'sales_page' => $snapshot->salesPage,
            'current_sku' => $snapshot->skuFilename,
            'expected_sku' => '',
            'current_download_url' => $snapshot->downloadUrl,
            'download_url' => '',
        ];
    }

    /**
     * @return list<string>
     */
    private function loadLogs(ProductUpdateSnapshot $snapshot): array
    {
        return [
            'UPDATE LOAD = READY',
            'PRODUCT ID = ' . $snapshot->productId,
            'STATUS = ' . $snapshot->status,
            'CURRENT VERSION = ' . (
                $snapshot->version !== ''
                    ? $snapshot->version
                    : '[empty]'
            ),
            'ENVATO ITEM ID = ' . (
                $snapshot->itemId > 0
                    ? $snapshot->itemId
                    : 'REVIEW_REQUIRED'
            ),
            'CURRENT SKU = ' . (
                $snapshot->skuFilename !== ''
                    ? $snapshot->skuFilename
                    : '[empty]'
            ),
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array{array<string, string>, list<string>}
     */
    private function withEnvatoSuggestion(
        array $fields,
        ProductUpdateSnapshot $snapshot
    ): array {
        $token = $this->token();

        if ($token === '') {
            return [
                $fields,
                [
                    'ENVATO CHECK = SKIPPED: TOKEN MISSING',
                    'NEW VERSION = MANUAL REVIEW REQUIRED',
                ],
            ];
        }

        try {
            $suggestion = $this->advisor->suggest(
                $snapshot,
                $token
            );
        } catch (Throwable $exception) {
            return [
                $fields,
                [
                    'ENVATO CHECK = FAILED',
                    'ENVATO ERROR = ' . $exception->getMessage(),
                    'NEW VERSION = MANUAL REVIEW REQUIRED',
                ],
            ];
        }

        $fields = $this->applySuggestion(
            $fields,
            $suggestion
        );
        $candidateLabel = ProductUpdateCandidateClassifier::label(
            $snapshot,
            $suggestion
        );

        return [
            $fields,
            [
                'ENVATO CHECK = READY',
                'ENVATO VERSION = ' . (
                    $suggestion->version !== ''
                        ? $suggestion->version
                        : '[empty]'
                ),
                'ENVATO UPDATE DATE = ' . (
                    $suggestion->updateDate !== ''
                        ? $suggestion->updateDate
                        : '[empty]'
                ),
                'UPDATE CANDIDATE = ' . $candidateLabel,
                'ENVATO VERSION = SUGGESTION ONLY; VERIFY CHANGELOG',
                'EXPECTED SKU = ' . (
                    $suggestion->skuFilename !== ''
                        ? $suggestion->skuFilename
                        : '[manual after version review]'
                ),
                'SUGGESTED DOWNLOAD URL = ' . (
                    $suggestion->downloadUrl !== ''
                        ? $suggestion->downloadUrl
                        : '[manual]'
                ),
            ],
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array{array<string, string>, list<string>, bool}
     */
    private function prepareManualCandidate(array $fields): array
    {
        $logs = [
            'MANUAL CANDIDATE REQUEST = RECEIVED',
            'NO PRODUCT WRITTEN = YES',
        ];

        try {
            $suggestion = $this->manualCandidateBuilder->build(
                (int) $fields['item_id'],
                $fields['sales_page'],
                $fields['version'],
                $fields['current_download_url']
            );
        } catch (Throwable $exception) {
            $logs[] = 'MANUAL CANDIDATE = FAILED';
            $logs[] = 'ERROR MESSAGE: ' . $exception->getMessage();

            return [$fields, $logs, false];
        }

        $fields['expected_sku'] = $suggestion->skuFilename;
        $fields['download_url'] = $suggestion->downloadUrl;
        $logs[] = 'NEW VERSION = SOURCE OF TRUTH: '
            . $suggestion->version;
        $logs[] = 'EXPECTED SKU = ' . $suggestion->skuFilename;
        $logs[] = 'SUGGESTED DOWNLOAD URL = ' . (
            $suggestion->downloadUrl !== ''
                ? $suggestion->downloadUrl
                : '[manual]'
        );
        $logs[] = 'MANUAL CANDIDATE = READY';

        return [$fields, $logs, true];
    }

    /**
     * @param array<string, string> $fields
     * @return array{array<string, string>, list<string>}
     */
    private function refreshAfterApply(array $fields): array
    {
        try {
            $snapshot = $this->updater->load((int) $fields['product_id']);
        } catch (Throwable $exception) {
            return [
                $fields,
                [
                    'FORM REFRESH = FAILED',
                    'FORM REFRESH ERROR = ' . $exception->getMessage(),
                    'RELOAD PRODUCT BEFORE NEXT UPDATE',
                ],
            ];
        }

        return [
            $this->fieldsFromSnapshot($snapshot),
            [
                'FORM REFRESH = READY',
                'CURRENT VERSION REFRESHED = ' . (
                    $snapshot->version !== ''
                        ? $snapshot->version
                        : '[empty]'
                ),
                'CURRENT UPDATE DATE REFRESHED = ' . (
                    $snapshot->sourceUpdateDate !== ''
                        ? $snapshot->sourceUpdateDate
                        : '[empty]'
                ),
                'CURRENT SKU REFRESHED = ' . (
                    $snapshot->skuFilename !== ''
                        ? $snapshot->skuFilename
                        : '[empty]'
                ),
                'NEXT UPDATE CANDIDATE = CLEARED',
            ],
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private function applySuggestion(
        array $fields,
        ProductUpdateSuggestion $suggestion
    ): array {
        $fields['envato_version'] = $suggestion->version;
        $fields['envato_update_date'] = $suggestion->updateDate;
        $fields['expected_sku'] = $suggestion->skuFilename;

        if ($suggestion->version !== '') {
            $fields['version'] = $suggestion->version;
        }

        if ($suggestion->updateDate !== '') {
            $fields['source_update_date'] = $suggestion->updateDate;
        }

        if ($suggestion->downloadUrl !== '') {
            $fields['download_url'] = $suggestion->downloadUrl;
        }

        return $fields;
    }

    /**
     * @param array<string, string> $defaults
     * @return array<string, string>
     */
    private function postedFields(array $defaults): array
    {
        $fields = [];

        foreach (array_keys($defaults) as $key) {
            $fields[$key] = $this->posted('update_' . $key);
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private function data(array $fields): ProductUpdateData
    {
        return new ProductUpdateData(
            (int) $fields['product_id'],
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['base_title']
            ),
            (int) $fields['item_id'],
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['current_version']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['version']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['source_update_date']
            ),
            (string) ($this->call)(
                'esc_url_raw',
                $fields['sales_page']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['current_sku']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['expected_sku'] !== ''
                    ? $fields['expected_sku']
                    : $fields['current_sku']
            ),
            (string) ($this->call)(
                'esc_url_raw',
                $fields['download_url']
            )
        );
    }

    private function renderLoadForm(string $productId): void
    {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">1. Load Existing Product</h2>';
        echo '<p>Enter the WooCommerce Product ID. Loading is read-only and also requests an Envato update suggestion when the saved token is available.</p>';
        echo '<form method="post">';
        $this->nonceField('wp_shop_pm_load_product');
        $this->hiddenAction('load_product');
        $this->input(
            'Product ID',
            'update_product_id',
            $productId,
            'number'
        );
        $this->submit('Загрузить товар + проверить Envato', 'secondary');
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param array<string, string> $fields
     */
    private function renderUpdateForm(array $fields): void
    {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">2. Review & Update Version</h2>';
        echo '<p><strong>Safety:</strong> Envato values are suggestions only. Verify the public Envato changelog; the manually reviewed New Version remains the source of truth. Always run Preflight before Apply.</p>';
        echo '<p><strong>ZIP update:</strong> before Apply, select the downloaded source ZIP. Product Manager validates it, builds the canonical new filename, routes it to the product storage folder and updates the WooCommerce download entry. If the same canonical file already exists, it is backed up until the product update succeeds.</p>';
        echo '<p><strong>Manual candidate:</strong> after entering a verified New Version, use the manual prepare button to rebuild the canonical SKU/ZIP name and suggested Download URL without writing the product.</p>';
        echo '<p><strong>Preserved:</strong> RU/EN descriptions, SureRank content, tags, taxonomies, attributes, featured image and Hit/New labels are not rewritten.</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        $this->nonceField('wp_shop_pm_update_product');
        $this->hiddenAction('apply_update');

        foreach (
            [
                ['Product ID', 'product_id', 'number'],
                ['Current status', 'status', 'text'],
                ['Base title', 'base_title', 'text'],
                ['Envato Item ID', 'item_id', 'number'],
                ['Current Version', 'current_version', 'text'],
                ['Current official update date', 'current_update_date', 'date'],
                ['Envato suggested Version', 'envato_version', 'text'],
                ['Envato suggested update date', 'envato_update_date', 'date'],
                ['New Version — manual source of truth', 'version', 'text'],
                ['Official update date — manual source of truth', 'source_update_date', 'date'],
                ['Sales Page', 'sales_page', 'url'],
                ['Current SKU / ZIP filename', 'current_sku', 'text'],
                ['Expected new SKU / ZIP filename', 'expected_sku', 'text'],
                ['Current Download URL (reference only)', 'current_download_url', 'url'],
                ['New Download URL', 'download_url', 'url'],
            ] as $field
        ) {
            $this->input(
                $field[0],
                'update_' . $field[1],
                $fields[$field[1]],
                $field[2]
            );
        }

        $this->fileInput(
            'New Product ZIP (recommended for Apply)',
            'update_archive_zip'
        );
        echo '<p style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
        echo '<button type="submit" class="button button-secondary" onclick="this.form.elements[\'wp_shop_pm_update_action\'].value=\'prepare_manual_candidate\';">Подготовить SKU/ZIP по New Version</button>';
        echo '<button type="submit" class="button button-secondary" onclick="this.form.elements[\'wp_shop_pm_update_action\'].value=\'preflight_update\';">Проверить Update без записи</button>';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Обновить существующий товар? Сначала убедитесь, что Preflight = READY.\');">Обновить товар</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
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
        echo '<strong>PRODUCT UPDATE LOG</strong>';
        echo '<pre style="white-space:pre-wrap;margin-bottom:0;">'
            . $this->escape(implode("\n", $logs))
            . '</pre>';
        echo '</div>';
    }

    private function input(
        string $label,
        string $name,
        string $value,
        string $type = 'text'
    ): void {
        echo '<p><label><strong>'
            . $this->escape($label)
            . '</strong><br><input style="width:900px;max-width:100%;" type="'
            . $this->escape($type)
            . '" name="'
            . $this->escape($name)
            . '" value="'
            . $this->escape($value)
            . '"></label></p>';
    }

    private function fileInput(string $label, string $name): void
    {
        echo '<p><label><strong>'
            . $this->escape($label)
            . '</strong><br><input type="file" accept=".zip,application/zip" name="'
            . $this->escape($name)
            . '"></label><br><span class="description">Исходное имя ZIP не важно. Файл переименовывается только при Apply. После Prepare/Preflight выберите ZIP заново.</span></p>';
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
        echo '<input type="hidden" name="wp_shop_pm_update_action" value="'
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

    /**
     * @return array<string, mixed>
     */
    private function uploadedFile(string $key): array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : [];
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)(
            'esc_html',
            $value
        );
    }
}
