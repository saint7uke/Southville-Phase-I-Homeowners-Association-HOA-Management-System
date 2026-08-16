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
