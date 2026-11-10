# CAREFLOW AI
## Product Requirements Document and Technical Blueprint

**Product category:** Healthcare SaaS / International Patient Operations  
**Working product name:** CareFlow AI  
**Initial prospective customer:** Kolan British Hospital, Nicosia, Northern Cyprus  
**Product model:** Multi-tenant SaaS  
**Primary users:** International patient coordinators, doctors, clinical reviewers, hospital administrators and management.

**Product positioning:**

CareFlow AI is an intelligent international patient operations platform that helps hospitals manage international patients from their first enquiry through medical document submission, physician evaluation, treatment coordination and post-treatment follow-up.

It combines multilingual patient intake, AI-assisted medical document processing, patient case management, doctor review, communication and administrative workflow automation.

It is designed to complement existing Hospital Information Systems rather than replace them.

---

# PART 1: PRODUCT STRATEGY

## 1.1 The problem space

International patient management involves more than receiving an appointment request.

A typical patient journey can involve:

1. A patient discovers a hospital.
2. The patient submits an enquiry.
3. The patient provides medical history and supporting documents.
4. The hospital reviews the submitted information.
5. Medical personnel evaluate the case.
6. The hospital prepares an appropriate treatment proposal.
7. The patient asks questions and considers the proposal.
8. The hospital coordinates appointments and travel.
9. The patient arrives for treatment.
10. The hospital coordinates follow-up.

Several people may participate in this process: international patient coordinators, doctors, nurses, administrative personnel, travel coordinators and management.

CareFlow AI provides a shared operational workspace for this journey.

We do not assume that prospective customers currently manage these processes poorly. The product should demonstrate the value of making the process more connected, traceable and efficient.

## 1.2 Product objectives

The product should:

- Make international patient enquiries easier to receive and manage.
- Provide a multilingual patient-facing experience.
- Organize medical documents in a structured case workspace.
- Reduce repetitive administrative document handling.
- Assist staff with translation and document summarization.
- Give doctors a controlled environment for reviewing submitted information.
- Track the patient's journey and outstanding requirements.
- Coordinate treatment-related administrative activities.
- Improve follow-up consistency.
- Give management visibility into operational performance.
- Support integration with existing hospital systems.

## 1.3 Product boundaries

CareFlow AI is not initially:

- A complete hospital information system.
- An electronic prescribing system.
- A replacement for an EHR.
- A laboratory information system.
- A radiology information system or PACS.
- A pharmacy management system.
- A medical diagnosis engine.
- An autonomous treatment recommendation system.
- A replacement for qualified interpreters.
- A replacement for hospital billing software.

These boundaries are important for product scope, regulatory risk and commercial positioning.

## 1.4 Target customers

Design the product for:

- Private hospitals receiving international patients.
- Hospitals with international patient departments.
- IVF and fertility centres.
- Specialist surgical centres.
- Medical tourism providers.
- Multi-location healthcare groups.
- Specialist clinics receiving cross-border patients.

The architecture should support independent hospitals and larger healthcare groups.

---

# PART 2: PRODUCT MODULES

## 2.1 Hospital Administration

Each customer is an organization.

An organization should be able to configure:

- Organization name.
- Logo.
- Brand colours.
- Contact information.
- Address.
- Country.
- Timezone.
- Default currency.
- Default language.
- Supported patient languages.
- Departments.
- Staff members.
- Roles and permissions.
- Patient journey statuses.
- Notification settings.
- Enabled modules.
- Document storage settings.
- AI processing settings.
- Communication integrations.

### Organization settings

Provide these screens:

1. Organization profile.
2. Branding.
3. Departments.
4. Staff.
5. Roles and permissions.
6. Supported languages.
7. Workflow settings.
8. Notification settings.
9. AI configuration.
10. Integration settings.
11. Security and audit.
12. Subscription and enabled modules.

## 2.2 International Patient Portal

This is the public-facing application through which patients submit their information.

### Supported languages

Initial languages:

- English.
- Turkish.
- Arabic.
- Russian.
- Azerbaijani.

Requirements:

- Translation of interface labels.
- Language selection.
- Localized validation messages.
- Correct date and number formatting.
- Arabic right-to-left support.
- Language preference saved with the patient.
- Hospital-configurable default language.

Patient-entered information must not be silently translated or overwritten.

### Patient enquiry form

Organize the form into clear steps.

