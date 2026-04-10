<?php

namespace ProofAge\WordPress\Verification;

final class ApprovalMatcher
{
    /**
     * @param  array<string, mixed>  $pendingState
     */
    public static function matches(array $pendingState, string $verificationId, string $externalId): bool
    {
        if (($pendingState['verification_id'] ?? '') !== $verificationId) {
            return false;
        }

        $pendingExternalId = (string) ($pendingState['external_id'] ?? '');

        if ($pendingExternalId === '') {
            return true;
        }

        return $externalId !== '' && hash_equals($pendingExternalId, $externalId);
    }
}
