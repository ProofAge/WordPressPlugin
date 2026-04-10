<?php

namespace ProofAge\WordPress\Verification;

final class UserStateStatusResolver
{
    public static function resolve(int $verifiedAt, int $expiresAt, string $storedSessionToken, string $browserSessionToken): string
    {
        if ($verifiedAt > 0 && $expiresAt >= time()) {
            return 'approved';
        }

        return 'pending';
    }
}
