# CareerBotTz Architecture

**Document:** `docs/ARCHITECTURE.md`
**Project:** CareerBotTz
**Source baseline:** Version 1.0, 11 October 2026
**Status:** Draft for review — not approved
**Current phase:** P01 — Requirements and Architecture Approval

## 1. Purpose

This document defines the proposed application architecture, component responsibilities, service boundaries, data flows, integration boundaries, and deployment considerations for CareerBotTz.

It is based on the approved source baseline described in:

1. `CareerBotTz_System_Design_and_Implementation_Roadmap_v1 (1).pdf`
2. `CareerBotTz_Complete_Implementation_Blueprint.pdf`
3. `CareerBotTz_Codespaces_Roadmap.pdf`

The architecture remains a draft until the required P01 decisions and phase gate have been reviewed and explicitly approved.

This document does not establish that any component has been installed, configured, integrated, or tested in the current repository.

## 2. Architectural objectives

The architecture must support:

- WhatsApp-based CV creation and management for job seekers in Tanzania.
- English, Kiswahili, and mixed-language conversations.
- English-only CV content and PDF output.
- Multiple named CVs and resumable drafts.
- Incremental persistence, review, explicit confirmation, and immutable confirmed snapshots.
- Asynchronous AI processing and PDF generation.
- Private document storage and secure WhatsApp delivery.
- Separation of document generation status from delivery status.
- Administrative monitoring and authorized WhatsApp connection/session operations.
- Data integrity, authorization, privacy, auditability, recovery, and operational monitoring.
- Incremental implementation through the eight source-defined phases.

## 3. Architectural baseline

The source documents establish the following architectural baseline.

| Component | Baseline responsibility | Approval status |
|---|---|---|
| Laravel/PHP application | Application workflows, authorization, validation, CV business rules, and orchestration | Baseline identified; implementation details pending review |
| `app/Services/` | Service-layer responsibilities within the Laravel application | Baseline identified |
| MySQL 8.4 LTS | Authoritative persistent store | Baseline identified; schema pending P01-T04 |
| Redis | Queue and coordination infrastructure | Baseline identified; configuration pending |
| Laravel Horizon | Queue monitoring and management | Baseline identified; configuration pending |
| Node.js/Baileys gateway | WhatsApp connectivity and gateway-specific operations | Baseline identified; integration contract pending P01-T06 |
| Gemini API | Approved interpretation, extraction, clarification, professional English CV writing, and consistency checks | Baseline identified; AI contract pending P01-T05 |
| HTML/CSS-based PDF rendering | Generation of professional English CV PDFs | Baseline identified; renderer selection and details pending |
| Private document storage | Storage and controlled retrieval of generated documents | Requirement identified; provider and retention decisions pending |
| Administrative interface | Operational visibility and authorized operational controls | Scope identified; permissions and detailed behavior pending |

These entries describe the target architecture. They are not evidence that the components are present or operational.

## 4. Logical component boundaries

### 4.1 Laravel application

Laravel is the proposed owner of authoritative application behavior.

Its responsibilities include:

- Resolving application users and enforcing ownership.
- Managing CV creation, selection, editing, naming, deletion, and history.
- Validating user input and AI-generated structured output.
- Enforcing conversation-state and workflow rules.
- Applying authorization and business validation.
- Persisting messages, drafts, CV sections, versions, documents, and relevant audit records.
- Coordinating asynchronous processing.
- Managing document-generation and delivery states.
- Exposing authorized operational functionality to the administrative interface.
- Preventing unsupported AI output from directly mutating authoritative records.

The final internal module and service inventory must follow the approved task register. Application directories and files must be introduced only when their implementation tasks are authorized.

### 4.2 WhatsApp gateway

The proposed gateway uses Node.js and Baileys.

Its responsibilities include:

- Managing WhatsApp connectivity.
- Receiving inbound WhatsApp events.
- Handling gateway-level acknowledgement and communication.
- Managing gateway-specific connection and session operations according to the approved contract.
- Delivering outbound WhatsApp messages and document requests.
- Reporting connection and delivery outcomes through the approved integration mechanism.

The gateway must remain separate from Laravel CV business logic. It must not become an alternative authoritative store for CVs or implement independent CV business rules.

