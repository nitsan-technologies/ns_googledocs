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
| `Domain/Repository/UserInfoRepository` | Inserts `tt_content` / `sys_file_reference`, updates `be_users` OAuth fields, caches remote API HTML |
| `Domain/Model/UserInfo` | Extbase model leftover (credentials live on `be_users`, not a dedicated table) |

DI: `Configuration/Services.yaml` autowires `NITSAN\NsGoogledocs\` (models excluded).

## Tables (`ext_tables.sql`)

| Table | Content |
|---|---|
| `be_users` (extra columns) | `client_id`, `client_secret`, `refresh_token`, `import_type` |
| `tx_nsgoogledocs_domain_model_apidata` | Cached marketing HTML blocks (`googleDocsSetupWizard`, support/footer/premium HTML), `last_update` |

Imported content is standard `tt_content` (+ `sys_file` / `sys_file_reference` for images under `fileadmin/ns_googledocs/`).

## Views

| File | Used by |
|---|---|
| `Layouts/Default.html` | Backend shell: assets, navbar (Dashboard / Import / Global Settings), premium strip, `content` section |
| `Templates/UserInfo/Dashboard.html` | Dashboard + setup wizard entry |
| `Templates/UserInfo/Import.html` | Drive docs table, column select, Import Now modal/AJAX |
| `Templates/UserInfo/GlobalSettings.html` | OAuth client id/secret/refresh token + import type |
| `Templates/UserInfo/Premium.html` | Unused (premium tab not in Modules.php) |
| `Partials/FieldInfoIcon.html` | Tooltip icon for settings fields |

## Assets

- Layout `<script>` / `<link>`: `JavaScript/Jquery.js`, `Main.js` (module), `Datatables.js`, `Vendor/bootstrap4.3.1.min.css`, `Css/main.css`.
- Premium strip: `Css/backend-premium-strip.css` via `PageRenderer::addCssFile()` when the strip renders.
- `Configuration/JavaScriptModules.php` maps `@nitsan/ns-googledocs/` → `Resources/Public/JavaScript/`.
- `Configuration/Icons.php` registers `googledocs` SVG.

## Labels

`locallang.xlf` (UI), `locallang_googledocs.xlf` (module title), `BackendModule.xlf` (main module), `locallang_db.xlf` (TCA).
