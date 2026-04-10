<?php

namespace ProofAge\WordPress\WooCommerce;

use ProofAge\WordPress\Verification\SessionManager;

final class OrderVerificationDetails
{
    private const META_REQUIRED = '_proofage_verification_required';
    private const META_PASSED = '_proofage_verification_passed';
    private const META_STATUS = '_proofage_verification_status';
    private const META_VERIFICATION_ID = '_proofage_verification_id';
    private const META_EXTERNAL_ID = '_proofage_external_id';
    private const META_VERIFIED_AT = '_proofage_verified_at';
    private const META_EXPIRES_AT = '_proofage_verification_expires_at';

    public function __construct(
        private readonly ProductGuard $productGuard,
        private readonly SessionManager $sessionManager,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_checkout_update_order_meta', [$this, 'captureClassicCheckoutMeta']);
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'captureStoreApiMeta']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'renderAdminOrderDetails']);
    }

    public function captureClassicCheckoutMeta(int $orderId): void
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : null;

        if (! $order instanceof \WC_Order) {
            return;
        }

        $this->applyVerificationSnapshot($order);
        $order->save_meta_data();
    }

    public function captureStoreApiMeta(\WC_Order $order): void
    {
        $this->applyVerificationSnapshot($order);
        $order->save_meta_data();
    }

    public function renderAdminOrderDetails(\WC_Order $order): void
    {
        $status = (string) $order->get_meta(self::META_STATUS, true);

        if ($status === '') {
            return;
        }

        $verificationId = (string) $order->get_meta(self::META_VERIFICATION_ID, true);
        $verifiedAt = (int) $order->get_meta(self::META_VERIFIED_AT, true);
        $summaryValue = $this->formatSummaryValue($status);
        $details = [];

        if (! in_array($status, ['approved', 'not_required', 'not_verified'], true)) {
            $details[] = sprintf(
                /* translators: %s is the normalized verification status. */
                __('Status: %s', 'proofage-age-verification'),
                $this->formatStatusLabel($status)
            );
        }

        if ($verificationId !== '') {
            $details[] = sprintf(
                /* translators: %s is a ProofAge verification identifier. */
                __('Verification ID: %s', 'proofage-age-verification'),
                $verificationId
            );
        }

        if ($verifiedAt > 0) {
            $details[] = sprintf(
                /* translators: %s is the localized verification timestamp. */
                __('Verified at: %s', 'proofage-age-verification'),
                $this->formatTimestamp($verifiedAt)
            );
        }

        echo '<p class="form-field form-field-wide proofage-order-verification-field">';
        echo '<label>' . esc_html__('ProofAge verified', 'proofage-age-verification') . '</label>';
        echo '<span>' . esc_html($summaryValue) . '</span>';

        if ($details !== []) {
            echo '<span class="description" style="display:block; margin-top:4px;">' . esc_html(implode(' | ', $details)) . '</span>';
        }

        echo '</p>';
    }

    private function applyVerificationSnapshot(\WC_Order $order): void
    {
        $requiresVerification = $this->orderRequiresVerification($order);
        $state = $this->sessionManager->getState();
        $status = $this->resolveStatus($requiresVerification, $state);
        $passed = $status === 'approved';

        $order->update_meta_data(self::META_REQUIRED, $requiresVerification ? 'yes' : 'no');
        $order->update_meta_data(self::META_PASSED, $passed ? 'yes' : 'no');
        $order->update_meta_data(self::META_STATUS, $status);

        $this->updateOptionalMeta(
            $order,
            self::META_VERIFICATION_ID,
            $requiresVerification ? (string) ($state['verification_id'] ?? '') : ''
        );
        $this->updateOptionalMeta(
            $order,
            self::META_EXTERNAL_ID,
            $requiresVerification ? (string) ($state['external_id'] ?? '') : ''
        );
        $this->updateOptionalMeta(
            $order,
            self::META_VERIFIED_AT,
            $passed ? (int) ($state['verified_at'] ?? 0) : 0
        );
        $this->updateOptionalMeta(
            $order,
            self::META_EXPIRES_AT,
            $passed ? (int) ($state['expires_at'] ?? 0) : 0
        );
    }

    private function orderRequiresVerification(\WC_Order $order): bool
    {
        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $productId = (int) $item->get_variation_id();

            if ($productId <= 0) {
                $productId = (int) $item->get_product_id();
            }

            if ($productId > 0 && $this->productGuard->evaluateProduct($productId)->requiresVerification) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveStatus(bool $requiresVerification, array $state): string
    {
        if (! $requiresVerification) {
            return 'not_required';
        }

        $status = (string) ($state['status'] ?? '');

        if ($status === '') {
            return 'not_verified';
        }

        return $status;
    }

    private function updateOptionalMeta(\WC_Order $order, string $key, string|int $value): void
    {
        if ($value === '' || $value === 0) {
            $order->delete_meta_data($key);
            return;
        }

        $order->update_meta_data($key, $value);
    }

    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
            'approved' => __('Approved', 'proofage-age-verification'),
            'not_required' => __('Not required', 'proofage-age-verification'),
            'review' => __('In review', 'proofage-age-verification'),
            'pending' => __('Pending', 'proofage-age-verification'),
            'declined' => __('Declined', 'proofage-age-verification'),
            'resubmission_requested' => __('Resubmission requested', 'proofage-age-verification'),
            'abandoned' => __('Abandoned', 'proofage-age-verification'),
            'expired' => __('Expired', 'proofage-age-verification'),
            'failed' => __('Failed', 'proofage-age-verification'),
            'not_verified' => __('Not verified', 'proofage-age-verification'),
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function formatSummaryValue(string $status): string
    {
        return match ($status) {
            'approved' => __('Yes', 'proofage-age-verification'),
            'not_required' => __('Not required', 'proofage-age-verification'),
            default => __('No', 'proofage-age-verification'),
        };
    }

    private function formatTimestamp(int $timestamp): string
    {
        $format = trim(get_option('date_format') . ' ' . get_option('time_format'));

        if (function_exists('wp_date')) {
            return wp_date($format, $timestamp);
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
