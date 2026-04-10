<?php

namespace ProofAge\WordPress\Admin;

final class ProductMeta
{
    public const META_REQUIRES = '_proofage_requires_verification';
    public const META_EXCLUDED = '_proofage_excluded_from_verification';

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerMeta']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderFields']);
        add_action('woocommerce_process_product_meta', [$this, 'saveFields']);
    }

    public function registerMeta(): void
    {
        register_post_meta('product', self::META_REQUIRES, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => static fn (): bool => current_user_can('edit_products'),
        ]);

        register_post_meta('product', self::META_EXCLUDED, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => static fn (): bool => current_user_can('edit_products'),
        ]);
    }

    public function renderFields(): void
    {
        echo '<div class="options_group">';
        wp_nonce_field('proofage_product_meta', 'proofage_product_meta_nonce');

        woocommerce_wp_checkbox([
            'id' => self::META_REQUIRES,
            'label' => __('Requires age verification', 'proofage-age-verification'),
            'description' => __('Always require verification for this product.', 'proofage-age-verification'),
            'cbvalue' => '1',
        ]);

        woocommerce_wp_checkbox([
            'id' => self::META_EXCLUDED,
            'label' => __('Exclude from broader rules', 'proofage-age-verification'),
            'description' => __('Skip site-wide or category-level verification for this product.', 'proofage-age-verification'),
            'cbvalue' => '1',
        ]);

        echo '</div>';
    }

    public function saveFields(int $productId): void
    {
        if (! current_user_can('edit_post', $productId)) {
            return;
        }

        $nonce = isset($_POST['proofage_product_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['proofage_product_meta_nonce'])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, 'proofage_product_meta')) {
            return;
        }

        update_post_meta($productId, self::META_REQUIRES, isset($_POST[self::META_REQUIRES]) ? 1 : 0);
        update_post_meta($productId, self::META_EXCLUDED, isset($_POST[self::META_EXCLUDED]) ? 1 : 0);
    }

    public static function isEnabledValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'on'], true);
    }
}