**Step 1: Personal information**

- First name.
- Last name.
- Date of birth.
- Gender, where necessary.
- Country of residence.
- Email address.
- Telephone number.
- Preferred language.

**Step 2: Medical enquiry**

- Department or specialty.
- Reason for contacting the hospital.
- Diagnosis, if known.
- Symptoms or medical history supplied by the patient.
- Previous procedures, if applicable.
- Current medications, if applicable.
- Known allergies, if applicable.
- Additional comments.

**Step 3: Medical documents**

Allow multiple uploads:

- PDF.
- JPG.
- JPEG.
- PNG.

Potential later formats include DICOM and other specialist medical formats.

Each upload should have:

- Original filename.
- File type.
- File size.
- Upload timestamp.
- Document category.
- Original language, once detected.
- Processing status.

**Step 4: Consent**

Record:

- Privacy notice version.
- Consent text version.
- Consent timestamp.
- Patient's consent decision.
- Source IP where legally appropriate.
- Form submission identifier.

Separate general enquiry consent from any additional consent required for sensitive health information or external AI processing.

**Step 5: Submission**

After successful submission:

- Create a patient enquiry.
- Create or associate a patient record.
- Create a patient case.
- Generate a unique reference number.
- Store uploaded documents privately.
- Trigger appropriate workflow events.
- Display a confirmation page.

The patient should receive a confirmation through an enabled communication channel.

### Patient case tracking

Create a secure patient-facing page.

The patient should be able to:

- Check the current administrative case status.
- See outstanding information requests.
- Upload additional documents.
- Read approved messages.
- Respond to coordinator requests.
- View appointment information where enabled.
- Review approved administrative documents.

Use secure, expiring access tokens or an authenticated account.

Do not use sequential patient IDs as access credentials.

Never expose internal clinical notes, staff comments or AI processing details that have not been approved for patient access.

---

## 2.3 International Patient CRM

The CRM is the primary workspace for international patient coordinators.

### Main navigation

- Overview.
- All Patients.
- Enquiries.
- Patient Cases.
- My Assigned Cases.
- Unassigned Cases.
- Awaiting Documents.
- Awaiting Doctor Review.
- Awaiting Patient Response.
- Treatment Proposals.
- Travel Coordination.
- Follow-ups.
- Tasks.
- Communications.
- Reports.

### Patient enquiry list

Display:

- Case reference.
- Patient name.
- Country.
- Preferred language.
- Department.
- Enquiry date.
- Assigned coordinator.
- Assigned doctor.
- Case status.
- Priority.
- Last activity.
- Outstanding tasks.

Provide:

- Search.
- Filters.
- Sorting.
- Pagination.
- Saved views.
- Bulk assignment where permission allows.
- Export with appropriate authorization.

### Patient case workspace

Use a consistent case layout with:

**Case header**

- Patient name.
- Reference number.
- Country.
- Preferred language.
- Current status.
- Priority.
- Assigned coordinator.
- Assigned doctor.

**Tabs**

1. Overview.
2. Medical Documents.
3. AI Document Review.
4. Doctor Evaluation.
5. Communications.
6. Treatment Proposal.
7. Travel Coordination.
8. Appointments.
9. Tasks.
10. Activity Timeline.

### Case overview

Display:

- Patient information.
- Enquiry details.
- Medical information supplied by the patient.
- Department of interest.
- Assigned staff.
- Important dates.
- Outstanding requirements.
- Latest communication.
- Current case stage.

### Case status model

Initial statuses:

1. New Enquiry.
2. Awaiting Documents.
3. Documents Received.
4. AI Processing.
5. Awaiting Coordinator Review.
6. Awaiting Doctor Review.
7. Additional Information Required.
8. Treatment Proposal Preparation.
9. Proposal Sent.
10. Awaiting Patient Response.
11. Appointment Planning.
12. Travel Coordination.
13. Treatment Scheduled.
14. Treatment Completed.
15. Follow-up.
16. Closed.
17. Declined.

Status transitions must be validated.

For example, a case should not become Treatment Scheduled without the necessary approved proposal and appointment information.

The hospital administrator should eventually be able to configure permitted status transitions.

---

# PART 3: AI MEDICAL DOCUMENT ASSISTANT

## 3.1 Purpose

The AI Medical Document Assistant processes documents supplied by patients and prepares structured information for authorized staff.

