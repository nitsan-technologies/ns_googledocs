# Session state

## 2026-10-23 — Drop bundled jQuery / DataTables 1.10.21

- Module JS/CSS load through PageRenderer (CSP nonce, Core jQuery ES module).
- DataTables init removed (import list is already ≤ 10 rows). Bundled Jquery.js / Datatables.js deleted.

## 2026-10-23 — First-visit redirect to Global Settings

- `initializeAction()` no longer discards `$this->redirect()` (no-op since TYPO3 v12).
- `dashboardAction` / `importAction` return the redirect. AJAX actions are unchanged.

## 2026-10-23 — Marketing HTML cache actually expires

- `deleteOldApiData()` runs before the cache lookup; rows older than 2 weeks and empty rows are dropped.
- `checkApiData()` requires `extension_key = ns_googledocs`, a fresh `last_update`, and non-empty HTML.
- Failed fetches are not inserted. Marketing HTTP timeout is 2s (image downloads stay 10s).

## 2026-10-23 — Do not store parsed Doc HTML in be_sessions

- Preview no longer writes section HTML (incl. base64 images) into `BE_USER` session data.
- Import always re-exports the Doc from Drive (same path as the old cache-miss fallback).
- `clearImportPreviewSession()` drops leftover blobs from older versions.

## 2026-10-23 — findImage uses FAL folders, not hard-coded fileadmin

- `findImage()` creates the target folder via `hasFolder` / `createFolder` / `getFolder` on the default storage.
- Temp images go to `var/transient/` (not public `typo3temp/assets/`) and are unlinked in `finally`.

## 2026-10-23 — Import CType Header / Image Only

- Dialog options and DataHandler CType now follow TCA + page TSconfig `removeItems`.
- On theme_camino (`header`/`image` removed) the dropdown only shows Text / Text & Image;
  POSTed header/image still map to text/textpic (same as before, import still works).
- Sites that keep `header`/`image` get real header / image CEs.

## 2026-10-23 — FAL image references on import

- Mixed text/image sections split at `<img>` (`ImportSectionSplitter`); image pieces become `textpic`
  with `sys_file_reference`. Text-only HTML is unchanged (no DOM round-trip).
- Failed DataHandler inserts delete the files uploaded for that CE.
- Unit: `Tests/Unit/Service/ImportSectionSplitterTest.php`. Functional: `insertPageElementCreatesSysFileReferenceForImages`.

## 2026-10-16 — Agent context, ns_license, premium strip, export-ignore

- Branch `feature/v14`.
- Added agent context (`AGENTS.md`, `CLAUDE.md`, `context/`, `tasks/`) modelled on ns_feedback.
- Added `nitsan/ns-license ^14.5.4` / `ns_license 14.5.4-14.9.99`.
- Added `Classes/Backend/PremiumStrip.php` + `backend-premium-strip.css` (ns_revolution_slider pattern);
  strip rendered below the module navbar with `trial=ns_googledocs#free-section`.
- Module parent switched to `nitsan_module` (shared with ns_license / other T3Planet modules).
- `.gitattributes` export-ignores agent/dev paths (same as ns_backup `0f211d0`), so TER/Composer archives stay clean.

## 2026-10-19 — QA bugfixes (section import)

- Import appends CEs after the last CE of the chosen colPos in document order (was: top, reversed).
- Parser: no blank section rows (invisible-only headings ignored, fallbackTitle); H3–H6/p/li/br separated.
- Import modal: visible "Add Content" checkboxes (`.ns-gd-section-enabled`); closes after success, stays open with error.
- Unit tests: `php phpunit.phar -c Build/UnitTests.xml` with an autoloader for `NITSAN\NsGoogledocs\` (no phpunit in the projects).
