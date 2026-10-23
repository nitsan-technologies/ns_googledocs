# Run quality checks

The repo has no PHPUnit, PHPStan or CS config. Available checks:

```bash
# PHP syntax (skip extension vendor/)
for f in $(find . -name "*.php" -not -path "./.git/*" -not -path "./vendor/*"); do php -l "$f"; done

# composer.json
composer validate --no-check-publish

# XLF well-formedness
for f in Resources/Private/Language/*.xlf; do xmllint --noout "$f"; done
```

## Manual test (per TYPO3 major 12 / 13 / 14)

Setup: ns_license ≥ 14.5.4 installed and active. Backend user is system maintainer for the trial modal.

1. Backend: T3Planet → Google Docs. Premium strip appears below the navbar on Dashboard / Import / Global Settings.
2. "Start Free Trial" opens the ns_license module and its trial modal for `ns_googledocs` (system maintainer).
3. Global Settings: save Google OAuth client id / secret / refresh token. Dashboard shows connected state.
4. Select a normal page in the tree → Import: Drive docs list loads. Import Now creates `tt_content` on that
   page; images land under `fileadmin/ns_googledocs/` with `src="/fileadmin/..."`.
5. Frontend: imported content renders on the page (theme must provide `text` / `textpic` / `image` CTypes).
6. Check light and dark backend themes for the strip.

Remove test content elements and imported files afterwards.
