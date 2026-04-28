<?php

namespace ProofAge\WordPress\Admin;

use ProofAge\WordPress\ProofAge\ApiClient;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsPage
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly ScopeSelectorProvider $scopeSelectorProvider,
    )
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_notices', [$this, 'renderDependencyNotice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_proofage_search_scope_items', [$this, 'handleScopeSearch']);
    }

    public function registerPage(): void
    {
        add_options_page(
            __('ProofAge Verification', 'proofage-age-verification'),
            __('ProofAge Verification', 'proofage-age-verification'),
            'manage_options',
            SettingsRegistrar::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $this->maybeHandleConnectivityTest();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ProofAge age verification', 'proofage-age-verification') . '</h1>';
        echo '<p>' . esc_html__('Configure ProofAge credentials, gate behavior, and targeting rules for WordPress and WooCommerce content.', 'proofage-age-verification') . '</p>';

        $this->renderServiceDisclosure();
        $this->renderDiagnostics();

        echo '<form method="post" action="options.php">';
        settings_fields(SettingsRegistrar::PAGE_SLUG);
        do_settings_sections(SettingsRegistrar::PAGE_SLUG);
        submit_button(__('Save settings', 'proofage-age-verification'));
        echo '</form>';
        echo '</div>';
    }

    public function renderDependencyNotice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if ($this->isWooCommerceAvailable()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || $screen->id !== 'settings_page_' . SettingsRegistrar::PAGE_SLUG) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('WooCommerce is not active. WordPress page and post-category protection still works, but WooCommerce product, category, cart, and checkout guards stay disabled until WooCommerce is available.', 'proofage-age-verification');
        echo '</p></div>';
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_' . SettingsRegistrar::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'proofage-age-verification-admin',
            PROOFAGE_WP_PLUGIN_URL . 'assets/css/admin-settings.css',
            [],
            PROOFAGE_WP_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'proofage-age-verification-admin',
            PROOFAGE_WP_PLUGIN_URL . 'assets/js/admin-settings.js',
            [],
            PROOFAGE_WP_PLUGIN_VERSION,
            true
        );

        wp_localize_script('proofage-age-verification-admin', 'ProofAgeAdminSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'searchNonce' => wp_create_nonce('proofage_scope_selector'),
            'messages' => [
                'searchPlaceholder' => __('Search and add items…', 'proofage-age-verification'),
                'noResults' => __('No matching items found.', 'proofage-age-verification'),
            ],
        ]);
    }

    public function handleScopeSearch(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to search these items.', 'proofage-age-verification')], 403);
        }

        check_ajax_referer('proofage_scope_selector', 'nonce');

        $source = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $excludedIds = isset($_POST['excluded_ids']) ? array_map('intval', (array) wp_unslash($_POST['excluded_ids'])) : [];

        wp_send_json_success([
            'items' => $this->scopeSelectorProvider->search($source, $query, $excludedIds),
        ]);
    }

    private function renderDiagnostics(): void
    {
        $returnUrl = add_query_arg('proofage-return', '1', home_url('/'));
        $webhookUrl = rest_url('proofage/v1/webhook');
        $connectivityUrl = wp_nonce_url(
            add_query_arg(
                [
                    'page' => SettingsRegistrar::PAGE_SLUG,
                    'proofage-connectivity-test' => '1',
                ],
                admin_url('options-general.php')
            ),
            'proofage_connectivity_test'
        );

        echo '<div class="card" style="max-width: 960px; margin: 16px 0; padding: 16px;">';
        echo '<h2>' . esc_html__('Diagnostics', 'proofage-age-verification') . '</h2>';
        echo '<p><strong>' . esc_html__('Return URL:', 'proofage-age-verification') . '</strong> <code>' . esc_html($returnUrl) . '</code></p>';
        echo '<p><strong>' . esc_html__('Webhook URL:', 'proofage-age-verification') . '</strong> <code>' . esc_html($webhookUrl) . '</code></p>';
        echo '<p><strong>' . esc_html__('WooCommerce status:', 'proofage-age-verification') . '</strong> ' . esc_html($this->isWooCommerceAvailable() ? __('Active', 'proofage-age-verification') : __('Inactive', 'proofage-age-verification')) . '</p>';
        echo '<p><a class="button button-secondary" href="' . esc_url($connectivityUrl) . '">' . esc_html__('Run connectivity test', 'proofage-age-verification') . '</a></p>';
        echo '</div>';
    }

    private function renderServiceDisclosure(): void
    {
        $privacyPolicyUrl = 'https://proofage.xyz/privacy';
        $termsOfServiceUrl = 'https://proofage.xyz/terms';

        echo '<div class="card" style="max-width: 960px; margin: 16px 0; padding: 16px;">';
        echo '<h2>' . esc_html__('External service disclosure', 'proofage-age-verification') . '</h2>';
        echo '<p>' . esc_html__('This plugin requires a ProofAge account and valid API credentials.', 'proofage-age-verification') . '</p>';
        echo '<p>' . esc_html__('It connects to the ProofAge API to create verifications, check verification status, and process signed webhook callbacks.', 'proofage-age-verification') . '</p>';
        echo '<p>' . esc_html__('When a shopper starts verification, the plugin sends limited verification request data to ProofAge, such as an external identifier, callback or return URL, supported storefront language, and verification-related metadata.', 'proofage-age-verification') . '</p>';
        echo '<p>' . esc_html__('The plugin stores limited verification state locally in WordPress and WooCommerce, including verification status, verification ID, external ID, return URL, timestamps, session token, and optional order verification metadata.', 'proofage-age-verification') . '</p>';
        printf(
            '<p>%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>. %4$s <a href="%5$s" target="_blank" rel="noopener noreferrer">%6$s</a>.</p>',
            esc_html__('Privacy Policy:', 'proofage-age-verification'),
            esc_url($privacyPolicyUrl),
            esc_html($privacyPolicyUrl),
            esc_html__('Terms of Service:', 'proofage-age-verification'),
            esc_url($termsOfServiceUrl),
            esc_html($termsOfServiceUrl)
        );
        echo '</div>';
    }

    private function maybeHandleConnectivityTest(): void
    {
        if (! isset($_GET['proofage-connectivity-test'])) {
            return;
        }

        check_admin_referer('proofage_connectivity_test');

        $result = $this->apiClient->testConnectivity();
        $noticeClass = $result['success'] ? 'notice-success' : 'notice-error';

        printf(
            '<div class="notice %1$s"><p>%2$s</p></div>',
            esc_attr($noticeClass),
            esc_html((string) $result['message'])
        );
    }

    private function isWooCommerceAvailable(): bool
    {
        return $this->scopeSelectorProvider->isWooCommerceAvailable();
    }
}
