# CareFlow AI — Development Progress

**Source of truth:** `docs/CARE_FLOW_PRODUCT_SPEC.md`  
**Last updated:** 2026-11-10 (Slice 2F + ADMIN_EMAILS impersonation)

---

## Milestone 0 — Laravel AI installed + repository inspection

### Completed

- Installed `laravel/ai` v1.3.0.
- Published AI config (`config/ai.php`), stubs, and conversation migrations.
- Ran migrations for `agent_conversations` and `agent_conversation_messages`.
- Added optional AI key placeholders to `.env.example` (all null; mock-only for Phase 1).
- Provider API keys are **not** configured; Phase 1 demo AI will use a mock provider (no paid credentials required).

---

## Repository inspection report

### 1. Current Laravel / PHP / frontend versions

| Layer | Version |
| --- | --- |
| PHP | 8.3.33 |
| Laravel | 13.40.0 |
| Inertia Laravel | 3.7.0 |
| Fortify | 1.41.0 |
| Wayfinder | 0.1.23 |
| Pest | 4.7.8 |
| React | 19.2.0 |
| `@inertiajs/react` | ^3.0.0 |
| Tailwind CSS | ^4.0 / 4.1.11 |
| Vite (vite-plus) | 1.4.0 |
| TypeScript | ^5.7.2 |
| Node | 26.12.0 |
| Laravel AI SDK | 1.3.0 |

**Note:** The product spec mentions Vue. This repository is **Inertia + React**, not Vue. Implementation continues with React.

### 2. Existing authentication and authorization

- **Auth:** Laravel Fortify (login, registration, password reset, email verification, 2FA columns present).
- **Session:** database driver.
- **Teams:** membership via `team_members` pivot with role string.
- **Roles (Slice 1A):** `owner`, `admin`, `coordinator`, `doctor`, `nurse`, `management_viewer`, `member`.
- **Policies:** `TeamPolicy`, `DepartmentPolicy`.
- **Middleware:** `EnsureTeamMembership` on `{current_team}` routes.
- **Platform admin:** `users.is_platform_admin` exists; does **not** grant patient/org access by default.

### 3. Current database architecture

SQLite by default (`DB_CONNECTION=sqlite`).

Tables now include:

- Auth/session/cache/jobs (starter kit)
- `teams`, `team_members`, `team_invitations`
- `organization_settings` (1:1 with teams)
- `departments` (tenant-scoped, soft deletes)
- `users.is_platform_admin`
- `agent_conversations`, `agent_conversation_messages` (Laravel AI)

No patient, document, or audit tables yet.

### 4. Existing tenant architecture

**Teams = organization tenant boundary** (product language: Organization).

- Tenant resolved from `{current_team}` route slug.
- Organization settings and departments hang off `team_id`.
- `BelongsToTeam` concern ready for future domain models.
- Cross-tenant department access returns 404.

### 5. Existing UI component system

- Inertia React + Radix/shadcn-style UI + Tailwind v4
- App shell sidebar now includes Organization + Departments links
- Wayfinder typed routes/actions

### 6. Queue, cache and storage configuration

| Concern | Driver |
| --- | --- |
| Queue | `database` |
| Cache | `database` |
| Session | `database` |
| Filesystem default | `local` → `storage/app/private` |
| S3 | configured but unused |
| Mail | `log` |

### 7. Existing AI integrations

- `laravel/ai` installed and migrated.
- Org settings allow enabling AI with **`ai_provider = mock` only**.
- No document-processing jobs yet; no claim of real AI processing.

### 8. Existing tests and architectural conventions

Pest feature tests; thin controllers; Form Requests; policies; action classes; factories; Pint + Larastan.

---

## Phase 1 implementation plan

| Slice | Scope | Status |
| --- | --- | --- |
| **1A** | Tenant foundation, org management, access control | **Completed** |
| **1B** | Patient intake vertical slice | **Completed** |
| **1C** | Coordinator CRM + case workspace | **Completed** |
| **1D** | Mock AI document processing + comparison UI | **Completed** |
| **1E** | Doctor review + assignment | **Completed** |
| **1F** | Demo seed data + acceptance tests | **Completed** |
| **2A** | Treatment proposal management | **Completed** |
| **2B** | Appointment coordination | **Completed** |
| **2C** | Travel, tasks, communications + Laravel AI vision | **Completed** |
| **2D** | Message templates + workflow automation | **Completed** |
| **2E** | Patient follow-ups + executive/operational dashboards | **Completed** |

