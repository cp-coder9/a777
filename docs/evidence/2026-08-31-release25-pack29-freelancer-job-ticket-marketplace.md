# Release 25 / Pack 29 freelancer job-ticket marketplace evidence

## Certified scope

- Functional source commit: `b0c14eba5a6359397a4653a133c871ad6426a07f`.
- Pack: `29. architex-freelancer-candidate-professional-pack`; all supplied files were read before implementation.
- Runtime: PHP 8.3.32, MariaDB 11.4 on reserved loopback port 43320, and the authenticated V1 route `http://127.0.0.1:8809/toolbox/standalone/freelancer-job-ticket-marketplace`.
- Boundary: local staged-release certification for PHP 8.3 / MariaDB shared hosting and FTP packaging. No FTP upload, remote backup/rehearsal, target-host migration, remote smoke test or promotion was performed.

## Implemented contract

- Public and direct job tickets have deterministic advisory classification, canonical SHA-256 evidence, human overrides, material clarifications, private quotation visibility and optimistic versions.
- Quotation selection is always human-confirmed. A selected registered BEP can recursively expand a governed ticket tree while the original project tenant and exact poster authority remain enforceable.
- Freelancer profiles publish only after current verification evidence is checked. Freelancer work creates a locked eligible-supervisor route and never claims an independent professional appointment.
- Timesheets, claims and payment evidence are persisted with explicit `recorded_evidence_not_bank_confirmation` wording and `payment_execution_claimed = 0`.
- Deliverables use immutable revisions. Only the exact current revision may be signed off, and external issue is rejected until that sign-off exists.
- Completion requires resolved claims and signed deliverables. Reviews require a completed engagement and are unique per reviewer.
- Authentication, membership, role, CSRF, direct-invite privacy, cross-tenant access, stale versions, human-confirmation gates and stable domain errors are enforced by the existing PHP kernel.

## Automated verification

- Complete functional suite before the final reviewed accessibility and expanded-authority remediations: **341 tests, 3,573 assertions, 0 failures, 0 errors, 0 skips**, completed in 20:26.695 against guarded disposable MariaDB database `architex_task24_a7770001_test`. The run rebuilt and exercised all 55 migrations.
- Final connected Release 25 service/schema/HTTP gate: **5 tests, 140 assertions**, completed in 03:41.871. Its expanded-ticket organisation-admin regression independently passed **1 test, 44 assertions** in 03:31.758 after first proving the prior `job_ticket_not_found` failure.
- Final frontend/route gate after Lighthouse remediation: **2 tests, 78 assertions**; the dedicated selected-state frontend contract is 1 test / 45 assertions.
- `node --check app/public/assets/js/job-ticket-marketplace.js`, PHP syntax checks and `git diff --check` passed.
- The full suite preceded the final reviewed remediations; their affected frontend contract and connected service/schema/HTTP gate were rerun green after those changes.

## Authenticated browser and MariaDB proof

Chrome DevTools MCP remained open throughout the release on page 1. The real UI used three authenticated accounts and three organisations to complete this lifecycle:

1. Client user `dae0d90735ca6e86dd2fce3dae8f4309` posted public root ticket `6915f0277c69b937f7981da28ba4484c` and human-selected registered-BEP quotation `a714adb19845dac78b39e4c4bb3c5ce9`.
2. Registered BEP `49f422850d0dad8163c297d5f352fc22` expanded the root to direct freelancer child `205e91aebc049eac1ad6cf5f85e3144a` and human-selected freelancer quotation `2557496c27409b76ad3b89866ad2f075cd63521f2165f00b9908933060f786bf` under its own locked `registered_architect` supervision route.
3. Freelancer `2e8e1bcab6117be7a1923e4b9e86545e` published a profile, submitted approved timesheet `c8b1f632c64f91bc3f01129b65ac00eb`, submitted claim `e38c13eb128bc7a8d533d9e4860d0978`, and produced two immutable revisions of deliverable `0d7a87cc77b588bc3a1d55bf89ddf739`.
4. The BEP approved the claim, recorded payer evidence `DEVTOOLS-EFT-25`, signed exact revision `9080db587a30e5fe46a8a94651b4b067`, issued it externally, completed engagement `9ea6153496d0333917264c253d9682bd`, and recorded one 5-star completed-work review.
5. A hard reload retained the published profile and the completed engagement with the approved time, `recorded_paid` claim boundary, externally issued revision 2 and closed route.

