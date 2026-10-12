
# CareerBotTz AI and Conversation Contract

**Document:** `docs/AI_CONVERSATION.md`
**Project:** CareerBotTz
**Source baseline:** Version 1.0, 11 October 2026
**Status:** Draft for review — not approved
**Current phase:** P01 — Requirements and Architecture Approval
**Task:** P01-T05 — Define AI and conversation contract

## 1. Purpose

This document defines the proposed conversation, AI interpretation, language, CV lifecycle, validation, and recovery rules for CareerBotTz.

It must remain consistent with:

- `CareerBotTz_System_Design_and_Implementation_Roadmap_v1 (1).pdf`
- `CareerBotTz_Complete_Implementation_Blueprint.pdf`
- `CareerBotTz_Codespaces_Roadmap.pdf`
- `docs/REQUIREMENTS.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE_ERD.md`
- `docs/IMPLEMENTATION_PLAN.md`

This document is a design draft. It does not authorize implementation, provider integration, Gemini API calls, database migrations, or production deployment.

The final conversation state machine, AI response schema, intent catalogue, confidence thresholds, and unresolved workflow decisions must be reviewed before implementation.

## 2. Core principles

The conversation and AI design must follow these principles.

1. **User facts remain authoritative.** AI may interpret, organize, clarify, and professionally rewrite information supplied by the user. It must not invent qualifications, employment, dates, achievements, skills, certifications, projects, references, or other personal facts.

2. **Conversation language and CV language are separate.** A user may communicate in English, Kiswahili, or a mixture of both. The generated CV must be written in English.

3. **Laravel owns business decisions.** AI proposes structured interpretations. Laravel validates the output, checks authorization, applies business rules, persists accepted changes, and controls state transitions.

4. **Ambiguity must be handled safely.** If a message has multiple plausible meanings or lacks information necessary for a safe action, ask a clarification question rather than making an unsupported assumption.

5. **Drafts must be recoverable.** Accepted information must be persisted according to the approved persistence rules so that a user can resume an incomplete CV after an interruption.

6. **CV ownership must be enforced.** A user must not access or modify another user's CV, version, or generated document.

7. **Confirmation must be explicit.** Reviewing a CV or receiving an AI-generated preview does not, by itself, authorize creation of a confirmed immutable version.

8. **Retries must be safe.** Reprocessing an inbound event or retrying document delivery must not unintentionally create duplicate users, CVs, confirmed versions, or business operations.

9. **Optional information must remain optional where approved.** The absence of employment history or another optional section must not cause AI to fabricate information to fill a gap.

10. **Unresolved decisions remain visible.** This draft must not turn unspecified behavior into an approved requirement.

## 3. Supported conversation languages

### 3.1 Conversation languages

The intended conversation experience supports:

- English.
- Kiswahili.
- Mixed English and Kiswahili within the same conversation.

The system should interpret the meaning of the user's message rather than requiring every message to use one language consistently.

### 3.2 Language preference

The conversation design must define how the system identifies, stores, and updates a user's preferred conversation language.

The exact precedence between an explicitly selected language, a previously stored preference, and the language detected in a new message remains an approval item.

The system must not confuse the language used in a conversation with language-proficiency entries that may appear in a CV.

### 3.3 Response language

User-facing questions, instructions, clarification requests, and status messages should follow the approved conversation-language rules.

The exact behavior when a user switches languages during an active workflow must be specified and tested before implementation.

### 3.4 CV output language

All generated CV content must be in English.

AI may translate or professionally rewrite user-provided information into English, provided the meaning remains faithful to the source facts.

The English-only rule applies to the CV document. It does not require the entire WhatsApp conversation to be in English.

## 4. Conversation intent contract

### 4.1 Purpose of intent classification

Intent classification identifies what the user is trying to do in the current conversation.

An AI interpretation is a proposal, not authorization to execute an operation. The application must validate the proposed intent against the current state, the user's ownership, required information, and the approved business rules.

### 4.2 Proposed intent groups

The following groups provide a working catalogue for review. They are not a final approved list of intent identifiers.

| Intent group | Intended meaning |
|---|---|
| Greeting or start | Begin or resume an appropriate interaction |
| Create CV | Start creating a new CV |
| List CVs | Show the user's available CVs |
| Select or resume CV | Select an existing CV or draft explicitly |
| Provide CV information | Supply information for the current step |
| Clarify information | Answer a clarification question |
| Edit CV | Change information in an owned working CV |
| Rename CV | Change the name of an owned CV |
| Delete CV | Request deletion of an owned CV, subject to the approved confirmation and retention policy |
| View CV history | List available CV versions |
| Review CV | Request a review or preview of working content |
| Confirm CV | Explicitly confirm the reviewed content |
| Generate or redeliver document | Request document generation or delivery where the workflow permits it |
| Help or navigation | Request help or an applicable navigation action |
| Unknown or unsupported | Express an unclear, unsupported, or unrecognized request |

