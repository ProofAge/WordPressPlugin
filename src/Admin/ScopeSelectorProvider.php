<?php

namespace ProofAge\WordPress\Admin;

final class ScopeSelectorProvider
{
    public const SOURCE_WC_CATEGORIES = 'wc_categories';
    public const SOURCE_WC_PRODUCTS = 'wc_products';
    public const SOURCE_WP_CATEGORIES = 'wp_categories';
    public const SOURCE_WP_PAGES = 'wp_pages';

    public function isWooCommerceAvailable(): bool
    {
        return class_exists('WooCommerce') || defined('WC_ABSPATH');
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int|string>>
     */
    public function getSelectedItems(string $source, array $ids): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($normalizedIds === []) {
            return [];
        }

        return match ($source) {
            self::SOURCE_WC_CATEGORIES => $this->getSelectedTerms('product_cat', $normalizedIds),
            self::SOURCE_WC_PRODUCTS => $this->getSelectedPosts('product', $normalizedIds),
            self::SOURCE_WP_CATEGORIES => $this->getSelectedTerms('category', $normalizedIds),
            self::SOURCE_WP_PAGES => $this->getSelectedPosts('page', $normalizedIds),
            default => [],
        };
    }

    /**
     * @param  array<int, int>  $excludedIds
     * @return array<int, array<string, int|string>>
     */
    public function search(string $source, string $query = '', array $excludedIds = []): array
    {
        $normalizedQuery = trim($query);
        $normalizedExcludedIds = array_values(array_unique(array_filter(array_map('intval', $excludedIds), static fn (int $id): bool => $id > 0)));

        return match ($source) {
            self::SOURCE_WC_CATEGORIES => $this->searchTerms('product_cat', $normalizedQuery, $normalizedExcludedIds),
            self::SOURCE_WC_PRODUCTS => $this->searchPosts('product', $normalizedQuery, $normalizedExcludedIds),
            self::SOURCE_WP_CATEGORIES => $this->searchTerms('category', $normalizedQuery, $normalizedExcludedIds),
            self::SOURCE_WP_PAGES => $this->searchPosts('page', $normalizedQuery, $normalizedExcludedIds),
            default => [],
        };
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int|string>>
     */
    private function getSelectedTerms(string $taxonomy, array $ids): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'include' => $ids,
            'hide_empty' => false,
            'orderby' => 'include',
        ]);

        if (is_wp_error($terms) || ! is_array($terms)) {
            return $this->buildMissingItems($ids);
        }

        $mapped = [];

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }

            $mapped[] = [
                'id' => (int) $term->term_id,
                'label' => sprintf('%s (#%d)', $term->name, $term->term_id),
            ];
        }

        return $this->mergeMissingItems($ids, $mapped);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int|string>>
     */
    private function getSelectedPosts(string $postType, array $ids): array
    {
        $posts = get_posts([
            'post_type' => $postType,
            'post__in' => $ids,
            'orderby' => 'post__in',
            'numberposts' => count($ids),
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'suppress_filters' => false,
        ]);

        $mapped = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $mapped[] = [
                'id' => (int) $post->ID,
                'label' => sprintf('%s (#%d)', $post->post_title !== '' ? $post->post_title : __('(no title)', 'proofage-age-verification'), $post->ID),
            ];
        }

        return $this->mergeMissingItems($ids, $mapped);
    }

    /**
     * @param  array<int, int>  $excludedIds
     * @return array<int, array<string, int|string>>
     */
    private function searchTerms(string $taxonomy, string $query, array $excludedIds): array
    {
        if ($taxonomy === 'product_cat' && ! $this->isWooCommerceAvailable()) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'search' => $query,
            'number' => 20,
            'exclude' => $excludedIds,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }

        $results = [];

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }

            $results[] = [
                'id' => (int) $term->term_id,
                'label' => sprintf('%s (#%d)', $term->name, $term->term_id),
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $excludedIds
     * @return array<int, array<string, int|string>>
     */
    private function searchPosts(string $postType, string $query, array $excludedIds): array
    {
        if ($postType === 'product' && ! $this->isWooCommerceAvailable()) {
            return [];
        }

        $posts = get_posts([
            'post_type' => $postType,
            's' => $query,
            'post__not_in' => $excludedIds,
            'numberposts' => 20,
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        $results = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $results[] = [
                'id' => (int) $post->ID,
                'label' => sprintf('%s (#%d)', $post->post_title !== '' ? $post->post_title : __('(no title)', 'proofage-age-verification'), $post->ID),
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $expectedIds
     * @param  array<int, array<string, int|string>>  $items
     * @return array<int, array<string, int|string>>
     */
    private function mergeMissingItems(array $expectedIds, array $items): array
    {
        $indexed = [];

        foreach ($items as $item) {
            $indexed[(int) $item['id']] = $item;
        }

        $merged = [];

        foreach ($expectedIds as $id) {
            $merged[] = $indexed[$id] ?? [
                'id' => $id,
                'label' => sprintf(__('Unavailable item (#%d)', 'proofage-age-verification'), $id),
            ];
        }

        return $merged;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int|string>>
     */
    private function buildMissingItems(array $ids): array
    {
        $items = [];

        foreach ($ids as $id) {
            $items[] = [
                'id' => $id,
                'label' => sprintf(__('Unavailable item (#%d)', 'proofage-age-verification'), $id),
            ];
        }

        return $items;
    }
}
