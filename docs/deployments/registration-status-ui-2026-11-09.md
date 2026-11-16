# Registration-status UI — 2026-11-09

Implemented locally and deployed over host-key-verified SSH to Hostinger.

## Changes

- Scoped the application-status background to `/images/hoa-hero.webp`, with a navy overlay and solid fallback. Other authentication layouts are unchanged.
- Added a centered responsive status card, official HOA logo, state-specific SVG icons, readable status badge, wrapping email/feedback, separated actions, focus/skip navigation and translated status headings/instructions.
- Fixed inherited decorative-span styling that caused the status eyebrow to overlap the heading; removed the page's default body margin.
- Kept signed-link expiry, tampering/ownership protection, existing pending/approved/rejected behavior and existing action destinations. No controller, account-state or approval workflow changes.

## Local verification

- Portal workflow/security regression: 17 tests, 71 assertions passed.
- JavaScript tooling: 14 tests passed.
- Chromium: all three synthetic states at 320, 375, 768 and 1440 pixels passed, including unchanged signed Refresh query parameters, loaded logo/hero, no horizontal overflow and nonoverlapping eyebrow/heading. Desktop/mobile screenshots were visually inspected, including long rejection feedback.
- Build, targeted Pint and `git diff --check` passed. Vite leaves public image references unchanged at runtime; the supplied hero exists and was tested by HTTP. The pre-existing missing community-hero reference remains on unrelated authentication surfaces.
- An initial browser assertion compared a relative generated URL against an absolute normalized Refresh URL. Corrected the assertion to compare pathname and query parameters, preserving signature checks; no application routing change was needed.

## Deployment

- Manifest: [reviewed files](registration-status-ui-2026-11-09-manifest.txt).
- All five page/translation/image sources matched reviewed baselines or existing identical content; the previous feedback integration on the production template was explicitly reviewed and preserved.
- Installed five manifest files and the complete 27-file matching build (32 files); uploaded and installed SHA-256 values matched.
- Private backups: `/home/u130796816/.hoa-deploy/registration-status-ui-20261109-v2` (0700). Source backup: 1,998,603 bytes; database backup: 980,284 bytes; backup files 0600. The earlier v1 directory contains staging only and was never installed.
- Held both SMS file locks, briefly entered maintenance mode, installed atomically with manifest last, rebuilt configuration/Blade caches, then restored live mode.
- All 30 checked tables retained their complete row fingerprints during installation. Preserved 66 queued jobs and six failed jobs, production `.env`, root `.htaccess`, private SMS worker wrapper, migration state, SMS settings, household 2 assignment and completed request 5.
- No migration, queue processing, scheduler change, live registration, approval, OTP request or SMS send was performed.

## Live read-only verification

- User-provided signed status page returned HTTP 200 with the new page classes/logo; no applicant data or signed URL was copied into this report.
- Hero image returned HTTP 200; downloaded checksum matched the reviewed local image.
- Public manifest and compiled landing CSS checksums matched the local production build.
- Fresh server inspection: maintenance off, all five manifest sources unchanged from reviewed local versions, SMS live mode/master switch still enabled, daily cap 50, 66 queued/six failed jobs unchanged.
- No production end-to-end application, login or SMS mutation was exercised. Browser screenshots use isolated synthetic records only.