The transport, authentication, acknowledgement, timeout, retry, status-update, QR, and session-recovery contracts remain unresolved until P01-T06.

### 4.3 AI integration

Gemini is proposed for controlled language and content assistance.

Its permitted responsibilities include:

- Interpreting English, Kiswahili, and mixed-language input.
- Extracting candidate facts from messages.
- Identifying ambiguity and requesting clarification.
- Expressing supported facts in professional English CV language.
- Checking consistency in proposed CV content.

The AI integration must not:

- Invent employers, qualifications, employment dates, achievements, metrics, skills, references, or other user facts.
- Directly write authoritative database records.
- Bypass Laravel validation, authorization, state transitions, or business rules.
- Execute unsupported operations.
- Modify an existing confirmed snapshot outside the approved version and confirmation workflow.

Structured AI output must be validated against an approved schema before Laravel performs any operation.

Exact intent identifiers, schema fields, confidence handling, retry limits, timeouts, and fallback behavior remain subject to P01-T05 approval.

### 4.4 Document generation

The proposed document-generation workflow creates English-language PDF documents from approved CV content.

It must:

- Use the appropriate confirmed CV version.
- Generate content in English.
- Store the resulting document privately.
- Associate the document with the appropriate user, CV, and version.
- Record generation status separately from WhatsApp delivery status.
- Preserve confirmed content if PDF generation or delivery fails.
- Support approved retry and redelivery behavior without unintentionally creating duplicate CVs or confirmed versions.

The final renderer, layout templates, storage provider, retention policy, and detailed job contracts require review before implementation.

### 4.5 Administrative interface

The administrative interface is for authorized operational use. It is not a general user-facing CV management portal.

The intended scope includes:

- Application and queue health visibility.
- AI, document-generation, and delivery monitoring.
- Gateway connection and session status.
- Authorized WhatsApp account-linking and QR operations.
- Reconnect, disconnect, logout, and recovery operations where permitted.
- Operational error visibility and approved administrative actions.

The administrator roles, permissions, audit requirements, QR exposure controls, and exact operational actions remain pending approval.

## 5. Proposed high-level data flows

The following flows describe intended behavior. Exact request formats, endpoints, queue names, and transition rules must be defined in the relevant P01 design documents.

### 5.1 Inbound WhatsApp message

1. A user sends a WhatsApp message.
2. The Node.js/Baileys gateway receives the event.
3. The gateway acknowledges or reports the event according to the approved gateway contract.
4. The integration forwards the event into the approved Laravel processing path.
5. Laravel validates the event and applies deduplication.
6. Laravel resolves or creates the appropriate user identity according to the approved identity rules.
7. Laravel loads the relevant conversation and language preference.
8. The conversation workflow determines the current state and permitted next action.
9. Where needed, a controlled AI integration interprets the message or proposes structured facts.
10. Laravel validates the result and applies authorized business rules.
11. Valid changes are persisted using the approved transaction and concurrency rules.
12. An appropriate response is queued or sent through the gateway.
13. Processing outcomes and relevant failures are recorded for monitoring.

The exact order of acknowledgement, persistence, queue dispatch, and retries must be settled in the Laravel–Node contract.

### 5.2 CV creation and draft persistence

1. The user requests a new CV or selects an existing CV or draft.
2. Laravel verifies ownership and the permitted workflow state.
3. The system collects approved CV sections incrementally.
4. User input is interpreted and clarified where necessary.
5. Laravel validates and persists accepted facts.
6. The user may pause, cancel, resume, correct, or skip permitted sections according to the approved state machine.
7. The user reviews the proposed content.
8. Explicit confirmation is required before a confirmed snapshot is created.
9. The confirmed snapshot is treated as immutable.
10. Subsequent edits follow the approved versioning workflow and do not alter an existing confirmed snapshot.

The final list of states, commands, transitions, and persistence rules belongs in `docs/AI_CONVERSATION.md`.

### 5.3 PDF generation and delivery