Its purpose is to reduce repetitive administrative document handling and improve document accessibility.

It does not make clinical decisions.

## 3.2 Document processing pipeline

The processing pipeline should be asynchronous.

1. Patient uploads document.
2. File is stored in private storage.
3. Document database record is created.
4. A processing job is dispatched.
5. File type and basic metadata are checked.
6. Text is extracted.
7. Document language is detected.
8. Document category is classified.
9. Relevant information is extracted.
10. Content is translated if required.
11. A structured summary is generated.
12. Results are stored.
13. Authorized staff are notified.
14. Staff review and correct the results.

### Processing statuses

- Uploaded.
- Pending.
- Validating.
- Extracting.
- Detecting Language.
- Translating.
- Structuring.
- Summarizing.
- Completed.
- Needs Review.
- Failed.
- Cancelled.

Provide clear error messages without exposing sensitive content.

## 3.3 Document categories

Initially support:

- Medical Report.
- Laboratory Report.
- Imaging Report.
- Discharge Summary.
- Prescription.
- Referral Letter.
- Medical History.
- Consultation Report.
- Other Supporting Document.

The classification model must be able to return Unknown or Uncertain.

## 3.4 Structured extraction

For every document, extract fields when explicitly present.

Suggested schema:

{
  "document_type": null,
  "original_language": null,
  "document_date": null,
  "issuing_institution": null,
  "clinician_name": null,
  "patient_identifiers": [],
  "diagnoses_as_documented": [],
  "symptoms_as_documented": [],
  "medical_history_as_documented": [],
  "medications_as_documented": [],
  "allergies_as_documented": [],
  "laboratory_results": [],
  "imaging_findings_as_documented": [],
  "procedures_as_documented": [],
  "uncertain_sections": [],
  "missing_or_unreadable_information": [],
  "source_references": []
}

Every field should support:

- Extracted value.
- Source reference.
- Confidence or uncertainty information where available.
- Human correction.
- Review status.

For laboratory results, preserve:

- Test name.
- Result.
- Unit.
- Reference range.
- Abnormal flag if explicitly indicated in the source.

Never invent missing values or units.

## 3.5 Translation

Support translation between the configured hospital languages.

Translation interface:

- Original document.
- Extracted original text.
- Translated content.
- Language identification.
- Translation timestamp.
- AI provider.
- Translation status.
- Human reviewer.
- Review outcome.

Label the translation:

"AI-assisted translation. Not a certified medical translation."

Where clinical interpretation depends on translated content, require qualified human verification.

Do not represent AI translation as professional interpretation.

## 3.6 AI summary

Create a structured summary with:

1. Document overview.
2. Document date.
3. Reported diagnosis.
4. Relevant documented history.
5. Reported symptoms.
6. Medications listed.
7. Allergies listed.
8. Laboratory findings.
9. Imaging findings as written.
10. Procedures or treatments documented.
11. Missing or unclear information.
12. Source document references.

Every summary must display:

"AI-generated draft. Requires review by an authorized healthcare professional."

The summary must distinguish:

- Facts explicitly found in the source.
- Information that could not be extracted.
- Uncertain or ambiguous content.

## 3.7 Document comparison workspace

Create a split-screen document review interface.

Left side:

- Original document viewer.
- Page navigation.
- Zoom.
- Download permission.

Right side:

- Extracted text.
- Translation.
- Structured information.
- AI summary.

Allow reviewers to correct extracted text and structured fields.

Store every approved correction with:

- User ID.
- Timestamp.
- Previous value.
- New value.
- Reason where required.

Never overwrite the original file.

## 3.8 AI processing architecture

Create interfaces such as:

- DocumentTextExtractor.
- DocumentClassifier.
- LanguageDetector.
- DocumentTranslator.
- MedicalInformationExtractor.
- DocumentSummarizer.

Implement providers behind interfaces.

Initially provide:

1. Mock provider for demo data.
2. Real AI provider adapter.
3. OCR provider adapter.

Provider selection should be configurable.

Use schema validation for all structured AI outputs.

Implement retries, timeouts, idempotency and usage tracking.

Do not send real patient health documents to an external AI provider until the hospital has approved the processing arrangement, data transfers and contractual safeguards.

---

# PART 4: DOCTOR EVALUATION WORKSPACE

## 4.1 Doctor dashboard

Display:

