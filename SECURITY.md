# Security

This is self-hosted software. You are responsible for the instance you run
(HTTPS, `APP_KEY`, backups, SMTP, Stripe live keys).

## Reporting a vulnerability

Please **do not** open a public issue for security bugs.

Use [GitHub Security Advisories](https://github.com/tavbuilds/tav-it-MSP/security/advisories/new)
on this repository. We will coordinate a fix before any disclosure.

## What is in scope

- Auth bypass (admin panel, portal magic links, Agent API)
- Ability to register a second administrator after first setup
- Secret leakage (license keys, webhook tokens, SMTP/Stripe secrets, `APP_KEY` ciphertext)
- CSRF / webhook signature issues (`/stripe/webhook`, `/hooks/endpoints/{token}`)
- Bypass of the demo account’s view-only mode
- Bypass of sign-in lockout

## What is out of scope

- Self-XSS in your own admin session
- Missing rate limits on your reverse proxy
- Reports against a misconfigured deploy (`APP_DEBUG=true`, default `DB_PASSWORD`)
