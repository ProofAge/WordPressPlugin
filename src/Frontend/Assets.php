<?php

namespace ProofAge\WordPress\Frontend;

use ProofAge\WordPress\Support\LocalizedGateTexts;
use ProofAge\WordPress\Support\Options;
use ProofAge\WordPress\Support\VerificationLanguage;
use ProofAge\WordPress\Verification\SessionManager;

final class Assets
{
    public function __construct(private readonly SessionManager $sessionManager)
    {
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        if (is_admin()) {
            return;
        }

        wp_register_style(
            'proofage-age-verification',
            PROOFAGE_WP_PLUGIN_URL . 'assets/css/frontend-gate.css',
            [],
            PROOFAGE_WP_PLUGIN_VERSION
        );

        wp_register_script(
            'proofage-age-verification',
            PROOFAGE_WP_PLUGIN_URL . 'assets/js/frontend-gate.js',
            [],
            PROOFAGE_WP_PLUGIN_VERSION,
            true
        );

        wp_localize_script('proofage-age-verification', 'ProofAgeWordPress', [
            'sessionEndpoint' => rest_url('proofage/v1/session'),
            'statusEndpoint' => rest_url('proofage/v1/status'),
            'nonce' => wp_create_nonce('wp_rest'),
            'launchMode' => Options::get('launch_mode', 'redirect'),
            'language' => VerificationLanguage::resolveCurrent(),
            'isVerified' => $this->sessionManager->isVerified(),
            'messages' => [
                'success' => LocalizedGateTexts::get('success_message'),
                'error' => LocalizedGateTexts::get('error_message'),
            ],
        ]);

        wp_enqueue_style('proofage-age-verification');
        wp_enqueue_script('proofage-age-verification');
    }
}