1. An authorized workflow requests generation for the appropriate confirmed CV version.
2. Laravel records the generation request and relevant status.
3. An asynchronous job generates the English PDF.
4. The document is stored using the approved private storage mechanism.
5. The document record and generation outcome are persisted.
6. Delivery is requested through the approved gateway contract.
7. Delivery outcome is recorded separately from generation outcome.
8. A delivery failure can be retried without unnecessarily regenerating an already valid document.
9. An authorized user can request safe redelivery under the approved rules.

Failure handling, idempotency keys, retry limits, and recovery behavior must be defined before implementation.

### 5.4 WhatsApp connection and QR lifecycle

1. An authorized administrator opens the WhatsApp connection controls.
2. The administrative interface requests connection/session status through the approved application contract.
3. Authorized operations are passed through the approved Laravel–gateway integration.
4. QR or connection information is returned only through an approved, access-controlled mechanism.
5. The interface reflects the reported connection state.
6. Reconnect, disconnect, logout, expiry, and recovery follow the approved session lifecycle.
7. Operational events are logged without exposing credentials or sensitive session material.

The QR delivery mechanism, update transport, session ownership, expiry rules, and recovery behavior are not decided in this document.

## 6. Data ownership and consistency

### 6.1 Authoritative data

MySQL 8.4 LTS is the proposed authoritative persistent store.

Laravel must enforce the rules governing users, CV ownership, drafts, CV versions, confirmed snapshots, documents, conversation state, and authorized operations.

The gateway and AI provider must not independently establish authoritative CV facts.

### 6.2 Database design

The source Blueprint proposes a 19-table inventory, documented in `docs/REQUIREMENTS.md`.

That inventory is not an approved schema. P01-T04 must establish the final entities, relationships, primary and foreign keys, constraints, indexes, ownership rules, version semantics, retention behavior, deletion semantics, and migration order.

No migration should be created or applied on the assumption that the proposed inventory is already approved.

### 6.3 Concurrency and idempotency

The architecture must prevent:

- Duplicate account creation for the same normalized identity.
- Repeated processing of the same inbound provider event.
- Rapid messages corrupting the active conversation state.
- Answers being saved into an incorrect CV section.
- Duplicate CV or confirmed-version creation during retries.
- Cross-user access to CVs or generated documents.

The detailed mechanisms—including unique constraints, transaction boundaries, locking, revision checks, event identifiers, and idempotency records—must be specified and reviewed before implementation.

### 6.4 Version integrity

Working drafts and confirmed snapshots must follow distinct lifecycle rules.

A confirmed snapshot must remain unchanged after confirmation. Later edits must follow the approved version-creation process. Document generation and redelivery must reference the intended CV version.

The exact database representation and historical-retention rules remain pending P01-T04 and P01-T05.

## 7. Asynchronous processing and failure isolation

Redis and Laravel Horizon form the proposed queue and queue-monitoring baseline.

Asynchronous work is expected for slow operations such as AI processing and PDF generation, subject to the approved workflow design.

The architecture must define:

- Which operations run asynchronously.
- What is acknowledged synchronously.
- Job payloads and identifiers.
- Idempotency and concurrency behavior.
- Timeouts and bounded retries.
- Failure and terminal states.
- Logging and alerting requirements.
- Recovery behavior for failed or interrupted work.
- The relationship between queued work and persisted workflow state.

A successful queue dispatch must not be treated as proof that the underlying operation succeeded.

Exact queue names, job classes, retry schedules, timeout values, and monitoring thresholds remain unapproved until the relevant implementation and operations decisions are recorded.

## 8. Security and privacy boundaries

The architecture must enforce:

- Server-side authorization for CV ownership and administrative operations.
- Controlled access to generated documents.
- Safe handling of API credentials, environment variables, gateway session credentials, and QR information.
- Redaction of secrets and sensitive personal information from logs.
- Validation of AI output before data mutation.
- Protection against cross-user access.
- Auditable privileged operations.
- Appropriate transaction and concurrency controls.
- Defined consent, retention, deletion, backup, and restoration behavior.

Applicable Tanzanian personal-data protection requirements must be reviewed before production.

Detailed controls, permissions, retention periods, operational thresholds, and security verification procedures belong in `docs/SECURITY.md` and the approved operations plan.

## 9. Deployment and runtime boundaries

