# CareerBotTz Implementation Plan

**Document:** `docs/IMPLEMENTATION_PLAN.md`
**Project:** CareerBotTz
**Source baseline:** Version 1.0, 11 October 2026
**Status:** Draft for review — not approved
**Current phase:** P01 — Requirements and Architecture Approval

## 1. Purpose and source authority

This plan preserves the original eight phases and stable task IDs
defined by the CareerBotTz source documents.

Authoritative sources:

1. `CareerBotTz_System_Design_and_Implementation_Roadmap_v1 (1).pdf`
2. `CareerBotTz_Complete_Implementation_Blueprint.pdf`
3. `CareerBotTz_Codespaces_Roadmap.pdf`

The System Design defines the agreed product and architecture.
The Blueprint supplies requirement traceability and detailed design.
The Codespaces roadmap provides dependency-ordered tasks, file guidance,
verification expectations, and phase gates.

This is a plan, not proof that any task is complete. Task status,
test outcomes, approvals, and technical decisions must be supported
by actual evidence.

## 2. Architectural baseline

The source-defined baseline is:

- Laravel/PHP modular monolith.
- `app/Services/` for application service responsibilities.
- MySQL 8.4 LTS as authoritative persistent storage.
- Redis and Laravel Horizon for asynchronous queues and coordination.
- Separate Node.js/Baileys WhatsApp gateway.
- Gemini API for approved interpretation, extraction, clarification,
  English CV writing, and consistency checks.
- HTML/CSS-based PDF rendering and private document storage.
- Administrative interface for operational visibility and controls.

The baseline does not approve every implementation detail.
Exact runtime/package versions, the final ERD, gateway transport and
authentication, QR/status-update mechanism, AI schemas, administrator
permissions, template behavior, storage choices, and operational
thresholds remain subject to review.

## 3. Execution and status rules

Use the four statuses defined by the Codespaces roadmap:

- **Not started:** no work or verification has been performed.
- **In progress:** work has begun, but acceptance evidence is incomplete.
- **Blocked:** a prerequisite, decision, tool, or test prevents progress.
- **Completed:** deliverables exist, acceptance criteria are met,
  required tests/manual checks pass, and evidence is recorded.

A task is not complete merely because files exist or a command returned
without an obvious error.

Execution rules:

1. Inspect the current repository before changing it.
2. Preserve the root README, Git history, and unrelated work.
3. Work on one bounded task at a time.
4. Verify prerequisites before running a task.
5. Do not install packages, initialize Laravel, or create migrations
   before the relevant approval gates.
6. Do not treat the proposed 19-table inventory as an approved schema.
7. Do not claim a test passed without its actual result.
8. Record evidence, failures, blockers, and next actions.
9. Never place secrets or Baileys session credentials in documentation
   or ordinary logs.
10. Keep original phase and task IDs stable. Substeps may be added
    without replacing the original IDs.
11. Do not advance through a phase gate without explicit approval.
12. P02 must not begin until P01 is explicitly approved.

## 4. Phase overview and gates

| Phase | Original name | Objective | Gate |
|---|---|---|---|
| P01 | Requirements and Architecture Approval | Inspect the environment; prepare requirements and design records; resolve blocking decisions; approve the dependency-ordered backlog. | Required design records are reviewed, blocking contracts are approved or explicitly classified, and P01 is explicitly approved. |
| P02 | Laravel Foundation | Establish a compatible Laravel foundation, approved persistence, queues, tests, health checks, and Git/configuration safeguards. | Application/runtime compatibility is verified; foundational configuration and migrations/tests pass. |
| P03 | WhatsApp Gateway and Identity | Establish the isolated Baileys gateway, identity resolution, inbound/outbound communication, and idempotent event handling. | Gateway contract, identity, event-deduplication, and failure-handling checks pass. |
| P04 | Conversation Engine and Gemini | Implement approved conversation states, language behavior, AI contracts, validation, and recovery. | State, schema, bilingual, unsupported-intent, and outage tests pass. |
| P05 | CV Creation, Drafts, and History | Implement multiple CVs, structured content, draft persistence, ownership, editing, and history. | CV ownership, persistence, editing, concurrency, and history tests pass. |
| P06 | English CV Generation | Implement reviewed English content, explicit confirmation, immutable versions, PDF generation, private storage, and delivery. | English-output, factuality, confirmation, PDF, access, and retry tests pass. |
| P07 | Administration, Reliability, and Security | Implement authorized administration, QR/session operations, monitoring, privacy/security, and recovery. | Admin authorization, QR lifecycle, monitoring, security, and recovery checks pass. |
| P08 | Staging, Pilot, and Production | Deploy to staging, execute acceptance/load/recovery tests, conduct a controlled pilot, and review release readiness. | Required scenarios and operational checks have recorded outcomes, blockers are resolved, and release approval is explicit. |

