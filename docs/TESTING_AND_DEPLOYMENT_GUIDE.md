# CareFlow: Testing and Deployment Guide

Last updated 2026-11-11. Read this together with `CARE_FLOW_COMPLETION_AUDIT.md`.

## 1. Where the app stands

- **Suite:** 266 automated tests, all passing.
- **Live AI check:** done on 2026-11-11 with synthetic documents, using your OpenAI key: Turkish text, an English report as a photo, and an English PDF (about 25–30 seconds each).
- **Public landing page** at `/` explains the product and links staff to sign in.
- **No simulated providers left in the running app:**
  - **AI:** OpenAI only. The test doubles live in `tests/Support` and are never loaded outside the test suite.
  - **WhatsApp:** record-only. Staff log a conversation that happened on their phone; CareFlow never claims to have sent it.
  - **Hospital booking system:** no live connection. Staff type the booking reference from the hospital system; it is stored exactly as entered and never shown as electronically verified.
  - **Demo hospital:** the seeder refuses to run in production.

## 2. What still has to happen before a real pilot

| # | Item | Who | Why |
|---|---|---|---|
| 1 | Configure a real mail provider (`MAIL_MAILER`, e.g. SES, Postmark, Mailgun or SMTP) | You | Emails to patients and staff are only written to the log right now |
| 2 | Run a queue worker permanently (`php artisan queue:work --tries=3`, kept alive by Supervisor or the host) | You / host | Document AI, emails and automations run in the background |
| 3 | Run the scheduler every minute (`php artisan schedule:run`) | You / host | Cleans expired invitations |
| 4 | Production settings: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` | You | Security |
| 5 | `CAREFLOW_REQUIRE_2FA=true` and `CAREFLOW_ALLOW_REGISTRATION=false` | You | Only invited staff, all using two-factor login |
| 6 | Daily backups of the database **and** the document storage, plus one test restore | You / host | Medical records cannot be lost |
| 7 | One live upload against the storage Kolan will use (own S3 bucket or Cloudinary), if not CareFlow storage | You + Kolan IT | Only tested with simulated responses so far |
| 8 | A data processing agreement with OpenAI, and Kolan's approval before real patient documents go to AI | You + Kolan | Legal requirement for health data |
| 9 | Native-speaker check of the Turkish, Arabic, Russian and Azerbaijani patient texts | Translator | They were written without a native review |
| 10 | Uptime and error monitoring (e.g. Sentry, Better Stack) | You | Know when something breaks |

Run `php artisan careflow:preflight` on the server. It names every blocker and exits successfully only when none remain.

## 3. Deploying (any Laravel host, e.g. Laravel Cloud or Forge)

1. Create the database (MySQL or PostgreSQL recommended for production; SQLite is fine for a small pilot).
2. Set the environment variables:

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://careflow.yourdomain.com
   APP_KEY=                # php artisan key:generate (keep it safe: it encrypts storage credentials and 2FA secrets)
   DB_CONNECTION=...
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
   SESSION_SECURE_COOKIE=true
   SESSION_ENCRYPT=true
   MAIL_MAILER=...         # real provider
   MAIL_FROM_ADDRESS=...
   CAREFLOW_AI_PROVIDER=openai
   OPENAI_API_KEY=...
   CAREFLOW_REQUIRE_2FA=true
   CAREFLOW_ALLOW_REGISTRATION=false
   ADMIN_EMAILS=you@yourdomain.com
   CAREFLOW_CONTACT_EMAIL=pilots@yourdomain.com   # "Request a pilot conversation" on the landing page
   ```

