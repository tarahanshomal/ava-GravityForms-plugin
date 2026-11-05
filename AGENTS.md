# Ava Gravity Forms plugin

Standalone public WordPress add-on. Namespace AvaGF. Target PHP 7.4+, WordPress 6.5+, Gravity Forms 2.8+. Never copy private backend code, customer data, credentials or server topology. Tests use synthetic data and mocked HTTP; never send real SMS.

Current API POST /api/v1/sms requires sms:send; GET /api/v1/sms/{id} requires sms:read. New SMS requests await moderation and explicit start in Ava; accepted is not delivered. Preserve review metadata. Use HTTPS origin only, safe HTTP, no redirects, TLS verification. Never expose saved API keys or response bodies. Preserve blank key input. Settings require manage_options and nonce.

Architecture: Settings::get/defaults/sanitize/valid_url; Phone::normalize/mask; Api::send/report; Delivery::process($feed,$entry,$form) returns per-recipient summaries, with durable atomic per-entry/feed guard before HTTP and no automatic retry. AddOn extends GFFeedAddOn and calls Delivery. Main file ava-gravityforms-plugin.php, constants AVA_GF_VERSION and AVA_GF_FILE. Option ava_gf_settings. Secret override AVA_GF_API_KEY.

Run php tests/run.php, Python distribution tests, PHP lint, and WordPress integration smoke with simulated GF contracts before release. Document exact scope of testing; commercial Gravity Forms may not be installed. ZIP explicit allowlist; no tests, .git or generated/runtime files.
