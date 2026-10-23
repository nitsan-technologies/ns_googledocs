# ns_googledocs — Operating Principles

*Every change to code, context or docs must serve the goals below.*

---

## Goal 1 — Nothing that works may break

The extension is declared for TYPO3 v12, v13 and v14 at the same time (Composer and Classic mode).

- Every change must keep all three majors working. Prefer existing version switches
  (`Typo3Version` in `Configuration/Backend/Modules.php`, `IconSize` enum check in `PremiumStrip`)
  instead of inventing new ones.
- Prefer the smallest change that fixes the root cause. No drive-by refactors.
- Run `tasks/run-quality.md` and re-test the affected backend flow before claiming done.

## Goal 2 — Token-cost efficiency (agent context)

- **DRY context:** each fact lives in exactly ONE file. Reference, don't copy.
- **Load on demand:** read `context/features/<area>.md` for the task, not everything.
- **Prune as you go:** keep `context/session-state.md` short.

## Goal 3 — Accuracy and trust

- Answer from context + code, never from invented API, table or field names.
- Conflicting facts across files = fix the source file.

---

## Guardrails (non-negotiable)

- OAuth credentials (`client_id`, `client_secret`, `refresh_token`) live on `be_users`; never log or expose them.
- Do not change ns_license from an ns_googledocs task; reuse its module and trial modal.
- All UI text goes into `Resources/Private/Language/*.xlf`, no hard-coded labels (except Google API error passthrough).
- Keep Composer (`nitsan/ns-license`) and Classic (`ns_license` in `ext_emconf.php`) constraints in sync.
- Bundled `vendor/` (from `composer.libraries.json`) is for Classic installs only; Composer projects use the root autoload.

---

## Apply this whenever you

- Edit `context/` or `AGENTS.md`
- Land a feature or bugfix (update the matching `context/features/*.md` + `session-state.md`)