## 5. P01 — Requirements and Architecture Approval

**Objective:** establish the actual environment, translate the source
design into maintainable documentation, resolve blocking decisions,
and approve the backlog before application code is written.

**Prerequisite:** repository access and the supplied source PDFs.

### P01-T01 — Inspect repository and environment

Work:

- Inspect repository root, Git branch/status, existing files, OS,
  tool versions, PHP modules, and available configuration.
- Record actual outputs and errors.
- Review Composer diagnostics and required extensions.
- Check whether database/Redis services are available locally,
  through containers, or through an approved external service.
- Determine a safe project-creation approach that preserves README
  content and Git history.

Acceptance:

- Actual findings are recorded.
- Missing CLI tools are not treated as proof that remote services
  are unavailable.
- Runtime/package compatibility is assessed before Laravel creation.
- No unobserved component is claimed to be installed or working.
- The proposed project-creation approach is justified.

**Current status:** In progress. Initial inspection outputs have been
received; compatibility assessment and acceptance are not complete.

### P01-T02 — Prepare requirements and traceability documentation

Required deliverables:

- `docs/REQUIREMENTS.md`
- `docs/IMPLEMENTATION_PLAN.md`
- `docs/PROGRESS.md`

Work:

- Preserve Version 1 scope and exclusions.
- Preserve requirement IDs and the eight phases/task IDs.
- Map requirements to tasks and acceptance criteria wherever supported.
- Record all 15 numbered roadmap acceptance scenarios.
- Record the source deletion-scenario discrepancy.
- Record actual inspection findings, unresolved decisions, and evidence.

Acceptance:

- Source requirements map to tasks and acceptance tests where supported.
- Unsupported task mappings remain explicitly uncertain.
- Documents agree on task status, blockers, decisions, and next action.
- Drafts are reviewed and saved with evidence before P01-T02 is complete.

**Current status:** In progress. The documentation drafts have been saved and pushed. Source review and acceptance remain pending; saving/pushing does not constitute approval.

### P01-T03 — Approve user journeys, supported commands, and language rules

Work:

- Review first-contact identity and greeting behavior.
- Review English, Kiswahili, and mixed-language interaction.
- Review language selection and persistence.
- Review create/select/rename/edit/delete/history journeys.
- Review draft save, resume, review, confirmation, pause, back,
  cancellation, skip, help, and recovery behavior.
- Document source-supported journeys and mark unresolved wording
  or state behavior for review.

Deliverables: user-journey and language decision records.

Acceptance:

- English/Kiswahili behavior and English-only CV output are explicit.
- CV creation, editing, review, cancellation, and recovery are reviewed.
- State-dependent commands and fallback behavior are documented.
- Unresolved journey behavior is not silently invented.

### P01-T04 — Finalize ERD and persistence constraints

Work:

- Reconcile the proposed 19-table inventory against the required data.
- Define entities, primary/foreign keys, relationships/cardinalities,
  ownership, uniqueness, indexes, and state/status fields.
- Define transaction boundaries and concurrency requirements.
- Define confirmed-version immutability and document-to-version links.
- Review deletion, retention, data minimization, and migration order.

Deliverable: `docs/DATABASE_ERD.md`.

Acceptance:

- ERD and constraints are explicitly reviewed and approved.
- Every relationship has a documented reason.
- Version/document ownership and deletion semantics are clear.
- Remaining schema decisions are explicitly classified as blockers.
- Dependent migrations do not begin before approval.

### P01-T05 — Approve state machine, AI output contract, and CV lifecycle

Work:

