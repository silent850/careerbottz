# CareerBotTz Requirements and Traceability

**Document:** `docs/REQUIREMENTS.md`  
**Project:** CareerBotTz  
**Source baseline:** Version 1.0, 11 October 2026  
**Status:** Draft for review — not approved  
**Current phase:** P01 — Requirements and Architecture Approval

## 1. Purpose and source authority

CareerBotTz enables job seekers in Tanzania to create, edit, save,
review, manage, and download professional English-language CVs through
WhatsApp.

Users may communicate in English, Kiswahili, or mixed-language
messages. CV content and generated PDF documents must remain in English.

This document establishes stable requirement IDs, traceability, and
verifiable acceptance criteria for implementation and testing.

### 1.1 Authoritative source documents

1. `CareerBotTz_System_Design_and_Implementation_Roadmap_v1 (1).pdf`
2. `CareerBotTz_Complete_Implementation_Blueprint.pdf`
3. `CareerBotTz_Codespaces_Roadmap.pdf`

The System Design establishes the product and architectural baseline.
The Blueprint provides requirement IDs and traceability.
The Codespaces roadmap provides the dependency-ordered task register,
acceptance IDs, verification expectations, and phase gates.

If the documents differ, the discrepancy must be recorded and reviewed.
Do not silently replace source terminology, task IDs, or requirements.

### 1.2 Requirement and evidence rules

- Requirement IDs are stable and must not be reused for unrelated work.
- Requirements must map to implementation phases/tasks and observable
  acceptance tests wherever the sources support that mapping.
- A task cannot be accepted merely because its files exist.
- Automated tests or documented manual verification must have actual
  results and evidence before a task is marked complete.
- Planned tests are not passing tests.
- Proposed technical details are not approved decisions.
- No implementation may begin before its prerequisite decisions and
  phase gates are satisfied.
- Scope changes must be explicitly reviewed and recorded.

## 2. Version 1 scope

### 2.1 Included

Version 1 includes:

- WhatsApp interaction through a separate Node.js/Baileys gateway.
- Automatic identity resolution and account creation on the first valid
  inbound interaction.
- Normalized WhatsApp phone identity.
- English, Kiswahili, and mixed-language conversations.
- Persistent conversation-language preference.
- English-only CV content and PDF output.
- Multiple named CVs per user.
- Incrementally saved and resumable drafts.
- Guided collection of personal details, education, work experience,
  skills, and other approved CV sections.
- CV selection, editing, renaming, deletion, and history.
- Review and explicit confirmation before an immutable confirmed
  snapshot is created.
- Asynchronous PDF generation and private document storage.
- Secure WhatsApp document delivery and safe redelivery.
- Separate document-generation and WhatsApp-delivery status.
- Help, clarification, back, pause, cancellation, resume, and skip
  behavior according to approved workflow states.
- An administrative dashboard for operational visibility.
- Authorized WhatsApp connection, QR/session status, reconnect,
  disconnect, logout, and recovery operations.
- Queue, AI, document, and gateway monitoring.
- Security, privacy, backup, recovery, staging, pilot, and release
  controls.

### 2.2 Excluded from Version 1

The following are outside Version 1:

- Payments.
- Subscriptions.
- Paid premium templates.
- A general user-facing web portal for CV management.
- An unapproved migration to WhatsApp Cloud API.
- Any other scope expansion without explicit review and approval.

The administrative interface is included for operational purposes.
It does not imply approval for a general user-facing portal.

## 3. Users and principal journeys

### 3.1 Job seeker

A job seeker communicates through WhatsApp. The system resolves the
user's identity, applies the saved language preference, and guides the
user through creating or managing a CV.

The user may create multiple named CVs, provide facts incrementally,
leave and resume drafts, correct answers, review the content, confirm
a version, and request a PDF.

### 3.2 Administrator

An authorized administrator uses the administrative interface to
inspect system health and perform approved operational actions,
including WhatsApp connection/session lifecycle operations.

The exact roles, permissions, and allowed operations remain subject to
approval.

### 3.3 Principal journey

1. Receive a valid inbound WhatsApp message.
2. Resolve or create the account without duplicate identities.
3. Apply the saved language preference or approved language-selection
   journey.
4. Present help and CV-management options as appropriate.
5. Create a named CV or explicitly select an existing CV/draft.
6. Collect and persist answers incrementally.
7. Clarify ambiguous or incomplete answers.
8. Allow the user to correct and review content.
9. Obtain explicit confirmation before creating a confirmed snapshot.
10. Generate an English PDF asynchronously.
11. Store the document privately.
12. Deliver the saved document through the approved gateway.
13. Track generation and delivery separately and recover safely from
    failures.

Exact conversation wording and state transitions require P01-T03 and
P01-T05 review.

## 4. Language, AI, and factuality requirements

### 4.1 Conversation languages