The source documents contain an unresolved difference in the stated intent count. The final count and exact identifiers must be reconciled against the source acceptance scenarios and approved requirements before this catalogue is frozen.

The groups above must not be interpreted as proof that a specific count or final enumeration has been approved.

### 4.3 Intent validation

Before an operation is executed, the application must determine:

- Whether the intent is supported.
- Whether the current conversation state permits it.
- Whether the required target CV can be identified unambiguously.
- Whether the target belongs to the requesting user.
- Whether required arguments are present and valid.
- Whether explicit user confirmation is required.
- Whether the operation has already been applied to the same inbound event.

Unsupported, unauthorized, incomplete, or conflicting operations must not be executed.

## 5. AI interpretation and structured output

### 5.1 Responsibility of AI

Gemini is intended to assist with:

- Interpreting English, Kiswahili, and mixed-language messages.
- Proposing a structured intent and relevant arguments.
- Extracting CV facts from user-provided text.
- Identifying missing, ambiguous, or conflicting information.
- Suggesting clarification questions.
- Professionally rewriting supported facts into English CV wording.
- Checking generated wording for consistency with supplied facts.

AI must not be the authoritative store for CV data or the authority that independently changes application state.

### 5.2 Proposed interpretation fields

The final AI response schema must be reviewed and approved before implementation.

At a minimum, the schema design must consider these logical fields:

| Field | Purpose |
|---|---|
| `intent` | Proposed intent identifier |
| `arguments` | Structured arguments relevant to the proposed intent |
| `language` | Detected or proposed conversation-language classification |
| `confidence` | Model confidence information, if approved and meaningfully defined |
| `missing_information` | Required information that is missing |
| `ambiguities` | Unresolved interpretation issues |
| `clarification_required` | Whether clarification is proposed |
| `proposed_response` | Optional user-facing response proposal |
| `source_facts` | References to or representations of facts supporting the proposed interpretation |

These are proposed logical fields, not an approved JSON schema. Exact property names, data types, enumerations, nullability, validation rules, and required fields remain open decisions.

A model-provided confidence value must not be treated as a calibrated probability unless that interpretation has been validated.

### 5.3 Validation boundary

The application must validate model output before using it.

Validation must cover:

- Schema conformance.
- Supported intent identifiers.
- Expected argument types and permitted values.
- Required arguments.
- Valid references to the active user, conversation, and CV.
- Consistency with the current workflow state.
- Ownership and authorization.
- Explicit confirmation requirements.
- Factual support for proposed CV content.

Malformed output must not be applied as a business operation.

### 5.4 Factuality and provenance

Every CV claim produced by AI must remain traceable to information supplied or explicitly confirmed by the user.

Professional rewriting may improve grammar, clarity, structure, and presentation. It must not increase the user's qualifications or experience beyond the evidence provided.

When the source information is insufficient, the system should ask a relevant question or omit an optional section according to the approved rules.

The design must determine how supporting facts are represented and retained without storing unnecessary sensitive information.

## 6. Clarification and conflict handling

### 6.1 When clarification is required

Clarification should be considered when:

- A message has multiple plausible interpretations.
- A required field is missing.
- A date, qualification, employer, or other fact is contradictory.
- Multiple CVs or drafts could match the user's request.
- A requested edit does not identify the intended field or entry.
- The target operation is not valid in the current state.
- The user requests an action whose consequences require explicit confirmation.

### 6.2 Clarification behavior

The system should ask a focused question that explains what information is needed.

Where practical, the user should be able to answer without repeating information already accepted into the working draft.

The system must preserve previously accepted information while waiting for clarification.

The exact limits on repeated clarification, fallback behavior, and escalation after unresolved ambiguity remain approval items.

### 6.3 Conflicting information

When new information conflicts with previously accepted information, the application must not silently overwrite the existing value based only on an AI inference.

The approved rules must determine when the system can accept a clear correction directly and when it must ask the user to resolve the conflict.

The handling of corrections to an already confirmed CV must preserve the immutability of that confirmed snapshot.

## 7. Proposed conversation state machine

### 7.1 State-machine purpose

The state machine defines the workflow stage and which actions are permitted at that stage.