- Define legal conversation states and transitions.
- Define CV draft, review, confirmation, and version lifecycle.
- Define document-generation and delivery states independently.
- Define approved intent identifiers and unknown-intent behavior.
- Define structured AI request/response schemas, required fields,
  validation, confidence handling, unsupported actions, timeouts,
  retries, and recovery.
- Resolve or explicitly retain the 14-versus-13 intent discrepancy.

Deliverable: `docs/AI_CONVERSATION.md`.

Acceptance:

- Every state transition has a trigger, validation rule, persisted
  effect, and recovery path.
- AI outputs are schema-validated before application actions.
- Unsupported actions and fabricated facts are prevented.
- Document-generation and delivery statuses are not conflated.
- The intent discrepancy has an explicit review record.

### P01-T06 — Approve Laravel–Node gateway contract

Work:

- Define Laravel/gateway responsibilities and trust boundaries.
- Decide transport and service-to-service authentication.
- Define inbound acknowledgement, normalized messages, correlation,
  timeouts, errors, retries, and status updates.
- Define session ownership, persistence, and lifecycle boundaries.
- Specify QR/status payload behavior only after the relevant mechanism
  is chosen and approved.

Deliverable: contract specification in `docs/ARCHITECTURE.md`.

Acceptance:

- The contract is reviewed and recorded.
- Gateway business logic remains separate from Laravel CV rules.
- Transport, authentication, and QR/status-update mechanism are not
  treated as approved before a decision is recorded.
- Dependent gateway work remains blocked until its contract is approved.

### P01-T07 — Approve security, testing, and operations requirements

Work:

- Define trust boundaries, authentication, authorization, and service
  credentials.
- Define secrets, QR/session protection, logging, correlation,
  redaction, and AI-context minimization.
- Define consent, retention, deletion, and privacy review needs.
- Define health checks, alerts, backup/restore, recovery, rollback,
  staging, and release checks.
- Define required unit, feature, integration, security, concurrency,
  and acceptance evidence.

Deliverables: `docs/SECURITY.md` and the security/test/operations plan.

Acceptance:

- Every sensitive resource has an access policy.
- Secrets and session credentials are excluded from ordinary logs.
- Privacy and retention decisions are explicitly recorded.
- Recovery and release requirements are defined before dependent work.

### P01-T08 — Finalize dependency-ordered backlog and progress process

Work:

- Reconcile dependencies and acceptance criteria across all phases.
- Preserve all original phase/task IDs.
- Assign responsibility for unresolved decisions.
- Establish progress/evidence and change-control practices.
- Conduct the P01 phase-gate review.

Deliverables: approved task register and P01 gate decision.

Acceptance:

- Required documentation is reviewed and consistent.
- Blocking decisions are resolved or explicitly recorded as blockers.
- Task evidence and status recording are usable.
- P01 is explicitly approved before P02 begins.

## 6. P02 — Laravel Foundation

**Prerequisite:** P01 gate passed; compatible framework/runtime versions
and safe application-creation strategy approved.

Original task range: P02-T01 through P02-T08.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P02-T01 | Confirm PHP, Composer, Laravel, and package compatibility. | Record the compatible version matrix before installation. |
| P02-T02 | Create/configure the Laravel foundation and developer environment safely. | Verify framework setup and configuration; preserve README/Git history; use placeholders only for secrets. |
| P02-T03 | Implement approved foundational migrations. | Fresh migration succeeds against the approved development database; constraints match the approved ERD. |
| P02-T04 | Establish foundational models, relationships, factories, and seeders. | Relationship, factory, and persistence tests pass. |
| P02-T05 | Configure Redis queues and Laravel Horizon. | Queue dispatch/execution and Horizon worker checks pass. |
| P02-T06 | Establish the approved automated-test configuration. | A deliberate smoke test passes. |
| P02-T07 | Add approved health checks and required routes. | Health tests verify dependency status without exposing secrets. |
| P02-T08 | Add logging/correlation and Git/configuration safeguards. | Sensitive files are excluded; ignore rules and tracked-file checks pass. |

The Codespaces roadmap permits configuration or route files to be
created/modified only when required by the selected Laravel version
and approved design. Do not replace the README wholesale.

Do not start P02 before explicit P01 approval.

## 7. P03 — WhatsApp Gateway and Identity

**Prerequisite:** P01 gateway and identity decisions approved; required
P02 foundations available.