- English is supported.
- Kiswahili is supported.
- Mixed-language input is supported.
- Language preference must persist across reconnects and later
  conversations.
- Users should be able to explain CV information in their preferred
  supported language.

### 4.2 English-only CV output

Generated CV content and PDFs must be in English. Source facts supplied
in Kiswahili or mixed language may be interpreted and professionally
expressed in English, but must not be changed into unsupported claims.

### 4.3 AI boundaries

Gemini may interpret messages, extract facts, ask for clarification,
write professional English CV content, and check consistency.

Gemini must not:

- Invent employers, employment dates, qualifications, achievements,
  metrics, skills, references, or other user facts.
- Directly mutate authoritative database records.
- Bypass Laravel authorization, validation, state rules, or business
  logic.
- Turn an unsupported intent into an authorized operation.
- Cause confirmed snapshots to change without the approved
  confirmation/version workflow.

Structured AI output must be validated against an approved schema
before Laravel acts on it.

Exact intent definitions, schemas, confidence handling, timeouts,
retry limits, and fallbacks remain subject to P01-T05 approval.

## 5. Requirement traceability matrix

The requirement IDs and their core meanings are preserved from the
Complete Implementation Blueprint. Task mappings below follow the
source roadmap; where only a phase-level mapping is established by the
source, no more specific task is invented.

| ID | Requirement | Phase | Acceptance criterion |
|---|---|---|---|
| REQ-001 | Register users on the first valid inbound interaction. | P03 | The first valid message creates one account; repeated messages do not create duplicate accounts. |
| REQ-002 | Normalize WhatsApp phone identity. | P02–P03 | Equivalent normalized numbers resolve to one account. |
| REQ-003 | Persist English/Kiswahili language preference. | P04 | Preference persists across reconnects and is respected in later responses. |
| REQ-004 | Generate English-only CVs. | P06 | Kiswahili or mixed-language source facts produce English CV content and an English PDF. |
| REQ-005 | Create and name multiple CVs. | P05 | Creating a CV does not overwrite an existing CV or draft. |
| REQ-006 | Save incomplete drafts incrementally. | P05 | Committed answers remain available after interruption. |
| REQ-007 | Resume a selected draft. | P05 | Multiple drafts are offered for explicit selection; the wrong draft is not resumed implicitly. |
| REQ-008 | Collect and persist approved CV sections. | P05 | Core approved sections persist; optional sections may be omitted where permitted. |
| REQ-009 | Support users without work experience. | P05–P06 | Experience can be omitted and employment history is not fabricated. |
| REQ-010 | Edit, rename, and delete owned CVs. | P05 | Authorized operations succeed; cross-owner access is denied. Deletion follows the approved retention policy. |
| REQ-011 | View CV history. | P05 | Historical versions can be listed without mutating them. |
| REQ-012 | Review and confirm content. | P06 | Only explicit confirmation creates an immutable confirmed snapshot. |
| REQ-013 | Deliver and redeliver saved PDFs. | P06 | Delivery can be retried without duplicating a CV or confirmed version. |
| REQ-014 | Provide help, cancellation, pause, back, and skip behavior. | P04–P05 | Applicable commands work in the approved workflow states. |
| REQ-015 | Isolate Baileys from Laravel business logic. | P03 | The gateway owns WhatsApp connectivity and contains no CV business rules. |
| REQ-016 | Deduplicate inbound events. | P03 | Replaying a provider event does not repeat its business operation. |
| REQ-017 | Process inbound messages asynchronously. | P02–P04 | Gateway acknowledgement is independent of slow AI processing. |
| REQ-018 | Interpret ambiguous messages with Gemini. | P04 | Unclear input is interpreted safely or clarified without unsupported data mutation. |
| REQ-019 | Prevent unsupported AI actions and fabricated facts. | P04–P06 | Unsupported actions are rejected and generated facts remain supported by source information. |
| REQ-020 | Preserve immutable confirmed versions. | P05–P06 | Later edits cannot alter an existing confirmed snapshot. |
| REQ-021 | Track document generation separately from delivery. | P06 | Delivery failure does not invalidate a successfully generated document. |
| REQ-022 | Prevent concurrent conversation corruption. | P02–P07 | Rapid or repeated messages cannot corrupt state or save answers into the wrong field. |
| REQ-023 | Monitor queue, AI, document, and gateway health. | P07 | Operational failures are visible and actionable. |
| REQ-024 | Secure secrets, files, and administrative actions. | P07 | Security tests pass and sensitive values are not exposed. |
| REQ-025 | Verify backup, recovery, and deployment rollback. | P07–P08 | Recovery and rollback procedures succeed in documented tests. |
| REQ-026 | Support authorized WhatsApp QR/account-linking operations. | P07 | QR lifecycle, status, reconnect, disconnect, logout, and recovery work according to the approved contract. |
| REQ-027 | Inspect the existing project before changes. | P01–P02 | Inspection evidence identifies actual repository structure, Git state, tools, and versions. |
| REQ-028 | Approve ERD and state transitions before dependent implementation. | P01 | Approved design records and ERD exist before dependent code begins. |
| REQ-029 | Preserve all eight phases and phase gates. | P01–P08 | Phase evidence and explicit sign-off are recorded. |
| REQ-030 | Maintain the seven required design documents. | P01–P08 | Required documents are maintained and consistent with approved decisions. |
| REQ-031 | Pass the 15 numbered roadmap acceptance scenarios. | P03–P08 | Each `ACC-01` through `ACC-15` has a recorded outcome and evidence. |
| REQ-032 | Keep payments, subscriptions, and a user-facing portal out of Version 1. | All phases | Scope review finds no unauthorized implementation of excluded features. |

