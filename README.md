# Google Docs

[![GoogleDocs](https://img.shields.io/badge/stable-v14.0.0-green?style=flat-square)](https://github.com/nitsan-technologies/ns_googledocs) [![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg?style=flat-square)](https://get.typo3.org/version/12) [![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg?style=flat-square)](https://get.typo3.org/version/13) [![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg?style=flat-square)](https://get.typo3.org/version/14) [![License](https://img.shields.io/badge/license-GPL--2.0--or--later-orange?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html) [![T3Planet](https://img.shields.io/badge/T3Planet-GoogleDocs-50b99a?style=flat-square)](https://t3planet.com/typo3-googledocs-extension)

Import Google Docs into TYPO3 pages with one click.

- Free version: https://extensions.typo3.org/extension/ns_googledocs
- Premium version: https://t3planet.com/typo3-googledocs-extension
- Documentation: https://docs.t3planet.com/en/latest/ExtNsGoogleDocs/Index.html

## Requirements

- TYPO3 **12.4 / 13.4 / 14.x**
- PHP **^8.1**
- Google API client via Composer (`google/apiclient`)

TYPO3 8–11 are no longer supported. Use an older release branch if you need those versions.

## Installation

### Composer

```bash
composer require nitsan/ns-googledocs
```

Composer only runs `scripts`, `config` and `extra.google/apiclient-services` on the **project root**.
To keep only the Drive service (optional; import still works with the full client):

```json
{
    "scripts": {
        "pre-autoload-dump": "Google\\Task\\Composer::cleanup"
    },
    "extra": {
        "google/apiclient-services": ["Drive"]
    }
}
```

Then run `composer dump-autoload` in the TYPO3 project.

### Classic (non-Composer) TYPO3

This extension ships a local `vendor/` with `google/apiclient` (Drive service only).
To regenerate it:

```bash
COMPOSER=composer.libraries.json composer install --no-dev
```

## Features (free)

- Backend module: Dashboard, Import, Global Settings
- Heading-based Google Docs → TYPO3 page content (`text` / `textpic`; `header` / `image` when the page allows them)
- Per-backend-user Google OAuth client configuration (`be_users` fields)

## Upgrade note

Docs prepared with the old TEXT / TEXTIMAGE / IMAGES marker syntax are no longer split by those markers. Use Heading 1 / Heading 2 to define import sections.

Existing databases may still have unused columns on `tx_nsgoogledocs_domain_model_apidata` (old setup-wizard HTML and a misspelled premium HTML column). The Install Tool Database Analyzer can drop unused columns; do not drop that table itself (marketing HTML cache).
