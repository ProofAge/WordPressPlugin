(() => {
    const config = window.ProofAgeWordPress;
    const pendingUrlKey = 'proofage-pending-url';
    const pendingVerificationKey = 'proofage-verification-id';

    if (!config) {
        return;
    }

    const getVerificationLanguage = () => {
        const documentLanguage = document.documentElement?.lang || '';

        return documentLanguage || config.language || '';
    };

    const startVerification = async (returnUrl) => {
        const response = await fetch(config.sessionEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify({
                return_url: returnUrl || window.location.href,
                language: getVerificationLanguage(),
            }),
        });

        if (!response.ok) {
            let message = config.messages.error || 'Unable to start verification.';

            try {
                const payload = await response.json();

                if (typeof payload?.message === 'string' && payload.message !== '') {
                    message = payload.message;
                }
            } catch (_error) {
                // Fall back to the configured storefront copy when the error payload is unavailable.
            }

            throw new Error(message);
        }

        return response.json();
    };

    const terminalFailureStatuses = new Set([
        'declined',
        'resubmission_requested',
        'abandoned',
        'expired',
        'failed',
    ]);

    const stripProofAgeStatus = (inputUrl) => {
        const url = new URL(inputUrl || window.location.href, window.location.origin);
        url.searchParams.delete('proofage_status');

        return url.toString();
    };

    const pollVerificationStatus = (verificationId, returnUrl) => {
        if (!verificationId) {
            return;
        }

        let attempts = 0;
        const intervalId = window.setInterval(async () => {
            attempts += 1;

            try {
                const response = await fetch(config.statusEndpoint, {
                    headers: {
                        'X-WP-Nonce': config.nonce,
                    },
                });

                if (!response.ok) {
                    if (attempts >= 30) {
                        window.clearInterval(intervalId);
                    }

                    return;
                }

                const payload = await response.json();

                if (payload.verified) {
                    window.clearInterval(intervalId);
                    resumePendingAction(returnUrl);
                    return;
                }

                if (terminalFailureStatuses.has(payload.state?.status)) {
                    window.clearInterval(intervalId);
                    localStorage.removeItem(pendingVerificationKey);

                    const failureUrl = new URL(stripProofAgeStatus(returnUrl || window.location.href), window.location.origin);
                    failureUrl.searchParams.set('proofage_status', payload.state.status);
                    window.location.assign(failureUrl.toString());
                    return;
                }
            } catch (_error) {
                if (attempts >= 30) {
                    window.clearInterval(intervalId);
                }
            }
        }, 2000);
    };

    const launchHostedFlow = (payload) => {
        if (payload.launch_mode === 'new_tab') {
            const popup = window.open(payload.url, '_blank');

            if (!popup) {
                window.location.assign(payload.url);
                return;
            }

            pollVerificationStatus(payload.verification_id, payload.state?.return_url || window.location.href);
            return;
        }

        window.location.assign(payload.url);
    };

    const handleStartClick = async (event) => {
        const trigger = event.target.closest('[data-proofage-start]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        try {
            const returnUrl = trigger.dataset.proofageReturnUrl || window.location.href;
            const payload = await startVerification(returnUrl);
            localStorage.setItem(pendingVerificationKey, payload.verification_id);
            launchHostedFlow(payload);
        } catch (error) {
            window.alert(error.message);
        }
    };

    const handleProtectedAddToCart = async (event) => {
        const link = event.target.closest('[data-proofage-protected-add-to-cart]');

        if (!link) {
            return;
        }

        event.preventDefault();
        localStorage.setItem(pendingUrlKey, link.href);

        try {
            const payload = await startVerification(window.location.href);
            localStorage.setItem(pendingVerificationKey, payload.verification_id);
            launchHostedFlow(payload);
        } catch (error) {
            window.alert(error.message);
        }
    };

    const resumePendingAction = (redirectUrl) => {
        const pendingUrl = localStorage.getItem(pendingUrlKey);
        const nextUrl = stripProofAgeStatus(redirectUrl || window.location.href);

        if (pendingUrl) {
            localStorage.removeItem(pendingUrlKey);
            localStorage.removeItem(pendingVerificationKey);
            window.location.assign(pendingUrl);
            return;
        }

        localStorage.removeItem(pendingVerificationKey);

        if (nextUrl !== stripProofAgeStatus(window.location.href)) {
            window.location.assign(nextUrl);
            return;
        }

        if (nextUrl === window.location.href) {
            window.location.reload();
            return;
        }

        window.location.replace(nextUrl);
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) {
            return;
        }

        if (event.data?.source !== 'proofage-wordpress') {
            return;
        }

        if (event.data.status === 'approved') {
            resumePendingAction(event.data.redirectUrl);
            return;
        }

        if (terminalFailureStatuses.has(event.data.status) && event.data.redirectUrl) {
            window.location.assign(event.data.redirectUrl);
        }
    });

    document.addEventListener('click', handleStartClick);
    document.addEventListener('click', handleProtectedAddToCart);

    const proofageStatus = new URLSearchParams(window.location.search).get('proofage_status');

    if (proofageStatus === 'approved') {
        resumePendingAction(window.location.href);
    } else {
        const pendingVerificationId = localStorage.getItem(pendingVerificationKey);

        if (pendingVerificationId && (proofageStatus === 'pending' || proofageStatus === 'review' || !proofageStatus)) {
            pollVerificationStatus(pendingVerificationId, window.location.href);
        }
    }
})();