Original task range: P03-T01 through P03-T10.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P03-T01 | Establish the isolated Node.js/Baileys gateway foundation. | Gateway setup and controlled startup checks. |
| P03-T02 | Implement protected Baileys session lifecycle/storage. | Session persistence, permissions, and recovery checks. |
| P03-T03 | Implement approved QR/linking and connection lifecycle behavior. | QR/session behavior matches the approved contract. |
| P03-T04 | Implement gateway configuration and safe operational boundaries. | Secrets/session state are protected; configuration checks pass. |
| P03-T05 | Define Laravel's WhatsApp gateway interface and approved adapter. | Contract tests for the chosen transport, authentication, timeouts, and retries. |
| P03-T06 | Normalize incoming WhatsApp messages into the approved contract. | Fixture-based normalization tests. |
| P03-T07 | Authenticate inbound requests and persist/deduplicate webhook events. | Request validation, unique-event, and idempotency tests. |
| P03-T08 | Implement inbound/outbound message processing and identity integration. | New/returning-user, acknowledgement, and response tests. |
| P03-T09 | Implement connection/error handling and operational status behavior. | Failure, reconnect, and status checks against the approved contract. |
| P03-T10 | Test gateway integration, identity, duplicate events, and failures. | Required P03 contract/integration tests pass with evidence. |

**Mapping note:** the Codespaces roadmap distributes gateway details
across its task and file tables. If a task label above cannot be matched
unambiguously to its exact source row during review, retain the original
ID and flag that row for source-page verification rather than renumbering
or silently changing it.

Acceptance for P03:

- A valid new user is created once and returning users resolve correctly.
- Phone identity is normalized according to the approved contract.
- Replayed inbound events do not repeat business effects.
- Gateway acknowledgement is independent of slow AI processing.
- Baileys connectivity remains separate from CV business rules.
- Session credentials and QR information are protected.
- Errors and reconnect behavior have recorded verification evidence.

## 8. P04 — Conversation Engine and Gemini

**Prerequisite:** P02 gate passed and the P03 normalized-message contract
is available. P03/P04 may share contract tests but neither may bypass
its required gate.

Original task range: P04-T01 through P04-T10.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P04-T01 | Define typed intent/result objects. | Valid and invalid interpretation fixtures. |
| P04-T02 | Implement AI provider abstraction and Gemini adapter. | Provider contract tests. |
| P04-T03 | Implement intent classification. | All approved intents and unknown intent are tested. |
| P04-T04 | Implement extraction and clarification. | Ambiguous answers trigger clarification instead of unsupported changes. |
| P04-T05 | Implement the conversation state machine. | Illegal transitions are rejected. |
| P04-T06 | Implement conversation-language handling. | English, Kiswahili, and mixed input behavior is tested. |
| P04-T07 | Implement help, back, pause, cancel, and skip. | Commands behave correctly in applicable states. |
| P04-T08 | Implement serialized incoming-message orchestration. | Transactional processing and concurrency tests. |
| P04-T09 | Add AI usage limits, timeouts, and bounded retries. | Rate, timeout, usage, and retry tests. |
| P04-T10 | Test bilingual input, malformed AI output, and outages. | No fabricated actions; saved data survives AI failure. |

Gemini may interpret and propose structured results. Laravel validates
the result, checks authorization/state, and executes only an allowed
action. Gemini cannot directly mutate the database or invoke privileged
actions by itself.

## 9. P05 — CV Creation, Drafts, and History

**Prerequisite:** approved ERD, relevant P02/P03/P04 foundations, and
approved ownership/concurrency rules.

Original task range: P05-T01 through P05-T11.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P05-T01 | Implement CV creation/selection and CV service orchestration. | Multiple named CVs remain distinct; ownership tests pass. |
| P05-T02 | Implement structured personal details. | Validation and ownership tests. |
| P05-T03 | Implement education records. | Ordering, validation, and date tests. |
| P05-T04 | Implement work-experience records and preserve source facts. | No-fabrication and optional-experience tests. |
| P05-T05 | Implement skills and approved skill categories. | Validation and ownership tests. |
| P05-T06 | Implement projects, certifications, languages, and references as approved. | Relationship, validation, and privacy tests. |
| P05-T07 | Implement approved optional/custom CV sections. | Allowed-section and schema tests. |
| P05-T08 | Implement incremental draft persistence and resume behavior. | Interruption and multiple-draft tests. |
| P05-T09 | Implement authorized content editing/correction and concurrency safeguards. | Correct section updates; concurrent updates do not corrupt data. |
| P05-T10 | Implement read-only CV history/version queries as required. | History is read-only and ownership-scoped. |
| P05-T11 | Complete CV workflow, ownership, and persistence verification. | Required P05 feature and database tests pass. |

