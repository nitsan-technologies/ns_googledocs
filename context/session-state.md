# Session state

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
