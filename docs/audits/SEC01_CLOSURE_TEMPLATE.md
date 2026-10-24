# SEC-01 Closure Record

Do not place passwords, tokens, private keys, session values, complete environment files or sensitive forensic contents in this record. Store restricted evidence separately and reference its controlled location and checksum.

## Ownership

- Incident owner:
- Security reviewer:
- Organization/privacy reviewer:
- Incident window:
- Trusted recovery environment identifier:

## Asset disposition

| Asset class | Affected or potentially affected | Containment/rebuild action | Evidence reference | Reviewer/date |
|---|---|---|---|---|
| Developer workstations | | | | |
| CI runners and caches | | | | |
| Deployment hosts | | | | |
| Production/staging databases | | | | |
| Mail, storage and DNS services | | | | |

## Credential classes

| Credential class | Revoked/rotated | Old credential rejected | Sessions invalidated | Evidence reference |
|---|---|---|---|---|
| Primary email and recovery methods | | | | |
| GitHub users, PATs, SSH/deploy keys and apps | | | | |
| Hosting and deployment credentials | | | | |
| Database and cache credentials | | | | |
| SMTP, storage, DNS and backup credentials | | | | |
| Laravel application key and encrypted-data decision | | | | |

## Trusted-environment acceptance

- `npm run audit:security-source`:
- Composer/NPM audits:
- Full PHP suite:
- Native MySQL 8.4 suite and migration rollback:
- Chromium/Firefox/WebKit matrix:
- Backup restoration rehearsal:
- Queue, scheduler, mail and private-storage checks:
- `php artisan hoa:audit-data --json` disposition:
- `php artisan hoa:release-check`:

## Closure

- Outstanding risks and accepted exceptions:
- Notification/legal determination:
- Security owner approval and date:
- Deployment approver and date:

Only after this record is approved may operations set `HOA_SECURITY_INCIDENT_CLOSED=true` in the deployment environment.