The following is a proposed logical model. Exact state identifiers, transition guards, persistence representation, and transition coverage must be reviewed before approval.

### 7.2 Proposed logical states

| Logical state | Meaning |
|---|---|
| `READY` | No active CV step is being completed; the user can choose an applicable action |
| `AWAITING_CV_SELECTION` | The user must identify a CV or draft where more than one candidate exists |
| `COLLECTING_INFORMATION` | The system is collecting information for a working CV |
| `AWAITING_CLARIFICATION` | The system needs the user to resolve a specific ambiguity |
| `EDITING_DRAFT` | The user is editing an existing working CV |
| `REVIEWING_CV` | The working CV is being presented for review |
| `AWAITING_CONFIRMATION` | Explicit confirmation or a permitted revision is required |
| `CONFIRMED` | A confirmed immutable version exists |
| `GENERATING_DOCUMENT` | Document generation is in progress |
| `DOCUMENT_READY` | A document has been generated and is available subject to authorization |
| `DELIVERY_PENDING` | Delivery is awaiting processing or retry |
| `DELIVERED` | Delivery has been recorded as successful |
| `PAUSED` | The user has paused the active workflow |
| `CANCELLED` | The applicable workflow has been cancelled |
| `RECOVERY_REQUIRED` | A recoverable interruption or processing problem requires safe resumption |

These labels are proposed design identifiers. They are not approved database enum values or implementation constants.

Conversation state, CV lifecycle state, document-generation state, and delivery state must remain conceptually distinct even if the final implementation uses a different representation.

### 7.3 Transition rules

Every permitted transition must specify:

- Source state.
- Trigger or user action.
- Required input.
- Validation and authorization guards.
- State and data changes.
- User-facing response.
- Failure behavior.
- Retry and idempotency behavior.

An invalid transition must not mutate CV data or create a confirmed version.

### 7.4 State persistence

The approved design must identify the authoritative location for conversation state and how it relates to the working CV.

State changes and accepted data changes must be persisted safely.

A retry or delayed message must not advance the workflow twice or apply an answer to the wrong CV.

The exact concurrency and message-ordering strategy remains subject to the gateway, queue, and persistence contracts.

## 8. CV lifecycle

### 8.1 Creating a CV

A user may create multiple CVs, each with a distinct identity and user-selected name.

The system must not assume that a new CV request means the user wants to overwrite an existing CV.

If the target of an operation is ambiguous, the user must select the intended CV explicitly.

### 8.2 Collecting and saving information

The system should collect information incrementally and preserve accepted answers in the relevant working draft.

The approved CV structure must account for applicable core sections and optional sections.

The design must define how incomplete answers, corrections, skipped optional sections, and interrupted sessions are represented.

AI must not fabricate information to complete a section.

### 8.3 Multiple education and experience entries

The conversation design must support multiple education and work-experience entries where required by the approved CV requirements.

The user must be able to clarify or edit the intended entry without accidentally changing a different entry.

A user without employment history must be able to proceed without invented work experience.

### 8.4 Editing a working draft

Editing must target an explicitly identified, user-owned CV.

Accepted edits must update the appropriate working content according to the approved persistence rules.

An edit to a working draft must not silently modify a previously confirmed immutable snapshot.

The exact treatment of edits made after confirmation, including the creation of a new working revision, must be approved before implementation.

### 8.5 Review and confirmation

The user must be able to review the CV content before confirmation.

The review should make the proposed CV content understandable enough for the user to identify corrections.

Only an explicit, valid confirmation may authorize creation of a confirmed immutable snapshot.

The design must define what happens if the user requests a correction during review, declines confirmation, or abandons the workflow.

### 8.6 CV history

The system must support listing relevant CV history according to the approved access and retention rules.

Historical confirmed versions must not be changed by subsequent edits to a working draft.

The exact version numbering, snapshot representation, and retention behavior remain open decisions.

### 8.7 Rename and deletion

Rename and deletion operations must target an identified CV owned by the user.

The final design must define confirmation requirements, effects on associated versions and documents, and the applicable retention policy.

No hard-delete, soft-delete, cascade-delete, or retention-period behavior is approved by this draft.

## 9. Document generation and delivery lifecycle

### 9.1 Generation eligibility

Document generation must use the intended confirmed CV version according to the approved workflow.

The generated CV must be in English.

A generated document must be associated with the correct CV and version so that later edits cannot silently change the historical content represented by that document.

### 9.2 Separate generation and delivery states

Document generation and delivery are different operations and must have separate lifecycle tracking.

