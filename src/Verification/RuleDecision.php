<?php

namespace ProofAge\WordPress\Verification;

if (! defined('ABSPATH')) {
    exit;
}

final class RuleDecision
{
    public function __construct(
        public readonly bool $requiresVerification,
        public readonly ?string $matchedRule = null,
        public readonly array $context = [],
    ) {
    }
}