The precise file/service boundaries must follow the approved design.
The Codespaces roadmap explicitly warns against creating duplicate
services merely because both appear in a proposed inventory.

Acceptance for P05:

- Multiple named CVs and drafts remain distinct.
- Draft answers persist after interruptions.
- Users select which draft to resume.
- Core sections persist; optional sections can be omitted as approved.
- No work experience is fabricated.
- CV operations are scoped to the owner.
- Corrections do not mutate confirmed snapshots.
- Concurrency tests prevent cross-field corruption.
- Historical versions remain read-only.

## 10. P06 — English CV Generation

**Prerequisite:** P05 CV domain is stable; P04 AI contracts are approved;
private document storage and template behavior are decided.

Original task range: P06-T01 through P06-T10.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P06-T01 | Define generated CV content schema. | Schema validation tests. |
| P06-T02 | Implement professional English CV writing. | Kiswahili source facts produce English CV content. |
| P06-T03 | Check factual consistency. | Unsupported qualifications or facts are rejected/not introduced. |
| P06-T04 | Implement content review and explicit confirmation workflow. | No confirmed version without explicit confirmation. |
| P06-T05 | Implement immutable confirmed versions. | Version uniqueness and immutability tests. |
| P06-T06 | Implement approved CV template resolution/rendering inputs. | Template selection tests; template choice remains a prior decision. |
| P06-T07 | Implement PDF generation and generation-job behavior. | Rendering and failure/retry tests. |
| P06-T08 | Implement private document storage and document metadata/state. | Unauthorized access, status, and version-link tests. |
| P06-T09 | Implement WhatsApp delivery and independent redelivery behavior. | Delivery retry does not require unnecessary regeneration. |
| P06-T10 | Test English output, snapshots, PDF, storage, and delivery end-to-end. | Required P06 tests pass and evidence is recorded. |

Acceptance:

- Only explicitly confirmed content creates an immutable snapshot.
- Generated CV content and PDFs remain English.
- Source facts are preserved; unsupported facts are not added.
- PDF generation references the correct confirmed version.
- Documents remain private.
- Generation and delivery statuses are independent.
- Delivery failure does not invalidate a generated document.
- Redelivery does not create a new CV or confirmed version.

## 11. P07 — Administration, Reliability, and Security

**Prerequisite:** application, gateway, conversation, and document
workflows exist sufficiently for operational monitoring; the approved
administrator role/permission model is available.

Original task range: P07-T01 through P07-T10.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P07-T01 | Establish administrator authentication and authorization. | Unauthenticated/unauthorized requests are rejected. |
| P07-T02 | Build the operational dashboard. | Health, queue, AI, document, and delivery data are accurate. |
| P07-T03 | Build the WhatsApp Connection/Link Account page. | QR and connection states render correctly under the approved contract. |
| P07-T04 | Implement serialized connection lifecycle controls. | Conflicting connect/logout/reconnect operations are prevented. |
| P07-T05 | Monitor and retry queue failures. | Failed jobs are inspectable and retryable within policy. |
| P07-T06 | Monitor AI usage, documents, and delivery. | Failure categories and usage metrics are visible without sensitive data. |
| P07-T07 | Implement approved retention and deletion rules. | Deletion/retention tests match the approved policy. |
| P07-T08 | Add structured logs, metrics, and audit behavior as approved. | Correlation, audit integrity, and redaction checks pass. |
| P07-T09 | Complete security/privacy/recovery controls and operational documentation as allocated by the source plan. | Security and recovery evidence is recorded; exact source mapping is checked during review. |
| P07-T10 | Complete P07 operational/security verification and readiness review. | Required P07 checks pass; blockers and evidence are recorded. |

**Mapping note:** preserve the original P07 task IDs. If the exact
P07-T09/T10 wording is not confirmed from the source task table during
review, verify it against the PDF rather than silently replacing the
original task meaning.

