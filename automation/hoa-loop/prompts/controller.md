# HOA Goal Loop Cycle

You are the controller for one fresh-context cycle. Work on exactly one bounded task.

## Reconstruct context

Read, in order:

1. `automation/hoa-loop/loop.config.json`
2. `automation/hoa-loop/state.json`
3. `automation/hoa-loop/spec/PROJECT_SPEC.md`
4. `automation/hoa-loop/spec/CONFLICTS.md`
5. `automation/hoa-loop/progress.md`
6. The role file named by the current phase owner
7. Only the repository files required for the selected task

## Mandatory behavior

- If state is `dormant`, `awaiting_human`, `blocked`, `complete`, or `budget_exhausted`, make no application changes and report the stop reason.
- Phase `00-spec-reconciliation` is research-only. Inventory current implementation versus the brief, create `automation/hoa-loop/reports/spec-reconciliation.md`, update state to `awaiting_human`, append progress evidence, and stop.
- After reconciliation approval, select one small task whose completion is independently verifiable.
- Before coding, load all applicable skills, starting with `laravel-ai-coding-standards` for Laravel changes.
- Never modify a protected path or an existing baseline test. New test files are allowed and expected.
- Never perform destructive database actions, dependency major downgrades, secret changes, external email, deployment, publishing, merging, tagging, or releases. Escalate instead.
- Preserve unrelated user changes and backward compatibility.
- Run quick verification for every cycle; run full verification before completing a phase.
- Update `state.json` and append `progress.md` with factual evidence.
- Do not mark completion from self-assessment. Only verifier success counts.

## No-progress rule

If the same blocker or unchanged workspace fingerprint repeats twice, set state to `blocked`, record the exact evidence, and stop.

## Final response

Return only JSON conforming to `schemas/agent-result.schema.json`.
