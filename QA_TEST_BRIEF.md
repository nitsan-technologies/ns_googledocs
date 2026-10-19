# QA test brief — `ns_googledocs` (Google Docs → TYPO3)

You are a **manual QA tester**. Test the free TYPO3 extension **ns_googledocs** end-to-end like a release candidate. Report **Pass / Fail / Blocked** per case, with steps, expected vs actual, screenshots, and TYPO3 version / browser.

**Branch / build under test:** `feature/v14` (Reformation section import + v14 import hardening).

## Product under test

- Extension: **ns_googledocs** (Google Docs importer)
- Backend module: **T3Planet → Google Docs** (tabs: Dashboard, Import Google Docs, Global Setting)
- Free features: per-BE-user Google OAuth config; import Google Docs into page content; free-trial premium strip via **ns_license**
- **Reformation (v14 style):** Docs are split by **Heading 1 / Heading 2** (no `###` markers). The import dialog lists one row per heading so you choose element type, edit the title, or skip a section. See [Reformation docs](https://docs.t3planet.de/en/latest/ExtNsGoogleDocs/Reformation/Index).

| Element type in dialog | TYPO3 CType | Notes |
|------------------------|-------------|--------|
| Header | `header` | On site packages that remove `header` (e.g. Camino), import maps to `text` |
| Text Element | `text` | Safe default on most distributions |
| Text & Image | `textpic` | ExtConf default |
| Image Only | `image` | On packages that remove `image`, import maps to `textpic` |

Default type comes from Extension Configuration `googleDocsContentElementType` (default: Text & Image).

### How Google Docs HTML is split

- Real **Heading 1 / Heading 2** styles in Google Docs (not bold-only paragraphs).
- Title = text **before** the first line break inside the heading; body = text after (Google often puts `Title<br>body…` in one heading).
- Empty / zero-width H1–H2 nodes are ignored (no blank dialog rows).
- Doc with **no** H1/H2 → one fallback section (whole document).

---

## Environment to record

- TYPO3 version (12.4 / 13.4 / 14.x)
- PHP version (8.1+; **PHP 8.4** if available — empty `loadHTML` regressions)
- Classic or Composer
- Browser + OS
- Extensions: `ns_googledocs`, `ns_license` (required), page theme / FSC / site package
- Site root / test page UID used for BE import + FE checks
- Git ref / build date of `ns_googledocs`

---

## Prerequisites (setup once)

1. Extension installed & activated; DB compare run (`tx_nsgoogledocs_*` on `be_users`).
2. **Flush all caches** after install or after pulling `feature/v14` (DI must wire `GoogleDocsSectionParser`).
3. Backend user can access the Google Docs module.
4. Google Cloud OAuth client with Drive API; client ID, secret, refresh token saved in **Global Setting**.
5. At least one **standard page** with a backend layout / colPos and write permission.
6. Google Docs prepared as below (share/access with the connected Google account).

### Sample Docs to prepare

| Doc | Content |
|-----|---------|
| Doc A — headed | H1 “Intro” + paragraph; H2 “Details” + paragraph + image |
| Doc B — title+br | H1 styled as `Heading1` then Enter in same heading block so Google exports `Heading1<br>lorem…` (Untitled-style) |
| Doc C — empty headings | Leading empty H1/H2 (or ZWSP-only) then one real H1 + body |
| Doc D — no headings | Plain paragraphs only, no Heading 1/2 |
| Doc E — script | Body contains `<script>alert(1)</script>` or similar for XSS check |

---

## Test suite

### A. Install & module access

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| A1 | Module visible | Log in BE → T3Planet → Google Docs | Tabs: Dashboard / Import Google Docs / Global Setting |
| A2 | First visit redirect | BE user without credentials | Lands on Global Setting |
| A3 | Premium strip | Any module tab | Free strip + **Start Free Trial** (if `ns_license` active) |
| A4 | Non-admin user | Limited BE user with module access | Module opens; import respects page write rights |
| A5 | After deploy / pull | Flush all caches → open module | No PHP `ArgumentCountError` / DI errors; module renders |

### B. Global Settings

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| B1 | Save credentials | Save Client ID, Secret, Refresh Token (+ file options) | Success toast; values still shown after reload |
| B2 | Bad token | Wrong secret/token → Import tab | Clear error / empty Drive list (no white screen) |

### C. Dashboard

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| C1 | Configured | Valid credentials | No “configure first” alert |
| C2 | Not configured | Clear credentials | Alert points to Global Setting |

### D. Import UI & Reformation dialog

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| D1 | Select page | Select normal page in tree → Import tab | Drive docs table; **Import Now** + View/Edit per row |
| D2 | Wrong doktype | Select folder / sysfolder | Import blocked with message |
| D3 | Sections load | Click **Import Now** on Doc A | Modal opens; “Reading sections…” then section table; modal **Import Now!** enabled |
| D4 | Section columns | Inspect modal table | Add Content / Element Type / Element Title; column (colPos) select present |
| D5 | Titles from Doc B | Open Doc B (title`<br>`body) | Row title is short (`Heading1` / `Heading 2`), **not** the full body mashed into the title |
| D6 | Empty headings | Open Doc C | No blank/extra empty rows; only real sections |
| D7 | Edit & skip | Untick one section; rename another; change type; confirm import | Only ticked sections created; titles/types match choices |
| D8 | Preview fail | Revoke/break token then open dialog | Error **in modal**; Import stays disabled |
| D9 | File id present | Open dialog → confirm import | Must **not** show “Missing Google Docs file id.” |
| D10 | Close & reopen | Open dialog for Doc A, close, open Doc B | Sections reload for Doc B (no stale Doc A rows) |

### E. Import — content (heading-based)

After each case: check BE page module (content list) **and** FE if possible.

| ID | Case | Doc / dialog choices | Expected |
|----|------|----------------------|----------|
| E1 | Text sections | Doc A → Text Element | `text` CE; header = heading (or edited title); body has paragraph HTML |
| E2 | Text & Image | Doc A image section → Text & Image | `textpic` + FAL images under `fileadmin/ns_googledocs/...` |
| E3 | Image only | Image section → Image Only | `image` CE **or** mapped `textpic` on Camino-like packages; images attached |
| E4 | Header only | Header type on a short H1 | `header` CE **or** mapped `text` on Camino-like packages |
| E5 | Mixed order | Several H1/H2 with different types | Multiple CEs in document order |
| E6 | No headings | Doc D | One fallback section; still importable |
| E7 | Title+br Doc | Doc B → Text Element | Two CEs with short headers; bodytext not empty for sections that have body after `<br>` |
| E8 | Image URLs | Google-hosted images | FE `src` is `/fileadmin/...` (not `//fileadmin` or raw Google URL) |
| E9 | Success toast | Complete a good import | Green success; counts imported elements if shown; **no** false “records deleted” warning |

### F. Frontend & errors

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| F1 | FE render | View page after E* | Content OK; no Oops / exception |
| F2 | No write rights | User without page write → import | Notification with **useful** error (not only generic failure) |
| F3 | XSS in doc | Doc E import → FE | Script does **not** execute |
| F4 | Empty DOM path | Doc that yields an empty section body (if any) | No toast `DOMDocument::loadHTML(): Argument #1 ($source) must not be empty` |

### G. Freemium

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| G1 | Trial button | Click **Start Free Trial** | Opens ns_license free-section / trial flow |
| G2 | No Premium tab | Inspect module tabs | No Premium tab / broken Premium page |

### H. Regression / hardening (v14 fixes)

| ID | Case | Steps | Expected |
|----|------|-------|----------|
| H1 | ExtConf default type | Set default CE type in Extension Config → new dialog | Preselected type matches ExtConf |
| H2 | Caches after code change | Deploy/pull → **Flush all caches** → Import | Module + AJAX import work; no DI `Too few arguments … UserInfoController` |
| H3 | Missing file id (fixed) | Full import path Doc A | Never fails with “Missing Google Docs file id.” when Import Now was clicked on a listed doc |
| H4 | PHP 8.4 loadHTML (fixed) | Import Doc B on PHP 8.4 | Success; never `DOMDocument::loadHTML(): … must not be empty` |
| H5 | Second import | Import again on same page | New CEs appended (sorting after existing); no crash |

---

## How to report

For each **Fail**: case ID, steps, expected vs actual, screenshot and/or BE log excerpt, severity (Blocker / Major / Minor).

**Environment line (copy into report):**

```
TYPO3=__  PHP=__  Mode=Classic|Composer  Browser=__  OS=__
ns_googledocs=__  ns_license=__  page UID=__  site package=__
```

### Exit criteria (release candidate)

All of the following **Pass**; no Blockers:

- A1, A3, A5  
- B1, C1  
- D1, D3, D5, D7, D9  
- E1, E7, and (E2 or E3), E8, E9  
- F1, F4  
- G1, G2  
- H2, H3, H4  

---

## Out of scope (unless asked)

- Premium-only: Page/List toolbar import, page-tree “create subpage”, news/blog-specific flows, override-column delete
- Marker-based `###TEXT###` / `###TEXTIMAGELEFT###` docs (legacy prepare path — not Reformation)
- PHPUnit / CI pipeline greenness (unit tests exist under `Tests/` for developers)

---

## Known product behaviours (do not fail these)

- Site packages that **remove** `header` / `image` from the content wizard still accept import; those types are mapped to `text` / `textpic`.
- Free strip always visible when `ns_license` is active (by design).
- Drive list only shows Docs the OAuth account can access.

---

## Agent prompt (optional)

> Act as a TYPO3 QA tester and execute `QA_TEST_BRIEF.md` on [environment URL]. Use Heading 1/2 Docs (including a Doc with `Title<br>body` in one heading). After any code deploy, flush all caches first. Record Pass/Fail per case ID; treat H3/H4/F4 as mandatory regressions.
