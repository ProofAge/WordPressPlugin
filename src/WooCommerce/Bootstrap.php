<?php

namespace ProofAge\WordPress\WooCommerce;

use ProofAge\WordPress\Admin\CategoryMeta;
use ProofAge\WordPress\Admin\ProductMeta;

if (! defined('ABSPATH')) {
    exit;
}

final class Bootstrap
{
    public function __construct(
        private readonly ProductMeta $productMeta,
        private readonly CategoryMeta $categoryMeta,
        private readonly ProductGuard $productGuard,
        private readonly CartCheckoutGuard $cartCheckoutGuard,
        private readonly OrderVerificationDetails $orderVerificationDetails,
    ) {
    }

    public function registerHooks(): void
    {
        $this->productMeta->registerHooks();
        $this->categoryMeta->registerHooks();
        $this->productGuard->registerHooks();
        $this->cartCheckoutGuard->registerHooks();
        $this->orderVerificationDetails->registerHooks();
    }
}
