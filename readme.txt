=== ProofAge Age Verification ===
Contributors: proofage
Tags: age verification, age gate, woocommerce, checkout, ecommerce
Requires at least: 6.4
Tested up to: 6.9.4
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds ProofAge-powered age verification to WordPress and WooCommerce storefronts.

== Description ==

ProofAge Age Verification integrates the hosted ProofAge verification flow with WordPress and WooCommerce.

With this plugin you can:

* protect the full site or selected WordPress pages and post categories
* protect WooCommerce products and product categories
* block add-to-cart, cart, checkout, and Store API flows until verification is complete
* show a full-page gate or a blocking overlay, depending on the protected content
* store a verification snapshot on WooCommerce orders and show it in the admin order details panel
* localize gate copy and pass the supported storefront language to ProofAge when creating a verification

The plugin uses the hosted ProofAge flow and webhooks together with browser-side return reconciliation and status polling.

External service disclosure:

* this plugin requires a ProofAge account and valid API credentials
* it connects to the ProofAge API to create verifications, fetch verification status, and process signed webhook callbacks
* when a shopper starts verification, the plugin sends limited verification request data to ProofAge, such as an external identifier, callback or return URL, supported storefront language, and verification-related metadata
* the plugin stores limited verification state locally in WordPress and WooCommerce, including verification status, verification ID, external ID, return URL, timestamps, session token, and optional order verification metadata

Supported browser flows:

* redirect in the current window
* open the hosted verification in a new tab

This version intentionally does not support:

* hosted iframe or modal embedding
* a custom in-page ProofAge capture UI
* theme-specific quick-view integrations beyond the generic add-to-cart interception hooks

== Installation ==

1. Upload the proofage-age-verification folder to the /wp-content/plugins/ directory, or install the plugin through WordPress.
2. Activate ProofAge Age Verification in the WordPress admin.
3. Go to Settings -> ProofAge Verification.
4. Enter your ProofAge API key and secret key.
5. Configure launch mode, gate texts, verification TTL, and protection scope.
6. Set the ProofAge webhook URL to https://your-store.example/wp-json/proofage/v1/webhook.
7. Make sure ProofAge can return to https://your-store.example/?proofage-return=1.

The plugin automatically appends an origin query parameter so shoppers can be returned to the page where they started verification.

== Frequently Asked Questions ==

= Does this plugin support WooCommerce? =

Yes. It supports WooCommerce product protection, category protection, single-product gating, loop add-to-cart interception, cart validation, checkout validation, and Store API add-to-cart validation for block-based flows.

= Can I protect only specific products or categories? =

Yes. You can combine site-wide rules with product, product-category, page, and WordPress post-category targeting. Exclusions are also supported.

= Can I translate the gate texts? =

Yes. The Gate texts can be translated through Polylang or WPML String Translation. The plugin also passes the current storefront language to ProofAge when it matches a supported ProofAge SDK language.

= Does this plugin use an external service? =

Yes. This plugin connects to ProofAge to create verifications, fetch status updates, and process webhook callbacks. A ProofAge account and API credentials are required.

= What data does the plugin send to ProofAge and store locally? =

When a shopper starts verification, the plugin sends limited verification request data to ProofAge, such as an external identifier, callback or return URL, supported storefront language, and verification-related metadata. The plugin stores limited verification state locally in WordPress and WooCommerce, including verification status, verification ID, external ID, return URL, timestamps, session token, and optional order verification metadata.

= Does the plugin store verification data on orders? =

Yes. For WooCommerce orders the plugin stores a ProofAge verification snapshot in order meta and displays the verification result in the admin order details view.

= Does this plugin embed ProofAge in an iframe or modal? =

No. This version uses the hosted ProofAge flow only.

== Changelog ==

= 0.1.0 =

* Initial release.
* Added WordPress and WooCommerce protection rules.
* Added hosted verification launch flows for redirect and new tab.
* Added WooCommerce order verification snapshot in admin order details.
* Added multilingual gate text support through Polylang and WPML String Translation.
* Added storefront language passthrough for supported ProofAge SDK languages.

== Upgrade Notice ==

= 0.1.0 =

Initial release.
