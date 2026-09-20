# Contributing

Anyone can open **issues** and submit **pull requests**. Direct commits and
merges to `dev`, `staging`, and `main` are limited to OpenMSP maintainers.

## Branch workflow

```
feature/*  →  PR  →  dev  →  staging  →  main
                 (community)  (maintainers)
```

1. **Feature work** starts from `dev` (not `main` or `staging`):

   ```bash
   git fetch origin
   git checkout dev
   git pull origin dev
   git checkout -b feature/your-short-name
   ```

2. Push the feature branch and open a **pull request into `dev`**:

   - base: `dev`
   - head: `feature/...`

   Community pull requests that target `staging` or `main` are rejected.

3. After review and CI, **maintainers** merge the PR into `dev`.

4. **Maintainers** promote with pull requests:

   - `dev` → `staging` (pre-release / staging deploy)
   - `staging` → `main` (production)

Do not push directly to `dev`, `staging`, or `main`. Prefer small, focused PRs
with tests for API and domain changes.

Keep the product **vendor-neutral** (no customer/company names in UI, MCP, or
docs). Platform name, mail, Stripe and API tokens are configured from the admin
UI.

## Languages

The UI is **English-native**. English is also the fallback locale. Operator-facing
docs, comments, and commit messages are English.

- UI strings use English as the translation key (`__('Upcoming renewals')`).
- Translations live in `lang/{code}.json`.
- Supported codes are listed in `app/Support/LocaleCatalog.php`.
- To add a language: register the code there, add `lang/{code}.json`, and keep
  keys identical to the English source.

Do not hard-code Dutch (or any other language) in Blade, Filament labels, mail,
or Markdown.

## Agent / API changes

See [`AGENT.md`](AGENT.md) for authentication, base path, and resource conventions. Keep [`openapi/agent-api.yaml`](openapi/agent-api.yaml) in sync when changing `/api/v1` routes or payloads.
