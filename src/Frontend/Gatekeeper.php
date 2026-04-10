<?php

namespace ProofAge\WordPress\Frontend;

use ProofAge\WordPress\Admin\CategoryMeta;
use ProofAge\WordPress\Admin\ProductMeta;
use ProofAge\WordPress\Support\LocalizedGateTexts;
use ProofAge\WordPress\Support\Options;
use ProofAge\WordPress\Verification\RulesEngine;
use ProofAge\WordPress\Verification\SessionManager;

final class Gatekeeper
{
    /**
     * @var array<string, string>|null
     */
    private ?array $overlayGateContext = null;

    public function __construct(
        private readonly RulesEngine $rulesEngine,
        private readonly SessionManager $sessionManager,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('template_redirect', [$this, 'maybeProtectRequest'], 1);
        add_action('wp_footer', [$this, 'renderOverlayGate'], 5);
        add_filter('body_class', [$this, 'addOverlayBodyClass']);
    }

    public function maybeProtectRequest(): void
    {
        if ($this->shouldSkipRequest() || $this->sessionManager->isVerified()) {
            return;
        }

        $settings = Options::all();
        $decision = $this->rulesEngine->evaluate($settings, $this->resolveSubject($settings));

        if (! $decision->requiresVerification && ! $this->cartOrCheckoutRequiresVerification()) {
            return;
        }

        $gateContext = $this->buildGateContext();

        nocache_headers();

        if ($this->shouldUseOverlay($gateContext['displayMode'])) {
            $gateContext['displayMode'] = 'overlay';
            $this->overlayGateContext = $gateContext;
            return;
        }

        $gateContext['displayMode'] = 'gate';
        status_header(403);

        extract($gateContext, EXTR_SKIP);

        include PROOFAGE_WP_PLUGIN_DIR . 'templates/gate.php';
        exit;
    }

    /**
     * @param  array<int, string>  $classes
     *
     * @return array<int, string>
     */
    public function addOverlayBodyClass(array $classes): array
    {
        if ($this->overlayGateContext === null) {
            return $classes;
        }

        $classes[] = 'proofage-gate-active';
        $classes[] = 'proofage-display-mode-overlay';

        return $classes;
    }

    public function renderOverlayGate(): void
    {
        if ($this->overlayGateContext === null) {
            return;
        }

        extract($this->overlayGateContext, EXTR_SKIP);

        include PROOFAGE_WP_PLUGIN_DIR . 'templates/gate-overlay.php';
    }

    /**
     * @param  array<string, mixed>  $settings
     *
     * @return array<string, mixed>
     */
    private function resolveSubject(array &$settings): array
    {
        $subject = [
            'product_id' => 0,
            'product_category_ids' => [],
            'product_requires_verification' => false,
            'product_excluded' => false,
            'product_category_requires_verification' => false,
            'product_category_excluded' => false,
            'page_id' => 0,
            'wp_category_ids' => [],
        ];

        if ((function_exists('is_product') && is_product()) || (function_exists('is_singular') && is_singular('product'))) {
            $productId = get_the_ID();
            $categoryIds = wp_get_post_terms($productId, 'product_cat', ['fields' => 'ids']);
            $categoryIds = is_array($categoryIds) ? array_map('intval', $categoryIds) : [];
            $categoryFlags = $this->resolveWooCategoryFlags($categoryIds);

            $subject['product_id'] = (int) $productId;
            $subject['product_category_ids'] = $categoryIds;
            $subject['product_requires_verification'] = ProductMeta::isEnabledValue(get_post_meta($productId, ProductMeta::META_REQUIRES, true));
            $subject['product_excluded'] = ProductMeta::isEnabledValue(get_post_meta($productId, ProductMeta::META_EXCLUDED, true));
            $subject['product_category_requires_verification'] = $categoryFlags['requires_verification'];
            $subject['product_category_excluded'] = $categoryFlags['excluded'];
        } elseif (function_exists('is_tax') && is_tax('product_cat')) {
            $term = get_queried_object();

            if ($term instanceof \WP_Term) {
                $subject['product_category_ids'] = [(int) $term->term_id];
                $subject['product_category_requires_verification'] = CategoryMeta::isEnabledValue(get_term_meta($term->term_id, CategoryMeta::META_REQUIRES, true));
                $subject['product_category_excluded'] = CategoryMeta::isEnabledValue(get_term_meta($term->term_id, CategoryMeta::META_EXCLUDED, true));
            }
        } elseif (function_exists('is_page') && is_page()) {
            $subject['page_id'] = (int) get_queried_object_id();
        } elseif (function_exists('is_category') && is_category()) {
            $term = get_queried_object();

            if ($term instanceof \WP_Term) {
                $subject['wp_category_ids'] = [(int) $term->term_id];
            }
        } elseif (function_exists('is_singular') && is_singular('post')) {
            $postId = (int) get_queried_object_id();

            if ($postId > 0) {
                $categoryIds = wp_get_post_categories($postId, ['fields' => 'ids']);
                $subject['wp_category_ids'] = is_array($categoryIds) ? array_map('intval', $categoryIds) : [];
            }
        }

        return $subject;
    }

