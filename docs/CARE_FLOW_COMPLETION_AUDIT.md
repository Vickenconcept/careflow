# CareFlow AI: Completion Audit

Audit date: 2026-11-11 (updated after the 2FA and storage-migration fixes). Scope: the whole codebase against `CARE_FLOW_PRODUCT_SPEC.md`, with a security, privacy and reliability pass. Automated suite: **262 tests, all passing, 0 skipped, 0 failing**. TypeScript: 25 errors, all pre-existing and unchanged.

"Verified" below means a passing automated test or a check I ran against the dev database. It does not mean tested against a live external provider.

## Defects found and fixed during this audit

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | **Critical** | Case and proposal references (`CF-2026-000001`) are generated per hospital, but the database required them to be unique across all hospitals. The second hospital's first enquiry would have crashed, and URLs would have resolved to the wrong hospital's case. | Uniqueness is now per hospital. Case URLs resolve only inside the hospital named in the URL. Tests cover two hospitals sharing a reference. |
| 2 | **High** | The "AI processing" switch and the patient's AI consent were recorded but never enforced. Documents were processed regardless. | AI now runs only if the hospital has AI on **and** the patient accepted AI consent. Otherwise the document goes to manual review with the reason shown. Checked both when queuing and when the job runs. |
| 3 | High | Preview could serve a stored file inline with whatever MIME type was saved, so a mislabelled HTML file could render in the app's origin. | Only PDF, JPEG and PNG render inline. Everything else is forced to download. Responses carry `nosniff` and `no-store`. |
| 4 | Medium | No browser security headers. Patient tracking links carry a token in the query string, which could leak through the Referer header. | Added `Referrer-Policy: no-referrer`, `X-Frame-Options`, `nosniff`, `Permissions-Policy`, HSTS on HTTPS, and `no-store` on portal pages. |
| 5 | Medium | Outbound communication logs contained full phone numbers, email addresses and message text. | Contact details are masked and message text is no longer logged. |
| 6 | Medium | Anyone could register an account. | `CAREFLOW_ALLOW_REGISTRATION=false` restricts sign-up to people holding a pending staff invitation. |
| 7 | Low | Repeated document opens flooded the access log. | Identical opens within a minute are recorded once. Downloads are always logged. |

## New in this round

- **Per-organization document storage** (admin page "Document storage"):
  - CareFlow private storage (default).
  - The hospital's own S3-compatible bucket (AWS S3, Cloudflare R2, MinIO, Wasabi). Needed the `league/flysystem-aws-s3-v3` package.
  - The hospital's own Cloudinary account, stored as private "authenticated" assets. Implemented directly against Cloudinary's API with signed requests, so no extra package was needed.
  - Credentials are encrypted at rest and never sent back to the browser.
  - Saving runs a write, read-back and delete test first.
  - Custom endpoints must be `https` and resolve to a public address, which blocks server-side request forgery against the platform's own network.
  - Switching to another bucket or account, or back to platform storage, is blocked while documents still depend on the old one, so none are orphaned.
  - Files are always streamed through the app after a permission check. Storage URLs are never exposed.
- `php artisan careflow:preflight` checks production readiness and exits non-zero on blockers.
- **Two-factor authentication** (authenticator app, recovery codes, sign-in challenge, optional enforcement).
- **Move existing documents** between CareFlow storage and the organization's own storage.
- Earlier this session: saved views, bulk assignment, data retention, patient export and erasure, logo upload, translation sign-off, viewer zoom, platform plans, seat limits, module locks and suspension.

## A. Fully implemented and verified (automated tests)

- Multi-hospital organizations and tenant isolation (cross-hospital document, task and case access returns 403 or 404).
- Staff roles and permissions. Doctor, nurse, coordinator and viewer scoping.
- Public intake in five languages, with consent records and tracking token.
- Patient portal: status, document upload, replies, appointments. Only approved, patient-safe data is shown.
- Case workspace, status workflow, assignment, saved views, bulk assignment, CSV export.
- Document upload to private storage, access logging, review, correction, confirm and translation sign-off.
- Doctor queue, clinical observations, recommendations. **Approval is gated by the clinical-approve permission (doctor and owner only); AI code paths never write approvals.**
- Treatment proposals with approval and patient response.
- Appointments, travel, follow-ups, tasks, notifications and workflow automation, with queue retries and unique jobs.
- Audit log, document access log, retention policy and purge, patient export and erasure.
- Module switches, notification switches, workflow switches.
- Platform admin: plans, locked modules, seat limit, suspension, usage, impersonation.
- Organization storage (platform, S3-compatible config, Cloudinary), logo upload.