- Assigned cases.
- Cases awaiting review.
- Recently reviewed cases.
- Cases requiring additional information.
- Upcoming consultations.
- Review turnaround.

Doctors should only see cases they are authorized to access.

## 4.2 Case review

The doctor should be able to:

- Review patient-submitted information.
- Open original documents.
- Read extracted text.
- Compare translations.
- Read AI summaries.
- Correct extraction errors where authorized.
- Add clinical comments.
- Request additional documentation.
- Record evaluation findings.
- Prepare a draft recommendation.
- Approve or reject clinical content.

## 4.3 Clinical approval

The doctor must explicitly approve clinical content.

Maintain:

- Author.
- Reviewer.
- Approval timestamp.
- Revision history.
- Status.
- Supporting source documents.

Never treat an AI-generated summary as an approved clinical record.

Do not permit the AI to diagnose, prescribe or approve treatment.

---

# PART 5: TREATMENT AND TRAVEL COORDINATION

## 5.1 Treatment proposal

Create a treatment proposal workspace.

Fields:

- Patient case.
- Proposal reference.
- Department.
- Responsible physician.
- Clinician-approved recommendation reference.
- Proposed procedure or service.
- Estimated cost.
- Currency.
- Expected duration.
- Required appointments.
- Additional information.
- Validity period.
- Proposal status.

Statuses:

- Draft.
- Awaiting Clinical Approval.
- Approved.
- Sent to Patient.
- Patient Accepted.
- Patient Declined.
- Revised.
- Expired.
- Cancelled.

An administrative user must not be able to approve clinical recommendations unless they hold the required clinical authorization.

## 5.2 Travel coordination

Track:

- Expected arrival date.
- Expected departure date.
- Flight information where provided.
- Airport pickup.
- Transfer arrangements.
- Accommodation.
- Hotel details.
- Visa assistance status.
- Special administrative requirements.
- Assigned coordinator.
- Outstanding tasks.

Use configurable checklists.

Do not collect passport or travel information unless necessary for a defined workflow and supported by appropriate permissions and retention policies.

## 5.3 Appointment coordination

Initially support administrative appointment planning rather than replacing the hospital's scheduling system.

Fields:

- Patient case.
- Department.
- Doctor.
- Proposed date and time.
- Confirmed date and time.
- Appointment type.
- Location or meeting instructions.
- Status.
- External HIS reference, if integrated.

Statuses:

- Proposed.
- Awaiting Confirmation.
- Confirmed.
- Rescheduled.
- Cancelled.
- Completed.

Prevent the application from claiming a slot is confirmed unless it has been verified through an authoritative scheduling source.

---

# PART 6: COMMUNICATIONS

## 6.1 Unified communication history

Each patient case should have a communication timeline.

Channels:

- Email.
- WhatsApp Business.
- Internal comments.
- Patient portal messages.
- Other configured channels.

Record:

- Direction.
- Channel.
- Sender.
- Recipient.
- Message status.
- Language.
- Timestamp.
- Related case.
- Related staff member.
- Provider reference.

## 6.2 AI communication assistance

AI may:

- Translate incoming messages.
- Summarize long conversations.
- Draft administrative replies.
- Suggest follow-up messages.
- Identify missing documents.
- Identify unanswered enquiries.

AI must not autonomously send clinical advice.

For the initial release, require staff approval before external messages are sent.

## 6.3 Message templates

Create reusable templates for:

- Enquiry received.
- Additional documents required.
- Documents received.
- Case assigned for review.
- Evaluation completed.
- Treatment proposal available.
- Appointment confirmation.
- Travel information.
- Follow-up reminder.

Templates must support translations and organization-specific customization.

---

# PART 7: TASKS AND WORKFLOW AUTOMATION

## 7.1 Tasks

Each task should include:

- Organization.
- Related patient case.
- Title.
- Description.
- Assigned user.
- Priority.
- Due date.
- Status.
- Created by.
- Completion date.
- Activity history.

Statuses:

- Open.
- In Progress.
- Waiting.
- Completed.
- Cancelled.

## 7.2 Initial workflows

**Workflow A: New patient enquiry**

1. Enquiry submitted.
2. Patient case created.
3. Coordinator task created.
4. Coordinator notified.
5. Confirmation sent if configured.

**Workflow B: Medical documents received**

1. Document uploaded.
2. File validated.
3. Processing job dispatched.
4. AI processing completed.
5. Coordinator notified.
6. Case moves to the appropriate review queue.