The source documents identify Laravel/PHP, MySQL 8.4 LTS, Redis, Laravel Horizon, Node.js/Baileys, Gemini, and private document storage as the target baseline.

The current repository inspection recorded PHP 8.4.15, Composer 2.10.3, Node.js 24.21.0, and npm 11.19.0. The repository was initially inspected as containing a README and Git metadata without an existing Laravel application manifest.

These are historical inspection observations, not proof of current runtime compatibility or service availability.

The following require verification before the relevant implementation tasks:

- Laravel version and package compatibility.
- PHP extensions and Composer dependency requirements.
- Node.js and Baileys compatibility.
- MySQL and Redis service availability.
- Queue configuration and worker operation.
- Gateway-to-Laravel connectivity.
- Gemini configuration and credential handling.
- PDF renderer compatibility.
- Private document storage and retrieval.
- Staging, backup, restoration, and rollback procedures.

Missing local database or Redis command-line tools do not, by themselves, prove that managed or containerized services are unavailable.

No application setup or dependency installation is authorized solely by this architecture draft.

## 10. Observability and operations

The operational design must make relevant failures visible and actionable.

Required areas include:

- Queue health and failed jobs.
- AI request failures, latency, retries, and usage.
- PDF generation outcomes.
- WhatsApp delivery outcomes.
- Gateway connection and session state.
- Application errors and relevant workflow failures.
- Authorized administrative activity.
- Backup and restoration outcomes.
- Deployment and rollback outcomes.

Logs and metrics must avoid unnecessary exposure of CV content, personal data, API credentials, and session secrets.

Exact metrics, alert thresholds, retention periods, recovery objectives, and operational procedures remain subject to P01-T07 review.

## 11. Architecture decisions requiring approval

The following are open decisions, not settled implementation details:

1. Final Laravel version and compatible package set.
2. Final ERD, schema constraints, indexes, versioning, and deletion/retention semantics.
3. Conversation state machine and persistence behavior.
4. Complete AI intent catalogue, schemas, validation, timeouts, retries, and fallbacks.
5. Laravel–Node transport and service-authentication contract.
6. Gateway acknowledgement, event deduplication, and status-update mechanisms.
7. WhatsApp QR/session lifecycle and recovery mechanism.
8. Queue boundaries, job contracts, timeout values, and retry policies.
9. PDF renderer, template behavior, and document-storage provider.
10. Administrator roles, permissions, audit events, and allowed actions.
11. Consent, privacy, retention, and deletion requirements.
12. Monitoring thresholds, backup, restoration, and rollback procedures.
13. Runtime and dependency compatibility.
14. Development, staging, and production service topology.

These decisions must be recorded in the relevant design documents and the project progress register.

## 12. Architecture verification criteria

Before this document is approved:

- Every major component has a defined responsibility and boundary.
- The Laravel application remains authoritative for business rules and persistent CV data.
- The gateway remains isolated from CV business logic.
- AI output is validated before Laravel acts on it.
- English-only CV output and factuality constraints are preserved.
- Draft, confirmation, versioning, generation, and delivery behavior are consistent.
- Ownership, authorization, idempotency, and concurrency concerns are addressed.
- QR/session lifecycle and gateway status-update contracts are explicitly resolved.
- The architecture agrees with the final ERD and conversation/AI contract.
- Security, privacy, monitoring, and recovery requirements are traceable.
- Open decisions and their owners are recorded.
- Review evidence and explicit approval are recorded before dependent implementation begins.

## 13. Approval record

**Document status:** Draft for review — not approved.

| Review item | Status |
|---|---|
| Component responsibilities and boundaries | Pending review |
| High-level data flows | Pending review |
| Laravel–Node integration contract | Pending P01-T06 |
| Database and versioning alignment | Pending P01-T04 |
| Conversation and AI alignment | Pending P01-T05 |
| Security and privacy alignment | Pending P01-T07 |
| Runtime compatibility | Pending verification |
| Open decisions and owners | Pending review |
| User approval | Not granted |
| P01 phase gate | Not approved |
| P02 authorization | Not granted |

**Implementation rule:** This document is a design draft. It does not authorize application coding, dependency installation, database migrations, or progression to P02. Such work may begin only after the relevant prerequisites and the explicit P01 phase gate have been satisfied.