3. Deploy commands:

   ```
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

4. Start the queue worker and the scheduler (table above, items 2 and 3).
5. Run `php artisan careflow:preflight`.
6. Sign in with an `ADMIN_EMAILS` address, create the Kolan organization, and invite the first hospital admin.

**Never change `APP_KEY` after go-live.** It encrypts storage credentials and two-factor secrets, so changing it locks everyone out of both.

## 4. Setting up a new hospital (about 15 minutes)

All of this lives on one page: **Organization**, which has a tab bar along the top.

1. **Profile & branding:** legal name, contact details, timezone, currency, languages, colours, logo. Switch **AI processing on** here; it is off for new hospitals.
2. **Staff:** invite coordinators, doctors and nurses with the right role.
3. **Departments:** add departments in the dialog (cardiology, orthopaedics, IVF…).
4. **Modules & emails:** switch off any module the hospital will not use, and choose which emails are sent.
5. **Message templates:** adjust the patient email wording.
6. **Storage:** keep CareFlow storage, or connect the hospital's own bucket. Saving runs a live test first.
7. Share the public intake link `https://…/p/{hospital}/intake` on the hospital website.

## 5. End-to-end test script (one tester per role)

Use a fresh hospital account and synthetic documents only. Tick each step.

**Patient (phone, no login)**
1. Open the intake link and switch the language to Turkish, then back to English.
2. Fill in the form, attach a PDF and a photo, accept the three consents (including AI processing), and submit.
3. Check the confirmation page shows a reference and a tracking link, and that the confirmation email arrived.
4. Open the tracking link. Status should read "We received your enquiry".

**Coordinator**
1. The bell shows new work and the case appears under Cases → Unassigned.
2. Open the case and assign yourself and a doctor. The doctor receives an email.
3. Documents tab: AI badges move from processing to "needs review" within about a minute. Open a document, zoom, read the translation, correct a field, **Mark reviewed**, and **Sign off translation**.
4. Use **Ready for doctor review?** and press **Send to doctor**.
5. Save a filtered view and bulk-assign two cases.

**Doctor**
1. Sign in. You land on **My review queue**, showing only your cases.
2. Add an observation and a recommendation, then approve it.
3. Confirm you cannot see a case that is not assigned to you (open its URL directly and expect a refusal).

**Coordinator (continued)**
1. Proposal tab: create a proposal; the doctor approves; send it.
2. **Patient:** the tracking link shows the proposal and the patient replies.
3. Appointments: propose a slot and confirm it with the hospital booking reference (typed by hand).
4. Travel: fill in arrival and pickup.
5. Follow-up: schedule one, complete it, close the case.

**Admin**
1. Audit log shows every document opened.
2. Data & retention: export the test patient as JSON, then erase them. The case remains with the personal details removed.
3. Turn on your own two-factor login, sign out, and sign in with the code.

**Isolation check (two browsers)**
1. Create a second hospital with its own case.
2. A user of hospital A pastes hospital B's case URL and must be refused.
3. Both hospitals can have case `CF-2026-000001` without errors.

**Failure checks**
1. Stop the queue worker, upload a document, and confirm it stays "pending". Start the worker and confirm it completes.
2. Temporarily set a wrong OpenAI key. The document shows a clear error and **Run AI again** works once the key is fixed.

## 6. Who uses what

| Role | Lands on | Uses |
|---|---|---|
| Patient | Intake form, then tracking link | Status, upload documents, reply, see proposal and appointments |
| Coordinator | Dashboard | My work, Cases, Patients, Coordination; Organization (message templates, automations) |
| Doctor | My review queue | Assigned cases, documents, clinical evaluation and approval |
| Nurse | My review queue | Assigned or coordinated cases, observations (cannot approve) |
| Admin / owner | Dashboard | Everything, plus the Organization area |
| Management viewer | Dashboard | Read-only reporting |
| Platform admin | Platform admin | Hospitals, plans, seat limits, suspension, usage, impersonation |

## 7. Not built (do not promise)

- Stripe billing (waiting on your pricing decision).
- Sending WhatsApp messages (record-only today).
- Live connection to the hospital's own system (HIS/HBYS).
- Single sign-on.
- Custom roles and an editable workflow builder (roles are fixed in code; automations can be switched on or off).
