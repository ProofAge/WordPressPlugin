<?php

namespace ProofAge\WordPress\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class LocalizedGateTexts
{
    private const GROUP = 'ProofAge';

    /**
     * @var array<string, array{name: string, multiline: bool}>
     */
    private const TEXT_KEYS = [
        'gate_title' => [
            'name' => 'Gate title',
            'multiline' => false,
        ],
        'gate_description' => [
            'name' => 'Gate description',
            'multiline' => true,
        ],
        'verify_button_label' => [
            'name' => 'Verify button label',
            'multiline' => false,
        ],
        'success_message' => [
            'name' => 'Success message',
            'multiline' => false,
        ],
        'error_message' => [
            'name' => 'Error message',
            'multiline' => false,
        ],
    ];

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerStrings']);
        add_action('update_option_' . Options::OPTION_KEY, [$this, 'handleSettingsUpdated'], 10, 2);
    }

    public function registerStrings(): void
    {
        self::registerOptionStrings(Options::all());
    }

    public function handleSettingsUpdated(mixed $oldValue, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        self::registerOptionStrings(array_replace(Options::defaults(), $value));
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = (string) Options::get($key, $default);

        if ($value === '') {
            return $default;
        }

        if (self::hasPolylang()) {
            return (string) pll__($value);
        }

        if (self::hasWpmlTranslation()) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML exposes this external hook name.
            return (string) apply_filters('wpml_translate_single_string', $value, self::GROUP, self::stringName($key));
        }

        return $value;
    }

    public static function getSettingsHint(): string
    {
        if (self::hasPolylang()) {
            return __('These fields store the source text. Translate them in Languages -> Translations.', 'proofage-age-verification');
        }

        if (self::hasWpmlSupport()) {
            return __('These fields store the source text. Translate them in WPML -> String Translation.', 'proofage-age-verification');
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function registerOptionStrings(array $options): void
    {
        foreach (self::TEXT_KEYS as $key => $config) {
            $value = isset($options[$key]) ? (string) $options[$key] : '';

            if ($value === '') {
                continue;
            }

            if (self::hasPolylang()) {
                pll_register_string($config['name'], $value, self::GROUP, $config['multiline']);
            }

            if (self::hasWpmlRegistration()) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML exposes this external hook name.
                do_action('wpml_register_single_string', self::GROUP, $config['name'], $value);
            }
        }
    }

    private static function stringName(string $key): string
    {
        return self::TEXT_KEYS[$key]['name'] ?? $key;
    }

    private static function hasPolylang(): bool
    {
        return function_exists('pll_register_string') && function_exists('pll__');
    }

    private static function hasWpmlSupport(): bool
    {
        return self::hasWpmlRegistration() || self::hasWpmlTranslation();
    }

    private static function hasWpmlRegistration(): bool
    {
        return function_exists('has_action') && has_action('wpml_register_single_string') !== false;
    }

    private static function hasWpmlTranslation(): bool
    {
        return function_exists('has_filter') && has_filter('wpml_translate_single_string') !== false;
    }
}