    private function shouldSkipRequest(): bool
    {
        return is_admin()
            || wp_doing_ajax()
            || (defined('REST_REQUEST') && REST_REQUEST)
            || isset($_GET['proofage-return'])
            || is_feed()
            || is_preview();
    }

    /**
     * @return array<string, string>
     */
    private function buildGateContext(): array
    {
        return [
            'gateTitle' => LocalizedGateTexts::get('gate_title'),
            'gateDescription' => LocalizedGateTexts::get('gate_description'),
            'buttonLabel' => LocalizedGateTexts::get('verify_button_label'),
            'displayMode' => (string) Options::get('content_display_mode', 'gate'),
            'currentUrl' => (string) home_url(add_query_arg([])),
        ];
    }

    private function shouldUseOverlay(string $displayMode): bool
    {
        if ($displayMode !== 'overlay') {
            return false;
        }

        if (function_exists('is_cart') && is_cart()) {
            return false;
        }

        if (function_exists('is_checkout') && is_checkout()) {
            return false;
        }

        return true;
    }

    private function cartOrCheckoutRequiresVerification(): bool
    {
        if (! function_exists('WC') || (! $this->isCartRequest() && ! $this->isCheckoutRequest())) {
            return false;
        }

        foreach (WC()->cart?->get_cart() ?? [] as $cartItem) {
            $productId = (int) ($cartItem['product_id'] ?? 0);

            if ($productId > 0 && $this->productRequiresVerification($productId)) {
                return true;
            }
        }

        return false;
    }

    private function productRequiresVerification(int $productId): bool
    {
        $baseProductId = $this->resolveBaseProductId($productId);
        $settings = Options::all();
        $categoryIds = wp_get_post_terms($baseProductId, 'product_cat', ['fields' => 'ids']);
        $categoryIds = is_array($categoryIds) ? array_map('intval', $categoryIds) : [];
        $categoryFlags = $this->resolveWooCategoryFlags($categoryIds);

        return $this->rulesEngine->evaluate($settings, [
            'product_id' => $baseProductId,
            'product_category_ids' => $categoryIds,
            'product_requires_verification' => ProductMeta::isEnabledValue(get_post_meta($baseProductId, ProductMeta::META_REQUIRES, true)),
            'product_excluded' => ProductMeta::isEnabledValue(get_post_meta($baseProductId, ProductMeta::META_EXCLUDED, true)),
            'product_category_requires_verification' => $categoryFlags['requires_verification'],
            'product_category_excluded' => $categoryFlags['excluded'],
        ])->requiresVerification;
    }

    private function resolveBaseProductId(int $productId): int
    {
        $product = function_exists('wc_get_product') ? wc_get_product($productId) : null;

        if ($product && method_exists($product, 'is_type') && $product->is_type('variation') && method_exists($product, 'get_parent_id')) {
            $parentId = (int) $product->get_parent_id();

            if ($parentId > 0) {
                return $parentId;
            }
        }

        return $productId;
    }

    private function isCartRequest(): bool
    {
        return function_exists('is_cart') && is_cart();
    }

    private function isCheckoutRequest(): bool
    {
        return function_exists('is_checkout') && is_checkout();
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array{requires_verification: bool, excluded: bool}
     */
    private function resolveWooCategoryFlags(array $categoryIds): array
    {
        $flags = [
            'requires_verification' => false,
            'excluded' => false,
        ];

        foreach ($categoryIds as $categoryId) {
            if (CategoryMeta::isEnabledValue(get_term_meta($categoryId, CategoryMeta::META_REQUIRES, true))) {
                $flags['requires_verification'] = true;
            }

            if (CategoryMeta::isEnabledValue(get_term_meta($categoryId, CategoryMeta::META_EXCLUDED, true))) {
                $flags['excluded'] = true;
            }
        }

        return $flags;
    }
}
