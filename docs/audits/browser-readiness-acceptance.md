# Browser readiness acceptance

The new `tests/Browser/release-readiness.spec.js` requires `HOA_BROWSER_READINESS_FIXTURE=true` and a localhost base URL. Never point it at an arbitrary remote host or existing application database.

## Isolated fixture

Use a fresh SQLite filename containing `phase23-browser-test`, verify the application's effective connection/path is that exact disposable file **before** migrating, then migrate only that file and run `Tests\Support\Phase23BrowserSeeder` with `APP_ENV=testing`, array mail and sync queues. Do not assume environment overrides take effect when configuration is cached; use an isolated application copy rather than clearing a live deployment's configuration. The seeder refuses other environments/database targets. It creates synthetic administrator, assigned/unassigned staff, Owner, same-address Co-owner, linked Tenant and unrelated Owner accounts, a current PHP 489 obligation and a finalized complaint. Test-only accounts are not invitations or production provisioning.

Serve the isolated database with `APP_ENV=local`, `SESSION_DRIVER=database`, array mail and sync queues; browser HTTP must not use Livewire's unit-test-only temporary-upload behavior. Use a separate localhost port and file-backed server diagnostics. Do not reuse the user's normal application server. Leave `.env` unchanged; set process-scoped environment variables in a dedicated shell.

## Execution

Set `PLAYWRIGHT_BASE_URL`, `HOA_BROWSER_READINESS_FIXTURE`, and the designated `HOA_BROWSER_*_EMAIL`/`PASSWORD` values for the guarded fixture. Run each engine serially, and never run Vite builds while acceptance is reading the manifest:

```text
npx playwright test tests/Browser/release-readiness.spec.js --project=chromium --workers=1
npx playwright test tests/Browser/release-readiness.spec.js --project=firefox --workers=1
npx playwright test tests/Browser/release-readiness.spec.js --project=webkit --workers=1
npx playwright test tests/Browser/phase23.spec.js tests/Browser/profile-validation.spec.js tests/Browser/role-panels.spec.js tests/Browser/reports.spec.js --project=chromium --workers=1
```

Preserve trace/screenshot/error-context output privately on failures. Do not publish session-bearing network traces. Record initial failures, corrections, retries, final counts and skipped workflows. The full matrix includes profile feedback/persistence/uploads, contact submission, payment method restrictions, household boundaries, finalized complaint controls and project modal state/focus. Existing PHP tests remain authoritative for transactional writes and forged submissions; browser checks do not replace them.

## Acceptance boundary

Current certificate workflow supersedes the earlier draft-registration browser scenario: the old `/admin/certificate-templates` URL must return 404. The isolated case now reviews/confirms the supplied source under `/admin/certificates`, selects the homeowner, prepares the private completed preview, confirms issuance and checks the stored PDF's inline View/Print response. Confirmation is synthetic fixture activity only, not real HOA authorization. Desktop/tablet/mobile overflow and bounded Axe checks are included. Exact engine results and any failed attempts are in the revision checklist.

Recorded integrated-flow results: Chromium **1 passed** (2.3 minutes), Firefox **1 passed** (2.1 minutes), WebKit **1 passed** (1.2 minutes), serial isolated runs using the installed original font. These are distinct engine executions of one scenario, not exhaustive certificate/printer coverage. The explicit font-availability opt-in was added after these local runs to prevent unsupported CI hosts from substituting another font.

That document-specific case additionally requires `HOA_BROWSER_RESIDENCY_FONT_AVAILABLE=true` and a legitimately available Times New Roman Bold TTF on the isolated server (configure `CERTIFICATE_RESIDENCY_FONT` where needed). Without it the case explicitly skips; a skip is not original-document acceptance. The original font is not distributed in Git or silently replaced for CI. Font-dependent PHP regression helpers likewise explicitly skip without the licensed font; permission/schema and other non-font tests remain runnable. Local Windows acceptance used the actual Times New Roman Bold font.

Follow-up report regression: one additional scenario passed separately in Chromium (1.8 minutes), Firefox (2.0 minutes) and WebKit (1.2 minutes), checking Filipino-rendered canonical status values, actual resolved complaint preview, unchanged subject and keyboard focus restoration. The fixture used a fresh `phase23-browser-test-reports-20261020.sqlite` database. Firefox logged a dashboard dynamic-chart import error; WebKit logged navigation-time Livewire access-control/module-import errors. The report assertions passed, but these console diagnostics remain investigation items, not an error-free browser certification. The chart asset existed and the isolated HTTP log recorded 200 responses; that alone does not establish the cause of the client errors.

Each engine must be identified separately; installing Firefox/WebKit does not count as running them. Axe checks are bounded to exercised screens/dialogs, not a whole-product accessibility certification. HOA approval and security incident recovery are separate gates. Financial mutation, legacy-proof review, restoration and publication require additional completed browser scenarios before claiming complete end-to-end coverage.

Recorded 2026-10-20: Firefox **4 passed** (5.3 minutes), WebKit **4 passed** (1.9 minutes). Chromium combined selection **8 passed / 1 setup failure** (5.9 minutes), followed by the corrected payment setup **1 passed** (59.2 seconds). This gives passing evidence for nine distinct Chromium cases, not a claim that the last combined run was clean. A modal-only corrected selection also passed two cases; it overlaps the nine and must not be added to the distinct total. Logs and limitations are in the revision checklist.
