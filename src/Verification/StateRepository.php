<?php

namespace ProofAge\WordPress\Verification;

use ProofAge\WordPress\Support\Options;

if (! defined('ABSPATH')) {
    exit;
}

final class StateRepository
{
    private const COOKIE_NAME = 'proofage_verification_state';
    private const TRANSIENT_PREFIX = 'proofage_verification_';
    private const USER_META_VERIFIED_AT = '_proofage_verified_at';
    private const USER_META_EXPIRES_AT = '_proofage_expires_at';
    private const USER_META_VERIFICATION_ID = '_proofage_verification_id';
    private const USER_META_SESSION_TOKEN = '_proofage_session_token';

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $cookieState = $this->readCookieState();
        $userState = $this->readUserState($cookieState);
        $mergedState = $cookieState;

        if ($userState !== []) {
            $mergedState = ($userState['status'] ?? 'pending') === 'approved'
                ? array_replace($cookieState, $userState)
                : array_replace($userState, $cookieState);
        }

        if (($userState['status'] ?? 'pending') === 'approved') {
            return $mergedState;
        }

        $verificationId = (string) ($mergedState['verification_id'] ?? '');

        if ($verificationId !== '') {
            $serverState = $this->getVerificationState($verificationId);

            if ($serverState !== []) {
                $sessionToken = (string) ($cookieState['session_token'] ?? '');
                $serverSessionToken = (string) ($serverState['session_token'] ?? '');

                if (
                    ($serverState['status'] ?? 'pending') === 'approved'
                    && $sessionToken !== ''
                    && $serverSessionToken !== ''
                    && hash_equals($sessionToken, $serverSessionToken)
                ) {
                    return array_replace($mergedState, $serverState);
                }

                if (($serverState['status'] ?? 'pending') !== 'approved') {
                    return array_replace($mergedState, $serverState);
                }
            }
        }

        return $mergedState;
    }

    public function isVerified(): bool
    {
        $state = $this->getState();
        $expiresAt = (int) ($state['expires_at'] ?? 0);

        return ($state['status'] ?? null) === 'approved'
            && $expiresAt > 0
            && $expiresAt >= time();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function persist(array $state): void
    {
        $normalized = [
            'status' => (string) ($state['status'] ?? 'pending'),
            'verification_id' => (string) ($state['verification_id'] ?? ''),
            'external_id' => (string) ($state['external_id'] ?? ''),
            'session_token' => (string) ($state['session_token'] ?? ''),
            'return_url' => (string) ($state['return_url'] ?? ''),
            'verified_at' => (int) ($state['verified_at'] ?? 0),
            'expires_at' => (int) ($state['expires_at'] ?? 0),
        ];

        $this->persistCookieState($normalized);
        $this->persistUserState($normalized);
        $this->persistServerState($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function getVerificationState(string $verificationId): array
    {
        if ($verificationId === '' || ! function_exists('get_transient')) {
            return [];
        }

        $state = get_transient($this->transientKey($verificationId));

        return is_array($state) ? $state : [];
    }

    public function clear(): void
    {
        $state = $this->getState();

        if (! headers_sent()) {
            setcookie(self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
            unset($_COOKIE[self::COOKIE_NAME]);
        }

        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        if ($userId > 0 && function_exists('delete_user_meta')) {
            delete_user_meta($userId, self::USER_META_VERIFIED_AT);
            delete_user_meta($userId, self::USER_META_EXPIRES_AT);
            delete_user_meta($userId, self::USER_META_VERIFICATION_ID);
            delete_user_meta($userId, self::USER_META_SESSION_TOKEN);
        }

        if (! empty($state['verification_id']) && function_exists('delete_transient')) {
            delete_transient($this->transientKey((string) $state['verification_id']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readCookieState(): array
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the signed payload is validated before decoding.
        $raw = isset($_COOKIE[self::COOKIE_NAME]) ? wp_unslash($_COOKIE[self::COOKIE_NAME]) : null;

        if (! is_string($raw) || ! str_contains($raw, '.')) {
            return [];
        }

        [$encoded, $signature] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $encoded, wp_salt('auth'));

        if (! hash_equals($expected, $signature)) {
            return [];
        }

        $decoded = base64_decode($encoded, true);

        if (! is_string($decoded)) {
            return [];
        }

        $state = json_decode($decoded, true);

        return is_array($state) ? $state : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persistCookieState(array $state): void
    {
        if (headers_sent()) {
            return;
        }

        $encoded = base64_encode(wp_json_encode($state));
        $signature = hash_hmac('sha256', $encoded, wp_salt('auth'));
        $expiresAt = (int) ($state['expires_at'] ?? (time() + (int) Options::get('session_ttl_hours', 24) * HOUR_IN_SECONDS));

        setcookie(
            self::COOKIE_NAME,
            $encoded . '.' . $signature,
            $expiresAt,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN ?: '',
            is_ssl(),
            true
        );

        $_COOKIE[self::COOKIE_NAME] = $encoded . '.' . $signature;
    }

    /**
     * @return array<string, mixed>
     */
    private function readUserState(array $cookieState = []): array
    {
        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        if ($userId <= 0 || ! function_exists('get_user_meta')) {
            return [];
        }

        $verifiedAt = (int) get_user_meta($userId, self::USER_META_VERIFIED_AT, true);
        $expiresAt = (int) get_user_meta($userId, self::USER_META_EXPIRES_AT, true);
        $storedSessionToken = (string) get_user_meta($userId, self::USER_META_SESSION_TOKEN, true);
        $browserSessionToken = (string) ($cookieState['session_token'] ?? '');

        return [
            'status' => UserStateStatusResolver::resolve($verifiedAt, $expiresAt, $storedSessionToken, $browserSessionToken),
            'verification_id' => (string) get_user_meta($userId, self::USER_META_VERIFICATION_ID, true),
            'session_token' => $storedSessionToken,
            'verified_at' => $verifiedAt,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persistUserState(array $state): void
    {
        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        if ($userId <= 0 || ! function_exists('update_user_meta')) {
            return;
        }

        if (($state['status'] ?? 'pending') !== 'approved') {
            return;
        }

        update_user_meta($userId, self::USER_META_VERIFIED_AT, (int) ($state['verified_at'] ?? 0));
        update_user_meta($userId, self::USER_META_EXPIRES_AT, (int) ($state['expires_at'] ?? 0));
        update_user_meta($userId, self::USER_META_VERIFICATION_ID, (string) ($state['verification_id'] ?? ''));
        update_user_meta($userId, self::USER_META_SESSION_TOKEN, (string) ($state['session_token'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persistServerState(array $state): void
    {
        $verificationId = (string) ($state['verification_id'] ?? '');

        if ($verificationId === '' || ! function_exists('set_transient')) {
            return;
        }

        $existingState = $this->getVerificationState($verificationId);

        if ($existingState !== []) {
            $state = array_replace($existingState, array_filter(
                $state,
                static fn (mixed $value): bool => $value !== '' && $value !== null
            ));
        }

        $expiresAt = (int) ($state['expires_at'] ?? time() + ((int) Options::get('session_ttl_hours', 24) * HOUR_IN_SECONDS));
        $ttl = max(1, $expiresAt - time());

        set_transient($this->transientKey($verificationId), $state, $ttl);
    }

    private function transientKey(string $verificationId): string
    {
        return self::TRANSIENT_PREFIX . md5($verificationId);
    }
}
