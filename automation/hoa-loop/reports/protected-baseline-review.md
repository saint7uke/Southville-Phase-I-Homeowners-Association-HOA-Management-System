# Protected Test Baseline Review

Date: 2026-09-09  
Status: Approved by the project owner; `baseline.json` was updated to the four reviewed hashes on 2026-09-09.

## Conclusion

The four protected test files whose hashes changed were strengthened to cover new security and functional requirements. No assertion was removed, skipped, loosened, or replaced with a weaker outcome. The two remaining protected files are byte-for-byte unchanged.

| Protected file | Baseline SHA-256 | Current SHA-256 | Review |
| --- | --- | --- | --- |
| `tests/Feature/AccessAndApiTest.php` | `9498c487a13f4ef45c10165f71d94ccce444a2176ff2049b2f655dbffd4017ea` | `a3f47d037b99ac9d4ccdf149d606172ff8391b10961924073c0e499113a01334` | Strengthened |
| `tests/Feature/LandingPageTest.php` | `880e89aa1c4f5e7546539e14313a420d03686c0392e55b76b45eedc8ea7c1c85` | `635a21253a2a7dadb35fe18911a2e3c9c7798c7da60398309b4f7c4367ee9510` | Strengthened |
| `tests/Feature/PortalWorkflowTest.php` | `03c128197b66e7d7ccdc72f99e4fdcd8b7bf0397ff6a4d3d4de461595558b05a` | `9c0325efc783df535d076315cebf872f6b995e4bc178081d705e6034f403cd06` | Strengthened |
| `tests/TestCase.php` | `c7c209f5579c42647c1f7b2a79ac828e8b54ac62a98442045a6e94fa2c0d0ebc` | same | Unchanged |
| `tests/Unit/ExampleTest.php` | `ffacae5c1a2a44c809420af5b3b404910da1b12167d58764dac6d10b8ba8cfaf` | same | Unchanged |
| `tests/Unit/RecordPaymentTest.php` | `1c404837c4a520a084d7940444ef1c0d4f97909f87f61365561f0cef0311869f` | `733a694ca7f744227276bf7d6898c5b6456a4c653e79a9bdb9496e0b48565ea8` | Strengthened |

## Change-by-change rationale

### AccessAndApiTest

- Adds proof that public announcement APIs exclude resident-only and expired records.
- Expands the Staff allow-list checks to announcements, contact inquiries, and dues settings.
- Proves Staff may create/edit dues settings but cannot delete them.
- Proves Staff cannot create homeowner-owned complaints or service requests.
- Changes the `/staff/users` expectation from `403 Forbidden` to `404 Not Found` because the Staff panel no longer registers that Admin-only resource. This reduces resource disclosure and is stricter, not weaker.

### LandingPageTest

- Adds public visibility tests for resident-only, expired, and scheduled announcements.
- Adds valid contact persistence plus honeypot rejection for web and JSON endpoints.
- Adds configurable branding and private logo-path non-disclosure coverage.
- Existing landing assertions remain intact.

### PortalWorkflowTest

- Updates the valid registration password fixture to satisfy the strengthened special-character rule.
- Adds a negative test proving passwords without the required composition are rejected and no account is created.
- Existing ownership and workflow assertions remain intact.

### RecordPaymentTest

- Seeds canonical permissions and gives the recorder the Staff role before exercising payment creation.
- This aligns the unit scenario with the production action's new authorization boundary; balance and status assertions remain unchanged.

## Verification supporting approval

- `composer qa`: passed, 128 tests / 514 assertions, with Pint.
- Focused report regression after the final UI focus patch: 13 tests / 53 assertions.
- Browser suite: Chromium, Edge, Firefox, and WebKit passed responsive, Axe, reduced-motion, authenticated report keyboard-focus, and live Admin/Staff/Homeowner isolation coverage.
- Composer audit: no security vulnerability advisories.
- npm audit: 0 vulnerabilities.
- `git diff --check`: passed.

## Approval effect

The project owner approved updating only the four changed SHA-256 values in `automation/hoa-loop/baseline.json`. No test or verifier logic was changed as part of the approval. The approval does not authorize deployment, sending real email, or modifying production secrets.
