# Security

Security fixes are provided for the latest published version. Keep WordPress, Gravity Forms and this plugin updated.

Use the repository's private **Security → Report a vulnerability** option when available. Otherwise ask the maintainer for a private reporting channel without publishing exploit details. Never attach API keys, complete phone numbers, customer entries or unredacted database exports to an issue, pull request or screenshot.

If an API key is exposed, revoke it in Ava and replace it with a key restricted to `sms:send` and `sms:read`. Restrict access to WordPress configuration, the database and backups, which may contain connection keys and form data. Consider defining `AVA_GF_API_KEY` in `wp-config.php` with file permissions appropriate for the host.

The add-on sends form notifications through an external service; it does not validate phone ownership or implement OTP. A received HTTP 202 response means accepted for Ava moderation, not sent or delivered. Preserve duplicate guards and investigate uncertain requests before any intentional new send.
