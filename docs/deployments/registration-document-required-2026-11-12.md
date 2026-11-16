# Required public-registration document — 12 November 2026

## Implementation and boundary

Implemented and verified locally, then deployed over SSH after separate user authorization. No migration, production configuration change, historical backfill or production notification send was performed.

- Shared `RegistrationDocumentRules` is enforced by both `RegisterHomeownerRequest` and `RegisterHomeowner::handle`. Every new public Owner, Co-owner and Tenant application requires an actual uploaded PDF/JPG/JPEG/PNG, maximum 10 MB. File, content MIME, filename extension and size are validated before account creation/storage/notification intents. String filenames and arrays cannot stand in for uploads.
- Public registration has a required indicator/native required input, updated English/Filipino guidance, translated inline errors and upload focus for native/custom/server validation failures. The error is associated with the upload control. Existing form/scrolling, privacy consent and optional SMS exclusion remain unchanged.
- Existing private storage, authorized document access and transaction/file cleanup remain in place. Existing accounts with null documents remain unchanged. Admin-created account and homeowner forms are not made document-required.
- Positive registration fixtures now provide valid documents, including the disposable MariaDB race worker payload. No race assertion or SQLite skip guard was removed. Affected feature suites fake local storage to keep synthetic documents out of application storage. The MariaDB-specific race was not executed in this verification pass.

## Verification results

- `php artisan test --compact --filter="RequiredRegistrationDocumentTest|VerificationDocumentTest"`: **10 passed, 83 assertions**. Missing uploads across all three ownership types, forged filename/array input, MIME/extension/size rejection, all four accepted extensions, private storage, translations, existing null documents and storage failure rollback; authorized access regressions included.
- Broader run using `RequiredRegistrationDocumentTest|VerificationDocumentTest|ConfirmedPropertyIdentityTest|RegistrationAddressAvailabilityTest|UiReliabilityTest|SmsEnrollmentHttpTest|PortalWorkflowTest`: **81 passed, one failed, 509 assertions** before the final additional JPEG-alias test. Remaining failure is `RegistrationAddressAvailabilityTest::test_endpoint_validates_input_and_is_throttled`: it still expects a `street` error even though the existing address endpoint no longer requires Street. This endpoint/assertion was not changed by the feature; the stale expectation is reported separately, not weakened to make the suite green. Two positive registration fixtures missing the newly required document were updated and subsequently passed.
- `node scripts/run-interface-browser-checks.mjs --registration-document`: **two passed**, 1280px/390px Chromium. Required-field focus, missing-upload submission prevention, supported/unsupported selection, English/Filipino errors/guidance and no horizontal overflow. No registration POST was sent. Synthetic screenshots in `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-UjE2n4/` were visually inspected.
- `node scripts/run-interface-browser-checks.mjs --registration-scroll`: **seven passed**, desktop, breakpoint, mobile, short-window/zoom-equivalent sizing, both languages and unchanged other authentication pages. Evidence in `C:/Users/bargo/AppData/Local/Temp/hoa-alert-browser-interface-Ou0h1W/`.
- `npm.cmd run test:tooling`: **21 passed**.
- Targeted Pint check: passed. `npm.cmd run build`: passed with the existing runtime hero-image URL warnings.
- Notification assertions use fakes/durable-intent checks, not SMTP or inbox delivery evidence.

## Verified release

`node scripts/package-registration-document.mjs` creates an explicit allowlist archive of seven application/template/translation sources plus the complete matching build, excluding tests, credentials, migrations and private files.

- Archive: `C:/Users/bargo/AppData/Local/Temp/hoa-registration-document-release-7TP86Z/release.tar.gz`
- Files: 33
- SHA-256: `5d6c57d6762e3d44c68712213b4659929a7378f6d4ce98cbe0c68b466d01d7a3`

## Authorized SSH deployment

User authorized deployment in a separate follow-up. Deployed 12 November 2026, approximately 18:58 Manila / 10:58 UTC. Private release directory: `/home/u130796816/.hoa-deploy/registration-document-20261112-v1`.

- Compared all existing affected production source/template/translation files. Differences matched the planned requirement, guidance and validation/focus behavior. Verified archive SHA-256 before staging.
- Private source/build backup `before-clean.tar.gz`: 3,515,991 bytes; database backup `database.sql`: 1,156,566 bytes. Both are outside the web root with mode 600.
- Installed the new shared validation class before its consumers and the build manifest last. All 33 deployed file checksums matched. Shared rule, request and action PHP syntax checks passed. `php artisan view:cache` succeeded. Composer classmap is not authoritative, so no dependency/autoloader rebuild was required. Routes/configuration caches were not changed.
- Protected `.env`, `.htaccess` and cron fingerprints unchanged; no tracked table changes. Existing accounts and documents, household/certificate/payment records, notification/settings rows and audit entries were preserved. Queue counts remained 73 queued and six failed. SMS delivery `updated_at` alone is excluded from the snapshot fingerprint to tolerate pre-existing housekeeping.
- Read-only public checks: registration 200, required upload attribute and new guidance present, new app/locale JS 200, health 200. No production registration POST or document upload was performed. Browser validation/rollback behavior was tested locally with isolated synthetic data, not production accounts.
- The unrelated stale Street assertion remains documented above; it was not deployed or silently altered.

Rollback restores prior source/build from the private backup, removes the newly introduced `app/Validation/RegistrationDocumentRules.php` if reverting completely, then refreshes compiled views. No database restoration is required. Preserve private backups and do not process queued/failed jobs during rollback.
