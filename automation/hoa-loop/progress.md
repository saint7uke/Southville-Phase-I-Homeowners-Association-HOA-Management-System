# Loop Progress

## Reconciliation cycle 0 - 2026-08-17

- Phase: `00-spec-reconciliation`
- Bounded task: audit the existing repository against the v1.2 brief and establish recoverability.
- Files changed: loop state/configuration, `reports/spec-reconciliation.md`, and this progress log.
- Evidence: baseline commit `005b019`; existing suite passes with 13 tests and 38 assertions; three independent read-only audits covered domain, panels/auth, and frontend/QA.
- Decisions: preserve newer dependency majors; add `/homeowner` alongside `/portal`; use additive schema changes; isolate panel sessions after introducing guard-aware actor resolution; keep SQLite tests plus a MySQL release lane.
- Remaining risk: most brief workflows are partial, and certificates/contact are absent.
- Next task: implement and verify the tri-panel/auth foundation without breaking the protected baseline.

Each future cycle must append: timestamp, phase, bounded task, files changed, verifier command/result, remaining risk, and next recommended task.

## Controller repair - 2026-08-17

- The first worker process exited before an agent turn because this Codex CLI version treats `--approve-for-me` and an explicit `--sandbox` as mutually exclusive.
- Removed the redundant sandbox argument; `--approve-for-me` already enforces the workspace-write sandbox.
- No application files changed and no iteration was charged.

## Implementation cycles 1-2 - 2026-08-17

- Phase: `01-foundation-tri-panel`
- Bounded task: introduce independent Admin, Staff, and Homeowner panel guards; a minimal `/homeowner` Filament panel; and request-context-safe actor attribution.
- Files changed: auth configuration; three panel providers; two panel middleware classes; guard-aware actor support; actor-stamped models/observer; export middleware; provider registration; and new `PanelFoundationTest` coverage.
- Review correction: an independent security pass found repeat legacy migration after logout and ambiguous multi-guard actor selection. The second cycle made Admin/Staff migration one-shot and consumptive, excluded Homeowner portal sessions, and bound actor resolution to the current panel or default web context only.
- Evidence: full verifier passed Laravel Pint, 35 tests/93 assertions, Composer QA, production Vite build, and route-cache compatibility. `/homeowner`, `/homeowner/login`, and `/homeowner/logout` are registered.
- Compatibility: `/portal` remains available on `web`; Spatie roles remain canonical on guard `web`; the new panel has no shared Admin resources.
- Remaining risk: password reset/email verification/account lifecycle and homeowner resources remain intentionally deferred.
- Next task: implement and test `02-auth-account-lifecycle` without expanding into dues or certificate work.