Acceptance:

- Admin routes enforce server-side authorization.
- QR codes and session credentials are protected.
- QR expiry/refresh, status, reconnect, disconnect, logout, and recovery
  work according to the approved contract.
- Operational failures are visible and actionable.
- Consent/retention behavior matches approved policy.
- Backup/restore and rollback procedures have recorded evidence.

## 12. P08 — Staging, Pilot, and Production

**Prerequisite:** P07 security and operations readiness and all
preceding phase gates have passed.

Original task range: P08-T01 through P08-T08.

| Task | Source-defined work | Verification expectation |
|---|---|---|
| P08-T01 | Provision staging environment. | Required services are reachable and secrets are configured securely. |
| P08-T02 | Deploy application, migrations, workers, gateway, and admin interface. | Versioned deployment procedure and health/smoke tests pass. |
| P08-T03 | Execute all source acceptance scenarios. | Each scenario has a recorded outcome and defect record. |
| P08-T04 | Run load and concurrency tests. | No unacceptable duplication, data loss, or cross-user access. |
| P08-T05 | Measure Gemini, PDF, and delivery performance. | Results are compared with approved thresholds. |
| P08-T06 | Demonstrate backup restoration and deployment rollback. | Restore and rollback evidence is recorded. |
| P08-T07 | Conduct a controlled pilot and resolve release-blocking defects. | Pilot outcomes and unresolved risks are recorded. |
| P08-T08 | Complete release checklist and approval. | Release readiness is explicitly approved by the release owner. |

Production is not approved merely because deployment succeeds.
All required tests, recovery evidence, operational measurements,
documentation, and explicit release approval are required.

## 13. Required seven documentation files and sequence

The seven source-required documents are:

| Document | Purpose | Preparation/approval sequence |
|---|---|---|
| `docs/REQUIREMENTS.md` | Requirements, scope, requirement IDs, acceptance criteria, traceability. | P01-T02 draft; reviewed before P01 gate. |
| `docs/ARCHITECTURE.md` | Component boundaries, responsibilities, data flow, integration contracts. | Prepare/update during P01-T03/T05/T06. |
| `docs/DATABASE_ERD.md` | Entity inventory, ERD, relationships, constraints, ownership, retention, migration order. | P01-T04; approve before dependent migrations. |
| `docs/AI_CONVERSATION.md` | Languages, intents, schemas, state machine, CV lifecycle, recovery. | P01-T03/T05; approve before P04 implementation. |
| `docs/SECURITY.md` | Trust boundaries, authorization, secrets, privacy, retention, backups, tests. | P01-T07; approve before dependent sensitive operations. |
| `docs/IMPLEMENTATION_PLAN.md` | Phases, tasks, dependencies, acceptance criteria, gates, change control. | P01-T02/T08. |
| `docs/PROGRESS.md` | Evidence, status, blockers, decisions, approvals, next action. | Update continuously from P01 onward. |

Drafting order may overlap where independent, but dependent decisions
must be approved before their implementation. The seven documents must
remain consistent.

## 14. Testing strategy and evidence

Testing is designed alongside implementation.

Required test categories include:

- Unit tests for domain rules, validation, normalization, state
  transitions, and version rules.
- Feature tests for routes, authorization, webhook ingestion, and
  administrative actions.
- Database tests for relationships, unique constraints, deletion rules,
  and transactions.
- Gateway contract tests for normalized messages, QR/status payloads,
  send results, and session errors.
- Gemini contract tests for valid/malformed JSON, missing fields,
  unsupported intents, low confidence, and timeouts.
- PDF tests for template rendering, optional sections, file validity,
  and content correctness.
- Queue/reliability tests for retries, idempotency, duplicate events,
  and failed jobs.
- Concurrency tests for duplicate registration, parallel conversation
  updates, and session operations.
- Admin tests for login, permissions, QR lifecycle, and dashboard errors.
- End-to-end tests for WhatsApp-to-CV-to-PDF.
- Operational tests for health, backup, restoration, alerts, and rollback.

Mocks and fakes support deterministic failure tests but do not replace
controlled integration tests for real Baileys connectivity, Gemini,
PDF rendering, storage, or production-like queue behavior.