## B. Implemented but not adequately tested

- **S3-compatible storage against a real bucket.** The configuration, validation and SSRF checks are tested. A live read/write test needs real credentials, so it was not run.
- **Cloudinary against the real service.** Request signing, private upload type and streaming are tested with simulated responses only. Run one real upload with a test account before relying on it.
- **Real AI provider output.** Verified live on 2026-11-11 with synthetic documents: a Turkish text report (language detection, translation, extraction, summary) and an English report as an image (vision reading). Each took 25 to 30 seconds. PDF input has not been tried live. The app now ships only the OpenAI provider; test doubles live in `tests/Support` and are never loaded by the running app.
- **Email delivery.** Notifications are tested, but the mailer is set to `log` in the dev environment.
- **Management dashboard numbers** are covered by metric tests. Reconcile them by hand against a known data set before the demo.
- **Mobile and RTL (Arabic) layouts.** Checked in the browser for the portal only.

## C. Partially implemented

- **Communications:** email is sent through the configured mailer. WhatsApp entries record a conversation held outside CareFlow; CareFlow never pretends to send them.
- **Retention:** policy and manual purge exist. Nothing deletes documents automatically on a schedule, by design, so a person must press the button.
- **Roles:** fixed in code. A read-only matrix is shown, but hospitals cannot define custom roles.
- **Workflow and status configuration:** the workflows can be switched on or off, but their rules cannot be edited in the UI.
- **Subscription plans:** recorded by hand. No prices or payments.
- **Translations (tr, ar, ru, az):** written without a native review.
- **Document viewer:** zoom and page stepping work, but there is no total page count.
- **Storage migration:** documents can be moved to or from the organization's own storage in the background (checksum-verified, original removed only after the copy is verified). Large moves have not been load-tested.

## D. Missing

- Stripe billing and invoicing (awaiting your pricing decision and approval for the package).
- WhatsApp Business integration.
- Hospital information system (HIS/HBYS) integration. Staff type the hospital booking reference by hand; it is stored as entered and never marked as electronically verified.
- Single sign-on.
- Scheduled backups and restore procedure (infrastructure, outside the app).
- Application monitoring and alerting.

## E. Security and privacy risks (open)

1. **Two-factor authentication is built but optional by default.** Staff can set it up with an authenticator app and recovery codes. Set `CAREFLOW_REQUIRE_2FA=true` to make it mandatory; preflight treats that as a blocker.
2. **Cloudinary is a media CDN, not a medical-records store.** Private delivery is used, but confirm with Kolan's compliance team that it is acceptable. A private bucket in their own region is the safer choice.
3. **Patient tracking token is in the URL.** Mitigated by the no-referrer policy and no-store caching, but the link behaves like a password. Anyone who forwards it gives access. Shorten its lifetime if needed (`CAREFLOW_PORTAL_TOKEN_TTL_DAYS`, default 90).
4. **AI and real patient data.** Sending real documents to OpenAI needs a data processing agreement and Kolan's approval. AI is off by default for new hospitals and needs both the hospital switch and the patient's consent.
5. **Erasure from external storage fails loudly** if that storage is unreachable. This is intentional, but an administrator must retry.
6. **Login throttling is Fortify's default.** There is no account lockout notification.

## F. Production deployment blockers

Run `php artisan careflow:preflight`. In a typical first deployment these are the blockers:

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`.
- A real mail provider (the dev setting only logs emails, so patients and staff would receive nothing).
- A queue worker running continuously (documents and notifications are processed in the background).
- A real AI provider key, if AI is to be shown.
- `CAREFLOW_ALLOW_REGISTRATION=false`.
- Backups of the database and the document storage, and a tested restore.
- `CAREFLOW_REQUIRE_2FA=true` (two-factor is built; it just has to be switched on for everyone).

## G. Recommended next steps, ranked

1. **Kolan demo rehearsal** with the demo data, using a fresh hospital account to prove tenant isolation and the new-hospital setup (enable AI, set storage, invite staff).
2. **Make two-factor mandatory** in production and have every demo account enrol once.
3. **Live storage test** with a real S3-compatible bucket and a real Cloudinary test account.
4. **Real AI test** with synthetic medical documents, reviewing output quality and the human-review steps.
5. **Deployment hardening:** run preflight until clean, configure mail and queue, set up backups.
6. **Kolan discovery:** ask for their HIS vendor, WhatsApp preference, data residency and compliance requirements before building integrations.
7. **Stripe**, once pricing is decided.
8. Custom roles and a workflow editor.
