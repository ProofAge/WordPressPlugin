<?php

namespace ProofAge\WordPress\Verification;

final class RulesEngine
{
    public function evaluate(array $settings, array $subject): RuleDecision
    {
        $productId = (int) ($subject['product_id'] ?? 0);
        $productCategoryIds = array_map('intval', $subject['product_category_ids'] ?? []);
        $pageId = (int) ($subject['page_id'] ?? 0);
        $wpCategoryIds = array_map('intval', $subject['wp_category_ids'] ?? []);
        $protectedProductIds = $this->enabledIds($settings, 'protect_wc_products_enabled', 'protected_product_ids');
        $excludedProductIds = $this->enabledIds($settings, 'exclude_wc_products_enabled', 'excluded_product_ids');
        $protectedProductCategoryIds = $this->enabledIds($settings, 'protect_wc_categories_enabled', 'protected_category_ids');
        $excludedProductCategoryIds = $this->enabledIds($settings, 'exclude_wc_categories_enabled', 'excluded_category_ids');
        $protectedWpCategoryIds = $this->enabledIds($settings, 'protect_wp_categories_enabled', 'protected_wp_category_ids');
        $protectedPageIds = $this->enabledIds($settings, 'protect_wp_pages_enabled', 'protected_page_ids');

        if (($subject['product_excluded'] ?? false) || $this->containsId($excludedProductIds, $productId)) {
            return new RuleDecision(false, 'product_exclusion', [
                'product_id' => $productId,
            ]);
        }

        if (($subject['product_requires_verification'] ?? false) || $this->containsId($protectedProductIds, $productId)) {
            return new RuleDecision(true, 'product', [
                'product_id' => $productId,
            ]);
        }

        if (($subject['product_category_excluded'] ?? false) || $this->intersects($excludedProductCategoryIds, $productCategoryIds)) {
            return new RuleDecision(false, 'category_exclusion', [
                'product_category_ids' => $productCategoryIds,
            ]);
        }

        if (($subject['product_category_requires_verification'] ?? false) || $this->intersects($protectedProductCategoryIds, $productCategoryIds)) {
            return new RuleDecision(true, 'category', [
                'product_category_ids' => $productCategoryIds,
            ]);
        }

        if ($this->containsId($protectedPageIds, $pageId)) {
            return new RuleDecision(true, 'page', [
                'page_id' => $pageId,
            ]);
        }

        if ($this->intersects($protectedWpCategoryIds, $wpCategoryIds)) {
            return new RuleDecision(true, 'wp_category', [
                'wp_category_ids' => $wpCategoryIds,
            ]);
        }

        if ((bool) ($settings['site_enabled'] ?? false)) {
            return new RuleDecision(true, 'global');
        }

        return new RuleDecision(false, null);
    }

    private function containsId(array $haystack, int $needle): bool
    {
        return in_array($needle, array_map('intval', $haystack), true);
    }

    private function intersects(array $left, array $right): bool
    {
        $normalizedLeft = array_map('intval', $left);

        return array_intersect($normalizedLeft, $right) !== [];
    }

    private function enabledIds(array $settings, string $enabledKey, string $idsKey): array
    {
        if (! (bool) ($settings[$enabledKey] ?? false)) {
            return [];
        }

        return array_map('intval', $settings[$idsKey] ?? []);
    }
}
