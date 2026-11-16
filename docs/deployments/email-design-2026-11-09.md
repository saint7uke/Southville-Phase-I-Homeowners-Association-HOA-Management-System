# HOA email design — 2026-11-09

## Presentation-only changes

Shared application-owned notification view and HTML/text components supply a title, greeting, readable body, optional detail rows, primary button, fallback link and HOA signature. Payment and complaint/request emails expose labeled reference/status/reason or remarks; operational and payment notices now greet the recipient. Contact replies use the same card and preserve multiline text. English/Filipino text is retained, including a translated fallback-link instruction.

The official original `public/images/HOA.png` is unchanged. Its new 160×160 email derivative is 45,759 bytes, versus the original 2,040,339 bytes. Remote-image blocking still leaves the HOA name and meaningful alt text. Templates use an absolute HTTPS logo URL, inline-compatible styles, table layout and responsive rules. Dynamic prose/details/titles are escaped and rendered literally; signed/reset URLs are not altered.

All current MailMessage notifications, including Laravel verification and role-aware reset/invitation flows, inherit the shared view. No trigger, recipient, transport, queue, token, permission, delivery ledger, migration or environment change is intended.

## Verification

- Synthetic preview generator: `php scripts/check-email-design.php` (does not send mail).
- Browser layout checks: `node scripts/check-email-layout.mjs`; 12 combinations covering EN/FIL, 375/768/1280px, loaded/blocked images. No horizontal overflow; title, detail rows, action button and literal-content checks passed.
- EN mobile and FIL desktop screenshots visually inspected for logo, hierarchy, alignment, wrapping and spacing.
- Final targeted PHP run: **94 tests, 841 assertions passed**, covering all branding cases, email reliability, account lifecycle/security, portal registration, workflow notifications, contact inbox and UI reliability. An older logo assertion was updated to the new optimized asset.
- **14 tooling tests passed**; targeted Pint formatting checks passed. No Vite source changed: email CSS is inlined by Laravel, not bundled by Vite. The matching existing build is preserved in the deployment package.
- Gmail/Outlook rendering and actual inbox receipt remain **unverified**. No real mail is sent for this work.
- A broader registration-address regression exposed an existing test expecting a Street validation error despite the previously removed Street requirement; this unrelated expectation is not changed by this email-design update.

## Rollout boundary

Deploy only the reviewed manifest and matching existing asset build after source comparison and private backups. Recompile Blade views; retain `.env`, `.htaccess`, SMS settings/cron, queued/failed jobs, business records, certificates and household assignments. No worker, scheduler, replay or live email test is authorized by this rollout.

Read-only SSH preflight matched dependencies and recognized all eight updated production files against prior reviewed releases; 11 files are new. Production preflight: 67 queued jobs, six failed jobs, request 5 Completed, household 2 head member 1. No records were reconciled.

## Completed SSH deployment

- Release: `/home/u130796816/.hoa-deploy/email-design-20261109-v1` (private directory).
- Private source backup: **1,808,493 bytes**; private database backup: **1,001,611 bytes**.
- Installed **19 reviewed source/image files plus 27 matching existing build files**; staged PHP lint and installed checksums passed.
- Laravel configuration cache and compiled Blade views rebuilt; application returned live.
- **30 tracked tables unchanged** by row counts and content fingerprints. `.env`, `.htaccess` and the SMS-worker wrapper remained unchanged. No migration or queue processing occurred; 67 queued and six failed jobs retained.
- Post-deployment: all 19 reviewed files exactly match local source; synthetic HTML/plain-text rendering passed without sending mail; `/up` HTTP 200; public HTTPS logo HTTP 200.
- Hostinger CDN losslessly recompresses the public PNG (41,824 bytes), so byte hashes differ from the origin image. Decoded comparison confirmed the same 160×160 dimensions and **zero difference in every RGBA channel**. Origin/deployed source hashes matched exactly.
- Request 5 remains Completed and household 2's existing head remains member 1. No notifications were sent, no records were edited, and no historical jobs were replayed.