Architectural decision: keep `Team` as tenant identity; product UI says “Organization”. Frontend remains React.

---

## Milestone 1A — Tenant foundation (completed)

### Completed work

- CareFlow roles + expanded permission matrix on `TeamRole` / `TeamPermission`.
- `organization_settings` + `departments` migrations/models/factories.
- `users.is_platform_admin` (no automatic patient access).
- `BelongsToTeam` concern.
- Organization settings + department CRUD (controllers, form requests, policies, React pages).
- Sidebar navigation for Organization / Departments.
- `OrganizationDemoSeeder` (demo hospital + departments; no patient data yet).
- Hospital admins can manage staff roles/members (tests updated accordingly).

### Tests

Full suite: **96 tests, 93 passed, 3 skipped**.

New / updated coverage:

- `tests/Feature/TeamRolePermissionTest.php`
- `tests/Feature/OrganizationSettingsTest.php`
- `tests/Feature/DepartmentTest.php`
- Updated `tests/Feature/Teams/TeamMemberTest.php` for admin staff management

### Unresolved issues

- Spec says Vue; repo is React — continuing with React.
- Table rename `teams` → `organizations` deferred.
- Platform admin UI not built yet.
- Real AI providers not configured (intentional).
- PHP timezone for Nicosia is `Asia/Nicosia` (not `Europe/Nicosia`) on this runtime.

---

## Milestone 1B — Patient intake vertical slice (completed)

### Architecture choices (scalability)

- **Transactional intake orchestration** via `SubmitPatientIntake` action + `IntakeSubmissionData` DTO (controllers stay thin).
- **Atomic case references** via `case_reference_sequences` + row locks (`CaseReferenceGenerator`), not `max()+1`.
- **Private document storage** through `PatientDocumentStorage` (tenant/case path layout, checksum, never public URLs).
- **Event → queued listener → unique job** pipeline (`ProcessPatientDocument` → `DocumentProcessingPipeline`).
- **Opaque hashed portal tokens** for patient tracking (not sequential IDs).
- **Tenant-scoped models** (`BelongsToTeam`) + staff visibility rules in `PatientCasePolicy` / `visibleToStaff`.
- **Rate-limited intake** endpoint (`patient-intake` limiter).
- Config surface in `config/careflow.php` for references, storage, consent versions, portal TTL.

### Completed work

- Domain tables: `patients`, `patient_enquiries`, `patient_cases`, `patient_consents`, `patient_documents`, `case_reference_sequences`.
- Public portal: `/p/{organization}/intake` (EN/TR/AR/RU/AZ + RTL), confirmation, secure tracking.
- Staff: case index + case workspace overview (sidebar “Cases”).
- Returning patients matched by tenant email (no silent duplicate patients).
- Consents versioned and stored separately (enquiry / health data / optional AI).

### Tests

Full suite: **107 tests, 104 passed, 3 skipped**.

New coverage:

- `tests/Feature/PatientIntakeTest.php`
- `tests/Feature/PatientCaseAuthorizationTest.php`
- `tests/Unit/CaseReferenceGeneratorTest.php`

---

## Milestone 1C — Coordinator CRM (completed)

### Architecture choices

- `CaseStatusTransitionMap` domain service for allowed edges (future org-config can replace the map).
- `TransitionPatientCaseStatus` / `AssignPatientCase` actions with row locks + activity recording.
- `InvalidCaseStatusTransitionException` for business-rule failures (not HTTP-coupled).
- `TreatmentScheduled` hard-blocked until proposal/appointment prerequisites exist.
- `PatientCaseIndexQuery` encapsulates filter/sort/pagination for the CRM list.
- `case_activities` timeline with actor, typed events, patient-visibility flag.

### Completed work

- Case list filters: search, status, priority, department, assignment views, sort.
- Case assignment for coordinator/doctor (role-validated membership).
- Validated status transitions with notes.
- Activity timeline on case workspace.
- Intake now records `case_created` / `document_uploaded` activities.

### Tests

Full suite: **114 tests, 111 passed, 3 skipped**.

New coverage:

- `tests/Feature/PatientCaseCrmTest.php`
- `tests/Unit/CaseStatusTransitionMapTest.php`

### Unresolved issues

