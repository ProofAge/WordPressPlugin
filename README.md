# ProofAge Age Verification for WordPress

This plugin integrates the hosted ProofAge verification flow with WordPress and WooCommerce.

## What It Does

- Adds a dedicated admin settings page for ProofAge credentials and gate behavior.
- Supports site-wide, category-based, and product-level protection rules.
- Protects WooCommerce product pages, add-to-cart flows, cart validation, and checkout validation.
- Stores a ProofAge verification snapshot on WooCommerce orders and shows it in the admin order details panel.
- Uses ProofAge webhooks together with browser-side return reconciliation and status polling.
- Stores only minimal verification state: status, verification ID, external ID, return URL, timestamps, expiry, and a session token.

## Supported Browser Flows

- `redirect`: opens the hosted ProofAge verification in the current browser tab.
- `new_tab`: opens the hosted ProofAge verification in a new browser tab.

## Not Supported in v1

- Hosted iframe or modal embedding.
- A custom in-page ProofAge capture UI.
- Theme-specific quick-view integrations beyond the generic add-to-cart interception hooks.

These items were intentionally deferred until ProofAge confirms the hosted flow's iframe and callback guarantees.

## Installation

1. Copy the `proofage-age-verification` directory into `wp-content/plugins/`.
2. Activate **ProofAge Age Verification** in the WordPress admin.
3. Open `Settings -> ProofAge Verification`.
4. Enter your ProofAge API key and secret key.
5. Configure launch mode, gate texts, TTL, and product/category targeting rules.
6. Set the ProofAge webhook URL to:

```text
https://your-store.example/wp-json/proofage/v1/webhook
```

7. Ensure ProofAge can return to this handler URL:

```text
https://your-store.example/?proofage-return=1
```

The plugin appends an `origin` query parameter automatically for each verification attempt so the shopper can be returned to the correct page after the hosted flow finishes.

## Configuration Notes

- Product and category overrides are available only when WooCommerce is active.
- For guests, verification state is tracked with a signed browser cookie plus a server-side transient.
- For authenticated users, verification also persists to user meta, but access still requires the current browser session token.
- The connectivity test in the settings page uses the `GET /v1/consent` ProofAge endpoint.
- When Polylang or WPML String Translation is active, the Gate texts fields act as source strings and can be translated through the multilingual plugin UI.
- Verification creation passes the current storefront language to ProofAge when it matches a supported ProofAge SDK language.

## Developer Notes

### Main Entry Points

- `proofage-age-verification.php` boots the plugin.
- `src/Plugin.php` wires all modules together.
- `src/ProofAge/ApiClient.php` handles outbound ProofAge API requests.
- `src/Verification/RulesEngine.php` resolves global, category, product, and exclusion precedence.
- `src/Verification/StateRepository.php` stores cookie, transient, and user-meta state.
- `src/Http/RestRoutes.php` exposes the session, status, and webhook endpoints.

### Runtime Flow

1. Frontend JavaScript calls `POST /wp-json/proofage/v1/session`.
2. The plugin creates a ProofAge verification and stores a pending local session.
3. The shopper completes the hosted flow via redirect or a new tab.
4. ProofAge sends a webhook to `/wp-json/proofage/v1/webhook`.
5. The browser returns to `/?proofage-return=1`, where the plugin reconciles transient state and updates the local session when a terminal decision is available.

## Supported Protection Points

- Site-wide page gating.
- WooCommerce single product gating.
- Loop add-to-cart interception.
- Server-side add-to-cart validation.
- Cart validation.
- Checkout validation.
- Store API add-to-cart validation for block-based flows.

## Known Limitations

- WooCommerce is not present in the current local `wordpress` tree, so Woo-specific runtime behavior still needs testing in a real WooCommerce installation.
- The plugin does not yet implement uninstall-time cleanup for user meta and transients beyond the main option.
- Hosted iframe/modal support is intentionally disabled in this version.
- Browser automation coverage for popup/new-tab return flows has not been added yet.

## Verification

The current implementation includes focused Pest tests for:

- launch-mode sanitization
- rule precedence
- ProofAge request signing
- webhook signature verification
- approval/session binding
- authenticated session confirmation rules
