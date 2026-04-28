<?php

namespace ProofAge\WordPress\Admin;

use ProofAge\WordPress\Support\LocalizedGateTexts;
use ProofAge\WordPress\Support\Options;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsRegistrar
{
    public const PAGE_SLUG = 'proofage-age-verification';

    public function __construct(private readonly ScopeSelectorProvider $scopeSelectorProvider)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE_SLUG,
            Options::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [Options::class, 'sanitize'],
                'default' => Options::defaults(),
            ]
        );

        add_settings_section(
            'proofage_api',
            __('ProofAge API', 'proofage-age-verification'),
            '__return_empty_string',
            self::PAGE_SLUG
        );

        add_settings_section(
            'proofage_behavior',
            __('Verification behavior', 'proofage-age-verification'),
            '__return_empty_string',
            self::PAGE_SLUG
        );

        add_settings_section(
            'proofage_scope',
            __('Protection scope', 'proofage-age-verification'),
            '__return_empty_string',
            self::PAGE_SLUG
        );

        add_settings_section(
            'proofage_texts',
            __('Gate texts', 'proofage-age-verification'),
            [$this, 'renderGateTextsSectionDescription'],
            self::PAGE_SLUG
        );

        foreach ($this->fieldDefinitions() as $field) {
            add_settings_field(
                $field['key'],
                $field['label'],
                [$this, 'renderField'],
                self::PAGE_SLUG,
                $field['section'],
                $field
            );
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function renderField(array $field): void
    {
        $options = Options::all();
        $value = $options[$field['key']] ?? '';
        $description = $field['description'] ?? '';

        echo '<div class="proofage-field">';

        switch ($field['type']) {
            case 'checkbox':
                printf(
                    '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
                    esc_attr(Options::OPTION_KEY),
                    esc_attr($field['key']),
                    checked((bool) $value, true, false),
                    esc_html($description)
                );
                break;

            case 'select':
                printf(
                    '<select name="%1$s[%2$s]" id="%2$s">',
                    esc_attr(Options::OPTION_KEY),
                    esc_attr($field['key'])
                );

                foreach ($field['options'] as $optionValue => $optionLabel) {
                    printf(
                        '<option value="%1$s" %2$s>%3$s</option>',
                        esc_attr((string) $optionValue),
                        selected((string) $value, (string) $optionValue, false),
                        esc_html((string) $optionLabel)
                    );
                }

                echo '</select>';
                break;

            case 'selector':
                $this->renderSelectorField($field, is_array($value) ? $value : [], $options);
                break;

            case 'textarea':
                printf(
                    '<textarea class="large-text" rows="4" name="%1$s[%2$s]" id="%2$s">%3$s</textarea>',
                    esc_attr(Options::OPTION_KEY),
                    esc_attr($field['key']),
                    esc_textarea(is_array($value) ? implode(', ', $value) : (string) $value)
                );
                break;

            default:
                $inputValue = is_array($value) ? implode(', ', $value) : (string) $value;
                $extraDescription = '';

                if ($field['type'] === 'password' && $inputValue !== '') {
                    $extraDescription = ' ' . sprintf(
                        /* translators: %s is a masked secret key preview. */
                        __('Current value: %s', 'proofage-age-verification'),
                        esc_html($this->maskValue($inputValue))
                    );
                    $inputValue = '';
                }

                printf(
                    '<input class="regular-text" type="%4$s" name="%1$s[%2$s]" id="%2$s" value="%3$s" />',
                    esc_attr(Options::OPTION_KEY),
                    esc_attr($field['key']),
                    esc_attr($inputValue),
                    esc_attr($field['type'])
                );

                if ($extraDescription !== '') {
                    $description .= $extraDescription;
                }
                break;
        }

        if (! in_array($field['type'], ['checkbox', 'selector'], true) && $description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }

        echo '</div>';
    }

    public function renderGateTextsSectionDescription(): void
    {
        $hint = LocalizedGateTexts::getSettingsHint();

        if ($hint === '') {
            return;
        }

        printf('<p class="description">%s</p>', esc_html($hint));
    }

    private function maskValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldDefinitions(): array
    {
        return [
            [
                'key' => 'api_key',
                'label' => __('API key', 'proofage-age-verification'),
                'section' => 'proofage_api',
                'type' => 'text',
                'description' => __('Public ProofAge workspace key.', 'proofage-age-verification'),
            ],
            [
                'key' => 'secret_key',
                'label' => __('Secret key', 'proofage-age-verification'),
                'section' => 'proofage_api',
                'type' => 'password',
                'description' => __('Secret key used for HMAC signing. Stored in WordPress options.', 'proofage-age-verification'),
            ],
            [
                'key' => 'site_enabled',
                'label' => __('Enable site-wide protection', 'proofage-age-verification'),
                'section' => 'proofage_behavior',
                'type' => 'checkbox',
                'description' => __('Require verification for the entire site unless an exclusion overrides it.', 'proofage-age-verification'),
            ],
            [
                'key' => 'launch_mode',
                'label' => __('Launch mode', 'proofage-age-verification'),
                'section' => 'proofage_behavior',
                'type' => 'select',
                'options' => Options::supportedLaunchModes(),
                'description' => __('Choose whether hosted verification opens in an iframe modal, the current window, or a new tab.', 'proofage-age-verification'),
            ],
            [
                'key' => 'content_display_mode',
                'label' => __('Protected content display', 'proofage-age-verification'),
                'section' => 'proofage_behavior',
                'type' => 'select',
                'options' => Options::supportedDisplayModes(),
                'description' => __('Choose whether protected pages use a full-page gate or a blocking overlay. Cart and checkout always stay on the full-page gate.', 'proofage-age-verification'),
            ],
            [
                'key' => 'session_ttl_hours',
                'label' => __('Verification TTL (hours)', 'proofage-age-verification'),
                'section' => 'proofage_behavior',
                'type' => 'number',
                'description' => __('How long an approved verification remains valid.', 'proofage-age-verification'),
            ],
            [
                'key' => 'protected_category_ids',
                'label' => __('Protected WooCommerce categories', 'proofage-age-verification'),
                'section' => 'proofage_scope',
                'type' => 'selector',
                'enabled_key' => 'protect_wc_categories_enabled',
                'source' => ScopeSelectorProvider::SOURCE_WC_CATEGORIES,
                'requires_woocommerce' => true,
                'description' => __('Require verification for selected WooCommerce product categories.', 'proofage-age-verification'),
            ],
            [
                'key' => 'protected_product_ids',
                'label' => __('Protected WooCommerce products', 'proofage-age-verification'),
                'section' => 'proofage_scope',
                'type' => 'selector',
                'enabled_key' => 'protect_wc_products_enabled',
                'source' => ScopeSelectorProvider::SOURCE_WC_PRODUCTS,
                'requires_woocommerce' => true,
                'description' => __('Require verification for selected WooCommerce products.', 'proofage-age-verification'),
            ],
            [
                'key' => 'excluded_category_ids',
                'label' => __('Excluded WooCommerce categories', 'proofage-age-verification'),
                'section' => 'proofage_scope',
                'type' => 'selector',
                'enabled_key' => 'exclude_wc_categories_enabled',
                'source' => ScopeSelectorProvider::SOURCE_WC_CATEGORIES,
                'requires_woocommerce' => true,
                'description' => __('Allow selected WooCommerce categories to bypass broader protection rules.', 'proofage-age-verification'),
            ],
            [
                'key' => 'excluded_product_ids',
                'label' => __('Excluded WooCommerce products', 'proofage-age-verification'),
                'section' => 'proofage_scope',
                'type' => 'selector',
                'enabled_key' => 'exclude_wc_products_enabled',
                'source' => ScopeSelectorProvider::SOURCE_WC_PRODUCTS,
                'requires_woocommerce' => true,
                'description' => __('Allow selected WooCommerce products to bypass broader protection rules.', 'proofage-age-verification'),
            ],
            [
                'key' => 'protected_wp_category_ids',
                'label' => __('Protected WordPress post categories', 'proofage-age-verification'),
                'section' => 'proofage_scope',
                'type' => 'selector',
                'enabled_key' => 'protect_wp_categories_enabled',
                'source' => ScopeSelectorProvider::SOURCE_WP_CATEGORIES,
                'description' => __('Require verification for posts and category archives that belong to selected WordPress categories.', 'proofage-age-verification'),
            ],
            [
                'key' => 'protected_page_ids',
                'label' => __('Protected WordPress pages', 'proofage-age-verification'),
                'section' => 'proofage_scope',
                'type' => 'selector',
                'enabled_key' => 'protect_wp_pages_enabled',
                'source' => ScopeSelectorProvider::SOURCE_WP_PAGES,
                'description' => __('Require verification for selected WordPress pages.', 'proofage-age-verification'),
            ],
            [
                'key' => 'gate_title',
                'label' => __('Gate title', 'proofage-age-verification'),
                'section' => 'proofage_texts',
                'type' => 'text',
                'description' => __('Headline displayed on the verification gate.', 'proofage-age-verification'),
            ],
            [
                'key' => 'gate_description',
                'label' => __('Gate description', 'proofage-age-verification'),
                'section' => 'proofage_texts',
                'type' => 'textarea',
                'description' => __('Description explaining why verification is required.', 'proofage-age-verification'),
            ],
            [
                'key' => 'verify_button_label',
                'label' => __('Verify button label', 'proofage-age-verification'),
                'section' => 'proofage_texts',
                'type' => 'text',
                'description' => __('Primary call to action for the gate.', 'proofage-age-verification'),
            ],
            [
                'key' => 'success_message',
                'label' => __('Success message', 'proofage-age-verification'),
                'section' => 'proofage_texts',
                'type' => 'text',
                'description' => __('Message shown when verification succeeds.', 'proofage-age-verification'),
            ],
            [
                'key' => 'error_message',
                'label' => __('Error message', 'proofage-age-verification'),
                'section' => 'proofage_texts',
                'type' => 'text',
                'description' => __('Message shown when verification fails.', 'proofage-age-verification'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<int|string, mixed>  $selectedIds
     * @param  array<string, mixed>  $options
     */
    private function renderSelectorField(array $field, array $selectedIds, array $options): void
    {
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), static fn (int $id): bool => $id > 0));
        $enabledKey = (string) ($field['enabled_key'] ?? '');
        $enabled = $enabledKey !== '' ? (bool) ($options[$enabledKey] ?? false) : false;
        $source = (string) ($field['source'] ?? '');
        $inputName = Options::OPTION_KEY . '[' . $field['key'] . '][]';
        $requiresWooCommerce = (bool) ($field['requires_woocommerce'] ?? false);
        $isUnavailable = $requiresWooCommerce && ! $this->scopeSelectorProvider->isWooCommerceAvailable();
        $selectedItems = $this->scopeSelectorProvider->getSelectedItems($source, $selectedIds);

        echo '<div class="proofage-selector-field" data-proofage-selector>';

        if ($isUnavailable) {
            printf(
                '<input type="hidden" name="%1$s[%2$s]" value="%3$s" />',
                esc_attr(Options::OPTION_KEY),
                esc_attr($enabledKey),
                $enabled ? '1' : '0'
            );
        } else {
            printf(
                '<input type="hidden" name="%1$s[%2$s]" value="0" />',
                esc_attr(Options::OPTION_KEY),
                esc_attr($enabledKey)
            );
        }

        echo '<label class="proofage-selector-field__toggle">';
        printf(
            '<input type="checkbox" value="1" %1$s %2$s %3$s />',
            $isUnavailable ? 'disabled="disabled"' : 'name="' . esc_attr(Options::OPTION_KEY . '[' . $enabledKey . ']') . '"',
            checked($enabled, true, false),
            $isUnavailable ? 'aria-disabled="true"' : ''
        );
        echo '<span>' . esc_html__('Enable this rule', 'proofage-age-verification') . '</span>';
        echo '</label>';

        if (! empty($field['description'])) {
            printf('<p class="description">%s</p>', esc_html((string) $field['description']));
        }

        if ($isUnavailable) {
            printf(
                '<p class="description">%s</p>',
                esc_html__('WooCommerce is not active, so this selector is currently read-only.', 'proofage-age-verification')
            );
        }

        printf(
            '<div class="proofage-selector-field__panel" %s>',
            $enabled ? '' : 'hidden'
        );
        printf('<input type="hidden" name="%s" value="" />', esc_attr($inputName));

        if (! $isUnavailable) {
            printf(
                '<input class="regular-text proofage-selector-field__search" type="search" value="" placeholder="%s" data-proofage-selector-search="1" data-source="%s" />',
                esc_attr__('Search and add items…', 'proofage-age-verification'),
                esc_attr($source)
            );
            echo '<div class="proofage-selector-field__results" data-proofage-selector-results="1"></div>';
        }

        echo '<div class="proofage-selector-field__selected" data-proofage-selector-selected="1" data-input-name="' . esc_attr($inputName) . '">';

        foreach ($selectedItems as $item) {
            $this->renderSelectedItem($inputName, (int) $item['id'], (string) $item['label'], ! $isUnavailable);
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function renderSelectedItem(string $inputName, int $id, string $label, bool $removable): void
    {
        echo '<span class="proofage-selector-item" data-proofage-selected-item="' . esc_attr((string) $id) . '">';
        echo '<input type="hidden" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $id) . '" />';
        echo '<span class="proofage-selector-item__label">' . esc_html($label) . '</span>';

        if ($removable) {
            echo '<button type="button" class="button-link proofage-selector-item__remove" data-proofage-remove-selected="1" aria-label="' . esc_attr__('Remove selected item', 'proofage-age-verification') . '">&times;</button>';
        }

        echo '</span>';
    }
}