MariaDB independently reported:

- root status `expanding` with child status `completed`, direct invitation and preserved parent ID;
- engagement `completed` v2, `human_accepted = 1`, and supervision route `closed` with the exact supervisor user;
- claim `recorded_paid`, payment evidence SHA-256 present and `payment_execution_claimed = 0`;
- deliverable `externally_issued` v4 at revision 2 with one exact-revision sign-off;
- one completed-work review with rating 5.

Browser exploration exposed and fixed three production defects before certification: direct child poster access incorrectly depended on the parent tenant, refreshed paid claims omitted the persisted verification boundary, and the completed-review button lacked its global action binding. Inline completion review then extended the child-poster correction to clarifications, advisory overrides, non-freelancer quotation acceptance and organisation-team read/quotation-register access, including the delegated child project record.

## Responsive, console and accessibility evidence

- Desktop 1920 x 897: document width 1920px and no horizontal overflow.
- Mobile 390 x 844: document width 390px and no horizontal overflow.
- Clean authenticated freelancer reload: zero JavaScript errors and zero failed application requests. Chrome retained generic form-field and PWA-install advisories from the broader shell.
- Selected completed-engagement Lighthouse snapshot: Accessibility 100, Best Practices 100, SEO 100 and Agentic Browsing 100; **30 passed, 0 failed**.
- The first audit caught low contrast; the second caught invalid tablist child semantics. Both were fixed, and the inline completion review then removed the resulting inert tab controls in favour of honest non-interactive category labels. The selected-state audit was rerun to 100/100/100/100.
- Screenshots: `screenshots/release25-job-ticket-marketplace-desktop.png` and `screenshots/release25-job-ticket-marketplace-mobile.png`.
- Lighthouse: `release25-lighthouse/report.html` and `release25-lighthouse/report.json`.

## Staged shared-hosting artifact

- Directory: `dist/architex-release-staged-20260831-b0c14eb`.
- FTP ZIP: `dist/architex-release-staged-20260831-b0c14eb.zip`.
- ZIP bytes: **1,244,006**.
- ZIP SHA-256: `A4415BFB7EBC84432EA40F3FB71859819B863C01BC8CA225C2CD08BA23F93323`.
- `release.json` records PHP 8.3, `php-shared-hosting`, version `staged-20260831-b0c14eb` and exact source commit `b0c14eba5a6359397a4653a133c871ad6426a07f`.
- Built directory and independent fresh extraction `dist/ftp-redownload-verification-b0c14eb` each passed the release verifier for **372 payload files**; each has 373 total files including `release-manifest.json`.
- Fresh extraction linted **293 PHP files with 0 failures** and contains migration `0055_freelancer_job_ticket_marketplace.sql`, marketplace JavaScript/CSS and `public/.htaccess`.

## Pack disposition and remote boundary

- Pack 29 was moved to `packs/Completed/29. architex-freelancer-candidate-professional-pack` outside Git.
- Its untouched source archive remains at `packs/ZIP/29. architex-freelancer-candidate-professional-pack.zip`.
- Remaining unattended packs are 30, 31 and 32.

This is a locally certified staged artifact suitable for the approved FTP workflow. FTP transfer, shared-host backup/rehearsal, remote MariaDB migration, SMTP/provider setup, remote authenticated smoke tests and staged promotion remain pending and must not be represented as complete.
