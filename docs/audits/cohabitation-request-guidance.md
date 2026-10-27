# Cohabitation request draft guidance

Date: 2026-10-22 (Asia/Manila)

## Confirmed cause and repair

`/service-requests/{id}/draft` intentionally refused every Cohabitation request with
HTTP 409 because requests contain names/address, but not all verified birth dates,
completed years together and requester declarations needed by the original PDF.
Both manager and homeowner tables nevertheless advertised **Preview draft PDF**.
This was a workflow/UI mismatch, not missing or repeated source approval.

- Removed Cohabitation draft-preview actions from the shared admin/staff table and
  homeowner table; Residency draft previews remain available.
- For approved requests, authorized managers use **Prepare certificate**, opening
  the existing form with `?request={id}`. Historical retired-type issuance keeps
  its existing action; no grandfathered behavior was removed.
- Approved bookmarked Cohabitation draft links redirect authorized managers into
  their authenticated admin/staff panel. Other authorized viewers get a private,
  localized guidance page and a fixed request-detail return link, not an exception.
- Pending/Processing guidance explains review comes first; Approved guidance
  explains staff preparation; Rejected guidance points to remarks/HOA; Completed
  guidance points to the saved document rather than a new draft.
- Shared guidance also appears on homeowner request details and legacy portal
  details. Existing saved-certificate download actions are unchanged.
- Authorization precedes guidance/redirects. Unrelated/unverified residents,
  inactive actors and staff without request permission remain denied. Deleted
  requests return 404. No arbitrary redirect URL is accepted.
- Regression testing exposed a related strict-lazy-loading exception when listing
  several homeowner requests. Eager-loading their homeowner relationship avoids
  that failure and per-row relationship lookups during authorization.

## Preserved boundaries

The request's names and address are preserved by the existing certificate form;
missing declarations are collected and validated there, never invented. Pending
requests cannot bypass `Pending -> Processing -> Approved`. Existing source and
signatory approval, actor-bound preview expiry, transactional issuance,
idempotency, numbering, private PDFs and notification behavior remain in use.
No extra source-confirmation or per-certificate approval step was added.

GET requests allocate no certificate/template/preview, send no notification and
do not change service-request data. Normal application/session bookkeeping is not
claimed to be globally write-free. Historical PDFs and uploaded sources are untouched.

No new dependency or migration. No live database access, source approval, data
repair, external notification, account provisioning, deployment or push performed.

## Verification evidence

- Focused SQLite checks: **11 passed / 313 assertions**, 35.548 s. Covers manager
  redirects/panel isolation, all five statuses, action visibility/labels/URLs,
  no request/issuance writes, authorization, deleted requests, saved downloads,
  Filipino copy, escaped record content and unchanged Residency draft PDFs.
  Log: `storage/logs/cohabitation-request-guidance-focused-20261022.txt`.
- Full SQLite suite: **479 passed / 6 skipped / 5,336 assertions**, 378.703 s;
  no failures/errors. Skips are the six native-database-only certificate,
  payment and property/receipt concurrency checks, not verified on SQLite.
  Text/JUnit evidence: `storage/logs/cohabitation-request-guidance-full-20261022.*`.
- Initial focused failures were retained while correcting fixture assumptions:
  database-refreshed attribute comparison, excluding normal fixture-creation
  alerts and request-scoped audit comparisons. The actual homeowner listing
  lazy-load failure was fixed in application code, not suppressed in tests.
- Scoped Pint check and PHP/JavaScript syntax checks passed. Production Vite build
  passed with exit 0 in 18.02 s; the pre-existing missing
  `/images/southville-community-hero.webp` warning remains unrelated and unresolved.
- Initial isolated browser run: **5 passed / 1 failed**, 3.7 minutes. WebKit's
  Tab navigation skipped the return link. The link now has an explicit
  normal-order tab stop (`tabindex="0"`); the original keyboard assertion remains.
  Test review also found that toggling `.dark` alone did not exercise this
  page's system-color-preference CSS. The final check emulates both actual
  preferences and asserts the rendered background changes before running Axe.
  Initial log: `storage/logs/cohabitation-request-guidance-browser-20261022.txt`.
- After the keyboard repair, focused SQLite rerun: **11 passed / 313 assertions**,
  16.964 s (`storage/logs/cohabitation-request-guidance-focused-final-20261022.txt`).
