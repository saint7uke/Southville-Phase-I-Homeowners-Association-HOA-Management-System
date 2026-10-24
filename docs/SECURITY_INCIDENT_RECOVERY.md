# SEC-01 Incident Recovery Runbook

Run this process from infrastructure not exposed to the historical remote-code loader. Do not use application code or a passing test suite as proof that a host is trustworthy.

## Evidence preservation

1. Name the incident owner and record the affected commit/time window.
2. Preserve repository history, CI logs, caches, artifacts, deployment logs and host telemetry with access controls and checksums.
3. Do not force-push, rewrite history, delete suspect artifacts or run cleanup before evidence capture.

## Scope and containment

Inventory affected developer machines, runners, deployment hosts, package caches, databases, mail/storage services, SSH keys, API tokens and user sessions. Isolate or rebuild affected hosts from trusted media. Record each asset and disposition without copying secret values into the report.

## Credential and dependency recovery

From a trusted host, revoke and rotate every potentially exposed credential, invalidate application sessions, and remove suspect CI caches. Reinstall Composer and NPM dependencies from reviewed lockfiles. Run source-integrity tests and both dependency audits.

## Acceptance and closure

Before installing dependencies, run `npm run audit:security-source`; it emits rule names and repository paths only, never matching contents or secret values. Then install from the reviewed lockfiles with fresh caches and run the complete PHP suite, native MySQL 8.4 migration/workflow suite and browser matrix on the rebuilt environment. Retain command output and candidate commit ID.

Complete [the closure record](audits/SEC01_CLOSURE_TEMPLATE.md). The named security owner must sign and date it before setting `HOA_SECURITY_INCIDENT_CLOSED=true`. That variable is an attestation, not a substitute for the evidence. Routine CI source scanning can prevent known loader patterns from returning, but it cannot certify the workstation that produced a commit.
