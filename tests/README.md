# Validation

The standalone harness uses synthetic data, simulated WordPress and Gravity Forms interfaces, and intercepted HTTP. It never contacts Ava or sends SMS:

```sh
php tests/run.php
python3 -m unittest discover -s tests -p 'test_*.py' -v
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

It checks connection validation and secret handling, Iranian mobile normalization, exact API JSON and transport settings, moderation metadata, report statuses, rejection/ambiguous results, merge tags, all-or-nothing recipient validation, recipient limits, durable deduplication, concurrent callbacks, and uncertain or interrupted requests. The report refresh checks permission and nonce enforcement, feed and connection scoping, and GET-only status updates.

`integration-smoke.php` additionally runs against **real disposable WordPress**. Gravity Forms interfaces and merge-tag values are simulated because the commercial Gravity Forms package is not included. This validates WordPress activation, settings storage and sanitization, permission checks, real atomic database claims, safe HTTP interception, and the add-on callback contract. It does **not** certify the native Gravity Forms UI, conditional-rule evaluation, payment add-ons, or delivery to a real handset.

Use a disposable installation with no customer data, real credentials, or unrelated active plugins. The script requires:

- `WP_ENVIRONMENT_TYPE` set to `local`.
- `WP_HOME` and `WP_SITEURL` hosts ending in `.invalid`.
- `DISABLE_WP_CRON` set to `true`.
- No enabled or configured Ava Gravity Forms installation and no `AVA_GF_API_KEY` constant.
- No loaded commercial Gravity Forms package; the test provides its own contract fixture.
- The plugin installed at `wp-content/plugins/ava-GravityForms-plugin/`.

```sh
AVA_GF_RUN_INTEGRATION=1 wp --path=/absolute/disposable-wordpress eval-file /absolute/plugin-source/tests/integration-smoke.php
```

The smoke preempts **all HTTP and mail** before invoking the integration. It restores the prior blank plugin state, removes created synthetic users and delivery guards, and leaves unrelated WordPress settings intact.

Local release validation on 2026-11-05 passed **221 standalone behavioral checks** and **42 real WordPress checks** (four intercepted HTTP calls), using PHP 8.5.4, WordPress 7.1.4 and SQLite Database Integration 3.1.3. Concurrent insert-only claims, stale-cache collisions, compare-and-swap conflicts, and a report arriving during another recipient request are covered. The WordPress smoke also verifies actual capability and nonce enforcement, the external sidebar form, and GET-only report persistence. GitHub Actions separately exercises PHP 7.4, 8.2, 8.4 and 8.5. For acceptance with a licensed Gravity Forms 2.8+ installation, verify the actual feed settings page, one customer feed and one fixed-admin feed, native merge tags and conditional rules, and matching pending-review entries in the Ava panel under an explicitly authorized real-send test.
