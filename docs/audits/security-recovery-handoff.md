# SEC-01 independent host and credential recovery handoff

Status: **externally blocked; production release prohibited**. Prepared 2026-10-20.

The confirmed remote loader, affected commits, static decoding and evidence hash are recorded in `system-wide-audit.md`. It executed during prior builds. Its second-stage effects, persistence and credential exposure are not established or excluded. Source removal and passing application tests do not establish host trust.

## Ownership and evidence preservation

- Assign an independent incident-response owner and a trusted device/environment before using administrative credentials.
- Identify every workstation, CI runner/cache, deployment host and build artifact that processed affected commits. Record dates, operators and evidence locations; do not infer the initial entry point or attribute an attacker without evidence.
- Preserve affected Git objects, logs, quarantined dependencies and relevant host evidence under access control. Record hashes and handling. Do not execute quarantined files or force-push away history; do not upload secrets, browser profiles or complete session-bearing exception dumps to Git/issues.
- Contain affected hosts according to the responder's evidence-preservation process. Collect volatile evidence before rebuild if the responder requires it. This repository change does not perform containment or forensic collection.

## Recovery sequence — trusted administrator execution only

1. From a trusted device, review GitHub users, keys, tokens, OAuth/apps, Actions runs, secrets and deployment integrations. Revoke suspicious or potentially exposed credentials/sessions; retain evidence of the decisions.
2. Inventory accessible SSH/deployment, database, SMTP, cloud/storage and other service credentials. Rotate/restrict them in a dependency-aware maintenance window. Separate application, migration and test database permissions. Verify old credentials no longer work without logging their values.
3. Rebuild affected workstations/build/deployment hosts from trusted media or replace them. Recreate dependencies from reviewed lockfiles with trusted tooling and fresh caches. Do not restore executable caches or dependency trees from the quarantined system.
4. Review startup tasks/services, scheduled tasks, extensions, repository hooks and host/browser persistence with the incident responder. Restore only reviewed data and configuration. A single antivirus scan is not a clearance criterion.
5. Re-establish workers, scheduler, mail and private storage on the recovered environment using test destinations first. Verify permissions, TLS, access logs, backups and a disposable restore exercise.
6. Run application acceptance, native MySQL verification and dependency checks again on the trusted environment. Retain sanitized evidence separately from the compromised host's earlier results.

## Application-key and session assessment

Do **not** blindly run `key:generate` on an existing installation. First inventory Laravel encrypted attributes/casts, encrypted files, cookies, sessions, signed URLs, queue payloads and integrations that depend on the key. Preserve a secured backup and test a staged recovery on a disposable restored copy. Decide with the responder whether and how existing ciphertext can be safely re-encrypted or retired. Keeping old keys for decryption also retains exposure if those keys were compromised; it is not automatically safe recovery.

Plan session/remember-token and password-reset-token invalidation for affected accounts, coordinated with key rotation and sign-in communications. Do not alter accounts or erase audit logs automatically. Test authorized access and intentional invalidation before deployment.

## Required release sign-off

Record incident owner, affected-asset inventory, evidence reference, rebuild provenance, credential classes rotated/revoked (never values), key/data recovery decision, restore-test outcome, trusted-environment acceptance results, outstanding risks, approving authority and date. Release remains blocked until independent sign-off is supplied. HOA approval is a separate gate.

No host rebuild, external credential rotation, account revocation, real notification or deployment was performed by preparing this handoff.