**Workflow C: Doctor evaluation**

1. Coordinator assigns doctor.
2. Doctor receives notification.
3. Doctor reviews documents.
4. Doctor requests additional information or records evaluation.
5. Coordinator receives notification.

**Workflow D: Additional documents**

1. Authorized staff requests additional information.
2. Patient receives an approved message.
3. Case enters Awaiting Documents.
4. Patient uploads files.
5. Coordinator receives notification.

**Workflow E: Treatment coordination**

1. Clinical evaluation completed.
2. Proposal prepared.
3. Clinical approval obtained where required.
4. Proposal sent to patient.
5. Patient response recorded.
6. Coordinator creates appointment and travel tasks.

**Workflow F: Post-treatment follow-up**

1. Treatment completion recorded by authorized staff.
2. Follow-up schedule created.
3. Reminder tasks generated.
4. Coordinator monitors completion.
5. Patient communication recorded.

## 7.3 Workflow reliability

Implement:

- Idempotency.
- Retries.
- Failure logs.
- Execution history.
- Permission checks.
- Human approval gates.
- Configurable triggers.
- Duplicate notification prevention.

Do not create an unrestricted AI agent that can execute arbitrary database actions.

---

# PART 8: MANAGEMENT AND ANALYTICS

## 8.1 Executive dashboard

Show:

- Total enquiries.
- New enquiries.
- Active cases.
- Cases awaiting documents.
- Cases awaiting doctor review.
- Cases awaiting patient response.
- Proposals sent.
- Proposals accepted.
- Appointments confirmed.
- Treatments completed.
- Overdue follow-ups.

## 8.2 Operational performance

Track:

- Average first-response time.
- Average document-processing time.
- Average doctor-review turnaround.
- Average proposal turnaround.
- Enquiry-to-proposal conversion.
- Proposal-to-acceptance conversion.
- Appointment completion.
- Outstanding task volume.
- Workload by coordinator.

## 8.3 Filters

- Date range.
- Country.
- Department.
- Preferred language.
- Coordinator.
- Doctor.
- Case status.

All metrics must derive from real application data.

Display the calculation definition behind important metrics so management understands what is being measured.

Do not treat an enquiry as revenue.

---

# PART 9: USERS AND PERMISSIONS

## 9.1 Platform Super Administrator

Can:

- Manage customer organizations.
- Create and suspend tenants.
- Manage global subscription plans.
- Configure platform-level integrations.
- View system health.
- Manage platform support.

Access to patient records must not be automatic simply because someone is a platform administrator. Any exceptional support access must be separately authorized, time-limited and audited.

## 9.2 Hospital Administrator

Can:

- Manage hospital configuration.
- Invite staff.
- Manage permissions.
- Configure departments.
- Configure workflow statuses.
- Manage enabled modules.
- View organization reports.
- Configure integrations.

## 9.3 International Patient Coordinator

Can:

- View authorized patient cases.
- Create and update enquiries.
- Assign cases.
- Request documents.
- Manage communication.
- Create tasks.
- Coordinate appointments.
- Manage travel arrangements.
- Prepare administrative proposals.

Cannot approve clinical recommendations.

## 9.4 Doctor

Can:

- View assigned cases.
- Review medical documents.
- Review AI summaries.
- Add clinical comments.
- Request information.
- Prepare clinical recommendations.
- Approve clinical content within their authorization.

## 9.5 Nurse / Clinical Reviewer

Permissions must be configurable according to the hospital's role definitions.

May:

- Review assigned documents.
- Add authorized clinical observations.
- Help coordinate follow-up.
- Escalate cases.

Do not assume every nurse has the same approval authority as a doctor.

## 9.6 Management Viewer

Can:

- View permitted operational dashboards.
- View aggregate performance.
- Review reports.

Cannot automatically access detailed clinical records.

---

# PART 10: DATA ARCHITECTURE

## 10.1 Main database entities

Build the schema around these domains.

### Organization

- organizations
- organization_settings
- organization_users
- organization_modules
- departments
- staff_profiles

### Access control

- roles
- permissions
- role_permissions
- user_roles
- organization_user_roles

### Patient domain

- patients
- patient_enquiries
- patient_cases
- patient_case_assignments
- patient_consents
- patient_preferences

### Document domain

