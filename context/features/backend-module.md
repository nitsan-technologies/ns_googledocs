# Feature — Backend module

*Status: Done.*

## Entry

T3Planet (`nitsan_module`) → Google Docs (`nitsan_nsgoogledocs`). Page tree is shown so editors can pick the
target page before Import.

## Screens

| Action | Template | Purpose |
|---|---|---|
| `dashboard` | `UserInfo/Dashboard.html` | Status, setup wizard CTA, support blocks |
| `import` | `UserInfo/Import.html` | Lists Drive docs, column (`colPos`) select, Import Now |
| `globalSettings` | `UserInfo/GlobalSettings.html` | Google OAuth client id / secret / refresh token, import types |
| `docsImport` | JSON only | Performs the import (see `features/import.md`) |
| `update` | JSON only | Saves Global Settings onto `be_users` |

Navbar + premium strip live in `Layouts/Default.html`. Flash/marketing HTML comes from the cached
`tx_nsgoogledocs_domain_model_apidata` rows filled via `composer.t3terminal.com` API blocks.

## Access

Module `access: user`. Google credentials are stored per backend user. Import requires a selected page and a
doktype that is allowed by the user's `import_type` setting (default: TYPO3 pages).
