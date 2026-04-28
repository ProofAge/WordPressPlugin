<?php

namespace ProofAge\WordPress\ProofAge;

if (! defined('ABSPATH')) {
    exit;
}

final class WebhookSignatureVerifier
{
    public function __construct(private readonly int $replayWindowSeconds = 300)
    {
    }

    public function isValid(
        string $timestamp,
        string $rawBody,
        string $providedSignature,
        string $secretKey,
        ?int $currentTime = null,
    ): bool {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $currentTime ??= time();

        if (abs($currentTime - (int) $timestamp) > $this->replayWindowSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secretKey);

        return hash_equals($expected, $providedSignature);
    }
}
