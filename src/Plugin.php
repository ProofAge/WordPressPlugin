<?php

namespace ProofAge\WordPress;

use ProofAge\WordPress\Admin\CategoryMeta;
use ProofAge\WordPress\Admin\ProductMeta;
use ProofAge\WordPress\Admin\SettingsPage;
use ProofAge\WordPress\Admin\SettingsRegistrar;
use ProofAge\WordPress\Admin\ScopeSelectorProvider;
use ProofAge\WordPress\Frontend\Assets;
use ProofAge\WordPress\Frontend\Gatekeeper;
use ProofAge\WordPress\Http\RestRoutes;
use ProofAge\WordPress\Http\ReturnController;
use ProofAge\WordPress\Http\WebhookController;
use ProofAge\WordPress\ProofAge\ApiClient;
use ProofAge\WordPress\ProofAge\WebhookSignatureVerifier;
use ProofAge\WordPress\Support\LocalizedGateTexts;
use ProofAge\WordPress\Support\Options;
use ProofAge\WordPress\Verification\RulesEngine;
use ProofAge\WordPress\Verification\SessionManager;
use ProofAge\WordPress\Verification\StateRepository;
use ProofAge\WordPress\WooCommerce\Bootstrap as WooCommerceBootstrap;
use ProofAge\WordPress\WooCommerce\CartCheckoutGuard;
use ProofAge\WordPress\WooCommerce\OrderVerificationDetails;
use ProofAge\WordPress\WooCommerce\ProductGuard;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public function boot(): void
    {
        $localizedGateTexts = new LocalizedGateTexts();
        $stateRepository = new StateRepository();
        $sessionManager = new SessionManager($stateRepository);
        $rulesEngine = new RulesEngine();
        $apiClient = new ApiClient();
        $webhookController = new WebhookController($sessionManager, new WebhookSignatureVerifier());
        $scopeSelectorProvider = new ScopeSelectorProvider();

        (new SettingsRegistrar($scopeSelectorProvider))->registerHooks();
        (new SettingsPage($apiClient, $scopeSelectorProvider))->registerHooks();
        $localizedGateTexts->registerHooks();
        (new Assets($sessionManager))->registerHooks();
        (new Gatekeeper($rulesEngine, $sessionManager))->registerHooks();
        (new RestRoutes($apiClient, $sessionManager, $webhookController))->registerHooks();
        (new ReturnController($sessionManager, $apiClient))->registerHooks();

        if ($this->isWooCommerceAvailable()) {
            $productMeta = new ProductMeta();
            $categoryMeta = new CategoryMeta();
            $productGuard = new ProductGuard($rulesEngine, $sessionManager);

            (new WooCommerceBootstrap(
                $productMeta,
                $categoryMeta,
                $productGuard,
                new CartCheckoutGuard($productGuard, $sessionManager),
                new OrderVerificationDetails($productGuard, $sessionManager),
            ))->registerHooks();
        }
    }

    public static function activate(): void
    {
        if (! get_option(Options::OPTION_KEY)) {
            add_option(Options::OPTION_KEY, Options::defaults());
        }
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private function isWooCommerceAvailable(): bool
    {
        return class_exists('WooCommerce') || defined('WC_ABSPATH');
    }
}
