<?php

namespace ProofAge\WordPress\Support;

final class VerificationLanguage
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED = [
        'cs',
        'da',
        'de',
        'en',
        'es',
        'fr',
        'id',
        'it',
        'lt',
        'hu',
        'nl',
        'no',
        'pl',
        'pt-BR',
        'pt-PT',
        'ro',
        'fi',
        'sv',
        'tr',
        'el',
        'bg',
        'ru',
        'hi',
        'th',
        'ja',
        'zh-CN',
        'zh-TW',
        'ko',
    ];

    public static function resolve(string $requestedLanguage = ''): string
    {
        $normalizedRequestedLanguage = self::normalize($requestedLanguage);

        if ($normalizedRequestedLanguage !== '') {
            return $normalizedRequestedLanguage;
        }

        return self::resolveCurrent();
    }

    public static function resolveCurrent(): string
    {
        foreach (self::currentCandidates() as $candidate) {
            $normalizedCandidate = self::normalize($candidate);

            if ($normalizedCandidate !== '') {
                return $normalizedCandidate;
            }
        }

        return '';
    }

    public static function normalize(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim(str_replace('_', '-', $value));

        if ($value === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('-', $value), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return '';
        }

        $language = strtolower((string) array_shift($segments));
        $normalizedSegments = [$language];

        foreach ($segments as $segment) {
            $normalizedSegments[] = strlen($segment) <= 3
                ? strtoupper($segment)
                : ucfirst(strtolower($segment));
        }

        $candidates = [implode('-', $normalizedSegments)];

        if (count($normalizedSegments) > 2) {
            $candidates[] = $normalizedSegments[0] . '-' . $normalizedSegments[1];
        }

        $candidates[] = $language;

        foreach ($candidates as $candidate) {
            $supported = self::matchSupported($candidate);

            if ($supported !== '') {
                return $supported;
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private static function currentCandidates(): array
    {
        $candidates = [];

        if (function_exists('pll_current_language')) {
            $polylangLocale = pll_current_language('locale');
            $polylangSlug = pll_current_language('slug');

            if (is_string($polylangLocale) && $polylangLocale !== '') {
                $candidates[] = $polylangLocale;
            }

            if (is_string($polylangSlug) && $polylangSlug !== '') {
                $candidates[] = $polylangSlug;
            }
        }

        if (function_exists('apply_filters')) {
            $wpmlLanguage = apply_filters('wpml_current_language', null);

            if (is_string($wpmlLanguage) && $wpmlLanguage !== '') {
                $candidates[] = $wpmlLanguage;
            }
        }

        if (function_exists('determine_locale')) {
            $candidates[] = (string) determine_locale();
        }

        if (function_exists('get_locale')) {
            $candidates[] = (string) get_locale();
        }

        return $candidates;
    }

    private static function matchSupported(string $candidate): string
    {
        foreach (self::SUPPORTED as $supportedLanguage) {
            if (strcasecmp($supportedLanguage, $candidate) === 0) {
                return $supportedLanguage;
            }
        }

        return '';
    }
}
