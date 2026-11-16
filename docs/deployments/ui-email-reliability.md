# UI and email reliability deployment handoff

Deployment status: completed over SSH on 9 November 2026; see [the deployment record](ui-email-2026-11-09.md). The user requested that queued jobs remain untouched, so workers/scheduler were not enabled. The notes below describe the original handoff prerequisites and verification boundary.

Local changes require `2026_11_09_000001_add_ui_and_mail_reliability.php`, reviewed application/view/translation changes and the matching complete `public/build`. Do not copy the entire dirty worktree blindly. Prior email, address, SweetAlert and certificate-preview changes are already present and must remain.

Review these change groups:

1. Complaint model, submission/update validators, complaint forms/detail/report views.
2. `PropertyPhase`, `CurrentPropertyAddress`, homeowner/household create/edit/profile paths, registration/availability/landlord matching, new snapshot producers, current address presentation/search.
3. Users table/ListUsers, `UserTablePreference`, Column Manager translations.
4. `EmailDelivery`, `WorkflowMail`, `DeliverWorkflowMail`, `RecoverWorkflowMail`, registration/account/workflow/payment/dues producers and notification guards.
5. Existing announcement/contact jobs and result messages, shared Markdown mail header/footer/theme, contact mailable, mail timeout configuration, scheduler and translations.

See `docs/audits/email-notification-audit.md` for evidence, limitations and operations. Tests and browser fixtures are local-only. No proprietary fonts, `.env`, root `.htaccess`, private uploads or database files belong in the release archive.

The exact 88-path application scope is [ui-email-source-manifest.txt](ui-email-source-manifest.txt). Transfer the complete matching `public/build` with `manifest.json`, not individual hashed assets. The manifest contains current integrated files, so compare them with the actual server version before transfer. It deliberately excludes tests, deployment secrets, fonts and stored documents. `.env.example` only documents an optional 30-second SMTP timeout; the default is already implemented without a production environment edit.

Local verification: consolidated PHP regressions passed (340 tests, 2,492 assertions, two licensed-Arial skips); final focused safeguards passed (20 tests, 88 assertions). All 4 Chromium browser checks, 10 JavaScript tooling checks, production build and scoped formatting passed. See the audit for outstanding delivery/environment checks. SQLite success is not a substitute for a production-like MySQL migration rehearsal.

Before rollout: verify source diff, production-like MySQL migration, backup/restore and existing queue state. Enter maintenance, deploy reviewed source/build, run `php artisan migrate --force`, refresh Laravel caches, restart workers, resume service and check designated test records. Do not run `migrate:fresh`, seeders or the test suite on production. Roll back application files from backup if smoke checks fail; do not destroy the additive data tables.

Do not mark mail/worker readiness attestations true until production verification is complete. No rollout, real SMTP send, inbox check or production record mutation has been performed for this change.
