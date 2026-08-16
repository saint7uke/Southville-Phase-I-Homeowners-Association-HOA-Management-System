# HOA Project Loop (Dormant)

This directory contains a bounded, semi-autonomous Codex goal loop for completing the HOA Information System described in `spec/PROJECT_SPEC.md`.

The loop is intentionally **not armed**. Creating these files does not start Codex, change application code, migrate data, install packages, send email, or deploy anything.

## Control flow

1. A human explicitly arms the loop by changing `enabled` to `true` in `loop.config.json` and `status` to `ready` in `state.json`.
2. A human explicitly starts `scripts/run-loop.ps1` with `-Start`.
3. Each cycle starts a fresh `codex exec` context and reads durable state from this directory.
4. The controller selects one bounded task, applies changes, runs the required verifier, records evidence, and exits.
5. The outer runner checks immutable control-file hashes, protected baseline tests, wall-clock limits, iteration limits, and no-progress fingerprints.
6. Success requires deterministic terminal verification and a final human UAT/deployment gate.

## First-run behavior

The first cycle is research-only. It must create `reports/spec-reconciliation.md`, inventory the existing application, and stop with `awaiting_human`. It may not modify application code. This gate is mandatory because the v1.2 brief conflicts with the current repository:

- Brief: Filament 3; repository: Filament 4.
- Brief: React 18 / Vite 5 / Tailwind 3; repository: React 19 / Vite 7 / Tailwind 4.
- Brief: `/homeowner` Filament panel; repository: custom `/portal` homeowner surface.
- Brief: MySQL target; current local environment: SQLite.
- Brief: Sanctum removed; repository still lists Sanctum.

The recommended reconciliation is to preserve supported newer dependency majors, add a true `/homeowner` Filament panel, and provide a deliberate transition from `/portal` rather than deleting working behavior in one pass. A human must approve the final choice.

## Files

- `loop.config.json`: hard limits, protected paths, and allowed commands.
- `state.json`: durable machine state, phase progress, and escalation status.
- `spec/PROJECT_SPEC.md`: normalized scope derived from the attached brief.
- `spec/brief-extract.txt`: plain-text snapshot of the attached DOCX.
- `spec/CONFLICTS.md`: decisions that must be reconciled before implementation.
- `agents/*.md`: role boundaries and required outputs.
- `prompts/controller.md`: fresh-context cycle prompt.
- `schemas/agent-result.schema.json`: structured final response contract.
- `verifiers/verify.ps1`: deterministic quick/full/terminal checks.
- `scripts/validate-loop.ps1`: setup validation only; never starts Codex.
- `scripts/run-loop.ps1`: dormant outer controller.
- `progress.md`: append-only human-readable cycle log.

## Safe validation (does not start the loop)

```powershell
powershell -ExecutionPolicy Bypass -File automation/hoa-loop/scripts/validate-loop.ps1
```

## Future start command

Do not run this until the reconciliation gate and source-control decision are approved:

```powershell
powershell -ExecutionPolicy Bypass -File automation/hoa-loop/scripts/run-loop.ps1 -Start
```

The runner refuses to start while `enabled` is `false`, state is not `ready`, or the workspace is not protected by Git.
