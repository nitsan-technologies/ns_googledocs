# ns_googledocs — Core facts

## Identity

| Item | Value |
|---|---|
| Extension key | `ns_googledocs` |
| Composer name | `nitsan/ns-googledocs` |
| Title (ext_emconf) / product name | Google Docs to TYPO3 Importer / Google Docs |
| Namespace | `NITSAN\NsGoogledocs\` → `Classes/` |
| Version (ext_emconf) | 14.0.0 |
| Repository | https://github.com/nitsan-technologies/ns_googledocs |
| Licensing status (NITSAN Free/Freemium sheet) | **Freemium** — this repo is the free version |
| Premium version | https://t3planet.com/typo3-googledocs-extension |

## Compatibility

| Source | TYPO3 | PHP |
|---|---|---|
| `ext_emconf.php` | `12.4.0-14.99.99` | `8.1.0-8.99.99` |
| `composer.json` | `^12.4 \|\| ^13.4 \|\| ^14.0` | `^8.1` |
| ns_license 14.5.4 (dependency) | `12.4.18-14.9.99` | — |

- Effective minimum is **TYPO3 12.4.18** because of ns_license; the extension's own constraint still says 12.4.0.
- Code uses constructor property types and `declare(strict_types=1)` → PHP 8.1+ (TYPO3 12 minimum anyway).

## Dependencies

| Package | Constraint | Why |
|---|---|---|
| `typo3/cms-*` (backend, core, extbase, fluid, frontend) | `^12.4 \|\| ^13.4 \|\| ^14.0` | core |
| `nitsan/ns-license` / `ns_license` | `^14.5.4` / `14.5.4-14.9.99` | TYPO3 Shop module + free-trial deep link (see `features/premium-cta.md`) |
| `google/apiclient` | `^2.15` | Google Drive export (HTML); Classic installs use extension-local `vendor/` via `composer.libraries.json` |
| PHP ext | `dom`, `intl`, `libxml`, `simplexml` | HTML parsing / transliteration |

## Tooling

- No PHPUnit, PHPStan, CS fixer or package.json in the repo.
- CI: `.github/workflows/ter-release.yml` only (publishes tags to TER via tailor, uploading the GitHub tag archive).
- `.gitattributes` export-ignores `AGENTS.md`, `CLAUDE.md`, `context/`, `tasks/`, `.github/`, `.crowdin.yml` and itself, so they are not in the GitHub/Composer archive or the TER package. Extension `vendor/` (google/apiclient for Classic) must stay exported.
- Translations: Crowdin when configured; source files `Resources/Private/Language/*.xlf`.

## Conventions

- Extbase backend module with `ModuleTemplateFactory` and `renderResponse()`.
- Labels from `locallang.xlf` / `locallang_googledocs.xlf` via `<f:translate>` or `LanguageService::sL()`.
- Backend CSS: `Resources/Public/Css/main.css` plus `backend-premium-strip.css` for the trial strip; markup uses TYPO3 backend Bootstrap 5.
- Google client is loaded from project Composer when available, otherwise `EXT:ns_googledocs/vendor/autoload.php`.

## Known issues (existing, not fixed)

- `lastInsertId('tt_content')` still passes a table name; harmless on MySQL (arg ignored on v13+), wrong for PostgreSQL sequences ([Breaking #102875](https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.0/Breaking-102875-ChangedConnectionMethodSignaturesAndBehaviour.html)). Prefer `$connection->lastInsertId()`.
- Direct `tt_content` / `sys_file_reference` inserts omit `tstamp` / `crdate`.
- Remote marketing HTML (`composer.t3planet.cloud` API blocks) is cached in `tx_nsgoogledocs_domain_model_apidata` for two weeks; empty/failed fetches are not stored.
- `Premium.html` template remains but the Premium tab/action is not registered in `Modules.php` (free product surface).
