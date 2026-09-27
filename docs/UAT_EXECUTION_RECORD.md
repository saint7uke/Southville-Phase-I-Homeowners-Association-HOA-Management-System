# Release-candidate UAT execution record

Use one copy of this record for each release candidate. Complete it in a non-production environment with representative test data. Do not enter passwords, API keys, database credentials, or other secrets in this file.

The task wording and acceptance thresholds come from the [ISO/IEC 25010 evaluation and UAT instrument](ISO_25010_EVALUATION.md). This record does not replace that questionnaire; it records the witnessed run and the final release decision.

## 1. Release identification

| Field | Value |
| --- | --- |
| Release/version or commit | `bbe03508ac00cd3906eca6e6123a6cda7c689ce0` (`main` / `origin/main`) |
| Candidate environment | Local XAMPP 8.2.12 / MySQL `hoa_system_uat` |
| Base URL | `http://127.0.0.1:8000` |
| Database/data-set description | Synthetic UAT fixtures; no production data |
| Evaluation start and end | Machine preparation refreshed 2026-09-20; witnessed UAT pending |
| UAT coordinator | |
| Technical observer | |
| HOA approver | |

## 2. Machine-verification handoff

The following machine evidence was last reconciled on 2026-09-27. Re-run the checks for the exact release commit and replace **Current evidence** if the candidate changes.

| Check | Current evidence | Candidate result / evidence link | Status |
| --- | --- | --- | --- |
| PHP quality suite | Guarded full verifier passed every approved protected hash and Pint plus two independent 145-test / 580-assertion SQLite executions; exact CI MySQL 8.4 lane passed | Local guarded verifier and [run 36305858757](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36305858757), 2026-09-27 | Machine passed |
| Exact GitHub release workflow | Hardened release commit passed SQLite, native MySQL 8.4, Chromium, Firefox, and WebKit | [Run 36305858757](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36305858757), 2026-09-27 | Machine passed; `CI-001` and `CI-002` closed |
| Focused reports/audit suite | 13 tests / 53 assertions passed | | Pre-verified |
| Production asset build | Vite build passed; 107 modules; landing page emitted as a lazy chunk | Guarded full verifier, 2026-09-27 | Machine passed |
| Fresh production Admin bootstrap | Environment-driven seeder is idempotent, refuses missing/weak input and existing-account elevation, and demo fixtures refuse production | 3 focused tests, 2026-09-20 | Machine passed |
| Dependency audits | Composer: no advisories; npm: 0 vulnerabilities | | Pre-verified |
| MySQL schema | All 23 migrations ran successfully; dues frequency is `VARCHAR(32)` | Isolated `hoa_system_uat`, 2026-09-20 | Machine passed |
| Laravel production compilation | Route and view caches compiled; four HOA schedules registered | | Pre-verified |
| Local candidate runtime | Database-aware `/up` and `/` HTTP 200; queue monitor OK with zero pending jobs; four schedules registered | Preparation and recovery checks, 2026-09-13 | Machine passed |
| Browser test inventory | Hardened candidate passed all 10 configured Chromium, Firefox, and WebKit tests, including favicon/logo loading; Firefox passed without retry after PHP CLI server worker isolation | [Run 36305858757](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36305858757), 2026-09-27 | Machine passed |
| Authenticated panel matrix | 12/12 role/browser cases passed, including keyboard sign-in, visible focus, skip-link activation, cross-panel 403, Axe serious/critical, and 320/768/1440/1920 px overflow checks | Exact current four-browser candidate run, 2026-09-20 | Machine passed |

“Pre-verified” is not a human UAT pass. The coordinator must attach the candidate run logs or record why existing evidence applies to the exact candidate.

## 3. Test identities and controls

Record identifiers only; do not record passwords.

Prepare the local candidate fixtures explicitly with:

```powershell
& "F:\Xampp 8\php\php.exe" artisan db:seed --class="Database\Seeders\UatSeeder"
```