### 5.1 Source-to-task mappings for numbered acceptance scenarios

The following mappings preserve the numbered scenario/task mappings in
the Codespaces roadmap. They are planned tests, not passing results.

| Acceptance ID | Scenario | Requirement / task mapping | Expected result | Gate |
|---|---|---|---|---|
| ACC-01 | New user sends a greeting | REQ-001; P03-T08/P04-T05 | Account is created and a welcome response is received. | P04 |
| ACC-02 | User selects Kiswahili | REQ-003; P04-T06 | Conversation uses Kiswahili; generated CV remains English. | P04 |
| ACC-03 | User sends an unclear answer | REQ-018; P04-T04 | Gemini interprets safely or the bot asks for clarification. | P04 |
| ACC-04 | User changes language mid-question | REQ-003; P04-T06/T07 | Preference changes and the question is repeated appropriately. | P04 |
| ACC-05 | User leaves an unfinished CV | REQ-006; P05-T08 | Committed draft data remains saved. | P05 |
| ACC-06 | User returns with several drafts | REQ-005/006; P05-T01/T08 | Bot offers explicit draft selection or creation of a new CV. | P05 |
| ACC-07 | User starts another CV | REQ-005; P05-T01 | A separate named CV is created. | P05 |
| ACC-08 | User has no employment history | REQ-009; P05-T04/P06-T03 | Experience can be omitted without invented data. | P06 |
| ACC-09 | User corrects a previous answer | REQ-010; P05-T09 | The correct working section updates without changing a prior confirmed snapshot. | P05/P06 |
| ACC-10 | Gemini is temporarily unavailable | REQ-018/019; P04-T09/T10 | Saved facts remain intact; the user receives a suitable response and retries are bounded. | P04 |
| ACC-11 | Same inbound WhatsApp event is received twice | REQ-016; P03-T07 | The same business operation is not performed twice. | P03 |
| ACC-12 | Two messages arrive quickly | REQ-022; P04-T08/P05-T09 | Conversation state remains consistent without lost committed answers or invalid transitions. | P05 |
| ACC-13 | PDF generation fails | REQ-021; P06-T07 | Failure is recorded; confirmed content remains available for retry. | P06 |
| ACC-14 | PDF delivery fails | REQ-013/021; P06-T09 | Delivery can be retried independently without unnecessary regeneration. | P06 |
| ACC-15 | User requests another user's CV | REQ-024; P05-T01/T10 | Another user's CV cannot be read, edited, or deleted. | P05/P07 |

### 5.2 Additional source scenario: deletion request

The System Design's prose acceptance list also includes a user
requesting deletion, with the expected result that the application
follows the defined deletion and retention policy.

The numbered roadmap/Blueprint acceptance set contains `ACC-01` to
`ACC-15` and does not assign deletion its own numbered `ACC` identifier.
This source discrepancy is preserved rather than creating an
unapproved `ACC-16`.

Deletion behavior must nevertheless be specified and verified under
REQ-010 and the approved retention/deletion policy, with its test ID
and relationship to the published acceptance set resolved during
P01-T02/P01-T07 review.

## 6. Security, privacy, reliability, and operations

The system must provide the following, subject to detailed design
approval:

- Server-side authorization for CV ownership and administrator actions.
- Safe handling of API credentials, environment variables, QR codes,
  and Baileys session credentials.
- Redaction of secrets and sensitive personal information from logs.
- Validation of AI outputs before persistent data is changed.
- Idempotent handling of inbound events.
- Transactions and concurrency controls that protect data integrity.
- Private document storage and authorized retrieval.
- Separate document-generation and delivery state.
- Bounded AI retries and usage monitoring.
- Queue, gateway, document, and AI operational visibility.
- Backup, restoration, rollback, staging, and release checks.
- Consent, retention, deletion, and privacy decisions before launch.
- Review of applicable Tanzanian personal-data protection requirements
  before production.

