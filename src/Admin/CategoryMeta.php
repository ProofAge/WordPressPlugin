<?php

namespace ProofAge\WordPress\Admin;

final class CategoryMeta
{
    public const META_REQUIRES = 'proofage_requires_verification';
    public const META_EXCLUDED = 'proofage_excluded_from_verification';

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerMeta']);
        add_action('product_cat_add_form_fields', [$this, 'renderAddFields']);
        add_action('product_cat_edit_form_fields', [$this, 'renderEditFields']);
        add_action('create_product_cat', [$this, 'saveFields']);
        add_action('edited_product_cat', [$this, 'saveFields']);
    }

    public function registerMeta(): void
    {
        register_term_meta('product_cat', self::META_REQUIRES, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => static fn (): bool => current_user_can('manage_product_terms'),
        ]);

        register_term_meta('product_cat', self::META_EXCLUDED, [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => static fn (): bool => current_user_can('manage_product_terms'),
        ]);
    }

    public function renderAddFields(): void
    {
        wp_nonce_field('proofage_category_meta', 'proofage_category_meta_nonce');
        $this->renderCheckboxRow(self::META_REQUIRES, __('Require age verification for this category', 'proofage-age-verification'));
        $this->renderCheckboxRow(self::META_EXCLUDED, __('Exclude this category from broader rules', 'proofage-age-verification'));
    }

    public function renderEditFields(\WP_Term $term): void
    {
        $requires = self::isEnabledValue(get_term_meta($term->term_id, self::META_REQUIRES, true));
        $excluded = self::isEnabledValue(get_term_meta($term->term_id, self::META_EXCLUDED, true));

        wp_nonce_field('proofage_category_meta', 'proofage_category_meta_nonce');

        $this->renderCheckboxTableRow(self::META_REQUIRES, $requires, __('Require age verification for this category', 'proofage-age-verification'));
        $this->renderCheckboxTableRow(self::META_EXCLUDED, $excluded, __('Exclude this category from broader rules', 'proofage-age-verification'));
    }

    public function saveFields(int $termId): void
    {
        if (! current_user_can('manage_product_terms')) {
            return;
        }

        $nonce = isset($_POST['proofage_category_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['proofage_category_meta_nonce'])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, 'proofage_category_meta')) {
            return;
        }

        update_term_meta($termId, self::META_REQUIRES, isset($_POST[self::META_REQUIRES]) ? 1 : 0);
        update_term_meta($termId, self::META_EXCLUDED, isset($_POST[self::META_EXCLUDED]) ? 1 : 0);
    }

    public static function isEnabledValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'on'], true);
    }

    private function renderCheckboxRow(string $name, string $label): void
    {
        printf(
            '<div class="form-field"><label for="%1$s">%2$s</label><input type="checkbox" name="%1$s" id="%1$s" value="1" /></div>',
            esc_attr($name),
            esc_html($label)
        );
    }

    private function renderCheckboxTableRow(string $name, bool $checked, string $label): void
    {
        echo '<tr class="form-field">';
        echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="checkbox" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /></td>';
        echo '</tr>';
    }
}
