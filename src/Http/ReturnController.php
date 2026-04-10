<?php

namespace ProofAge\WordPress\Http;

use ProofAge\WordPress\ProofAge\ApiClient;
use ProofAge\WordPress\Verification\ApprovalMatcher;
use ProofAge\WordPress\Verification\SessionManager;

final class ReturnController
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly ApiClient $apiClient,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('template_redirect', [$this, 'maybeHandle']);
    }

    public function maybeHandle(): void
    {
        if (! isset($_GET['proofage-return'])) {
            return;
        }

        $state = $this->sessionManager->getState();
        $verificationId = (string) ($state['verification_id'] ?? '');
        $originReturnUrl = $this->sessionManager->resolveReturnUrl(
            isset($_GET['origin']) ? rawurldecode((string) $_GET['origin']) : (string) ($state['return_url'] ?? '')
        );

        if ($verificationId !== '' && ! $this->sessionManager->isVerified()) {
            $response = $this->apiClient->getVerification($verificationId);

            if (
                ! is_wp_error($response)
                && (($response['data']['status'] ?? '') === 'approved')
                && ApprovalMatcher::matches($state, $verificationId, (string) ($response['data']['external_id'] ?? ''))
            ) {
                $this->sessionManager->markApproved($verificationId, [
                    'external_id' => (string) ($response['data']['external_id'] ?? ''),
                    'session_token' => (string) ($state['session_token'] ?? ''),
                    'return_url' => $originReturnUrl,
                ]);
            } elseif (! is_wp_error($response) && $this->isTerminalFailureStatus((string) ($response['data']['status'] ?? ''))) {
                $this->sessionManager->markFailed(
                    $verificationId,
                    (string) ($response['data']['status'] ?? 'failed'),
                    $originReturnUrl
                );
            }
        }

        $finalState = $this->sessionManager->getState();
        $status = $this->sessionManager->isVerified()
            ? 'approved'
            : $this->normalizeBrowserStatus((string) ($finalState['status'] ?? 'pending'));
        $redirectUrl = add_query_arg('proofage_status', $status, $originReturnUrl);

        nocache_headers();
        status_header(200);

        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e('Verification complete', 'proofage-age-verification'); ?></title>
        </head>
        <body>
            <p><?php esc_html_e('Returning to the store…', 'proofage-age-verification'); ?></p>
            <script>
                (function () {
                    const payload = {
                        source: 'proofage-wordpress',
                        status: <?php echo wp_json_encode($status); ?>,
                        redirectUrl: <?php echo wp_json_encode($redirectUrl); ?>,
                    };

                    if (window.opener) {
                        window.opener.postMessage(payload, window.location.origin);

                        if (payload.status === 'approved') {
                            window.close();
                            return;
                        }
                    }

                    window.location.replace(payload.redirectUrl);
                }());
            </script>
        </body>
        </html>
        <?php

        exit;
    }

    private function isTerminalFailureStatus(string $status): bool
    {
        return in_array($status, ['declined', 'resubmission_requested', 'abandoned', 'expired', 'failed'], true);
    }

    private function normalizeBrowserStatus(string $status): string
    {
        return $status === 'review' ? 'pending' : $status;
    }
}
