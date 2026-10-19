# QA test brief — `ns_googledocs` (Google Docs → TYPO3)

You are a **manual QA tester**. Test the free TYPO3 extension **ns_googledocs** end-to-end like a release candidate. Report **Pass / Fail / Blocked** per case, with steps, expected vs actual, screenshots, and TYPO3 version / browser.

## Product under test

- Extension: **ns_googledocs** (Google Docs importer)
- Backend module: **T3Planet → Google Docs** (tabs: Dashboard, Import Google Docs, Global Setting)
- Free features: per-BE-user Google OAuth config; import Google Docs into page content; free-trial premium strip via **ns_license**
- **Reformation (v14 style):** Docs are split by **Heading 1 / Heading 2** (no `###` markers). The import dialog lists one row per heading so you choose element type, edit the title, or skip a section. See [Reformation docs](https://docs.t3planet.de/en/latest/ExtNsGoogleDocs/Reformation/Index).

| Element type in dialog | TYPO3 CType |
|------------------------|-------------|
| Header | `header` |
| Text Element | `text` |
| Text & Image | `textpic` |
| Image Only | `image` |

Default type comes from Extension Configuration `googleDocsContentElementType` (default: Text & Image).

## Environment to record

- TYPO3 version (12.4 / 13.4 / 14.x)
- PHP version
- Classic or Composer
- Browser + OS
- Extensions: `ns_googledocs`, `ns_license` (required), page theme / FSC
- Site root page UID used for FE checks

## Prerequisites (setup once)

1. Extension installed & activated; DB compare run (columns `tx_nsgoogledocs_*` on `be_users`).
2. Caches cleared after install.
3. Backend user can access the Google Docs module.
4. Google Cloud OAuth client with Drive API; client ID, secret, refresh token saved in Global Settings.
5. At least one **standard page** with a backend layout / colPos.
6. Google Docs prepared with **Heading 1 / Heading 2** sections (not `###` markers), including docs with and without images.

---

## Test suite

### A. Install & module access

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| A1 | Module visible | Log in BE → Google Docs | Tabs Dashboard / Import / Global Setting |
| A2 | First visit redirect | BE user without credentials | Redirects to Global Setting |
| A3 | Premium strip | Any module tab | Free strip + Start Free Trial (if ns_license) |
| A4 | Non-admin user | Limited BE user | Module opens; import respects page write rights |

### B. Global Settings

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| B1 | Save credentials | Save Client ID, Secret, Refresh Token + TYPO3 Pages | Success; values still shown after reload |
| B2 | Bad token | Wrong secret/token → Import | Error / empty Drive list |

### C. Dashboard

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| C1 | Configured | Valid credentials | No configure-first alert |
| C2 | Not configured | Clear credentials | Alert points to Global Settings |

### D. Import UI & Reformation dialog

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| D1 | Select page | Normal page → Import tab | Docs list; Import Now per row |
| D2 | Wrong doktype | Folder selected | Import blocked |
| D3 | Sections load | Click Import Now on a headed Doc | Modal opens; “Reading sections…” then table of sections; Import enabled |
| D4 | Section columns | Inspect table | Add Content / Element Type / Element Title + column select |
| D5 | Edit & skip | Untick one section; rename another; change type; Import | Only ticked sections imported; titles/types match choices |
| D6 | Preview fail | Revoke token then open dialog | Error in modal; Import stays disabled |

### E. Import — content (heading-based)

Prepare Docs with H1/H2 (no markers). After import check BE + FE.

| ID | Case | Doc / dialog choices | Expected |
|----|------|----------------------|----------|
| E1 | Text sections | H1 + paragraphs → Text Element | `text` CE; title = heading (or edited title) |
| E2 | Text & Image | H1 + text + images → Text & Image | `textpic` + FAL images under `fileadmin/ns_googledocs/...` |
| E3 | Image only | H1 + images → Image Only | `image` CE; images attached; body empty |
| E4 | Header only | H1 → Header | `header` CE; no body |
| E5 | Mixed | Several H1/H2 with different types | Multiple CEs in order |
| E6 | No headings | Doc without H1/H2 | One fallback section (whole body); still importable |
| E7 | Image URLs | Google-hosted images | FE src `/fileadmin/...` (not `//fileadmin`) |

### F. Frontend & errors

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| F1 | FE render | View page after E* | Content OK; no Oops |
| F2 | DataHandler error | User without write rights | Notification shows useful error |
| F3 | XSS in doc | Script in Doc body | FE does not execute script |

### G. Freemium

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| G1 | Trial button | Start Free Trial | ns_license free-section |
| G2 | No Premium tab | Module tabs | No broken Premium page |

### H. Regression

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| H1 | ExtConf default type | Change default CE type in Extension Configuration | New dialogs preselect that type |
| H2 | Caches | Clear caches; re-open | No Fluid/DI errors |

---

## How to report

For each **Fail**: case ID, steps, expected vs actual, screenshot/log, severity.

**Exit criteria:** A1–A3, B1, C1, D1, D3–D5, E1, E2 or E3, E7, F1, G1 all **Pass**; no Blockers.

## Out of scope (unless asked)

- Premium-only: Page/List toolbar import, page-tree “create subpage”, news/blog-specific flows, override-column delete
- Marker-based `###TEXT###` docs (legacy 2.x — no longer the supported prepare path)
- PHPUnit CI

## Agent prompt (optional)

> Act as a TYPO3 QA tester and execute `QA_TEST_BRIEF.md` on [environment URL]. Use Heading 1/2 Docs only. Record Pass/Fail per case ID.
