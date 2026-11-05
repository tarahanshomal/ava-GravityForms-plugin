=== Ava SMS for Gravity Forms ===
Contributors: tarahanshomal
Tags: gravity forms, sms, persian, notifications, ava
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

اعلان پیامکی فرم‌های Gravity Forms از طریق پنل آوا، توسعه‌یافته توسط طراحان شمال.

== Description ==

Adds Ava SMS feeds to Gravity Forms. Requires separately installed Gravity Forms 2.8+, an Ava account and a key scoped to sms:send and sms:read. Gravity Forms is not included.

Configure Settings > Ava Gravity Forms, then create a native Ava SMS feed under the form settings. Select a phone field or up to five fixed administrator mobiles, compose a message with Gravity Forms merge tags and optionally set conditional logic. Iranian mobile numbers and Persian/Arabic digits are supported. Sending is disabled after installation; enable the connection before use. PHP 7.4+ and WordPress 6.5+ are required.

Ava's current POST /api/v1/sms creates a request awaiting content review. HTTP 202 means accepted for review; an owner must approve and explicitly start the campaign in Ava. Approval alone does not start sending. An accepted request does not prove delivery. Atomic per-entry/feed guards prevent duplicate processing. Uncertain sends are never automatically retried. This plugin provides text notifications, not OTP generation, validation or mobile verification.

= External service: Ava SMS =

On feed processing, the plugin sends the recipient phone number, rendered message text and optional sender to the configured HTTPS Ava panel, default https://ava.tarpardaz.com, via POST /api/v1/sms. The API key is a Bearer token and each request has an idempotency key. GET /api/v1/sms/{id} is the status endpoint. Form values included in merge tags are transferred in the message. Review the service terms and privacy information in your Ava panel.

WordPress stores connection settings and fixed recipients. Form entries and delivery summaries are stored in the WordPress database. Non-autoloaded ava_gf_delivery_ options retain duplicate guards and masked status summaries until the entry is deleted or the add-on is uninstalled; they do not store message text, full phone numbers or API keys. Administrators can refresh an entry's report with a GET request; this never resends the message. Phone masking in summaries does not remove original form data. Database backups can contain the saved API key and form data. Restrict access and configure form-entry retention appropriately. You may define AVA_GF_API_KEY in wp-config.php instead of storing the key through settings. Saved keys are not rendered back into the settings form.

Service: https://ava.tarpardaz.com

== Installation ==

1. Install and activate Gravity Forms 2.8+ separately.
2. Upload and activate ava-GravityForms-plugin-1.0.0.zip from GitHub Releases.
3. Create an Ava API key with sms:send and sms:read.
4. Configure Settings > Ava Gravity Forms and enable the connection.
5. Create an Ava SMS feed in your form settings, map a phone field or fixed recipients and set the message and optional conditions.
6. Validate your installation in staging. Real sends can incur charges after review and explicit start in Ava.

== Frequently Asked Questions ==

= Does the package include Gravity Forms? =
No. Install Gravity Forms separately. Tests use simulated Gravity Forms contracts; validate your commercial version in staging.

= Why did HTTP 202 not send my message? =
The current Ava endpoint accepts the message for review. The panel owner must approve it and explicitly start sending.

= Can I notify the user and administrator? =
Yes. Create separate feeds for the user phone field and the fixed administrator recipients.

= Will reprocessing a feed repeat the SMS? =
No. The same entry/feed is guarded before network processing. Unknown outcomes are not automatically retried; inspect the prior result and Ava report.

= Does this verify a user's phone number? =
No. This plugin sends text notifications and does not implement OTP verification.

== Changelog ==

= 1.0.0 =
* Initial Ava SMS feeds with Persian settings, Iranian phone normalization, merge tags, conditional logic, moderation metadata and duplicate guards.
