# Contributing

Open an issue describing observed and expected behavior, or submit a focused pull request. Never include API keys, customer records, full phone numbers or private Ava backend code.

Target PHP 7.4+, WordPress 6.5+ and Gravity Forms 2.8+. Preserve native feed configuration, merge tags and conditional logic. Connection setting writes require `manage_options` and a nonce. Validate input, escape output and keep credentials, full recipients, message text and raw upstream responses out of result logs.

Ava's current HTTP 202 response means accepted for moderation. Preserve review metadata; approval and explicit start in Ava are required. Keep the atomic per-entry/feed guard before network calls. Never automatically retry an uncertain send.

Run:

```sh
php tests/run.php
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 scripts/package.py
```

Use synthetic fixtures and mocked HTTP; automated checks must never send real SMS. Run WordPress integration checks only in a disposable local installation without production data or real Ava credentials. Simulated Gravity Forms contracts do not replace staging checks with the intended licensed Gravity Forms version.

Keep the main header, version constant, readme stable tag, release tag and changelog aligned. Distribution builds must contain only public runtime code and approved documentation.