The seeder is idempotent and refuses to run when `APP_ENV=production`. The base Admin, Staff, and Homeowner fixtures use `Southville2026!`; the added second-Staff, second-Homeowner, and pending-Homeowner fixtures use `SouthvilleUat2026!`. Rotate or remove them after UAT and never reuse either password outside this non-production environment.

| Purpose | Test account identifier | Data owner / approver | Ready |
| --- | --- | --- | --- |
| HOA Admin | `admin@southville.test` | Project owner | Ready |
| HOA Staff who records a payment | `staff@southville.test` | Project owner | Ready |
| Second HOA Staff for ownership-denial check | `staff2@southville.test` | Project owner | Ready |
| Approved Homeowner | `resident@southville.test` | Project owner | Ready |
| Second Homeowner for cross-owner denial | `resident2@southville.test` | Project owner | Ready |
| Pending Homeowner | `pending@southville.test` | Project owner | Ready |
| Designated test email inbox | | | |

Before testing, confirm that the environment is not production, outbound email is restricted to the designated inbox, the queue and scheduler are running, and a restorable database backup exists.

Preparation refresh on 2026-09-20: `APP_ENV=local`, database `hoa_system_uat`, all 23 migrations present, all six identities and their expected role/status mappings verified, and both `jobs` and `failed_jobs` contained zero rows. This confirms fixture readiness only; queue-worker, scheduler, email-inbox, and witnessed backup checks remain UAT tasks.

The 2026-09-13 recovery restored the clean archived fixture state with no payments or certificates. Use new records for every witnessed creation journey. Any unlinked synthetic file left in local storage is not UAT evidence.

## 4. HOA Admin witnessed journey

Mark **Pass**, **Fail**, or **Not tested**. Every mandatory row must be Pass for acceptance.

| ID | Witnessed task | Result | Evidence / defect ID | Witness and date |
| --- | --- | --- | --- | --- |
| A-01 | Sign in at `/admin`; verify `/staff` and `/homeowner` return access denied. | | | |
| A-02 | Create a pending Homeowner, review and activate it, then observe the correct queued notification in the designated inbox. | | | |
| A-03 | Create or edit a dues schedule; run generation twice and verify that obligations are not duplicated. | | | |
| A-04 | Record and review representative payments; open the authorized private PDF receipt and visually inspect names, amount, date, and layout. | | | |
| A-05 | Move a complaint and request through valid forward-only states; confirm invalid transitions and missing required notes are rejected. | | | |
| A-06 | Issue a certificate, visually inspect its private PDF, revoke it, and confirm the revoked download is denied. | | | |
| A-07 | Publish, schedule, expire, and audience-target announcements. Send an email blast only when the test-inbox owner approves it. | | | |
| A-08 | Apply report filters; inspect preview, CSV, PDF, and owner-bound queued private CSV results. | | | |
| A-09 | Change a safe test-role permission and confirm the actor, action, panel, and change appear in the immutable audit log; restore it. | | | |
| A-10 | Change branding and panel availability test settings, observe the result, and restore approved values. | | | |

## 5. HOA Staff witnessed journey

| ID | Witnessed task | Result | Evidence / defect ID | Witness and date |
| --- | --- | --- | --- | --- |
| S-01 | Sign in at `/staff`; verify `/admin` and `/homeowner` return access denied. | | | |
| S-02 | Edit permitted Homeowner operational/contact data; confirm account-management fields are unavailable. | | | |
| S-03 | Create, edit, and deactivate a dues schedule; confirm delete is unavailable. | | | |
| S-04 | Record and edit that Staff member's payment; verify a payment recorded by the second Staff member cannot be edited. | | | |
| S-05 | Review a payment proof and visually inspect the resulting authorized private receipt. | | | |
| S-06 | Update complaints and requests using allowed transitions and required notes; verify disallowed transitions fail. | | | |
| S-07 | Create, edit, and publish an announcement; confirm delete and Admin-only email blast are unavailable. | | | |
| S-08 | Issue a certificate for an approved request; confirm revoke and delete are unavailable. | | | |
| S-09 | Generate Staff-approved reports; confirm full payment-history reporting is denied. | | | |

## 6. Homeowner witnessed journey

