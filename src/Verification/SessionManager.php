<?php

namespace ProofAge\WordPress\Verification;

use ProofAge\WordPress\Support\Options;

final class SessionManager
{
    public function __construct(private readonly StateRepository $stateRepository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function beginPendingSession(string $verificationId, string $returnUrl, ?string $externalId = null): array
    {
        $externalId ??= $this->createExternalId();
        $expiresAt = time() + ((int) Options::get('session_ttl_hours', 24) * HOUR_IN_SECONDS);
        $sessionToken = function_exists('wp_generate_password')
            ? wp_generate_password(32, false, false)
            : bin2hex(random_bytes(16));

        $state = [
            'status' => 'pending',
            'verification_id' => $verificationId,
            'external_id' => $externalId,
            'session_token' => $sessionToken,
            'return_url' => $returnUrl,
            'verified_at' => 0,
            'expires_at' => $expiresAt,
        ];

        $this->stateRepository->persist($state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markApproved(string $verificationId, array $payload = []): void
    {
        $verifiedAt = time();
        $expiresAt = $verifiedAt + ((int) Options::get('session_ttl_hours', 24) * HOUR_IN_SECONDS);
        $existingState = $this->stateRepository->getVerificationState($verificationId);

        $this->stateRepository->persist([
            'status' => 'approved',
            'verification_id' => $verificationId,
            'external_id' => (string) ($payload['external_id'] ?? ($existingState['external_id'] ?? '')),
            'session_token' => (string) ($payload['session_token'] ?? ($existingState['session_token'] ?? '')),
            'return_url' => (string) ($payload['return_url'] ?? ($existingState['return_url'] ?? '')),
            'verified_at' => $verifiedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public function markFailed(string $verificationId, string $status = 'failed', string $returnUrl = ''): void
    {
        $existingState = $this->stateRepository->getVerificationState($verificationId);

        $this->stateRepository->persist([
            'status' => $status,
            'verification_id' => $verificationId,
            'external_id' => (string) ($existingState['external_id'] ?? ''),
            'session_token' => (string) ($existingState['session_token'] ?? ''),
            'return_url' => $returnUrl !== '' ? $returnUrl : (string) ($existingState['return_url'] ?? ''),
            'verified_at' => 0,
            'expires_at' => time() + HOUR_IN_SECONDS,
        ]);
    }

    public function markPendingStatus(string $verificationId, string $status = 'pending'): void
    {
        $existingState = $this->stateRepository->getVerificationState($verificationId);
        $expiresAt = (int) ($existingState['expires_at'] ?? (time() + ((int) Options::get('session_ttl_hours', 24) * HOUR_IN_SECONDS)));

        $this->stateRepository->persist([
            'status' => $status,
            'verification_id' => $verificationId,
            'external_id' => (string) ($existingState['external_id'] ?? ''),
            'session_token' => (string) ($existingState['session_token'] ?? ''),
            'return_url' => (string) ($existingState['return_url'] ?? ''),
            'verified_at' => 0,
            'expires_at' => $expiresAt,
        ]);
    }

    public function resolveReturnUrl(?string $candidate = null): string
    {
        if ($candidate && function_exists('wp_validate_redirect')) {
            return wp_validate_redirect($candidate, home_url('/'));
        }

        return function_exists('home_url') ? home_url('/') : '/';
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->stateRepository->getState();
    }

    public function isVerified(): bool
    {
        return $this->stateRepository->isVerified();
    }

    public function createExternalId(): string
    {
        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        if ($userId > 0) {
            return 'user-' . $userId;
        }

        return 'guest-' . wp_generate_uuid4();
    }
}
