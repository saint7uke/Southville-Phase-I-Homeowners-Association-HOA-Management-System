# House Number retirement audit — 2026-11-09

## Behavior boundary

House Number is retired from current input, address presentation, searches and newly prepared output. Existing database values, legacy identity keys, memberships, financial snapshots, issued certificates and saved receipt files are not deleted or rewritten.

## Connection inventory

| Connection | Change / retained purpose |
| --- | --- |
| Shared Admin/Staff Household Add/Edit | Removed the remaining field; existing values come from the stored record, not submitted data. New households store null and reject nonempty crafted creation values. |
| Household save and errors | Preserve raw legacy value and stored identity; detect property moves without rekeying retired fields; conflict errors target Block. Head reviews, memberships and permissions remain unchanged. |
| Household synchronization | New household records do not copy an old resident house number. Their original authoritative identity key is retained for membership compatibility; unrelated saves preserve that key. |
| Legacy registered owner selector | Duplicate checks and Owner/Co-owner validation use the locked household's authoritative legacy key, not a key reconstructed after retiring House Number. Existing links remain selectable even when the new household stores null. |
| Homeowner/User/registration forms | Already had no House Number controls. Existing create boundaries retain their null/prohibited behavior. Homeowner edits ignore retired input; profile requests now ignore it consistently, including crafted arrays, while retaining other protected-address checks. |
| Current household/homeowner address | Shared current formatter omits House Number and Street. Summaries, current exports and dropdown labels inherit it. |
| Current searches | Removed House Number from household/homeowner table searches and resident/report searches; Block/Lot/Phase/name searches remain. |
| New financial snapshots | Shared snapshot producer removes retired presentation fields only after calculating identity from the authoritative full record. New accepted payment snapshots and explicit missing-receipt-snapshot confirmation use it. Existing immutable snapshots are untouched. |
| New request/certificate preparation | Cohabitation request snapshots, preparation, Residency token context, direct approved draft endpoint and issuance snapshots omit House Number. Changed preview content naturally invalidates old unissued tokens; approval/checksum/source/year/actor checks are not bypassed. |
| Historical documents | Historical PropertySnapshot display and Cohabitation values retain compatibility with previously saved snapshots. Saved issued files/download routes are unchanged. No official document regeneration is part of deployment. |

## Remaining source references — intentional compatibility

- `PropertyAddress` normalization/key calculation and `PropertyIdentity::address` remain available for legacy keys and historical snapshot rendering. Do not remove House Number from these functions or regenerate stored keys.
- Homeowner/Household model attributes, homeowner saving hooks, integrity-audit comparisons and the existing nullable column are retained. They are not editable controls or exposed current search fields.
- `HomeownerProfileValidator` retains the protected record's stored house value internally for identity validation. `UpdateProfileRequest` restores it from the authenticated record; stale/crafted input cannot replace it.
- Homeowner form landlord option matching retains the record's authoritative legacy fields for unconfirmed identities. Confirmed matching continues to use Phase/Block/Lot. No field is rendered for House Number.
- Existing creation endpoints/actions retain explicit prohibited checks or null assignment. This is rejection of retired payloads, not an input requirement.
- Historical Cohabitation rendering and `PropertySnapshot::display` intentionally still understand old house-number-bearing snapshots. Historical receipt/certificate bytes are never rewritten.
- Old migration definitions and regression fixtures retain House Number as historical data. No destructive cleanup or new migration is planned.

## Verification record

Local tests use isolated databases and synthetic records. HTTP notifications/providers are faked where applicable. Results, skips and deployment evidence are finalized in the deployment report; no production account changes or notification sends are used for verification.

- New coverage: legacy/null/crafted household edits, preservation of head/member data and identity, null creation, visible conflict errors, legacy-key-without-copied-house compatibility, current-versus-historical snapshots, Admin/Staff dialogs and retired-field report search.
- Browser coverage exercises actual household/head dialogs and ordinary saves, not only PHP action calls.
- Existing household/head, address, tenant/payment, receipt, report/export, profile and RBAC suites are run. Certificate suites retain real-font prerequisites; unavailable Cohabitation font cases must be reported as skipped rather than passed.
- The broad PhaseThree run also exercises unrelated Projects behavior. An initial published-project image check returned 404, then passed unchanged when rerun alone (one test / nine assertions). No project storage or assertion change is included.

## Rollout

Deploy the reviewed manifest plus the matching full build only after local checks. Back up affected source/database state, verify nullable columns and production differences, take the existing SMS maintenance/worker locks during installation, rebuild Laravel configuration/Blade caches, and perform read-only HTTP/source/schema checks. Preserve `.env`, `.htaccess`, SMS settings, cron commands, current queued/failed work, household 2 and request 5. No new migration, issuance, queue processing or historical backfill.

## Local results

- Main household/head/address, receipt, certificate-preview/issuance, tenant payment and historical snapshot run: 187 tests / 1,131 assertions, no failures, 15 Cohabitation real-font prerequisite skips. This run contained the original six retirement cases; a later seventh case verifies retired-field report search.
- Final receipt, homeowner-profile, retirement and Residency run: 76 tests / 509 assertions passed, including explicit new snapshot omission and forged profile input preservation.
- Reporting/export, confirmed identity, dues/payment, landlord, homeowner summary and role/permission run: 88 tests / 721 assertions passed.
- Focused PhaseThree household movement, summaries, dry-run backfill, archival permission and visible conflict errors: five tests / 38 assertions passed.
- JavaScript tooling: 14 tests passed. Production Vite build and targeted Pint/whitespace checks passed. Existing public-image build warnings are unchanged.
- The initial retirement assertions compared partially hydrated newly created model attributes against fresh database rows; corrected the fixtures to compare fresh complete rows. A separate fixture also reused a unique legacy address key; corrected it to use distinct lots. No preservation assertions were weakened.
- Browser form selectors target the accessible dialog's child controls. Filament's display-contents dialog container can have no bounding rectangle despite visible controls; visibility is asserted on the actual textbox/button controls. Existing head-review scenarios already passed; the expanded Admin/Staff ordinary-save run is recorded in the deployment report.
- No new production mail/SMS delivery was tested or claimed. No local font files are included in deployment.
- The initial overlapping PHP runs also produced a transient PDF-source setup error; a subsequent combined document run completed without errors. Shared test storage should be verified in nonoverlapping runs; no certificate source/validation checks were disabled.
- Final focused retirement coverage: eight tests / 46 assertions passed, including the linked-legacy-owner regression. No stored key is regenerated to make this test pass.
- Expanded Chromium coverage passed all five cases, including Admin/Staff ordinary dialog saves and the existing head-assignment/replacement/clearing flows.

## Deployment evidence

The separately requested SSH rollout is recorded in [the deployment report](../deployments/house-number-retirement-2026-11-09.md). Existing production data and notification queues are preserved; no migration or historical reconciliation is performed.
