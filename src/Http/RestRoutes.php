<?php

namespace ProofAge\WordPress\Http;

use ProofAge\WordPress\ProofAge\ApiClient;
use ProofAge\WordPress\Support\Options;
use ProofAge\WordPress\Support\VerificationLanguage;
use ProofAge\WordPress\Verification\ApprovalMatcher;
use ProofAge\WordPress\Verification\SessionManager;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

final class RestRoutes
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly SessionManager $sessionManager,
        private readonly WebhookController $webhookController,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('proofage/v1', '/session', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'createSession'],
        ]);

        register_rest_route('proofage/v1', '/status', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'getStatus'],
        ]);

        register_rest_route('proofage/v1', '/webhook', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this->webhookController, 'handle'],
        ]);
    }

    public function createSession(WP_REST_Request $request): array|WP_Error
    {
        $guardResult = $this->guardSessionBootstrap($request);

        if (is_wp_error($guardResult)) {
            return $guardResult;
        }

        $originReturnUrl = $this->sessionManager->resolveReturnUrl((string) $request->get_param('return_url'));
        $callbackUrl = add_query_arg(
            [
                'proofage-return' => '1',
                'origin' => rawurlencode($originReturnUrl),
            ],
            home_url('/')
        );

        $externalId = $this->sessionManager->createExternalId();
        $language = VerificationLanguage::resolve((string) $request->get_param('language'));
        $metadata = [];

        if ($language !== '') {
            $metadata['sdk_preferences'] = [
                'language' => $language,
            ];
        }

        $response = $this->apiClient->createVerification($externalId, $callbackUrl, [
            'integration' => 'wordpress-plugin',
        ], $metadata);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $verificationId = (string) ($data['id'] ?? '');
        $hostedUrl = (string) ($data['url'] ?? '');

        if ($verificationId === '' || $hostedUrl === '') {
            return new WP_Error(
                'proofage_invalid_response',
                __('ProofAge did not return a verification URL.', 'proofage-age-verification')
            );
        }

        $state = $this->sessionManager->beginPendingSession($verificationId, $originReturnUrl, $externalId);

        return [
            'verification_id' => $verificationId,
            'url' => $hostedUrl,
            'launch_mode' => Options::get('launch_mode', 'redirect'),
            'state' => $state,
        ];
    }

    public function getStatus(WP_REST_Request $request): array|WP_Error
    {
        $state = $this->sessionManager->getState();
        $verificationId = (string) ($state['verification_id'] ?? '');

        if ($verificationId !== '' && ! $this->sessionManager->isVerified()) {
            $response = $this->apiClient->getVerification($verificationId);

            if (! is_wp_error($response)) {
                $data = is_array($response['data'] ?? null) ? $response['data'] : [];

                if (
                    ($data['status'] ?? '') === 'approved'
                    && ApprovalMatcher::matches($state, $verificationId, (string) ($data['external_id'] ?? ''))
                ) {
                    $this->sessionManager->markApproved($verificationId, [
                        'external_id' => (string) ($data['external_id'] ?? ''),
                        'session_token' => (string) ($state['session_token'] ?? ''),
                        'return_url' => (string) ($state['return_url'] ?? ''),
                    ]);
                    $state = $this->sessionManager->getState();
                } elseif ($this->isTerminalFailureStatus((string) ($data['status'] ?? ''))) {
                    $this->sessionManager->markFailed(
                        $verificationId,
                        (string) ($data['status'] ?? 'failed'),
                        (string) ($state['return_url'] ?? '')
                    );
                    $state = $this->sessionManager->getState();
                }
            }
        }

        return [
            'verified' => $this->sessionManager->isVerified(),
            'state' => $state,
        ];
    }

    private function isTerminalFailureStatus(string $status): bool
    {
        return in_array($status, ['declined', 'resubmission_requested', 'abandoned', 'expired', 'failed'], true);
    }

    private function guardSessionBootstrap(WP_REST_Request $request): ?WP_Error
    {
        $nonce = (string) $request->get_header('x-wp-nonce');

        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'proofage_invalid_nonce',
                __('ProofAge session bootstrap requires a valid storefront nonce.', 'proofage-age-verification'),
                ['status' => 403]
            );
        }

        $remoteAddress = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rateLimitKey = 'proofage_rate_' . md5($remoteAddress);
        $requestCount = function_exists('get_transient') ? (int) get_transient($rateLimitKey) : 0;

        if ($requestCount >= 20) {
            return new WP_Error(
                'proofage_rate_limited',
                __('Too many verification attempts from this address. Please wait and try again.', 'proofage-age-verification'),
                ['status' => 429]
            );
        }

        if (function_exists('set_transient')) {
            set_transient($rateLimitKey, $requestCount + 1, 10 * MINUTE_IN_SECONDS);
        }

        return null;
    }
}
