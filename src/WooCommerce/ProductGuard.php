<?php

namespace ProofAge\WordPress\WooCommerce;

use ProofAge\WordPress\Admin\CategoryMeta;
use ProofAge\WordPress\Admin\ProductMeta;
use ProofAge\WordPress\Support\LocalizedGateTexts;
use ProofAge\WordPress\Support\Options;
use ProofAge\WordPress\Verification\RuleDecision;
use ProofAge\WordPress\Verification\RulesEngine;
use ProofAge\WordPress\Verification\SessionManager;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductGuard
{
    public function __construct(
        private readonly RulesEngine $rulesEngine,
        private readonly SessionManager $sessionManager,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_before_single_product', [$this, 'renderProductNotice']);
        add_filter('woocommerce_is_purchasable', [$this, 'filterPurchasable'], 10, 2);
        add_filter('woocommerce_variation_is_purchasable', [$this, 'filterPurchasable'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddToCart'], 10, 2);
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'decorateLoopAddToCartLink'], 10, 3);
    }

    public function renderProductNotice(): void
    {
        if (! function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product(get_the_ID());

        if (! $product || ! $this->evaluateProduct($product->get_id())->requiresVerification || $this->sessionManager->isVerified()) {
            return;
        }

        echo '<div class="woocommerce-info proofage-woocommerce-notice" data-proofage-start="1">';
        echo esc_html(LocalizedGateTexts::get('gate_description', __('Age verification is required before purchasing this product.', 'proofage-age-verification')));
        echo '</div>';
    }

    public function filterPurchasable(bool $isPurchasable, $product): bool
    {
        if (! is_object($product) || ! method_exists($product, 'get_id') || $this->sessionManager->isVerified()) {
            return $isPurchasable;
        }

        // Keep protected items in the cart so cart/checkout can hard-block the entire page.
        if (
            (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
        ) {
            return $isPurchasable;
        }

        return $this->evaluateProduct((int) $product->get_id())->requiresVerification ? false : $isPurchasable;
    }

    public function validateAddToCart(bool $passed, int $productId): bool
    {
        if ($this->sessionManager->isVerified()) {
            return $passed;
        }

        if (! $this->evaluateProduct($productId)->requiresVerification) {
            return $passed;
        }

        if (function_exists('wc_add_notice')) {
            wc_add_notice(LocalizedGateTexts::get('gate_description', __('Age verification is required before adding this product to the cart.', 'proofage-age-verification')), 'error');
        }

        return false;
    }

    public function decorateLoopAddToCartLink(string $html, $product, array $args): string
    {
        if (! is_object($product) || ! method_exists($product, 'get_id') || $this->sessionManager->isVerified()) {
            return $html;
        }

        if (! $this->evaluateProduct((int) $product->get_id())->requiresVerification) {
            return $html;
        }

        return str_replace('<a ', '<a data-proofage-protected-add-to-cart="1" ', $html);
    }

    public function evaluateProduct(int $productId): RuleDecision
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
        ]);
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