- Full CRM nav saved views / bulk assign / export not built.
- Case workspace tabs beyond overview/timeline (clinical, travel) not built.
- Tasks / communications modules not built.
- Demo seed still lacks 15 synthetic cases (Slice 1F).

---

## Milestone 1D — Mock AI document processing + comparison UI (completed)

### Architecture choices

- **Provider contracts** under `App\Contracts\Documents` with Phase-1 **mock-only** bindings (`careflow.ai.provider=mock`). Non-mock providers throw until contractual safeguards exist.
- **Versioned AI outputs** (`document_processing_runs`, `document_extracted_texts`, `document_extractions`, `document_translations`, `document_summaries`) with `is_current` + integer versions; originals never overwritten.
- **Human corrections** via `CorrectDocumentExtraction` + `document_review_events` (stores previous/new values; corrected payloads live beside originals).
- **Pipeline orchestration** in `DocumentProcessingPipeline` (status machine on document + run, transactional persistence, case bump to `AwaitingCoordinatorReview`).
- **Synthetic labeling**: mock providers wrap/emit demo content and keep uncertain/missing clinical fields empty rather than inventing diagnoses.
- Comparison workspace at `organizations/documents/show` (original vs extraction/translation/summary + correction form).

### Completed work

- Mock adapters: text extract, language detect, classify, structure, translate, summarize.
- `ProcessPatientDocument` unique queued job (replaces validation-only path for intake uploads).
- Staff document routes: show / download / review correction.
- Case workspace links into document review.
- Factories for processing runs and AI output models.

### Tests

Full suite: **118 tests, 115 passed, 3 skipped**.

New / updated coverage:

- `tests/Feature/DocumentProcessingTest.php`
- `tests/Feature/DocumentReviewTest.php`
- Updated `tests/Feature/PatientIntakeTest.php` for `ProcessPatientDocument`

---

## Milestone 1E — Doctor review + clinical approvals (completed)

### Architecture choices

- Spec entities: `doctor_reviews`, `clinical_observations`, `clinical_recommendations`, `clinical_approvals`.
- Open reviews are ensured via `EnsureOpenDoctorReview` (assigned doctor owns the thread; owners/admins with clinical permission may assist).
- Versioned human recommendations (`is_current` + integer versions); AI content cannot be approved (`is_ai_generated` hard-blocked).
- Explicit approval trail in `clinical_approvals` (approver, outcome, notes, timestamp).
- `SubmitDoctorReview` closes the review and transitions case status when allowed (`TreatmentProposalPreparation` / `AdditionalInformationRequired`).
- Policy matrix: `viewClinical` / `reviewClinical` / `approveClinical` — coordinators can see coordination context via `ManageCases` but cannot write or approve clinical content.

### Completed work

- Clinical review section on case workspace (observations, recommendation drafts, submit review, approve/reject).
- Activity types for clinical observation / recommendation / approval / doctor review submitted.
- Routes under `cases/{case}/clinical/*`.

### Tests

Full suite: **125 tests, 122 passed, 3 skipped**.

Coverage in `tests/Feature/DoctorClinicalReviewTest.php` (assigned doctor happy path, additional-info outcome, recommendation approval, nurse observation-only, coordinator forbidden, unassigned doctor forbidden, Inertia payload).

---

## Milestone 1F — Demo dataset + acceptance tests (completed)

### Architecture choices

- Idempotent `CareFlowDemoDataset` builder (purge/recreate demo patients only; keep org/staff stable).
- 16 synthetic cases across workflow stages, departments, countries/languages; all `is_demo = true` and labelled `[SYNTHETIC DEMO]`.
- Seeded mock AI outputs + clinical review trails for later-stage cases (no invented real clinical facts).
- Dashboard metrics query reads live DB counts scoped by `visibleToStaff` + `ViewCases` (management viewer gets no case metrics).

### Demo accounts (password: `password`)

| Role | Email |
| --- | --- |
| Hospital Admin | `admin@careflow-demo.test` |
| Coordinator | `coordinator@careflow-demo.test` |
| Doctor | `doctor@careflow-demo.test` |
| Nurse | `nurse@careflow-demo.test` |
| Management Viewer | `viewer@careflow-demo.test` |

Org slug: `careflow-international`

### Tests

Full suite: **131 tests, 128 passed, 3 skipped**.

- `tests/Feature/DemoDatasetTest.php`
- `tests/Feature/Phase1AcceptanceTest.php` (intake → AI → coordinator → doctor review/approval → dashboard)
- `tests/Feature/OrganizationDashboardMetricsTest.php`

