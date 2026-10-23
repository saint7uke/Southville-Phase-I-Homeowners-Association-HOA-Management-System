# Localization coverage and review boundary

## Scoped export guidance — 2026-10-23

The admin-only letterhead export adds five English/Filipino UI keys (Excel action, image description and safe unavailable/return guidance). Including the earlier SC-07 message, dictionaries now have **1,198 matching keys**, zero parity errors and 41 existing inventory candidates. Original association artwork, certificate/receipt wording and user-entered export values are not translated. [Scope and checks](export-only-cohabitation-letterhead.md); these engineering translations are not Filipino editorial sign-off.

## Audit remediation — 2026-10-22

SC-09 literal missing-key defects are repaired for `Close`, `Download`, `Certificate`, document integrity/setup failures, and the new complaint/schedule errors. Both dictionaries contain **1,192 matching keys**, with zero parity differences. Rendered Filipino control text, shared validation messages, placeholder parity and unchanged resident content have regression coverage. Values remain engineering translations awaiting Filipino editorial sign-off.

The source scanner now reports **41 candidates**, not 41 confirmed UI defects: two language autonyms, nine association/property branding or fixed-prefix occurrences, eight unused Laravel welcome-example strings, and 22 fixed English PDF wording/metadata occurrences (three certificate, 16 detailed-receipt, three report). Original certificate/receipt designs and stored documents are deliberately unchanged. Report and detailed-receipt presentation wording needs contextual review before a broader print-language change; it is not silently rewritten by this audit. Arbitrary report cells, resident names/remarks, stored statuses and original-document text must not be bulk-translated.

Use `npm.cmd run test:browser:static` for the navigation/request-guidance acceptance fixtures: it rebuilds before exporting, then rejects missing/stale asset references before Playwright starts. This closes TEST-01's stale-hash workflow defect without weakening browser assertions. Final combined run: **15 passed** across Chromium/Firefox/WebKit, no retries. The initial WebKit timeout and separate recheck are retained in [the remediation evidence](sc-remediation-20261022.md); these are not editorial or full authenticated-form acceptance.

## Compact global language control — 2026-10-22

The user selected a **compact dropdown** with visible **English / Filipino** options.
The previous shared control wrapped inside a nine-rem width, stacked a prominent Language
label above the selector, and forced a white background in dark panels. Those inline styles
are replaced by one scoped stylesheet loaded through the existing locale Vite entry.

Implemented:

- Native select with 14px text, a 44px touch target, decorative globe/chevron, keyboard focus
  outline, theme-aware text/surface/border colors and a visually hidden Language/Wika label.
  Existing `#hoa-locale`, data attributes and polite status region remain compatible.
- Admin/staff/homeowner placement uses `USER_MENU_BEFORE`, aligned beside the avatar rather
  than appended after it. Guest Filament password pages use their simple-layout hook.
  Login, registration, password recovery/reset, status cards and the legacy portal use the
  same Blade control in header utilities; missing CSRF metadata and hardcoded HTML locale
  attributes on those screens were corrected without changing authentication rules.
- The public React navbar uses a small adapter with the same CSS and delegated handler.
  The extra standalone homepage control is removed. Mobile language access does not require
  opening the navigation menu; branding is constrained and navigation collapses before crowding.
  Legacy portal navigation wraps into a second row on narrow screens, not the language control.
- Intentionally light registration/status cards keep a light control. Other auth cards follow
  their existing preferred-color-scheme styling, and panel controls follow the panel theme.
- A stable busy state/spinner does not resize the control. Pending choices survive observer
  updates, failures restore the previous selection, and wrapper-relative status lookup plus
  a single runtime initializer prevent duplicate requests after navigation/module loading.
- `POST /locale`, `en`/`fil`, CSRF, throttling, stored preferences, existing translations,
  user content and no-reload behavior remain unchanged. There is no migration or new dependency.

Verification and evidence:

- Final focused SQLite regression: **99 passed / 3,009 assertions**, 101.150 seconds
  (`storage/logs/global-locale-acceptance-20261022.txt`), covering rendered controls, all three
  guards, guest/account persistence, invalid/inactive rejection, status/recovery pages,
  localization, profile, authentication, panel and portal regressions. No real preference was
  changed. Initial test-only failure assumed an exact account-menu class string; its assertion
  now uses the existing trigger class and still verifies ordering and one control per page.
- Three-file Pint, PHP syntax, JavaScript syntax and scoped diff checks passed. Production
  Vite build passed in **16.88 seconds** (`global-locale-build-verified-20261022.txt`). The
  existing missing `southville-community-hero.webp` warning is unrelated and remains open.
- The isolated application-server/SQLite-fixture setup command was **blocked by tool policy
  before execution**. No server/database was created or production account used. The guarded
  `GlobalLocaleBrowserSeeder` and opt-in `global-locale-switcher.spec.js` retain eight full
  workflow cases for a future authorized isolated run, including real sign-in, preference
  persistence, temporary uploads and open-modal state. Those workflows were **not browser-
  verified** in this increment; backend coverage does not substitute for that missing coverage.
- A separate, narrower **server-free component suite** reads synthetic HTML exported only
  by the in-memory SQLite tests and loads the built assets using Playwright interception.
  No HTTP application server is started; all other network requests are aborted and locale
  responses are mocked. It checks real Blade/React markup and locale JavaScript, not live
  authentication, backend persistence, Livewire uploads or modal hydration.
- The first Chromium component run passed three cases and failed the dashboard theme harness:
  static snapshots have no active Filament theme controller. The component suite now sets the
  existing root theme class directly to test real theme CSS; the separate full-workflow suite
  retains the real theme-event assertion. No product authorization or financial assertion was
  removed. Initial evidence remains in `global-locale-static-chromium-20261022.txt` and
  `storage/framework/testing/global-locale-chromium-20261022/`.
