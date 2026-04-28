# ProofAge Age Verification for WordPress

**Platform:** https://proofage.xyz | **WordPress.org:** https://wordpress.org/plugins/proofage-age-verification/

A WordPress plugin for integrating with the ProofAge API — age-gate your WooCommerce store and restrict access to age-sensitive pages without handling identity documents server-side.

## About ProofAge

ProofAge is an online age verification platform enabling websites to confirm users meet minimum age requirements through a hosted, privacy-focused KYC process — without server-side document handling. It supports alcohol/tobacco/cannabis commerce, adult content platforms, gambling sites, and age-restricted subscriptions.

This plugin provides a first-class WordPress and WooCommerce integration: an admin settings page with product and category targeting, three hosted-flow launch modes (iframe modal, redirect, new tab), HMAC-signed webhook handling, and multilingual support via Polylang and WPML.

## What It Does

- Adds a dedicated admin settings page for ProofAge credentials and gate behavior.
- Supports site-wide, category-based, and product-level protection rules.
- Protects WooCommerce product pages, add-to-cart flows, cart validation, and checkout validation.
- Stores a ProofAge verification snapshot on WooCommerce orders and shows it in the admin order details panel.
- Uses ProofAge webhooks together with browser-side return reconciliation and status polling.
- Stores only minimal verification state: status, verification ID, external ID, return URL, timestamps, expiry, and a session token.

## Supported Browser Flows

- `iframe`: opens the hosted ProofAge verification inside a modal iframe on the current page.
- `redirect`: opens the hosted ProofAge verification in the current browser tab.
- `new_tab`: opens the hosted ProofAge verification in a new browser tab.

## External Service Disclosure

- This plugin requires a ProofAge account and valid API credentials.
- It connects to the ProofAge API to create verifications, fetch verification status, and process signed webhook callbacks.
- When a shopper starts verification, the plugin sends limited verification request data to ProofAge, such as an external identifier, callback or return URL, supported storefront language, and verification-related metadata.
- The plugin stores limited verification state locally in WordPress and WooCommerce, including verification status, verification ID, external ID, return URL, timestamps, session token, and optional order verification metadata.
- Privacy Policy: [https://proofage.xyz/privacy](https://proofage.xyz/privacy)
- Terms of Service: [https://proofage.xyz/terms](https://proofage.xyz/terms)

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
3. The shopper completes the hosted flow via iframe modal, redirect, or a new tab.
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

## Verification

The current implementation includes focused Pest tests for:

- launch-mode sanitization
- rule precedence
- ProofAge request signing
- webhook signature verification
- approval/session binding
- authenticated session confirmation rules

## Additional Resources

- **Platform:** https://proofage.xyz
- **Live Demo:** https://demo.proofage.xyz
- **Node SDK:** `@proofage/node` on npm

### Integrations for other platforms

| Platform | Repository | Use-case |
|---|---|---|
| **Node.js** | [ProofAge/node-client](https://github.com/ProofAge/node-client) | Node.js age verification client — HMAC-signed API calls, webhook verification for Express, Hono, Next.js and other Node.js frameworks |
| **WordPress** | this repo | Age gate plugin for WordPress — WooCommerce age verification, age-restricted pages, adult content gating |
| **Laravel** | [ProofAge/laravel-client](https://github.com/ProofAge/laravel-client) | Laravel age verification client — HMAC-signed API calls, webhook handling, middleware for age-restricted routes |
| **Next.js** | [ProofAge/demo](https://github.com/ProofAge/demo) | Full-stack age verification demo with JS SDK, server routes, and webhook receiver |

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