The final status vocabulary remains to be approved, but the design must distinguish at least:

- Generation requested.
- Generation in progress.
- Generation succeeded.
- Generation failed.
- Delivery pending.
- Delivery in progress.
- Delivery succeeded.
- Delivery failed or awaiting retry.

These are logical status concepts, not a finalized enum or database schema.

A successful generation must not be recorded as successful delivery unless delivery has actually succeeded.

### 9.3 Retry and redelivery

A retry must use the intended document and confirmed CV version.

Retrying delivery must not create a duplicate CV or confirmed version.

The system must define retry limits, failure classification, idempotency, and the user-facing behavior after repeated failures.

### 9.4 Document access

Generated documents must be stored and delivered through the approved private-storage and authorization design.

A user must not be able to retrieve another user's document.

Storage paths, download mechanisms, expiry rules, and retention behavior remain subject to the security and operations approval.

## 10. Conversation commands and navigation

The requirements identify help, cancellation, pause, back, and skip behavior. The following command names are proposed examples for review, not a final command parser specification.

| Command | Intended behavior |
|---|---|
| `help` | Explain applicable actions in the current state |
| `pause` | Pause the applicable active workflow without discarding accepted data |
| `back` | Return to an allowed previous step where safe |
| `cancel` | Cancel the applicable operation or workflow according to approved rules |
| `skip` | Skip an optional field or section only when permitted |
| `resume` | Resume an eligible paused or interrupted workflow |
| `list` | Request a list of the user's CVs where applicable |

### 10.1 Command applicability

Commands must be interpreted in context.

For example, `skip` must not bypass a mandatory field or explicit confirmation requirement.

`back` must not reverse a completed external action or mutate a confirmed historical snapshot.

`cancel` must not be assumed to mean deletion of the user's saved CV.

The exact meaning of each command, supported aliases, language variants, and state-specific behavior must be approved.

### 10.2 Natural-language equivalents

Users may express an action in ordinary English, Kiswahili, or mixed language rather than using an exact command.

The intent interpreter may propose the corresponding supported action, but the application must still enforce state, validation, ownership, and confirmation rules.

## 11. Interruption, failure, and recovery

### 11.1 Interrupted conversations

If the user stops responding or the service is interrupted, previously accepted information must remain recoverable according to the approved persistence and retention rules.

On return, the system must identify the eligible workflow and avoid applying a new answer to an unrelated CV.

If several drafts or workflows are eligible, the user must select the intended one explicitly.

The inactivity period, session-expiry policy, and resume-selection behavior remain open decisions.

### 11.2 AI failure

If Gemini is unavailable, times out, or returns invalid output:

- Do not apply unvalidated model output.
- Preserve already accepted data.
- Record the failure using the approved privacy-aware logging policy.
- Return a suitable user-facing response where possible.
- Retry only according to the approved retry policy.
- Avoid repeating an already-applied business operation.

The exact retry count, delay, timeout, and fallback policy must be specified before implementation.

### 11.3 Queue and gateway failure

The Node.js/Baileys gateway is intended to remain separate from Laravel business logic.

Gateway acknowledgement, message ingestion, asynchronous processing, and user-facing delivery must follow the approved integration contract.

The gateway must not independently implement CV business rules.

The design must define how failed processing, duplicate provider events, outbound delivery failures, and reconnection are recovered without losing accepted information or repeating business effects.

### 11.4 Recovery safety

Recovery must distinguish between:

- An event that has not yet been applied.
- An event that has been accepted and persisted.
- An operation that is still in progress.
- An operation that completed but whose response or delivery failed.
- An operation whose outcome cannot yet be determined.

The final design must define how each condition is detected and reconciled. An uncertain result must not automatically trigger a duplicate irreversible action.

## 12. Security, privacy, and logging

The conversation and AI design must comply with the approved security requirements.

It must:

- Enforce user ownership for CVs, versions, and documents.
- Validate model output before business operations.
- Avoid exposing one user's personal information to another user.
- Minimize unnecessary personal data sent to AI services.
- Avoid logging unnecessary raw CV content or sensitive message data.
- Record relevant AI failures and operational outcomes according to the approved logging policy.
- Keep secrets and credentials out of conversation records and source-controlled documentation.
- Define retention and deletion behavior before production.

Consent requirements, data-retention periods, redaction rules, and applicable Tanzanian personal-data protection obligations require review before production.

This draft does not establish final legal conclusions or approved consent wording.

## 13. Testing and acceptance expectations

The final contract must be testable against the approved requirements and source acceptance scenarios.