- patient_documents
- document_processing_runs
- document_extractions
- document_translations
- document_summaries
- document_review_events

### Clinical review

- doctor_reviews
- clinical_observations
- clinical_recommendations
- clinical_approvals

### Coordination

- treatment_proposals
- proposal_items
- appointments
- travel_arrangements
- accommodation_arrangements
- transfer_arrangements
- patient_followups

### Communication

- conversations
- communications
- communication_attachments
- message_templates
- communication_provider_events

### Workflow

- tasks
- task_comments
- workflow_definitions
- workflow_executions
- workflow_execution_steps

### Security

- audit_logs
- record_access_logs
- document_access_logs
- consent_records

### SaaS

- subscription_plans
- subscriptions
- billing_records
- module_entitlements

## 10.2 Data isolation

Every organization-owned record must have an organization identifier.

Use tenant-aware query scopes and authorization policies.

Never rely solely on a frontend-supplied organization ID.

Test cross-tenant access explicitly.

Consider separate database schemas or databases for customers with stricter isolation requirements in future enterprise deployments.

## 10.3 Data relationships

A patient can have multiple enquiries and cases.

A case can contain multiple documents.

A document can have multiple processing runs.

A processing run can generate multiple extraction, translation and summary versions.

A case can have multiple coordinators over time.

A case can have multiple doctor reviews.

A case can have multiple proposals and appointments.

A case can have many tasks, communications and audit events.

Preserve historical relationships instead of overwriting them.

---

# PART 11: SECURITY AND COMPLIANCE FOUNDATION

Security is a product requirement, not a final development task.

Implement:

- HTTPS.
- Encryption at rest where supported.
- Private object storage.
- Tenant isolation.
- Role-based authorization.
- Secure authentication.
- Multifactor authentication capability.
- Strong password policy.
- Session security.
- Rate limiting.
- CSRF protection.
- Secure file upload validation.
- Malware scanning integration point.
- Audit trails.
- Document access tracking.
- Consent records.
- Retention policies.
- Secure backups.
- Queue failure monitoring.
- Incident logging.
- Data export and deletion workflows where legally appropriate.

Never put patient documents or clinical content into ordinary application logs.

Do not expose document storage paths or permanent public links.

Keep secrets out of source control.

For production deployment, obtain legal and security advice concerning applicable Northern Cyprus, Turkish, and any other relevant data protection rules, international data transfers and health-data processing.

Do not claim certification or compliance without formal assessment.

---

# PART 12: INTEGRATION ARCHITECTURE

## 12.1 AI providers

Create provider-independent interfaces for:

- Text extraction.
- OCR.
- Translation.
- Classification.
- Structured extraction.
- Summarization.
- Message drafting.

## 12.2 Communication providers

Create adapters for:

- Email.
- WhatsApp Business.

## 12.3 Storage

Use private object storage.

Support:

- Local development storage.
- S3-compatible storage.
- Configurable storage provider.
- Temporary authorized download URLs.

## 12.4 Hospital information systems

Future integration options:

- REST APIs.
- HL7.
- FHIR.
- Secure file exchange.
- Approved vendor-specific integration.

Do not assume a particular standard or API is available.

Create an integration adapter boundary so hospital-specific integration does not contaminate the core product.

## 12.5 Video and telemedicine

Do not build custom WebRTC infrastructure in the initial MVP.

Keep video consultations outside the first release unless a confirmed customer requirement justifies them.

Future options should support a third-party provider and an authenticated appointment workflow.

---

# PART 13: APPLICATION NAVIGATION AND SCREENS

## Public application

- Landing page.
- Language selector.
- International patient enquiry.
- Document upload.
- Submission confirmation.
- Secure patient tracking.
- Patient messages.
- Appointment information.

## Hospital application

- Dashboard.
- Enquiries.
- Patient directory.
- Patient cases.
- Assigned cases.
- Document processing.
- Doctor reviews.
- Treatment proposals.
- Travel coordination.
- Appointments.
- Communications.
- Tasks.
- Reports.
- Departments.
- Staff.
- Roles.
- Workflow settings.
- Languages.
- Integrations.
- Organization settings.
- Audit logs.

## Platform administration

- Organizations.
- Users.
- Subscription plans.
- Module entitlements.
- System monitoring.
- AI provider configuration.
- Usage reporting.
- Platform settings.

---

