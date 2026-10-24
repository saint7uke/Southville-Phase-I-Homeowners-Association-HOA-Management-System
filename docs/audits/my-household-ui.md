# My Household UI and bounded member queries

Implemented 24 October 2026 for `/homeowner/my-household` only. Existing landing/authentication UI work is preserved.

## Changes

- Added a Filament household overview with registered address, household ID, current-member count, and a clearly labelled complete-summary PDF link.
- Provisional RBI guidance, HOA-review status, read-only guidance, unavailable membership, and empty member lists have separate presentation states.
- Replaced the plain table with Filament's responsive split-row table layout: Name, Relationship, and Residency Status. Rows stack below the `md` breakpoint; styles are scoped to `.hoa-household` and use existing light/dark design tokens.
- Automatic server-side pagination uses 10 members, with no pagination controls at 10 or fewer. No search/filter/management toolbar was added. Ordering is name then ID. Invalid pages are clamped; a changed household resets the page.
- Account status, verification, role, current membership, archived state, and resolved property identity are checked from current database records on subsequent requests. The remembered household ID is Livewire-locked; it is not used as authority for queries.
- Member queries select only internal row/household keys and the three authorized display fields; no birth dates, sex, or private contacts are loaded into the member table. Ended and archived memberships are excluded. `OwnHouseholdSummary::forUser()` retains its existing array shape and complete restricted member list for other consumers.
- New copy has matching English/Filipino keys. Explicit application-owned markers update labels and badges without translating names/addresses or refreshing the component. Pagination localization is opted in only by the household-member wrapper.
- Shared PDF routes, report/admin exports, financial records, and editing permissions are unchanged. The PDF continues covering the complete authorized household rather than the current page.

## Verification

- Initial existing household regressions: `PhaseThreeTest`, **11 passed / 78 assertions**.
- Focused household/navigation/localization checkpoint: **42 passed / 3,128 assertions**.
- Broader household/property/export/landlord/payer/navigation/localization checkpoint: **122 passed, three native-MySQL-only skips / 3,644 assertions**. Additional access-revocation cases and a final rerun follow this checkpoint.
- Initial browser failures are retained: the first exposed incorrect accessible-label targeting on Filament list-item wrappers; the next had an insufficient 5-second wait. The subsequent intercepted-request run recorded Chromium `ERR_NETWORK_IO_SUSPENDED` before requests reached PHP. The harness now waits for real Livewire responses without network interception; no product assertions were removed.
- Final SQLite regression selection: **125 passed, three native-MySQL-only skips / 3,655 assertions** (190.78 seconds). Selection: `MyHouseholdPageTest|PhaseThreeTest|DashboardNavigationTest|CommunityNavigationTest|ReleaseReadinessTest|ExportLetterheadTest|ConfirmedPropertyIdentityTest|TenantPropertyPaymentsTest|LandlordTenantManagementTest`. JUnit evidence: `storage/logs/my-household-ui-regression-20261024.xml`.
- New household-specific tests: **16 passed / 260 assertions**, including the additional role/verification/archived-resident checks and locked-context rejection. Early test adapters were corrected to the existing 403 behavior on role/resident revocation and the existing panel login redirect; permissions were not relaxed.
- Scoped Pint, four changed/new PHP syntax checks, three JavaScript syntax checks, `git diff --check` and all **five Node tooling tests** passed. Final production Vite build passed in **16.36 seconds**. The existing unresolved `southville-community-hero.webp` build warning remains outside this page's scope; no temporary server origin is embedded in the output.
- Cross-browser acceptance checkpoint: **nine passed**, no skips/retries, **8.1 minutes**, serial Chromium/Firefox/WebKit. Covered real login and Livewire paging, loading state, keyboard operation, locale persistence, complete PDF download, ten-member pagination hiding and unavailable membership. All six viewport/theme combinations per engine (320/768/1440, light/dark) had no horizontal overflow or serious/critical scoped Axe findings. Evidence: `storage/framework/testing/my-household-browser-test-1792776410152/` (HTML report, screenshots, logs, synthetic database). This run used the pre-refinement pager-label bundle.
- Visual review then found the native Next/Previous visible text could retain its previous language even though its accessible name was updated. The scoped locale handler now updates only the opted-in pager's direct label text, preserving its spinner/button. Final cross-browser paging rerun explicitly checking visible text and accessible names: **three passed**, no skips/retries, **2.2 minutes**, Chromium/Firefox/WebKit, against the final bundle. Evidence: `storage/framework/testing/my-household-browser-test-1792776961510/`. Both recorded acceptance servers are stopped. These three follow-up checks overlap the nine-scenario checkpoint; they are not 12 unique scenarios.

## Isolation, reproduction and limitations

Run `php artisan test --compact --filter=MyHouseholdPageTest` for the isolated SQLite feature tests. Coverage includes 1/10/11/25 members, stable duplicate-name ordering, archived/ended member exclusion, forged page size/context, out-of-range pages, membership moves, access changes, escaped content, restricted PDF data and a complete PDF dataset. An empty current-member table is simulated after household authorization to exercise the concurrent-empty state; a normal authorized account is itself a current member.

Run `node scripts/run-my-household-browser-checks.mjs` for serial Chromium/Firefox/WebKit acceptance. It creates a new `storage/framework/testing/my-household-browser-test-*/database.sqlite`, uses a guarded synthetic seeder, array mail, and an owned random-port server. It builds portable assets and checks that the temporary origin is not compiled into them. It never attaches to port 8000 or migrates/seeds the ordinary database. Screenshots/traces and the synthetic database remain in that ignored directory; its owned server is stopped afterward.

No new migration, live record repair, membership reassignment, dependency, source/document change, external notification, commit, push, or deployment. No full-system PHPUnit run, physical printing, or native MySQL concurrency rerun is claimed for this UI task. Passing scoped checks is not full-system certification; existing release/security/human-review gates remain separate.