- Final isolated browser run: **6 passed / no skips or retries**, 218.884 s,
  Chromium/Firefox/WebKit serially. Covers English/Filipino status guidance,
  320/768/1440 layouts without overflow, keyboard focus (including WebKit), one
  mocked locale POST, unchanged ticket content, actual light/dark rendering and
  no serious/critical Axe findings on the exercised pages.
  JUnit: `storage/logs/cohabitation-request-guidance-browser-final-20261022.xml`;
  screenshots: `storage/framework/testing/cohabitation-request-guidance-browser-final-20261022/`.
  Screenshots of the dark and 320px focused-link states were visually inspected.
  The initial failed run is retained; it is not represented as a clean pass.
- Final full SQLite rerun after the keyboard change: **479 passed / 6 skipped /
  5,336 assertions**, 573.440 s; zero failures/errors. The same six native-only
  concurrency cases skipped. Final text/JUnit evidence:
  `storage/logs/cohabitation-request-guidance-full-final-20261022.*`.
  Scoped Pint, all five affected PHP syntax checks, browser-script syntax and
  whitespace checks passed. No owned application/database server was started.

## Browser boundary and remaining gates

The new browser suite uses actual Blade responses rendered with synthetic fixtures
in in-memory SQLite and built assets, intercepted on `certificate-guidance.test`.
It starts no application server and uses no real accounts. Locale responses are
mocked. This checks presentation, keyboard, marked translations and Axe; it does
not establish live browser sign-in, persistence, redirect-following or issuance.
Those server-side behaviors are covered by HTTP/Livewire tests, not represented
as new end-to-end browser acceptance. No native MySQL rerun is claimed here.

Existing independent host-security recovery, HOA/human document/editorial review
and production release gates remain outside this focused fix.

## Related save-workflow follow-up

The subsequent staff edit investigation confirmed that direct Pending -> Approved
was incorrectly offered and silently rejected. That repair keeps the existing
Processing stage and certificate preparation workflow. See
[service-request editing](service-request-editing.md) for shared transitions,
visible validation, same-page next-step updates and issuance-only certificate
completion. Neither fix requires repeating an existing source confirmation.

## Continuation verification (2026-10-27)

The existing implementation was retained; it was not replaced or re-approved.
Newer uncommitted registration/profile/property changes were left intact.

- Scoped Pint, PHP syntax for the controller, both request resources/tables and
  guidance test, browser JavaScript syntax and whitespace checks passed.
- English/Filipino dictionary-key and placeholder parity passed for **1,249 keys**.
- Production Vite build passed with exit 0 in **33.81 s**. The existing missing
  `/images/southville-community-hero.webp` warning remains unrelated and unresolved.
  Evidence: `storage/logs/cohabitation-continuation-build-20261027.txt`.
- The first broad filtered SQLite invocation was interrupted before its summary.
  Its buffered output subsequently showed 24 progress marks; it is not counted as
  a passing regression run or an application failure. Only the audit-owned PHP
  process was stopped. The log and incomplete JUnit file are retained under
  `storage/logs/cohabitation-continuation-focused-20261027.*`.
- Direct SQLite guidance-suite retry: **11 passed / 313 assertions / no skips**,
  443.087 s; zero failures/errors. This rechecks role/status guidance, panel-safe
  redirects, action visibility, authorization, existing downloads, escaped copy,
  Filipino output and private Residency draft PDFs on the current worktree.
  Debug/JUnit evidence:
  `storage/logs/cohabitation-continuation-guidance-direct-20261027.*`.
- Refreshed rendered-page browser checks: **6 passed / no retries or skips**,
  Chromium, Firefox and WebKit serially; JUnit time **81.229 s**. English/Filipino
  status copy, 320/768/1440 layouts, keyboard focus, marked locale switching,
  unchanged ticket content and actual light/dark presentation passed. Axe found
  no serious/critical violations on the exercised pages. Dark desktop and
  keyboard-focused mobile screenshots were visually inspected.
  Text/JUnit: `storage/logs/cohabitation-continuation-browser-20261027.*`;
  screenshots: `storage/framework/testing/cohabitation-continuation-browser-20261027/`.
  These are intercepted, synthetic rendered-page checks with mocked locale
  responses, not live sign-in, issuance or persistence acceptance.
- No new application-code defect was confirmed or source approval repeated.
  Earlier 2026-10-22 full-suite results above remain historical evidence, not new
  full-suite results for the current worktree. No new native MySQL run is claimed.

No live database, `.env`, source confirmation, historical PDF, financial record,
account, permission, production service or external notification was changed.
