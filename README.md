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

### Classic (non-Composer) TYPO3

This extension ships a local `vendor/` with `google/apiclient` (Drive service only).
To regenerate it:

```bash
COMPOSER=composer.libraries.json composer install --no-dev
```

## Features (free)

- Backend module: Dashboard, Import, Global Settings, Premium overview
- Google Docs → TYPO3 page content elements (`text` / `textpic` / `image`)
- Per-backend-user Google OAuth client configuration (`be_users` fields)
