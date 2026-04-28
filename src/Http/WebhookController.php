<?php

namespace ProofAge\WordPress\Http;

use ProofAge\WordPress\ProofAge\WebhookSignatureVerifier;
use ProofAge\WordPress\Support\Options;
use ProofAge\WordPress\Verification\SessionManager;

if (! defined('ABSPATH')) {
    exit;
}
use WP_REST_Request;
use WP_REST_Response;

final class WebhookController
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly WebhookSignatureVerifier $signatureVerifier,
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $timestamp = (string) $request->get_header('x-timestamp');
        $signature = (string) $request->get_header('x-hmac-signature');
        $rawBody = $request->get_body();
        $secretKey = (string) Options::get('secret_key', '');

        if ($secretKey === '' || ! $this->signatureVerifier->isValid($timestamp, $rawBody, $signature, $secretKey)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        $verificationId = (string) ($payload['verification_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($verificationId === '' || $status === '') {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Missing verification_id or status.',
            ], 400);
        }

        if ($status === 'approved') {
            $this->sessionManager->markApproved($verificationId, [
                'external_id' => (string) ($payload['external_id'] ?? ''),
            ]);
        } elseif ($status === 'review') {
            $this->sessionManager->markPendingStatus($verificationId, $status);
        } else {
            $this->sessionManager->markFailed($verificationId, $status);
        }

        return new WP_REST_Response([
            'ok' => true,
        ], 200);
    }
}