At a minimum, verification must cover the following areas.

| Test area | Expected result |
|---|---|
| Language handling | English, Kiswahili, and mixed-language messages are handled according to the approved rules |
| English CV output | Generated CV wording is in English |
| Intent validation | Unsupported or malformed AI interpretations are rejected |
| Ambiguity | Unclear requests trigger clarification or safe handling |
| Factuality | Unsupported qualifications, dates, achievements, and experience are not invented |
| Multiple CVs | A user's separate CVs remain distinct |
| Draft persistence | Accepted information survives an eligible interruption |
| Draft selection | An ambiguous draft target requires explicit selection |
| Editing | An edit affects only the intended working CV and entry |
| Optional sections | Permitted omissions do not lead to fabricated information |
| Confirmation | Only explicit confirmation creates a confirmed immutable snapshot |
| Version integrity | Later edits do not alter a historical confirmed snapshot |
| Ownership | Cross-user CV and document access is denied |
| Commands | Commands are accepted only in applicable states |
| Duplicate events | Replayed inbound events do not repeat business effects |
| AI failure | Invalid or failed AI operations do not corrupt persisted state |
| Document lifecycle | Generation and delivery outcomes remain distinct |
| Redelivery | A delivery retry does not create a duplicate confirmed version |
| Recovery | Interrupted workflows resume safely according to the approved rules |

These are planned verification expectations, not evidence that tests have already passed. Actual test cases, results, and evidence must be recorded during implementation.

## 14. Open decisions and blockers

The following items must be resolved or explicitly assigned before this contract is approved.

1. Reconcile the source discrepancy in the number and enumeration of intents.
2. Approve the exact intent identifiers and argument definitions.
3. Approve the structured Gemini response schema.
4. Define confidence handling and any thresholds based on validated behavior.
5. Approve language detection, preference storage, and language-switching rules.
6. Finalize the conversation state machine and permitted transitions.
7. Define required versus optional CV fields and section-skipping behavior.
8. Define clarification limits and unresolved-ambiguity handling.
9. Define the working-draft representation and edit behavior.
10. Define the confirmed-snapshot representation and immutability enforcement.
11. Define CV history, rename, deletion, and retention behavior.
12. Finalize document-generation and delivery status vocabularies.
13. Define AI, queue, gateway, and delivery retry behavior.
14. Define recovery rules for uncertain operation outcomes.
15. Define command aliases and state-specific semantics.
16. Approve the factual provenance and privacy-aware logging approach.
17. Map the final contract to every applicable source acceptance scenario.
18. Reconcile the source deletion scenario with the numbered acceptance scenarios; the source set must not be silently altered.

Each decision must have a recorded rationale, status, affected requirements, and responsible review or approval owner.

No unresolved item may be treated as approved merely because it appears in this draft.

## 15. Traceability

This document primarily supports the following requirements from `docs/REQUIREMENTS.md`:

- `REQ-005` — Multiple named CVs.
- `REQ-006` — Incrementally saved drafts.
- `REQ-007` — Explicit draft selection and resume.
- `REQ-008` — Collection of approved CV sections.
- `REQ-009` — Support users without work experience.
- `REQ-010` — Edit, rename, and delete owned CVs.
- `REQ-011` — View CV history.
- `REQ-012` — Review and explicitly confirm CV content.
- `REQ-013` — Deliver and redeliver saved PDFs.
- `REQ-014` — Help, cancellation, pause, back, and skip behavior.
- `REQ-018` — Interpret ambiguous messages with Gemini.
- `REQ-019` — Prevent unsupported AI actions and fabricated facts.
- `REQ-020` — Preserve immutable confirmed versions.

The final traceability review must also account for all other applicable requirements and all 15 numbered source acceptance scenarios.

This list is a preliminary mapping and must be verified against the complete approved requirements set.

## 16. Approval record

**Document status:** Draft for review — not approved.

**Required reviewers:** Project owner and the designated technical reviewer(s).

**Approval conditions:**

- The intent inventory and source discrepancies have been reconciled.
- The AI schema and validation boundary have been reviewed.
- The conversation state machine and CV lifecycle have been reviewed.
- Retry, recovery, and idempotency behavior have been defined.
- Security, factuality, and language rules have been reviewed.
- Traceability to the approved requirements and source acceptance scenarios is complete.
- Dependencies on the gateway, database, and security contracts have been resolved or explicitly recorded as blockers.

**Approval decision:** Pending.

**Approval date:** Not recorded.

No implementation authorization is granted by this draft.
