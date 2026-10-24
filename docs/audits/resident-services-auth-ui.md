# Resident services and authentication UI polish — 24 October 2026

## Follow-up: normal landing page blank after browser-server shutdown

The user reported a blank page at `127.0.0.1:8000`. Read-only browser diagnostics
reproduced HTML **200** with an empty React root: the main bundle attempted to load
lazy CSS/JavaScript from **127.0.0.1:61301**, the already-stopped acceptance server,
and received `ERR_CONNECTION_REFUSED`. The page/source was not deleted.

**Cause:** the newly added runner supplied its temporary `ASSET_URL` to Vite as well
as PHP. Laravel's Vite plugin uses that setting as the compiled asset base. The 21
isolated browser checks passed while their server was running but did not detect
the shared build becoming unusable from the normal application after shutdown.

**Repair:** the runner now explicitly leaves `ASSET_URL` empty so imports use portable
`/build/` URLs and PHP uses its request host. It also checks compiled JS/CSS against
the disposable origin before proceeding. A Node regression verifies rejection of
temporary origins/path traversal and acceptance of portable imports. `.env`, live
database records, routes, authentication and production source components were not
changed by this follow-up. Rebuilt shared artifacts restore the normal site.

Verification: **5 Node tests passed**; corrected-runner production build passed
in **13.28 seconds**; one focused Chromium case passed (**11.7 seconds**) and its
owned server stopped. **After that shutdown**, a fresh read-only browser visit to
`http://127.0.0.1:8000/` rendered the heading and all six service cards with **zero
JavaScript errors and zero failed requests**. Compiled-asset checks reject neither
the old nor new retired test origin because neither is present. The original
21-case result below remains bounded UI evidence, not a claimed 21-case rerun of
this infrastructure correction.

Private follow-up evidence: `storage/framework/testing/public-ui-1792773206869/`
and `landing-live-diagnostic.png`, `landing-live-restored.png`,
`landing-live-restored-after-tests.png` under `storage/framework/testing/`.
A tab holding the earlier failed bundle requires a reload/hard refresh.

## Implemented changes

- Landing page `#services`: six local outlined SVG icons reuse the installed
  Heroicon paths (Banknotes, Megaphone, Clipboard Document List, Exclamation
  Triangle, Identification and Chat Bubble Left Right). The source package's
  MIT notice is preserved under `resources/icons/`. No icon dependency or CDN.
- Cards use one column below 640px, two from 640px and three from 900px. Removing
  the featured two-column span produces balanced 3×2 desktop rows rather than an
  orphan last card. Its navy treatment, all six descriptions, numbering, anchors,
  translation keys and existing reveal animation hooks remain.
- Consistent 48px icon badges/28px SVGs, flexible equal-row cards, comfortable gaps
  and wrapping text avoid clipped translations. Cards remain informational
  articles, not misleading buttons or links. SVGs are decorative/non-focusable.
- `/login`: removed only the footer phrase “For homeowners, staff, and
  administrators” and its preceding separator. Login remains canonical and
  role-aware for all three account roles; authentication rules are unchanged.
- `/homeowner/register`: replaced only its form-heading “S1” badge with the
  existing official `images/HOA.png`, retaining 48×48 dimensions, proportional
  sizing and decorative semantics. Other pages' S1 marks are untouched.
- Browser Axe checks identified the service eyebrow's pre-existing **4.34:1**
  blue-on-gray contrast. Its text now uses the existing navy token, scoped only
  to the services section; other sections/palette are not redesigned.

## Tests and isolation

Rendered PHP tests verify both English and Filipino login navigation, the missing
phrase/separator, unchanged form endpoints, correct logo/badge attributes, Phase
input and absence of House Number. Node tests compare each SVG path with its
installed outlined original and verify existing copy in both dictionaries.

`node scripts/run-public-ui-browser-checks.mjs` builds first and creates a newly
named **disposable SQLite** database under ignored testing storage. It runs only
the existing migrations against that empty test file—no new schema migration and
no live schema/data operation. It starts an owned hidden PHP process on a fresh
loopback port, never attaches to port 8000, uses a synthetic application key and
test-only database sessions, and disables real mail/broadcast delivery. No users,
officers or financial records are provisioned. The owned server stops in `finally`;
its database/logs/screenshots remain as private diagnostic evidence.

