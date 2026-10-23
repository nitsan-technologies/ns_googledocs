# AGENTS.md — ns_googledocs (Google Docs)

Agent router for the Google Docs free extension. Load only what your task needs.

> **Principles:** `context/principles.md`
> **Session log:** `context/session-state.md`
> **Doc index:** `context/docs-map.md`

---

## Always load

| File | Why |
|---|---|
| `context/principles.md` | Guardrails, "nothing that works may break" rule |
| `context/core.md` | Identity, TYPO3/PHP/ns_license constraints, tooling, known issues |

---

## Task router

| Task | Load |
|---|---|
| Backend module (Dashboard, Import, Global Settings) | `context/features/backend-module.md` |
| Google Docs import (Drive API, content parsing, images, tt_content) | `context/features/import.md` |
| Premium strip / free trial / ns_license dependency | `context/features/premium-cta.md` |
| Classes, tables, templates, routes, assets | `context/architecture.md` |
| Lint / validate / manual test | `tasks/run-quality.md` |
| Update agent context after a change | `tasks/context-update.md` |

---

## Feature status

| Feature | Status | Agent entry |
|---|---|---|
| Backend module: Dashboard, Import list, Global Settings (OAuth credentials) | Done | `context/features/backend-module.md` |
| One-click Google Docs → `tt_content` import (heading-based H1/H2 sections) | Done | `context/features/import.md` |
| Premium strip + ns_license free-trial deep link | Done (needs ns_license 14.5.4 release) | `context/features/premium-cta.md` |

---

## Quick commands

```bash
cd packages/ns_googledocs            # or typo3conf/ext/ns_googledocs in Classic mode
for f in $(find . -name "*.php" -not -path "./.git/*" -not -path "./vendor/*"); do php -l "$f"; done
composer validate --no-check-publish
for f in Resources/Private/Language/*.xlf; do xmllint --noout "$f"; done
```
