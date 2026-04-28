<?php

namespace ProofAge\WordPress\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Options
{
    public const OPTION_KEY = 'proofage_age_verification_settings';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'api_key' => '',
            'secret_key' => '',
            'site_enabled' => false,
            'launch_mode' => 'redirect',
            'content_display_mode' => 'gate',
            'session_ttl_hours' => 24,
            'protect_wc_categories_enabled' => false,
            'protect_wc_products_enabled' => false,
            'exclude_wc_categories_enabled' => false,
            'exclude_wc_products_enabled' => false,
            'protect_wp_categories_enabled' => false,
            'protect_wp_pages_enabled' => false,
            'protected_category_ids' => [],
            'protected_product_ids' => [],
            'excluded_category_ids' => [],
            'excluded_product_ids' => [],
            'protected_wp_category_ids' => [],
            'protected_page_ids' => [],
            'gate_title' => 'Verify your age to continue',
            'gate_description' => 'This takes about 30 seconds. Once verified, you won\'t need to do it again.',
            'verify_button_label' => 'Continue',
            'success_message' => 'Verification completed successfully.',
            'error_message' => 'We could not confirm your age. Please try again.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $defaults = self::defaults();
        $launchMode = self::sanitizeLaunchMode($input['launch_mode'] ?? $defaults['launch_mode']);
        $existing = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        if (! is_array($existing)) {
            $existing = [];
        }

        $secretKey = self::sanitizeText($input['secret_key'] ?? '');

        if ($secretKey === '') {
            $secretKey = self::sanitizeText($existing['secret_key'] ?? $defaults['secret_key']);
        }

        $protectedCategoryIds = self::sanitizeIdList($input['protected_category_ids'] ?? ($existing['protected_category_ids'] ?? []));
        $protectedProductIds = self::sanitizeIdList($input['protected_product_ids'] ?? ($existing['protected_product_ids'] ?? []));
        $excludedCategoryIds = self::sanitizeIdList($input['excluded_category_ids'] ?? ($existing['excluded_category_ids'] ?? []));
        $excludedProductIds = self::sanitizeIdList($input['excluded_product_ids'] ?? ($existing['excluded_product_ids'] ?? []));
        $protectedWpCategoryIds = self::sanitizeIdList($input['protected_wp_category_ids'] ?? ($existing['protected_wp_category_ids'] ?? []));
        $protectedPageIds = self::sanitizeIdList($input['protected_page_ids'] ?? ($existing['protected_page_ids'] ?? []));

        return [
            'api_key' => self::sanitizeText($input['api_key'] ?? $defaults['api_key']),
            'secret_key' => $secretKey,
            'site_enabled' => self::sanitizeBool($input['site_enabled'] ?? $defaults['site_enabled']),
            'launch_mode' => $launchMode,
            'content_display_mode' => self::sanitizeDisplayMode($input['content_display_mode'] ?? $defaults['content_display_mode']),
            'session_ttl_hours' => self::sanitizeTtl($input['session_ttl_hours'] ?? $defaults['session_ttl_hours']),
            'protect_wc_categories_enabled' => self::sanitizeSelectorToggle($input, $existing, 'protect_wc_categories_enabled', $protectedCategoryIds),
            'protect_wc_products_enabled' => self::sanitizeSelectorToggle($input, $existing, 'protect_wc_products_enabled', $protectedProductIds),
            'exclude_wc_categories_enabled' => self::sanitizeSelectorToggle($input, $existing, 'exclude_wc_categories_enabled', $excludedCategoryIds),
            'exclude_wc_products_enabled' => self::sanitizeSelectorToggle($input, $existing, 'exclude_wc_products_enabled', $excludedProductIds),
            'protect_wp_categories_enabled' => self::sanitizeSelectorToggle($input, $existing, 'protect_wp_categories_enabled', $protectedWpCategoryIds),
            'protect_wp_pages_enabled' => self::sanitizeSelectorToggle($input, $existing, 'protect_wp_pages_enabled', $protectedPageIds),
            'protected_category_ids' => $protectedCategoryIds,
            'protected_product_ids' => $protectedProductIds,
            'excluded_category_ids' => $excludedCategoryIds,
            'excluded_product_ids' => $excludedProductIds,
            'protected_wp_category_ids' => $protectedWpCategoryIds,
            'protected_page_ids' => $protectedPageIds,
            'gate_title' => self::sanitizeText($input['gate_title'] ?? $defaults['gate_title']),
            'gate_description' => self::sanitizeTextarea($input['gate_description'] ?? $defaults['gate_description']),
            'verify_button_label' => self::sanitizeText($input['verify_button_label'] ?? $defaults['verify_button_label']),
            'success_message' => self::sanitizeText($input['success_message'] ?? $defaults['success_message']),
            'error_message' => self::sanitizeText($input['error_message'] ?? $defaults['error_message']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_replace(self::defaults(), self::sanitize($stored));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $options = self::all();

        return $options[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public static function supportedLaunchModes(): array
    {
        return [
            'iframe' => 'Open in an iframe modal',
            'redirect' => 'Redirect in current window',
            'new_tab' => 'Open in a new tab',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function supportedDisplayModes(): array
    {
        return [
            'gate' => 'Replace content with a full-page gate',
            'overlay' => 'Show a blocking overlay above the page content',
        ];
    }

    private static function sanitizeLaunchMode(mixed $value): string
    {
        $value = is_string($value) ? $value : 'redirect';

        return array_key_exists($value, self::supportedLaunchModes()) ? $value : 'redirect';
    }

    private static function sanitizeDisplayMode(mixed $value): string
    {
        $value = is_string($value) ? $value : 'gate';

        return array_key_exists($value, self::supportedDisplayModes()) ? $value : 'gate';
    }

    private static function sanitizeBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $existing
     * @param  array<int, int>  $ids
     */
    private static function sanitizeSelectorToggle(array $input, array $existing, string $key, array $ids): bool
    {
        if (array_key_exists($key, $input)) {
            return self::sanitizeBool($input[$key]);
        }

        if (array_key_exists($key, $existing)) {
            return self::sanitizeBool($existing[$key]);
        }

        return $ids !== [];
    }

    private static function sanitizeTtl(mixed $value): int
    {
        $ttl = (int) $value;

        if ($ttl < 1) {
            return 1;
        }

        return min($ttl, 720);
    }

    /**
     * @param  array<int|string, mixed>|mixed  $ids
     *
     * @return array<int, int>
     */
    private static function sanitizeIdList(mixed $ids): array
    {
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', $ids) ?: [];
        }

        if (! is_array($ids)) {
            return [];
        }

        $sanitized = [];

        foreach ($ids as $id) {
            $normalized = (int) $id;

            if ($normalized > 0) {
                $sanitized[] = $normalized;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private static function sanitizeText(mixed $value): string
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field((string) $value);
        }

        return trim(wp_strip_all_tags((string) $value));
    }

    private static function sanitizeTextarea(mixed $value): string
    {
        if (function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field((string) $value);
        }

        return trim(wp_strip_all_tags((string) $value));
    }
}
