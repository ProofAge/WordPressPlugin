<?php

namespace ProofAge\WordPress\ProofAge;

use ProofAge\WordPress\Support\Logger;
use ProofAge\WordPress\Support\Options;
use WP_Error;

final class ApiClient
{
    private const BASE_URL = 'https://api.proofage.xyz';

    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function createVerification(
        string $externalId,
        string $returnUrl,
        array $externalMetadata = [],
        array $metadata = []
    ): array|WP_Error
    {
        $payload = [
            'external_id' => $externalId,
            'callback_url' => $returnUrl,
        ];

        if ($externalMetadata !== []) {
            $payload['external_metadata'] = $externalMetadata;
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        return $this->request('POST', '/v1/verifications', $payload);
    }

    public function getVerification(string $verificationId): array|WP_Error
    {
        return $this->request('GET', '/v1/verifications/' . rawurlencode($verificationId));
    }

    public function getConsent(): array|WP_Error
    {
        return $this->request('GET', '/v1/consent');
    }

    public function testConnectivity(): array
    {
        $response = $this->getConsent();

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return [
            'success' => true,
            'message' => __('ProofAge connectivity check succeeded.', 'proofage-age-verification'),
        ];
    }

    private function request(string $method, string $path, ?array $payload = null): array|WP_Error
    {
        $apiKey = (string) Options::get('api_key', '');
        $secretKey = (string) Options::get('secret_key', '');

        if ($apiKey === '') {
            return new WP_Error('proofage_missing_api_key', __('ProofAge API key is not configured.', 'proofage-age-verification'));
        }

        $body = $payload === null ? '' : wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [
            'Accept' => 'application/json',
            'X-API-Key' => $apiKey,
        ];

        if ($body !== '') {
            $headers['Content-Type'] = 'application/json';
        }

        if ($secretKey !== '') {
            $headers['X-HMAC-Signature'] = (new RequestSigner($secretKey))->sign($method, $path, $body);
        }

        $response = wp_remote_request(self::BASE_URL . $path, [
            'method' => $method,
            'timeout' => 15,
            'headers' => $headers,
            'body' => $body === '' ? null : $body,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('ProofAge request failed before receiving a response.', [
                'path' => $path,
                'error' => $response->get_error_message(),
            ]);

            return $response;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($rawBody, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? __('Unknown ProofAge API error.', 'proofage-age-verification'))
                : __('Unknown ProofAge API error.', 'proofage-age-verification');

            $this->logger->error('ProofAge API request returned an error response.', [
                'path' => $path,
                'status_code' => $statusCode,
                'response_body' => $rawBody,
            ]);

            return new WP_Error('proofage_api_error', $message, [
                'status' => $statusCode,
                'response' => $decoded,
            ]);
        }

        return [
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }
}