Exact retention rules, privacy obligations, security thresholds,
recovery objectives, and administrative permissions remain unresolved
until reviewed and approved.

## 7. Architecture baseline

The source documents establish this architectural baseline:

- Laravel/PHP modular monolith for application logic and workflow.
- `app/Services/` for service-layer responsibilities.
- MySQL 8.4 LTS as the authoritative persistent store.
- Redis and Laravel Horizon for queues and coordination.
- A separate Node.js/Baileys gateway for WhatsApp connectivity.
- Gemini API for approved interpretation, extraction, clarification,
  professional English CV writing, and consistency checks.
- HTML/CSS-based PDF rendering and private document storage.
- An administrative interface for operational visibility and controls.

This baseline does not establish that these components are installed,
connected, or working in the current repository. Exact compatibility
and integration contracts require verification and approval.

## 8. Proposed database inventory — not an approved schema

The source Blueprint discusses a proposed 19-table inventory:

1. `users`
2. `conversations`
3. `messages`
4. `cvs`
5. `cv_personal_details`
6. `cv_educations`
7. `cv_experiences`
8. `cv_skills`
9. `cv_projects`
10. `cv_certifications`
11. `cv_languages`
12. `cv_references`
13. `cv_sections`
14. `cv_versions`
15. `documents`
16. `webhook_events`
17. `ai_usage_logs`
18. `user_consents`
19. `audit_logs`

This list is a design input, not an approved database schema.
P01-T04 must reconcile the inventory with the final ERD, relationships,
ownership, constraints, indexes, version semantics, deletion/retention,
concurrency, and migration order before dependent migrations are created.

## 9. Unresolved decisions and discrepancies

1. **Intent catalogue:** the System Design lists 14 identifiers:
   `greeting`, `create_cv`, `select_language`,
   `answer_current_question`, `ask_for_clarification`, `edit_section`,
   `view_cv_history`, `resume_draft`, `start_new_cv`,
   `cancel_workflow`, `skip_question`, `request_help`,
   `unrelated_request`, and `unknown`. The Blueprint and Codespaces
   roadmap refer to 13 source-defined identifiers. P01-T05 must resolve
   this explicitly.
2. **Acceptance scenario count:** the System Design's prose list has
   16 scenarios including deletion; the numbered roadmap/Blueprint
   defines 15 acceptance IDs. Preserve all 15 IDs and separately track
   deletion until reviewed.
3. **Final ERD:** table definitions, keys, relationships, ownership,
   indexes, constraints, concurrency, deletion, retention, and migration
   order require approval.
4. **Conversation state machine:** exact states, transitions, commands,
   cancellation, recovery, and persistence behavior require approval.
5. **AI contract:** schemas, required fields, confidence thresholds,
   validation, unsupported-intent behavior, timeouts, retry limits, and
   fallbacks require approval.
6. **Laravel–Node contract:** transport, service authentication,
   acknowledgement, timeouts, errors, retries, and status updates
   require approval.
7. **WhatsApp QR/session lifecycle:** session ownership, persistence,
   expiry, refresh, reconnect, logout, and recovery require approval.
8. **CV template behavior:** default template versus user-selectable
   templates requires confirmation.
9. **Administrator permissions:** roles and allowed operations require
   approval.
10. **Document storage:** provider, private access, retrieval, deletion,
    and retention behavior require a decision.
11. **Consent and privacy:** notices, consent evidence, retention,
    deletion, and applicable review obligations require approval.
12. **Operational thresholds:** monitoring, retries, timeouts, capacity,
    and recovery objectives require definition.
13. **Runtime compatibility:** the recorded runtime is not yet approved
    as compatible with a selected Laravel/package combination.
14. **Development services:** the missing local MySQL/Redis commands do
    not establish that remote/container services are unavailable.

## 10. Traceability and change control

Every implementation task must identify:

- Requirement IDs satisfied.
- Prerequisites and blocking decisions.
- Permitted files/components.
- Automated tests or documented manual verification.
- Expected result and evidence.
- The phase gate that depends on it.

A missing task mapping or acceptance test must be recorded as a gap.
A task without a supporting requirement or approved design decision
must be reviewed before implementation.

Changes to scope, requirements, architecture, schema, state transitions,
integration contracts, or acceptance criteria must record the affected
IDs, rationale, dependency/test impact, decision status, and required
documentation updates.

## 11. Approval record

**Status:** Draft for review — not approved.

| Review item | Status |
|---|---|
| Version 1 scope and exclusions | Pending user review |
| REQ-001 through REQ-032 | Pending source-to-draft review |
| ACC-01 through ACC-15 mappings | Pending source-to-draft review |
| Deletion-scenario discrepancy | Open |
| Intent catalogue discrepancy | Open |
| Proposed database inventory | Not approved as schema |
| Open design decisions classified | Pending |
| User approval | Not granted |