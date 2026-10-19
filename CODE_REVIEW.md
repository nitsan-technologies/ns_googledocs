# TYPO3 Code Review — `ns_googledocs` (re-review)

**TYPO3 target:** v12.4 / v13.4 / v14 (`composer.json` → `typo3/cms-core: ^12.4 || ^13.4 || ^14.0`)  
**PHP target:** `^8.1 || ^8.2` (`composer.json` → `require.php`)  
**Mode:** `extension` — whole `typo3conf/ext/ns_googledocs` (excluding `vendor/`)  
**Automated pass:** `php -l` clean; `composer validate --no-check-publish` OK; no typo3-rector / PHPStan / PHP-CS-Fixer in the extension.

| Severity | Count |
|----------|-------|
| Blocker  | 0 |
| Major    | 0 |
| Minor    | 0 |
| Nitpick  | 2 |

**Verdict: Approve** — Blockers, Majors and Minors are fixed. Nitpicks optional.

---

## Cleared since last review

| ID | Topic | Status |
|----|--------|--------|
| B1 | DataHandler for `tt_content` / `sys_file_reference` | Fixed |
| B2 | Remote HTML + `f:format.raw` | Fixed (sanitized via `RemoteHtmlSanitizer`) |
| M1 | `lastInsertId('tt_content')` | Fixed (DataHandler path) |
| M2 | Unprefixed `be_users` columns | Fixed (`tx_nsgoogledocs_*` + upgrade wizard) |
| M3 | HTTP on every `initializeAction` | Fixed (lazy `ensureApiContentLoaded`) |
| M4 | `@copy` image SSRF | Fixed (`RemoteContentFetcher` + HTTPS guard) |
| M5 | Empty `ext_tables.php` | Fixed (removed) |
| M6 | `extra.typo3/cms.version` | Fixed |
| M7 | TCA `exclude` on secrets | Fixed |
| m1–m5 | strict_types, session, DI, PHP constraint | Fixed |

---

## Major

### M1 — Tests namespace not registered in `autoload-dev` — **fixed**
`composer.json` now has `autoload-dev` + `require-dev` (`phpunit`, `typo3/testing-framework`).

### M2 — No Functional test for DataHandler import — **fixed**
`Tests/Functional/Domain/Repository/UserInfoRepositoryTest.php` asserts `insertPageElement()` creates a `tt_content` row via DataHandler.

---

## Minor

### m1 — OAuth secrets in form HTML — **reverted (product decision)**
Values are shown again in Global Settings / wizard forms (same as before the review
hardening), so editors can see and edit stored credentials.

### m2 — `premiumAction` / Premium screen — **fixed (removed)**
Free extension: deleted unreachable `premiumAction`, `Premium.html`, and the remote
`PremiumExtension` fetch. Kept `PremiumStrip` (trial CTA via `ns_license`) only.

### m3 — DataHandler `errorLog` silent — **fixed**
Errors are logged via `LogManager` and returned in the import JSON `error` field
(shown in the backend Notification).

---

## Nitpick

### N1 — `curlInitCall()` name is outdated
`Classes/Domain/Repository/UserInfoRepository.php:239`

Method now uses `RequestFactory` via `RemoteContentFetcher`. Rename to `fetchRemoteText()` (or call the fetcher from the controller and drop the wrapper).

### N2 — `UserInfoRepository` remains an Extbase `Repository` without Extbase persistence
Optional rename to a `*Service` / `*Writer` still applies; docblock already documents the role.

---

## Needs manual verification

- Install Tool: DB compare for `tx_nsgoogledocs_*` + upgrade wizard (or re-save Global Settings).
- AJAX `docsImport` / `update` request-token behaviour on v12–v14.
- FE rendering of imported `bodytext` with script tags (HTML sanitizer).
- Non-admin BE user import (DataHandler page write permissions).
- Confirm DI resolves `RemoteHtmlSanitizer` → `SanitizerBuilderFactory` after cache flush (Core registers it under `TYPO3\CMS\Core\`).

---

## Not applicable

TypoScript / site sets / FE plugins — unchanged. `vendor/` not reviewed.

---

## Notes

- `f:format.raw` on `{googleDocsSetupWizard}` / `{premiumExtensionData}` is acceptable **after** `RemoteHtmlSanitizer`.
- `f:format.raw` on own XLF labels that contain intentional HTML (`import_about`, steps) is trusted-extension content, not a Blocker.
