# ISO/IEC 25010 Evaluation and UAT Instrument

## Purpose and participants

Use this instrument in the release-candidate environment after automated checks pass. It evaluates the system against the eight ISO/IEC 25010 product-quality characteristics and records the final user-acceptance decision.

Use [UAT_EXECUTION_RECORD.md](UAT_EXECUTION_RECORD.md) as the controlled run sheet for test identities, witnessed role journeys, evidence, defects, production-readiness approvals, and signatures.

Target respondents:

- HOA Admin: at least 1
- HOA Staff: 3–5
- Homeowners: 15–20

Do not use real personal or payment data in evaluation accounts. Test email delivery only with designated addresses.

## Rating scale

Rate every applicable statement from 1 to 5:

| Score | Meaning |
| --- | --- |
| 5 | Strongly Agree |
| 4 | Agree |
| 3 | Neutral |
| 2 | Disagree |
| 1 | Strongly Disagree |
| N/A | Not observed; excluded from the mean |

Interpret calculated means as follows: 4.50–5.00 Outstanding, 3.50–4.49 Very Good, 2.50–3.49 Good, 1.50–2.49 Fair, and 1.00–1.49 Poor. The project target is an overall mean of at least 4.50 with no unresolved critical UAT defect.

## Core questionnaire for every role

Record one score per statement and add a short note for every score below 4.

| ID | Characteristic | Statement |
| --- | --- | --- |
| FS-1 | Functional suitability | The features available to my role support the HOA tasks I need to complete. |
| FS-2 | Functional suitability | Calculations, statuses, dates, and generated records are correct. |
| PE-1 | Performance efficiency | Pages, searches, filters, and form submissions respond within an acceptable time. |
| PE-2 | Performance efficiency | Lists and downloads remain usable with representative data volumes. |
| CO-1 | Compatibility | The system works in the approved desktop and mobile browsers used by the HOA. |
| CO-2 | Compatibility | Exported CSV and PDF files open correctly in the tools used by the HOA. |
| US-1 | Usability | Navigation labels and task steps are clear without special training. |
| US-2 | Usability | Form validation and success/error messages explain what happened and how to recover. |
| US-3 | Usability | The interface remains usable with keyboard-only navigation and at 200% zoom. |
| RE-1 | Reliability | Retrying a failed or interrupted action does not create duplicate records or payments. |
| RE-2 | Reliability | The system preserves data and provides a clear recovery path after an error. |
| SE-1 | Security | I can access only the panel, records, downloads, and actions intended for my role. |
| SE-2 | Security | Sensitive information is not exposed in URLs, messages, audit entries, or public pages. |
| MA-1 | Maintainability | Operational behavior, settings, scheduled tasks, and recovery procedures are documented clearly. |
| PO-1 | Portability | The documented installation and deployment steps can reproduce the application in the target environment. |

## HOA Admin task checklist

Complete each task and record Pass, Fail, or Not Tested, plus evidence or a defect ID.

1. Sign in at `/admin`; confirm `/staff` and `/homeowner` are denied.
2. Create a pending Homeowner account, review it, activate it, and trigger the correct notification.
3. Create/edit a dues schedule and confirm obligations are generated without duplicates.
4. Record and review representative payments; open the private PDF receipt.
5. Process a complaint and a service request through valid forward-only states.
6. Issue and revoke a certificate; verify its private signed download behavior.
7. Publish, schedule, expire, and target announcements; trigger an email blast only with approval.
8. Apply report filters; preview, download CSV/PDF, and retrieve a queued private CSV.
9. Change a safe role permission and confirm the change appears in the immutable audit log.
10. Change system branding and panel availability settings, then restore the approved values.

Admin-specific questions:

- The dashboard gives an accurate operational and financial summary.
- User, role, dues, report, audit, and system-setting controls are understandable.
- High-impact actions require clear confirmation and preserve an audit trail.

## HOA Staff task checklist

1. Sign in at `/staff`; confirm `/admin` and `/homeowner` are denied.
2. Create or edit a Homeowner's permitted operational/contact data; confirm account-management fields are unavailable.
3. Create, edit, and deactivate a dues schedule; confirm delete is unavailable.
4. Record a payment and edit only an entry recorded by the same Staff account.
5. Review a payment proof and verify the resulting private receipt.
6. Update complaints and requests using only allowed status transitions and required notes.
7. Create/edit/publish an announcement; confirm delete and Admin-only email blast are unavailable.
8. Issue a certificate for an approved request; confirm revoke/delete are unavailable.
9. Generate only the Staff-approved reports and confirm full payment history is denied.

Staff-specific questions:

- The dashboard emphasizes operational work without exposing restricted financial aggregates.
- The Staff panel contains all required operational resources and no Admin-only resources.
- Assignment, notes, status, and payment-proof workflows are efficient for daily use.

## Homeowner task checklist

1. Register, review the submitted summary, receive approval, verify email, and sign in at `/homeowner`.
2. Confirm `/admin` and `/staff` are denied.
3. Update personal/property details and profile photo, then change the password.
4. Review outstanding dues, upload a valid payment proof, and download an approved receipt.
5. Submit a complaint with attachments and inspect its status and resolution details.
6. Submit a service request with attachments and download any linked certificate.
7. Open current announcements and confirm unread state changes.
8. Attempt a supplied other-owner URL and confirm it reveals no record details.
9. Repeat the key journey at 320px width, at 200% zoom, and using keyboard only.

Homeowner-specific questions:

- My dashboard clearly shows outstanding dues and active requests or complaints.
- I can find payment, complaint, request, certificate, announcement, and profile tasks easily.
- I am confident that another homeowner cannot view my records or private files.

## Objective evaluation evidence

Attach or link the following evidence to the evaluation record:

- `composer qa` result and exact test/assertion totals
- Production asset build result
- Composer and npm security-audit results
- MySQL migration status and the MySQL CI job result
- Chromium, Edge, WebKit, and Linux Firefox browser results
- Axe results plus keyboard and NVDA/other screen-reader notes
- Representative CSV/PDF checks and an authorized/private-download test
- Queue, scheduler, email, backup, restore-drill, health-check, and monitoring evidence
- Cross-panel and cross-owner access-attempt results

## Scoring and acceptance

For each characteristic, calculate `sum of applicable scores / number of applicable responses`. Calculate the overall mean from all applicable responses. Keep role-level means separate as well as the combined result so a larger Homeowner sample does not hide Admin or Staff concerns.

Release acceptance requires all of the following:

- Overall mean is at least 4.50.
- Each role's mean is at least 4.00.
- Every mandatory task is Pass.
- No critical or high-severity security, privacy, data-integrity, accessibility, or recovery defect remains open.
- The HOA approver accepts the production settings, backup evidence, and deployment window.

## Sign-off record

| Field | Value |
| --- | --- |
| Release/version | |
| Environment and URL | |
| Evaluation dates | |
| Admin respondents | |
| Staff respondents | |
| Homeowner respondents | |
| Overall mean | |
| Open defect IDs | |
| Decision (Accepted / Rejected / Retest) | |
| HOA approver, signature, date | |
| Technical lead, signature, date | |
