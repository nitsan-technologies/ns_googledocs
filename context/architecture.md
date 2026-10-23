# ns_googledocs — Architecture

## Backend module (`Configuration/Backend/Modules.php`)

| Key | Parent | Access | Path |
|---|---|---|---|
| `nitsan_module` | — (main module, after `web` / `content` on v14, label "T3Planet") | — | — |
| `nitsan_nsgoogledocs` | `nitsan_module` (`before top`) | `user` | `/module/nitsan/NsGoogledocsNitsan` |

- `nitsan_module` is registered here only when `ns_basetheme` is not loaded; ns_license also registers it, so both share that main-menu entry.
- Navigation component: `@typo3/backend/page-tree/page-tree-element` on v12/v13,
  `@typo3/backend/tree/page-tree-element` on v14 (switch via `Typo3Version`).
- Extbase actions: `UserInfoController` → `dashboard` (default), `import`, `docsImport` (JSON), `globalSettings`, `update` (JSON).

## Classes

| Class | Role |
|---|---|
| `Controller/UserInfoController` | Backend: dashboard, import UI, Drive export import, global OAuth settings; assigns premium strip |
| `Backend/PremiumStrip` | Renders the freemium CTA with ns_license trial deep link |
| `Domain/Repository/UserInfoRepository` | Plain writer (not Extbase): inserts `tt_content` / `sys_file_reference`, updates `be_users` OAuth fields, caches remote API HTML |
| `Service/ImportSectionSplitter` | Splits section HTML at `<img>` so import can create FAL-backed `textpic` CEs in document order |
| `Service/ImportContentElementTypeResolver` | Keeps Header/Image Only when the page allows those CTypes; otherwise maps to text/textpic |

DI: `Configuration/Services.yaml` autowires `NITSAN\NsGoogledocs\`.

## Tables (`ext_tables.sql`)

| Table | Content |
|---|---|
| `be_users` (extra columns) | `client_id`, `client_secret`, `refresh_token`, `import_type` |
| `tx_nsgoogledocs_domain_model_apidata` | Cached marketing HTML blocks (support/footer HTML), `last_update` |

Imported content is standard `tt_content` (+ `sys_file` / `sys_file_reference` for images under `fileadmin/ns_googledocs/`).

## Views

| File | Used by |
|---|---|
| `Layouts/Default.html` | Backend shell: assets, navbar (Dashboard / Import / Global Settings), premium strip, `content` section |
| `Templates/UserInfo/Dashboard.html` | Dashboard status and support blocks |
| `Templates/UserInfo/Import.html` | Drive docs table, column select, Import Now modal/AJAX |
| `Templates/UserInfo/GlobalSettings.html` | OAuth client id/secret/refresh token + import type |
| `Templates/UserInfo/Premium.html` | Unused (premium tab not in Modules.php) |
| `Partials/FieldInfoIcon.html` | Tooltip icon for settings fields |

## Assets

- Assets via PageRenderer: `Css/main.css`, `@nitsan/ns-googledocs/Main.js` (jQuery from Core import map). Uses TYPO3 backend Bootstrap 5; no bundled Bootstrap 4.
- Premium strip: `Css/backend-premium-strip.css` via `PageRenderer::addCssFile()` when the strip renders.
- `Configuration/JavaScriptModules.php` maps `@nitsan/ns-googledocs/` → `Resources/Public/JavaScript/` (`backend.module`).
- `Configuration/Icons.php` registers `googledocs` SVG.

## Labels

`locallang.xlf` (UI), `locallang_googledocs.xlf` (module title), `BackendModule.xlf` (main module), `locallang_db.xlf` (TCA).