# PART 14: TECHNICAL IMPLEMENTATION PRINCIPLES

Use the existing repository's established conventions.

Preferred architecture:

- Laravel domain services or action classes.
- Thin controllers.
- Form Request validation.
- Laravel policies.
- Events and listeners.
- Queued jobs.
- Provider interfaces.
- Database transactions.
- API resources.
- Reusable Vue components.
- TypeScript where useful.

Keep business domains separated.

Do not put all logic into one giant service or model.

Use database constraints to enforce important invariants.

Make AI jobs idempotent and safe to retry.

Keep original documents immutable.

Version AI outputs and prompts.

Use explicit approval transitions for sensitive actions.

---

# PART 15: DEVELOPMENT ROADMAP

## Phase 1: First demonstrable MVP

Priority:

1. Repository inspection.
2. Tenant foundation.
3. Authentication and roles.
4. Hospital administration.
5. Application layout.
6. Patient CRM.
7. Patient case workspace.
8. Multilingual public intake.
9. Secure document upload.
10. AI demo processing provider.
11. Document comparison interface.
12. Coordinator assignment.
13. Doctor review.
14. Synthetic demo data.
15. Automated tests.

The first demo must work without paid external AI credentials.

## Phase 2: Operational completeness

1. Treatment proposal management.
2. Travel coordination.
3. Appointment coordination.
4. Task management.
5. Follow-up workflows.
6. Message templates.
7. Communication timeline.
8. Management analytics.

## Phase 3: Real integrations

1. Real OCR.
2. Approved AI provider.
3. Email.
4. WhatsApp Business.
5. HIS integration discovery.
6. Integration adapter implementation.
7. Provider usage and cost reporting.

## Phase 4: Enterprise readiness

1. Security testing.
2. Tenant isolation testing.
3. Performance testing.
4. Backup and restore testing.
5. Monitoring.
6. Disaster recovery.
7. Data retention controls.
8. Formal compliance assessment.
9. Deployment documentation.
10. Customer onboarding.

---

# PART 16: DEMONSTRATION REQUIREMENTS

The demo should use a fictional hospital named "CareFlow International Medical Centre."

Create fictional staff accounts:

- Hospital administrator.
- International patient coordinator.
- Doctor.
- Nurse.
- Management viewer.

Create at least 15 synthetic patient cases.

Use varied countries, languages, specialties and workflow stages.

Create fictional sample medical documents with clearly synthetic content.

Demonstrate:

1. Patient opens the multilingual portal.
2. Patient submits an enquiry.
3. Patient uploads documents.
4. A case is created.
5. Coordinator sees the enquiry.
6. AI processes the documents.
7. Coordinator reviews the extracted information.
8. Doctor reviews the case.
9. Doctor records a human-reviewed evaluation.
10. Coordinator prepares an administrative proposal.
11. Patient receives an approved administrative message.
12. Travel coordination is recorded.
13. Follow-up tasks are created.
14. Management sees operational metrics.

All synthetic records must be labelled as demonstration data.

Never use real Kolan patient information.

---

# PART 17: ACCEPTANCE CRITERIA

The first MVP is acceptable when:

- The application installs and runs using documented instructions.
- A hospital organization can be created.
- Users can be assigned organization roles.
- Tenant isolation is enforced.
- A patient can submit an enquiry.
- A patient can upload multiple documents.
- The application stores files privately.
- The system generates a unique case reference.
- A coordinator can manage the case.
- Document processing runs asynchronously.
- The demo AI provider generates structured results.
- Original documents remain unchanged.
- AI output is labelled as unapproved.
- A doctor can review and comment.
- Authorized users can update the case status.
- Tasks are assigned and tracked.
- Dashboard metrics use actual database data.
- Important actions generate audit records.
- Unauthorized users cannot access patient documents.
- Automated tests cover the critical workflows.
- The complete demonstration works without real patient data.

---

# FINAL PRODUCT PRINCIPLE

CareFlow AI should not be built as a collection of disconnected AI features.

It should be a workflow platform.

Its core value is connecting the international patient journey:

**Enquiry → Intake → Documents → AI Processing → Human Review → Clinical Evaluation → Treatment Coordination → Travel → Follow-up.**

AI should reduce repetitive work, make information easier to access and help staff complete workflows.

Human professionals remain responsible for clinical decisions.

The product must be useful without AI, more efficient with AI, and safe even when AI processing fails.