The 15 numbered acceptance scenarios are `ACC-01` through `ACC-15`.
The separate deletion scenario in the System Design must remain tracked
until the source discrepancy is reviewed.

For every test, record the actual procedure/command, environment,
result, and relevant evidence. A planned or unexecuted test is not
passed.

## 15. Dependency and decision register

| Decision/dependency | Required before | Status |
|---|---|---|
| Repository and runtime inspection | Project-creation decision | Initial results received; acceptance pending. |
| PHP/framework/package compatibility | P02-T01/T02 | Pending assessment. |
| Safe Laravel root-creation approach | P02-T02 | Pending; preserve README and Git history. |
| Final ERD and migration order | P02-T03 and dependent data work | Pending P01-T04. |
| Conversation state machine | P04 implementation | Pending P01-T05. |
| AI schemas, intent catalogue, retry/fallback behavior | P04 implementation | Pending P01-T05. |
| Laravel–Node transport/authentication contract | P03 integration | Pending P01-T06. |
| QR/session lifecycle and status-update mechanism | P03/P07 dependent work | Pending decision. |
| CV template behavior | P06-T06 | Pending confirmation. |
| Administrator roles/permissions | P07-T01 | Pending approval. |
| Private document storage | P06-T08 | Pending decision. |
| Consent, retention, and deletion policy | Dependent privacy/data work | Pending P01-T07. |
| Monitoring, recovery, capacity thresholds | P07/P08 readiness | Pending definition and approval. |
| Local/remote/container service availability | Dependent integration work | Not established by missing local CLI tools. |
| 14-versus-13 intent discrepancy | P04 intent contract | Open. |
| 16 prose scenarios versus 15 numbered ACC IDs | Acceptance planning | Open; preserve ACC-01 to ACC-15 and deletion separately. |

## 16. Phase gates

A phase gate is an explicit review, not an automatic consequence of
finishing the last numbered task.

### P01 gate

Required before P02:

- Repository/runtime inspection and compatibility assessment recorded.
- Requirements and traceability reviewed.
- User journeys and language rules reviewed.
- ERD and persistence decisions approved.
- State machine, AI contract, and CV lifecycle approved.
- Laravel–Node gateway contract approved.
- Security, privacy, testing, and operations requirements reviewed.
- Backlog dependencies and evidence process reviewed.
- Open decisions resolved or explicitly classified as blockers.
- Explicit approval to begin P02 recorded.

### P02–P07 gates

Each gate requires task-level acceptance evidence, relevant tests,
current documentation, and resolution or explicit classification of
blocking issues.

### P08 release gate

Required:

- All eight phases have completion evidence.
- All numbered source acceptance scenarios have recorded outcomes.
- Critical security, data-integrity, and factuality defects are resolved.
- QR/session lifecycle and admin permissions pass tests.
- Backups, restoration, and rollback have been demonstrated.
- Operational thresholds have been measured and accepted.
- Privacy and retention decisions have been reviewed.
- Production configuration has been checked without exposing secrets.
- Required documentation is current.
- The release owner explicitly approves deployment.

## 17. Change control

Changes to scope, architecture, requirements, task order, schema,
integration contracts, acceptance tests, or release criteria must be
reviewed and recorded.

Each change must identify affected requirement/task IDs, rationale,
dependencies, test impact, decision status, and documentation updates.
Original task IDs must remain stable.

## 18. Current execution status and authorization

**Current phase:** P01 — Requirements and Architecture Approval.
**Current task:** P01-T08 — Finalize dependency-ordered backlog and progress process.
**P01-T01:** In progress; initial inspection was performed, but
compatibility assessment and acceptance are incomplete.
**P01-T02:** In progress; documentation files have been saved and pushed; source-to-draft review and acceptance evidence remain pending.
**P01 gate:** Not approved.
**P02:** Blocked.

This plan authorizes documentation review only. It does not authorize
Laravel initialization, package installation, migrations, application
code, or production configuration.

## 19. Approval record

| Review item | Status |
|---|---|
| Phase/task IDs and task meanings | Pending source-to-draft review |
| Dependencies and phase gates | Pending review |
| Requirement/task/test traceability | Pending review |
| Source acceptance discrepancies | Open |
| Blocking decisions classified | Pending |
| User approval | Not granted |
| P01 gate | Not approved |
