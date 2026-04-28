<?php

namespace ProofAge\WordPress\WooCommerce;

use ProofAge\WordPress\Support\LocalizedGateTexts;
use ProofAge\WordPress\Verification\SessionManager;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class CartCheckoutGuard
{
    public function __construct(
        private readonly ProductGuard $productGuard,
        private readonly SessionManager $sessionManager,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_check_cart_items', [$this, 'validateCart']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateCheckout'], 10, 2);
        add_action('woocommerce_store_api_validate_add_to_cart', [$this, 'validateStoreApiAddToCart'], 10, 2);
        add_action('woocommerce_store_api_validate_cart_item', [$this, 'validateStoreApiCartItem'], 10, 2);
        add_filter('woocommerce_cart_item_is_purchasable', [$this, 'keepProtectedItemsInCart'], 10, 4);
    }

    public function validateCart(): void
    {
        if (! function_exists('WC') || $this->sessionManager->isVerified()) {
            return;
        }

        foreach (WC()->cart?->get_cart() ?? [] as $cartItem) {
            if ($this->itemRequiresVerification($cartItem)) {
                wc_add_notice(LocalizedGateTexts::get('gate_description', __('Age verification is required before continuing to the cart.', 'proofage-age-verification')), 'error');
                break;
            }
        }
    }

    public function validateCheckout(array $data, WP_Error $errors): void
    {
        if (! function_exists('WC') || $this->sessionManager->isVerified()) {
            return;
        }

        foreach (WC()->cart?->get_cart() ?? [] as $cartItem) {
            if ($this->itemRequiresVerification($cartItem)) {
                $errors->add(
                    'proofage_verification_required',
                    LocalizedGateTexts::get('gate_description', __('Age verification is required before checkout.', 'proofage-age-verification'))
                );
                break;
            }
        }
    }

    public function validateStoreApiAddToCart($product, array $request): void
    {
        if ($this->sessionManager->isVerified() || ! is_object($product) || ! method_exists($product, 'get_id')) {
            return;
        }

        if ($this->productGuard->evaluateProduct((int) $product->get_id())->requiresVerification) {
            throw new \Exception(esc_html(LocalizedGateTexts::get('gate_description', __('Age verification is required before adding this product to the cart.', 'proofage-age-verification'))));
        }
    }

    public function validateStoreApiCartItem($product, array $item): void
    {
        if ($this->sessionManager->isVerified() || ! is_object($product) || ! method_exists($product, 'get_id')) {
            return;
        }

        if ($this->productGuard->evaluateProduct((int) $product->get_id())->requiresVerification) {
            throw new \Exception(esc_html(LocalizedGateTexts::get('gate_description', __('Age verification is required before continuing.', 'proofage-age-verification'))));
        }
    }

    public function keepProtectedItemsInCart(bool $isPurchasable, string $cartItemKey, array $cartItem, $product): bool
    {
        if ($this->sessionManager->isVerified()) {
            return $isPurchasable;
        }

        return $this->itemRequiresVerification($cartItem) ? true : $isPurchasable;
    }

    /**
     * @param  array<string, mixed>  $cartItem
     */
    private function itemRequiresVerification(array $cartItem): bool
    {
        $productId = (int) ($cartItem['product_id'] ?? 0);

        if ($productId <= 0) {
            return false;
        }

        return $this->productGuard->evaluateProduct($productId)->requiresVerification;
    }
}
