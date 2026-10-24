# Current Findings Register

Last reviewed: 2026-10-24. This register is the release-facing summary; chronological audit files remain the evidence trail and must not be deleted or rewritten.

## Open findings

| ID | Severity | State | Owner | Exit criterion |
|---|---|---|---|---|
| SEC-01 | Critical | Release blocker; source guard implemented | Named security owner | Trusted-host investigation, credential rotation, cache/dependency rebuild, session invalidation and signed [closure record](SEC01_CLOSURE_TEMPLATE.md) are complete. |
| DATA-01 | Medium | Implemented; production migration pending | Engineering + data reviewer | Disposable MySQL migration/rollback passes, ambiguous legacy obligations are reviewed, and production migration is approved after SEC-01 closes. |
| OPS-01 | High | Automated gate implemented; staging evidence pending | Operations | `php artisan hoa:release-check` passes in production configuration with retained evidence for every attestation. |
| QA-01 | Medium | Acceptance pending | QA + HOA reviewers | Native MySQL, browser matrix, document review, volume/concurrency checks and UAT sign-off pass on the trusted release candidate. |

## Closed application findings

The historical `FIN-01` through `CERT-01` findings in [system-wide-audit.md](system-wide-audit.md) are repaired and covered by regression tests. They are not current defects unless a new reproduction is recorded. Dependency audits were clean on 2026-10-24.

## Release rule

Deployment is prohibited while any Critical finding is open or `hoa:release-check` fails. Nonzero `hoa:audit-data` checks block release; review observations require a recorded disposition and must never trigger an automatic ledger or identity rewrite.
