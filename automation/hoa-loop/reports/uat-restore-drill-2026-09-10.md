# Local UAT backup and restore drill

Date: 2026-09-10  
Environment: Local XAMPP 8.2.12, synthetic data only  
Source database: `hoa_system_uat`  
Scratch database: `hoa_system_uat_restore_20260910` (removed after verification)

## Result

The local technical restore drill passed. A current consistent database dump and both Laravel storage areas were archived, restored into isolated targets, verified, booted, exercised through authenticated role journeys and a database-queued job, and then cleaned up. This is release-candidate preparation evidence; it is not approval of production backup storage, retention, encryption, recovery targets, or ownership.

## Backup evidence

| Item | Result |
| --- | --- |
| MySQL method | `mysqldump --single-transaction --routines --triggers --events --default-character-set=utf8mb4` |
| Database dump size | 65,420 bytes |
| Laravel storage coverage | `storage/app/private` and `storage/app/public` |
| File manifest | 5 files, with byte size and SHA-256 per file |
| Archive | `storage/app/backup-drills/2026-09-10/hoa-system-uat-backup-20260910.zip` |
| Archive size | 6,772,827 bytes |
| Archive SHA-256 | `63bfe8445fb1abc0f6fb0635a4fd28a66e1c6246ff146dc42c1af9f15e1e47fc` |
| Backup preparation time | 3.75 seconds |

The archive contains synthetic UAT data and is intentionally local and unencrypted. Production backup approval still requires encrypted off-site storage, independent restore credentials, retention, access logging, monitoring, and an assigned owner.

## Restore verification

- Expanded the retained archive into an isolated directory.
- Verified all five restored files against the archived SHA-256 manifest.
- Imported the archived SQL dump into the newly created scratch database in 15.52 seconds.
- Matched exact row-count fingerprints for 12 core tables:

| Table | Rows at backup and restore |
| --- | ---: |
| announcements | 1 |
| audit_logs | 14 |
| certificates | 0 |
| complaints | 0 |
| dues_obligations | 0 |
| dues_settings | 1 |
| homeowners | 3 |
| payments | 0 |
| permissions | 25 |
| roles | 3 |
| service_requests | 0 |
| users | 6 |

- Booted a temporary PHP server on port 8002 against the restored database; `/up` returned HTTP 200.
- Passed the authenticated Chromium Admin, Staff, and Homeowner role matrix 3/3 against the restored database. The matrix includes cross-panel 403 checks, serious/critical Axe checks, and overflow checks at 320, 768, 1440, and 1920 px.
- Dispatched `GenerateDuesForSetting` into the restored database queue and processed it with a one-shot worker: one job queued, zero remained, zero failed, and two obligations were generated.
- Confirmed the primary UAT environment remained healthy at `http://127.0.0.1:8000/up` after cleanup.

## Cleanup evidence

- Stopped the exact temporary restore-server PHP process.
- Dropped only `hoa_system_uat_restore_20260910` after confirming the exact schema name.
- Removed only the resolved extraction and staging directories beneath `storage/app/backup-drills/2026-09-10`.
- Retained the ZIP backup and server logs as evidence.
- Preserved the active `hoa_system_uat` database and the older incompatible `hoa_system` database.

## Recovery objectives and limitations

| Measure | Observation |
| --- | --- |
| RPO | A fresh point-in-time UAT backup was restored; no business-approved production RPO exists yet. |
| Database import time | 15.52 seconds for the current six-user synthetic data set. |
| Critical browser verification | 3 role journeys passed in 1.0 minute. |
| End-to-end timing | Archive creation to post-cleanup observation was 6.88 minutes. The entire drill was not captured by one stopwatch, so this is a lower-bound observation, not an approved RTO measurement. |
| Production RTO | Not established or proven. It must include provisioning, encrypted off-site retrieval, secrets reconstruction, full file restore, DNS/traffic recovery, and stakeholder validation. |

Remaining human/production checks:

- Agree and sign off RPO/RTO targets and a named recovery owner.
- Configure encrypted, versioned, off-site backups and backup-age/dead-man monitoring.
- Repeat a timed drill from the actual production-format backup destination.
- Create representative complaint, request, proof, receipt, certificate, and report files during witnessed UAT, then validate linked private downloads and visual rendering.
- Record the next drill date and findings owner.