The seven browser cases per engine exercise 320/768/1440 layouts, distinct visible
SVGs, equal card widths/row heights, English/Filipino switching, light/dark
surroundings, scoped serious/critical Axe results, reduced/normal animation,
keyboard navigation, actual image loading, long text, registration role steps,
password feedback, unsaved fields and unsent file selection. An incomplete
registration is blocked by native validation; no application is submitted.

| Check | Actual result |
|---|---|
| Focused PHP regression | **23 passed / 175 assertions**, 48.33 seconds; landing, canonical authentication, registration availability and two new rendered-page checks. Evidence: `storage/framework/testing/public-ui-polish-focused.xml`. |
| Node tooling | **4 passed**, including both new icon/copy checks and existing static-fixture safeguards. |
| Build | Final Vite **7.3.7** build passed, 121 modules, **12.65 seconds**. Initial build also passed in 21.98 seconds. Existing unrelated hero-image runtime-URL warning remains. |
| Final cross-browser acceptance | **21 passed**, zero failures/skips/retries, **5.8 minutes**, serial Chromium/Firefox/WebKit (seven each). English/Filipino, 320/768/1440, light/dark, keyboard/unsaved-state and scoped Axe checks all passed. |
| Formatting and syntax | Full Pint, three PHP/Blade syntax checks, Node syntax checks for the runner/spec/tooling/content files and `git diff --check` passed. JSX is compiled by the passing production build. |
| Translation parity | Existing **1,198 keys** still match; zero parity errors and 41 existing review candidates. Dictionaries and service wording are unchanged. |
| Isolated-data and shutdown checks | Final synthetic database contains **zero users, homeowners, payments, dues obligations or certificates**. The owned PHP server was stopped by the runner; no existing application/database service was stopped. |

Final private evidence is under `storage/framework/testing/public-ui-1792772395283/`
(HTML report, screenshots and the empty synthetic database). Its `.last-run.json`
records success. Desktop services and mobile registration screenshots were visually
inspected. The run used `--max-failures=1`, but no failure limit was reached: all 21
cases completed. No automated serious/critical violations were found in the tested
services/authentication regions; this is not a whole-application accessibility certification.

Initial browser evidence remains under
`storage/framework/testing/public-ui-1792771999909/`: services exposed the genuine
eyebrow contrast defect; login tests initially expected a trailing slash that
Laravel legitimately omits from the root URL. The latter assertion now checks
the parsed URL's root pathname instead. No Axe/authorization/validation check
was disabled to conceal a failure. That initial run had **18 failed / 3 passed**
(nine repeated contrast cases, nine root-URL assertion cases and three successful
normal-motion/long-copy cases); it is not relabelled as a clean pass. A preliminary inline SVG-comparison command
also failed PowerShell quoting before execution; the file-based Node checks
replace it and pass.

Reproduction (requires installed Composer/npm dependencies and Playwright browsers):

```powershell
php vendor/bin/phpunit tests/Feature/PublicUiPolishTest.php tests/Feature/LandingPageTest.php tests/Feature/PhaseTwoAuthenticationTest.php tests/Feature/RegistrationAddressAvailabilityTest.php
npm.cmd run test:tooling
node scripts/run-public-ui-browser-checks.mjs --max-failures=1
php vendor/bin/pint --test
git diff --check
```

The browser runner rebuilds and initializes a fresh isolated database each time.
It accepts forwarded Playwright selectors such as `--project=chromium`; omitting
project selectors runs all three engines serially with retries disabled. It never
uses a running application on port 8000 or the configured live database.

## Boundaries and handoff

Application edits are limited to services presentation/data identifiers, its
scoped stylesheet, and the two authentication views. Existing service copy,
other S1 instances, language dictionaries, backend routes/actions, database
schema, permissions, password policy and account workflows are unchanged.

No new dependency, live migration, live data/preference update, document
regeneration, source approval, commit or push. Broader full-suite/native-engine,
authenticated account mutation and system/security acceptance are not inferred
from guest UI tests. Independent SEC-01 recovery remains a separate release gate.
