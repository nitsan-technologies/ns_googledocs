# Feature — Google Docs import

*Status: Done (heading-based section import, H1/H2).*

## Flow

1. `importAction` lists Drive files (`mimeType=application/vnd.google-apps.document`) owned by the connected user.
2. Editor picks a backend-layout column (`BackendLayoutView::getBackendLayoutForPage`) and clicks Import Now.
3. `docsImportAction` (AJAX / `JsonResponse`) exports the doc as `text/html` again (no session cache of
   the parsed HTML), normalizes markup, and splits on Heading 1 / Heading 2 via `GoogleDocsSectionParser`.
   Each section becomes one or more `tt_content` elements (`text` / `textpic`; `header` / `image` only when
   the target page still allows those CTypes).
4. Images are copied into the **default FAL storage** (`ns_googledocs/pages/<pid>-<slug>/`, usually
   `fileadmin/` but not assumed). Temp downloads go to `Environment::getVarPath()/transient/` and are
   unlinked after `addFile()`. A section that mixes text and images is split into consecutive
   CEs (`text` → `textpic` → `text` …) so Filelist tracks the files and Google Doc order is kept.
   HTML without images is still one CE. Images inside tables/lists stay inline (not split). Public URLs
   must stay absolute-path (`/fileadmin/...`), never protocol-relative (`//fileadmin/...`).
   If DataHandler rejects a CE, files uploaded for that CE are deleted.

## Section import (v14 dialog) — rules

- Placement: DataHandler ignores incoming `sorting` for new records (positive pid = top of page).
  `docsImportAction` inserts the first CE after `findLastContentElementUidInColumn(pid, colPos)`
  (`pid = -uid`) and chains each next CE after the previous one → appended, document order.
- `GoogleDocsSectionParser` drops headings that are empty after removing `\p{Cf}` / `\p{Z}`
  characters; image-only headings keep the image as body. Untitled sections get `fallbackTitle`
  (body excerpt, else `import_section_untitled`) shown as input placeholder; CE header stays empty.
- Section HTML is normalized for every CType: H3–H6 "Title<br>body" split into heading + `<p>`,
  empty `<p>` removed, newline after block ends / `<br>`. Block children nested inside an exported
  H1/H2 (e.g. an `<h3>`) stay real blocks; H3+ are never flattened into `<p>`.
- Headings exported as `<span>Title</span><span>Body…</span>` (no `<br>`) use the first run as title
  when the rest reads like body copy (> 40 chars or ends with sentence punctuation).
- `<!--?xml …-->` / `<?xml …?>` artifacts are stripped (`GoogleDocsSectionParser::stripXmlArtifacts`).
- Import CTypes (`header` / `text` / `textpic` / `image`) are offered only when the target page
  allows them (TCA + `TCEFORM.tt_content.CType.removeItems`). Unavailable `header`/`image`
  still fall back to `text`/`textpic` so DataHandler does not fail (e.g. theme_camino).

## Page type restriction

`ImportPageTypeGuard`: import allowed only on doktypes from extension setting `allowedImportDoktypes`
(default `1`). 3, 4, 6, 7, 199, 254, 255 are always blocked. Enforced in `importAction` (UI message
`import_access_error_msg`) and server-side in `previewSectionsAction` / `docsImportAction`.

## OAuth tokens

- Global Settings accepts either a refresh token (usually `1//…`) or an access token (`ya29.…`).
  Access tokens are applied with `setAccessToken()` and work until they expire (~1h).
- `Google\Client::refreshToken()` returns `['error' => 'invalid_grant']` instead of throwing; the
  result is checked and explained to the editor. Section reading retries once after a token refresh.

## Remote marketing blocks

Fetched from `https://composer.t3planet.cloud/API/ExtBackendModuleAPI.php` (old t3terminal host
answers 301). `RemoteSnippetValidator` drops full HTML documents / error pages, also from cached rows.

## Free vs premium

This free codebase only ships page import. Premium features (if any) stay on the commercial product page; the
backend CTA deep-links into ns_license (`features/premium-cta.md`) instead of linking out to the shop.