| ID | Witnessed task | Result | Evidence / defect ID | Witness and date |
| --- | --- | --- | --- | --- |
| H-01 | Register, review the summary, receive approval, verify email, and sign in at `/homeowner`. | | | |
| H-02 | Verify `/admin` and `/staff` return access denied. | | | |
| H-03 | Update personal/property details and profile photo, then change the password and sign in again. | | | |
| H-04 | Review dues, upload a valid private payment proof, and download the receipt after approval. | | | |
| H-05 | Submit a complaint with attachments; inspect timeline, status, notes, and resolution details. | | | |
| H-06 | Submit a service request with attachments and download its issued certificate. | | | |
| H-07 | Open current announcements and verify unread state changes. | | | |
| H-08 | Attempt supplied URLs belonging to the second Homeowner; verify no record metadata or private file is disclosed. | | | |
| H-09 | Repeat the key journey at 320 px, 200% zoom, and keyboard-only. | | | |

## 7. Accessibility, compatibility, and document review

| ID | Manual check | Result | Evidence / defect ID | Witness and date |
| --- | --- | --- | --- | --- |
| UX-01 | Keyboard-only: skip links, menus, forms, tables, dialogs, confirmations, validation recovery, and visible focus are operable. | Machine pass for role login forms, visible focus, skip-link activation, and Admin report-dialog focus return; witnessed menus/tables/validation/confirmations and real Safari full-keyboard-access remain pending | Four-browser 40/40 run, 2026-09-13 | Codex machine preparation, 2026-09-13 |
| UX-02 | NVDA or another approved screen reader announces landmarks, headings, labels, errors, statuses, dialogs, and tables meaningfully. | | | |
| UX-03 | At 200% browser zoom, the role journeys remain readable and operable without two-dimensional scrolling except data tables where necessary. | | | |
| UX-04 | Representative desktop and mobile browsers render the approved journeys without clipped controls or horizontal page overflow. | Machine pass at 320/768/1440/1920 px in Chromium, Edge, Firefox, and WebKit; representative-device witness pending | Four-browser 40/40 run, 2026-09-13 | Codex machine preparation, 2026-09-13 |
| DOC-01 | Receipt PDF opens and prints with correct identity, reference, amount, dates, branding, and page boundaries. | Current candidate machine-rendered with no visual defects; witnessed download/print review pending | [`pdf-structural-review-2026-09-10.md`](../automation/hoa-loop/reports/pdf-structural-review-2026-09-10.md) | Codex, 2026-09-10 |
| DOC-02 | Certificate PDF opens and prints with correct identity, number, purpose, dates, branding, and page boundaries. | Current branded certificate machine-rendered at 2x with the embedded HOA seal and no visual defects; witnessed download/print review pending | [`pdf-structural-review-2026-09-10.md`](../automation/hoa-loop/reports/pdf-structural-review-2026-09-10.md) | Codex, 2026-09-27 |
| DOC-03 | Report PDFs and CSV files contain the selected filters/columns, correct totals, safe spreadsheet text, and no unauthorized rows. | Current PDF machine-rendered and 120-row stress layout passed; witnessed filter/download/CSV review pending | [`pdf-structural-review-2026-09-10.md`](../automation/hoa-loop/reports/pdf-structural-review-2026-09-10.md) | Codex, 2026-09-10 |

## 8. Operations and recovery evidence

Perform these checks in the candidate environment. Production values are approved later in Section 11.

| ID | Check | Result | Evidence / defect ID | Owner and date |
| --- | --- | --- | --- | --- |
| O-01 | Queue processes a designated test notification and failed-job monitoring is visible. | | | |
| O-02 | Scheduler runs the four HOA tasks without overlapping or duplicating records. | | | |
| O-03 | `/up` and the approved monitoring check report healthy service state. | Machine pass: `/up` now checks database readability; healthy and simulated-outage regression cases pass. Production monitor witness pending. | [`uat-database-recovery-2026-09-13.md`](../automation/hoa-loop/reports/uat-database-recovery-2026-09-13.md) | Codex, 2026-09-13 |
| O-04 | Database and required private/public files are backed up according to the operations guide. | | | |
| O-05 | A restore drill into an isolated database/storage target succeeds and sampled records/files match. | Machine preparation pass; production-format drill still required | [`uat-restore-drill-2026-09-10.md`](../automation/hoa-loop/reports/uat-restore-drill-2026-09-10.md) | Codex, 2026-09-10 |
| O-06 | An observer follows the rollback and incident instructions and confirms they are actionable. | | | |