- Component results across the three engines: **Chromium 4/4, WebKit 4/4 and Firefox 4/4**.
  Checks cover 320/768/1440 widths, 14px text, computed 44px minimum targets, one control,
  avatar alignment, light/dark styling, public menu/keyboard dismissal, loading dimensions,
  failure recovery, unsaved browser credentials and one request per selection after navigation
  events. Scoped Axe checks reported no serious/critical violations in the tested controls/nav.
  Screenshots of the dark header and public mobile navbar were also visually inspected.
- The initial combined run had **11 passed / 1 failed**, 5.3 minutes: Firefox reported a
  geometrical height of `43.99999237060547` for a computed 44px target. Tests now require
  computed `min-block-size: 44px` and round the measured box to hundredths before comparing.
  No production touch-target size was reduced. The four-case Firefox rerun passed without
  automatic retries. Logs: `global-locale-static-all-browsers-20261022.txt` and
  `global-locale-static-firefox-final-20261022.txt`; artifacts use separate private directories
  under `storage/framework/testing/global-locale-static-*-20261022/`. The same four scenarios
  exercised across engines are twelve component checks, not twelve distinct end-to-end flows.
- The full PHPUnit suite, native MySQL, real browser login/persistence, FilePond upload
  preservation and live modal hydration were not run for this increment. No database schema
  changed. Existing PHP profile/authentication coverage passed but does not close those browser
  gaps. The narrower component checks are not presented as full application acceptance.

Reproduce component-only checks after building assets (PowerShell; synthetic SQLite only):

```powershell
npm.cmd run build
$env:HOA_BROWSER_EXPORT_LOCALE_FIXTURES = 'true'
php vendor/bin/phpunit tests/Feature/LanguageSwitcherTest.php
Remove-Item Env:HOA_BROWSER_EXPORT_LOCALE_FIXTURES
$env:HOA_BROWSER_STATIC_LOCALE_FIXTURES = 'true'
$env:PLAYWRIGHT_BASE_URL = 'http://locale-switcher.test'
npx.cmd playwright test tests/Browser/global-locale-static.spec.js --project=chromium --project=firefox --project=webkit --workers=1 --retries=0
```

The virtual `.test` URL is intercepted by the test; no service or DNS setup is required. This
does not run the separately guarded full-workflow browser suite. Keep its database private
and disposable if scheduling that remaining acceptance work; never point it at `hoa_system`.

This increment does not expand translation vocabulary, claim Filipino editorial approval,
change official documents, run live migrations, deploy, or close the independent security
release gate. Full application/browser acceptance remains distinct from component checks.

Prepared 2026-10-20; registration/Phase/Resibo, certificate-interface and uploaded-PDF continuations verified 2026-10-21. English/Filipino dictionaries currently contain **1,090 matching keys**, with no key-parity differences. Values are engineering drafts awaiting the named Filipino reviewer described in `hoa-approval-package.md`.

The latest increment adds registration Phase guidance, property/legacy-assessment review feedback, original receipt setup/preview/generation and payment-provenance messages. Registration language switching retained unsaved Phase, password and upload in Chromium, Firefox and WebKit. Stored statuses, financial values, user content and the original document wording remain unchanged. These bounded checks are not a claim of complete translated copy or editorial approval.

Follow-up: report status options now keep canonical stored values while translating only their labels. The report placeholders, All scope label and summary labels have additional localization coverage. Lowercase complaint/request types actually emitted by the workflow observer now translate in notification subjects and bodies. User content, report row values, financial calculations and export schemas remain unchanged.

## Implemented

- Public React sections, navigation, contact feedback, registration real-time availability/password/lease feedback, profile/password feedback, shared submission validators, and representative panel forms/actions use shared translations.
- Filament option labels and selected workflow-status columns translate presentation while retaining stored values. Notification templates use placeholders so names, remarks, references, historical certificate labels and arbitrary message content remain unchanged.
- Explicit Blade markers update static copy without translating resident content. The current Filament 4 field-label selector is supported. Profile feedback removed by an unrelated Livewire morph is restored without retaining field values or remounting the form.
- Dictionary parity and placeholder preservation, shared validation-message translation, recipient preference, notification content preservation and localized contact persistence have automated coverage.

## Reproducible inventory

Run `php scripts/audit-localization.php` for JSON candidates with file/line references. `--strict` fails while any candidates remain. The scanner is deliberately conservative: default labels, interpolated messages, content arrays, notification composition, dynamic report headings and library-rendered controls still need manual review. A zero result would not establish complete localization.

Known exclusions require explicit review rather than blind replacement:

- Association/property names, product names, language autonyms and identifiers remain unchanged.
- Official Residency/receipt wording and stored documents are not rewritten as a side effect of language changes. Any revised official wording requires HOA template approval. Draft presentation/watermarks may be translated; the approved template body is content, not UI copy.
- `resources/views/welcome.blade.php` is the unused framework example, not the React homepage. Its tutorial copy is not product acceptance coverage.
- Application-owned metadata around reports is being translated separately from dataset cells. Arbitrary resident subjects, names, addresses, template bodies and audit payloads must never be passed through bulk DOM replacement.

## Remaining acceptance

Human review across every role and screen is pending. Dynamic/default labels, report summaries, complete no-reload modal/button/helper coverage and every custom validator are not claimed fully verified. Browser evidence must identify the engine, fixtures, viewport, workflow and exact result. Passing a single language-switch case is not full cross-panel acceptance.
