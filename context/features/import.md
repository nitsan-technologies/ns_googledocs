# Feature — Google Docs import

*Status: Done (free markers + plain-document fallback).*

## Flow

1. `importAction` lists Drive files (`mimeType=application/vnd.google-apps.document`) owned by the connected user.
2. Editor picks a backend-layout column (`BackendLayoutView::getBackendLayoutForPage`) and clicks Import Now.
3. `docsImportAction` (AJAX / `JsonResponse`) exports the doc as `text/html`, normalizes markup, parses
   `###TEXT###` / `###TEXTIMAGE*###` / `###IMAGES###` markers into `tt_content` elements (`text`, `textpic`,
   `image`). Plain docs without markers become a single `text` CE.
4. Images are copied into FAL (`fileadmin/ns_googledocs/pages/<pid>-<slug>/`) and referenced from bodytext /
   `sys_file_reference`. Public URLs must stay absolute-path (`/fileadmin/...`), never protocol-relative
   (`//fileadmin/...`).

## Free vs premium

This free codebase only ships page import. Premium features (if any) stay on the commercial product page; the
backend CTA deep-links into ns_license (`features/premium-cta.md`) instead of linking out to the shop.