### Phase 1 note

Proposal/travel/task/communication modules remain Phase 2. Acceptance covers built Phase 1 capabilities only; later demo steps stay out of scope until those modules exist.

---

## Milestone 2A — Treatment proposals (completed)

### Architecture choices

- Versioned `treatment_proposals` + `treatment_proposal_items` with atomic `ProposalReferenceGenerator` (`TP-YYYY-######`).
- Explicit proposal status machine (`TreatmentProposalTransitionMap`) separate from case CRM transitions.
- Coordinators manage administrative packaging (`ManageProposals`); only clinicians with `ApproveClinical` can approve proposals.
- Proposals may link only to **approved, non-AI** clinical recommendations.
- Sending a proposal can advance case status to `ProposalSent`; patient acceptance advances to `AwaitingPatientResponse`.
- Case status prerequisites tightened: `ProposalSent` requires an approved/sent proposal; `TreatmentScheduled` still requires patient-accepted proposal **and** a confirmed appointment (appointments slice next).

### Completed work

- Create / update / submit / approve / send / record patient response actions.
- Case workspace Treatment proposals panel.
- Activity timeline events for proposal lifecycle.

### Tests

Full suite: **136 tests, 133 passed, 3 skipped**.

- `tests/Feature/TreatmentProposalTest.php`

### Next steps

1. Travel coordination, tasks, communications.
2. Optional: seed appointments into demo dataset; OCR/vision for binary PDFs.

---

## Milestone — Real OpenAI document AI via Laravel AI (completed)

### Architecture choices

- Kept document AI **contracts**; added OpenAI adapters under `app/Domains/Documents/Providers/OpenAi`.
- Laravel AI structured agents in `app/Ai/Agents/*` (language, classification, extraction, translation, summary) with `#[Provider(Lab::OpenAI)]`.
- Runtime switch: `CAREFLOW_AI_PROVIDER=mock|openai` (+ required `OPENAI_API_KEY` for openai).
- Tests stay on mock (`phpunit.xml`). Mock remains available for demos without keys.
- Pipeline usage/activity now records actual synthetic flags (false for OpenAI outputs).
- Org settings may store preferred provider `mock|openai` (runtime still env-driven).

### Completed work

- OpenAI text extractor (readable UTF-8 only; binary OCR not claimed).
- Provider bindings in `AppServiceProvider`.
- Config / `.env.example` documentation.

### Tests

- `tests/Feature/OpenAiDocumentAiProviderTest.php`

---

## Milestone 2B — Appointment coordination (completed)

### Architecture choices

- Administrative `case_appointments` (not a hospital HIS replacement).
- Status machine: Proposed → Awaiting Confirmation → Confirmed / Rescheduled / Cancelled / Completed.
- **Confirmed requires** `hospital_slot_verified` + `confirmation_source` (`manual_hospital_verified` | `external_his`); external HIS also requires `external_his_reference`.
- `TreatmentScheduled` case status now requires patient-accepted proposal **and** a hospital-verified confirmed appointment.
- Permission: `ManageAppointments` for Owner/Admin/Coordinator.

### Completed work

- Create / update / transition actions, controller, form requests, case workspace Appointments panel.
- Activity types for appointment lifecycle.

### Tests

- `tests/Feature/CaseAppointmentTest.php`

### Next steps

1. Message templates + workflow automation executions.
2. Seed travel/tasks/appointments into demo dataset.

---

## Milestone 2C — Travel, tasks, communications + Laravel AI vision (completed)

### Architecture choices

- **Laravel AI is the AI brain**; OpenAI is only the configured provider key.
- Document vision/PDF text extraction uses Laravel AI agents + file attachments (`DocumentVisionTextExtractionAgent`).
- Administrative reply drafting uses `AdministrativeReplyDraftAgent` (staff approval required; clinical advice blocked).
- Travel: one `travel_arrangements` record per case; no passport collection.
- Tasks: status machine Open → In Progress / Waiting → Completed / Cancelled + comments.
- Communications: timeline with channels; outbound requires approval; no autonomous clinical send.

### Completed work

- Travel upsert, tasks CRUD/transitions, communications log/approve.
- Case workspace panels for travel, tasks, communications.
- Permissions: `ManageTravel`, `ManageTasks`, `ManageCommunications`.

### Tests