## 9. Defect register

Severity: **Critical** blocks all use; **High** blocks a required workflow or creates security/privacy/data-integrity/accessibility/recovery risk; **Medium/Low** has a documented workaround or limited impact.

| Defect ID | Severity | Role/journey | Summary | Owner | State | Retest evidence |
| --- | --- | --- | --- | --- | --- | --- |
| CI-001 | High | Release verification | Earlier candidates exposed missing pre-test Vite builds, non-persistent browser sessions, MySQL JSON canonicalization differences, and inaccessible scrollable Filament table regions. All causes were corrected and the exact corrective commit passed every configured release job. | Technical lead | Closed, 2026-09-26 | [Green run 36245182854](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36245182854); failed [run 35497361687](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/35497361687) and [run 36243913243](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36243913243) retained as diagnostic history |
| CI-002 | High | Firefox release verification | PHP 8.3.35's single-process CLI test server segfaulted under browser request load, causing connection-refused errors rather than product assertion failures. Linux CI now runs the PHP CLI server with isolated workers and retains one CI-only Playwright retry; all 10 Firefox tests passed without using the retry. | Technical lead | Closed, 2026-09-27 | [Green run 36305858757](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36305858757); failed [run 36298622023](https://github.com/saint7uke/Southville-Phase-I-Homeowners-Association-HOA-Management-System/actions/runs/36298622023) retained as diagnostic history |

## 10. ISO/IEC 25010 results

Attach completed questionnaires from [ISO_25010_EVALUATION.md](ISO_25010_EVALUATION.md). Keep role means separate so a larger Homeowner sample cannot conceal Admin or Staff concerns.

| Measure | Required | Actual | Pass |
| --- | --- | --- | --- |
| Admin respondents | At least 1 | | |
| Staff respondents | 3–5 | | |
| Homeowner respondents | 15–20 | | |
| Admin mean | At least 4.00 | | |
| Staff mean | At least 4.00 | | |
| Homeowner mean | At least 4.00 | | |
| Combined overall mean | At least 4.50 | | |
| Mandatory witnessed tasks | All Pass | | |
| Open Critical/High defects | Zero | | |

## 11. Production readiness approvals

Record approval or a controlled evidence reference, never the secret value.

| Gate | Approved configuration/evidence reference | Approver and date | Status |
| --- | --- | --- | --- |
| Protected-test baseline changes reviewed against `automation/hoa-loop/reports/protected-baseline-review.md` | Project-owner approval reconfirmed; four reviewed hashes are current and the guarded full verifier passed | Project owner, 2026-09-20 | Approved |
| Production domain, HTTPS, `public/` document root, and secure session settings | | | |
| Production MySQL database, least-privilege account, migration backup, and retention policy | | | |
| SMTP sender/domain and approved recipient controls | | | |
| Queue, scheduler, failed-job alerts, uptime/log/disk/backup monitoring | | | |
| File storage, privacy, backup, and restore ownership | | | |
| Seeded development accounts removed or credentials rotated | | | |
| Deployment window, operator, rollback trigger, and business owner | | | |

## 12. Final decision

Acceptance requires an overall mean of at least 4.50, each role mean of at least 4.00, every mandatory task passed, no open Critical/High defect, and approval of production settings, backup evidence, and deployment window.

| Field | Decision |
| --- | --- |
| Candidate decision (Accepted / Rejected / Retest required) | |
| Open accepted Medium/Low defects and rationale | |
| HOA approver — printed name, signature, date | |
| Technical lead — printed name, signature, date | |
| Deployment authorized by — printed name, signature, date | |