- `tests/Feature/TravelArrangementTest.php`
- `tests/Feature/CaseTaskTest.php`
- `tests/Feature/CaseCommunicationTest.php`

---

## Milestone 2D — Message templates + workflow automation (completed)

### Architecture choices

- Org-scoped `message_templates` with translation maps + placeholders (`{{patient_name}}`, `{{case_reference}}`, `{{organization_name}}`).
- `workflow_definitions` / `workflow_executions` / `workflow_execution_steps` with idempotency keys, step history, and failure logging.
- Allowed workflow actions only: `create_task`, `draft_communication_from_template`, `notify_staff` (no unrestricted AI DB agent).
- External template drafts always land as awaiting approval.
- Defaults seeded per organization via `EnsureDefaultMessageTemplates` / `EnsureDefaultWorkflowDefinitions`.

### Hooks

- A New enquiry → `PatientEnquirySubmitted` listener
- B Documents processed → `DocumentProcessingCompleted`
- C Doctor assigned → `AssignPatientCase`
- D Additional info required → `SubmitDoctorReview`
- E Proposal accepted → `RecordTreatmentProposalPatientResponse`
- F Treatment completed → `TransitionPatientCaseStatus`

### Tests

- `tests/Feature/MessageTemplateTest.php`
- `tests/Feature/WorkflowAutomationTest.php`

---

## Milestone 2E — Patient follow-ups + dashboards (completed)

### Architecture choices

- First-class `patient_followups` entity (soft deletes, UUID route key) with statuses Scheduled / In Progress / Completed / Cancelled.
- Create / update / complete actions record case activity; authorization reuses `ManageTasks`.
- Workflow F (`create_follow_up`) uses `CreatePatientFollowUp`.
- Dashboard metrics derive only from live DB data; definitions exposed to the UI.
- `ViewCases` roles get triage cards + recent cases; `ViewReports`-only management viewers get aggregate executive/operational metrics without case lists.

### Completed work

- Follow-up model/migration/factory, controller, form requests, case workspace Follow-ups panel.
- Expanded `OrganizationDashboardMetricsQuery` for executive + operational KPIs and coordinator workload.
- Demo seed now creates proposals, appointments, travel, tasks, and follow-ups for later-stage cases (including an overdue follow-up on FollowUp status).

### Tests

- `tests/Feature/PatientFollowUpTest.php`
- `tests/Feature/OrganizationDashboardMetricsTest.php` (updated for aggregates + overdue follow-ups)

---

## Milestone 2F — Dashboard filters, doctor queue, platform admin, outbound send (completed)

### Architecture choices

- Dashboard filters apply through `FilterDashboardMetricsRequest` → `OrganizationDashboardMetricsQuery::applyFilters` (date range, status, department, country, language, coordinator, doctor).
- Doctor queue is a dedicated clinical backlog view scoped by `visibleToStaff` (doctors see assigned cases; coordinators see org backlog).
- Platform admin console (`/platform`) is gated by `EnsurePlatformAdmin`; inventory only — no automatic patient/org case access.
- Outbound communications use `LogOutboundCommunicationSender` (Laravel Mail for email + structured log for WhatsApp/portal). HIS scheduling uses `MockHisSchedulingAdapter` behind `HisSchedulingAdapter`.
- Message templates UI supports all portal languages, active toggle, and live preview.

### Completed work

- Dashboard filter bar + filter options in `DashboardController`.
- `DoctorQueueController` + sidebar/quick-link navigation.
- `WorkflowExecutionController@index` execution history UI.
- `PlatformDashboardController` + platform nav for `is_platform_admin` users.
- Message template multi-language editor polish.
- Communication approve + `mark_sent` dispatches mail via the log provider.

### Tests

- `tests/Feature/DashboardMetricsFilterTest.php`
- `tests/Feature/DoctorQueueTest.php`
- `tests/Feature/WorkflowExecutionIndexTest.php`
- `tests/Feature/PlatformDashboardTest.php`
- `tests/Feature/CaseCommunicationTest.php` (mark_sent + Mail::fake)

### Platform admin follow-up

- `ADMIN_EMAILS` (comma-separated) in `.env` / `config/careflow.php` grants platform admin without DB flag.
- `/platform` lists organizations + users with roles and **Impersonate** actions.
- Impersonation is audited in logs; amber banner + Leave returns to `/platform`.
- Platform admins cannot impersonate other platform admins.

