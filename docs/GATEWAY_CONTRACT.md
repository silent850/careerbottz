
# CareerBotTz WhatsApp Gateway Contract

**Document:** `docs/GATEWAY_CONTRACT.md`
**Project:** CareerBotTz
**Source baseline:** Version 1.0, 11 October 2026
**Status:** Draft for review — not approved
**Current phase:** P01 — Requirements and Architecture Approval
**Task:** P01-T06 — Approve Laravel–Node gateway contract

## 1. Purpose

This document defines the proposed internal integration contract between the CareerBotTz Laravel application and the separate Node.js/Baileys WhatsApp gateway.

It covers service responsibilities, authentication, inbound and outbound message payloads, event deduplication, session ownership, QR lifecycle, connection status, failure handling, retries, and operational security.

The contract is based on the following project sources:

- `CareerBotTz_System_Design_and_Implementation_Roadmap_v1 (1).pdf`
- `CareerBotTz_Complete_Implementation_Blueprint.pdf`
- `CareerBotTz_Codespaces_Roadmap.pdf`

It must remain consistent with:

- `docs/REQUIREMENTS.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE_ERD.md`
- `docs/AI_CONVERSATION.md`
- `docs/IMPLEMENTATION_PLAN.md`

This document is a design draft. It does not authorize gateway implementation, provider connection, deployment, or production session management.

## 2. Architecture boundary

### 2.1 Laravel application responsibilities

Laravel is responsible for:

- User identity resolution and ownership.
- Conversation state and workflow rules.
- CV creation, editing, persistence, and versioning.
- AI interpretation and validation.
- Authorization and business-rule enforcement.
- Document generation orchestration and metadata.
- Message-processing orchestration.
- Recording processing outcomes and relevant audit events.
- Applying approved retry and idempotency rules.

Laravel remains the authoritative owner of CV business data.

### 2.2 Node.js/Baileys gateway responsibilities

The separate Node.js gateway is responsible for:

- Managing the Baileys WhatsApp connection.
- Owning WhatsApp-specific authentication and session state.
- Receiving provider events and exposing normalized inbound events.
- Sending approved outbound text and document requests.
- Reporting actual connection and delivery-related outcomes available from the provider.
- Managing QR generation, expiry, refresh, connection, disconnection, logout, and reconnection according to the approved contract.
- Recovering valid session state after restart where possible.
- Exposing protected internal health and session operations.

The gateway must not implement CV business rules, independently manage authoritative CV records, or bypass Laravel authorization.

### 2.3 Responsibility separation

The gateway is not an alternative application backend for CV workflows.

A gateway acknowledgement means only what the approved gateway contract defines. It must not automatically be interpreted as proof that a WhatsApp message was delivered or read.

## 3. Proposed integration model

The gateway and Laravel require a defined internal communication mechanism.

The following decisions remain open until the project owner approves the contract:

- HTTP transport and exact endpoint paths.
- Internal service authentication mechanism.
- Request-signing or equivalent integrity protection.
- Request and connection timeouts.
- Health-check behavior.
- Inbound callback delivery mechanism.
- Connection-status update mechanism.
- Error response format.
- Retry ownership and limits.

No proposed endpoint or mechanism in this document is an approved production interface until the approval record is completed.

## 4. Internal authentication and trust

### 4.1 Authentication requirement

All internal gateway operations must use an approved service-authentication mechanism.

Requests that are unsigned, unauthenticated, expired where applicable, or otherwise invalid must be rejected.

The final design must specify:

- How Laravel authenticates to the gateway.
- How the gateway authenticates inbound callbacks to Laravel.
- How credentials are provisioned and rotated.
- How request integrity and replay protection are enforced.
- How authorization differs between read-only status requests and session-changing operations.
- How credentials are revoked and recovered after exposure.

### 4.2 Secret handling

Secrets must be supplied through approved environment configuration or secret storage.

They must not be:

- Committed to Git.
- Embedded in source code.
- Returned in ordinary health responses.
- Included in exception messages or application logs.
- Included in QR payload logs or session-debug output.

Real credentials, tokens, session files, QR contents, and private connection details must not be added to this document.

### 4.3 Network and access restrictions

The final deployment design must restrict internal gateway endpoints to authorized callers.

The gateway must not expose privileged session operations as unauthenticated public endpoints.

The exact network restrictions, TLS requirements, and deployment-specific controls remain approval items.

## 5. Normalized inbound message contract

### 5.1 Purpose

The gateway must normalize provider-specific inbound events into a stable contract that Laravel can validate and process.

Laravel must not depend directly on undocumented Baileys event internals throughout the business application.

### 5.2 Proposed logical fields

The inbound contract must consider the following information where available and necessary:

| Field | Purpose |
|---|---|
| `schema_version` | Identifies the payload contract version |
| `event_id` | Stable provider or gateway event identifier used for deduplication |
| `event_type` | Identifies the normalized event category |
| `received_at` | Records the relevant receipt timestamp |
| `provider_message_id` | Preserves the provider message identifier when available |
| `sender` | Represents the normalized sender identity |
| `message_type` | Identifies supported message content types |
| `text` | Contains text content when applicable |
| `media_reference` | Represents an approved reference to media where supported |
| `correlation_id` | Connects processing stages for troubleshooting |

These are proposed logical fields, not a finalized payload schema. Exact names, data types, required fields, privacy rules, and media support must be approved before implementation.

The gateway must not fabricate unavailable provider information.

### 5.3 Input validation

Laravel must validate:

- Payload structure and schema version.
- Internal request authentication.
- Event identity and deduplication requirements.
- Sender identity format.
- Supported event and message types.
- Required fields and allowed values.
- Payload size and other approved limits.
- The relationship between the event and its processing context.

Malformed or unsupported events must not be allowed to mutate CV business data.

### 5.4 Processing acknowledgement

The contract must define when an inbound event is considered accepted by Laravel.

Acceptance must be distinguished from completion of AI interpretation or the entire CV workflow.

The gateway must not wait indefinitely for slow AI processing merely to receive an ingestion acknowledgement.

The exact acknowledgement status codes, response schema, and timeout behavior remain to be approved.

## 6. Event deduplication and idempotency

### 6.1 Deduplication requirement

Repeated delivery of the same provider event must not repeat its business operation.

The final design must define the stable event identifier and its uniqueness scope.

It must also define how Laravel handles:

- A first-time event.
- An event already accepted for processing.
- An event currently being processed.
- An event whose processing previously failed.
- An event whose outcome is uncertain.
- A replay received after the original processing completed.

### 6.2 Persistence and concurrency

The approved persistence design must record enough event-processing information to support deduplication and recovery.

Concurrent requests for the same event must not create duplicate business effects.

The implementation must define appropriate transaction boundaries and concurrency controls.

A retry must not create a duplicate user, CV, confirmed version, or document-generation operation merely because a prior acknowledgement or response was lost.

### 6.3 Idempotency keys

The final contract must identify which operations require idempotency keys or equivalent stable identifiers.

Candidate operations include inbound-event ingestion, outbound message requests, and session lifecycle commands.

The exact key format, retention, and uniqueness scope remain open decisions.

## 7. Outbound text-message contract

### 7.1 Proposed request

The source blueprint proposes the following logical text-message request:

```json
{
  "schema_version": 1,
  "request_id": "unique-request-id",
  "recipient": "+255...",
  "message_type": "text",
  "text": "Localized response",
  "correlation_id": "trace-id"
}
```

This is an illustrative contract shape. The recipient example is a placeholder, not a real user identity or credential.

The final schema must define the recipient normalization rule, required fields, maximum message size, supported formatting, request authentication, and error response.

### 7.2 Outbound validation

Before dispatch, Laravel must ensure that:

- The recipient is associated with the intended operation.
- The message content is valid and appropriate for the approved workflow.
- The request is authorized.
- The request has a stable identifier for tracking and deduplication.
- The content does not expose another user's private information.
- The request follows the approved retry policy.

### 7.3 Outbound result

The gateway must return a structured result that distinguishes request acceptance or rejection from any later delivery confirmation.

The exact response fields and provider-status mapping must be agreed before implementation.

## 8. Outbound PDF/document contract

### 8.1 Proposed request

The source blueprint proposes the following logical document request:

```json
{
  "schema_version": 1,
  "request_id": "unique-request-id",
  "recipient": "+255...",
  "message_type": "document",
  "document_reference": "authorized-document-reference",
  "filename": "CV.pdf",
  "mime_type": "application/pdf",
  "correlation_id": "trace-id"
}
```

This is an illustrative contract shape, not an approved endpoint payload.

### 8.2 Document reference and file transfer

The final contract must decide how the gateway obtains the document.

The source blueprint identifies two possible approaches:

- An authenticated, short-lived download reference.
- An approved internal file-transfer mechanism.

The selected approach must preserve private document access and authorization.

The gateway must not accept an arbitrary public URL and fetch it without controls.

The design must define:

- How access to a document is authorized.
- How temporary references expire or are revoked.
- How the gateway validates the file type and size.
- How filenames are validated.
- How failed downloads are reported.
- How temporary files and cached content are protected and cleaned up.

The exact transfer mechanism remains unapproved.

### 8.3 Version association

Laravel must ensure that the requested PDF corresponds to the intended confirmed CV version.

Retrying delivery must not generate a new confirmed version or silently substitute a newer draft.

## 9. Delivery lifecycle and provider outcomes

The contract must distinguish these logical stages:

1. Request created.
2. Send attempt started.
3. Gateway accepted or rejected the request.
4. Provider delivery confirmation, if available.
5. Terminal failure after the approved retry policy.

The exact statuses and transitions remain to be finalized.

A gateway acknowledgement must not be recorded as proof that a message was delivered or opened.

If the provider supplies delivery callbacks, the receiving service must authenticate, validate, and correlate them with the original request.

The design must also define behavior for:

- A callback arriving more than once.
- A callback arriving out of order.
- A delayed callback.
- A callback referencing an unknown request.
- A gateway timeout where the actual send outcome is uncertain.
- A permanent provider rejection.

Delivery retries must be separate from CV creation, confirmation, and document generation.

## 10. WhatsApp session ownership

### 10.1 Session authority

The Node.js/Baileys gateway owns WhatsApp-specific authentication and session state.

Laravel's administrative interface must use the approved gateway contract to request session status, obtain QR information, and issue authorized lifecycle operations.

Laravel must not independently manipulate Baileys session internals.

### 10.2 Required logical connection states

The contract must represent the relevant connection states, including:

- Disconnected.
- Awaiting scan.
- QR expired.
- Connecting.
- Connected.
- Authentication failure.
- Gateway unreachable.
- Disconnecting or logging out.
- Reconnecting.

These are logical states identified by the project source. Exact machine-readable status identifiers and transition rules remain to be approved.

The interface must show the actual gateway-reported state and must not claim that the connection succeeded before confirmation from the gateway.

### 10.3 Session persistence and restart

The gateway should reuse valid session state after a restart where possible.

If valid session state cannot be recovered, it must report the appropriate linking or recovery state without exposing credentials.

Session files and authentication material must be protected using approved storage permissions and secret-handling practices.

## 11. QR generation, expiry, and refresh

### 11.1 QR requirements

The gateway contract must support requesting current QR information where the session requires device linking.

The QR lifecycle must define:

- When a QR becomes available.
- How expiry is represented.
- How a client learns that a QR has expired.
- How a fresh QR is requested.
- What happens when a connection succeeds while the QR is displayed.
- How gateway unavailability is reported.
- How stale QR data is prevented from being presented as current.

The exact QR payload format and expiry duration remain open decisions.

### 11.2 QR security

QR contents and session credentials are sensitive.

The design must ensure that:

- Only authorized administrators can access QR information.
- QR contents are not written to ordinary logs.
- QR responses are not stored in public or shared caches.
- Expired QR information is not presented as valid.
- Session-changing actions are protected by the approved authorization model.

### 11.3 Administrator instructions

The future administration page should explain the linking steps:

1. Open WhatsApp on the phone linked to the intended account.
2. Open the Linked Devices area.
3. Choose the option to link a device.
4. Scan the current QR shown by CareerBotTz.
5. Wait until the gateway confirms the connected state.

The exact WhatsApp labels may vary by application version.

This document does not authorize the administration page's implementation.

## 12. Session lifecycle operations

The internal contract must consider the following operations:

| Operation | Intended behavior | Required control |
|---|---|---|
| Read status | Return the actual current session state | Internal authentication |
| Request QR | Obtain current QR information when required | Administrator authorization and QR protection |
| Connect | Start an approved connection operation | Session-operation serialization |
| Disconnect | Disconnect according to the approved lifecycle rules | Authorization and operation serialization |
| Logout | Remove or invalidate the linked session according to approved semantics | Authorization and explicit confirmation |
| Reconnect | Attempt controlled recovery | Bounded retries and operation serialization |
| Health check | Report service health without leaking secrets | Internal authentication and timeout |

These are logical operations, not a finalized route list.

The exact endpoint paths, HTTP methods, response schemas, and error codes must be approved before implementation.

### 12.1 Serialized operations

Conflicting connection operations must not run concurrently.

For example, connect, logout, disconnect, and reconnect requests must be serialized according to the approved session state machine.

The contract must define how duplicate commands are handled and how an administrator learns that an operation is already in progress.

### 12.2 Safe lifecycle reporting

Session operations must report actual outcomes.

An accepted command does not necessarily mean the session has already reached its target state.

Long-running operations must expose enough state information for the administrative interface to show progress and safe recovery guidance.

## 13. Connection-status update mechanism

The project sources explicitly require a decision about how the administrative interface receives status changes.

The following alternatives must be compared before selecting one:

| Mechanism | Review considerations |
|---|---|
| Polling | Simplicity, request frequency, status freshness, and load |
| WebSockets | Bidirectional communication, connection management, and operational complexity |
| Server-Sent Events | One-way status streaming, reconnection, and deployment compatibility |

The final decision must account for the deployment environment, authentication, network constraints, operational complexity, and acceptable status delay.

**No update mechanism is approved by this draft.**

The selected mechanism, rationale, failure behavior, and verification plan must be recorded in the project documentation before P03/P07 interfaces are implemented.

## 14. Timeouts, retries, and recovery

### 14.1 Retry policy

Retries must be bounded and must use appropriate backoff according to the approved operation-specific policy.

The design must distinguish transient failures from permanent validation, authorization, or provider errors.

A retry must preserve the relevant request identity and must not repeat an operation that already completed.

Exact retry counts, delays, timeout values, and terminal-failure rules remain open decisions.

### 14.2 Gateway restart

After restart, the gateway must:

- Restore valid session state where possible.
- Report its actual connection state.
- Avoid reporting connected without confirmation.
- Recover or safely abandon incomplete operations according to the approved contract.
- Preserve identifiers needed to correlate in-flight requests and outcomes.

### 14.3 Ambiguous outcomes

If the gateway times out after a send request, Laravel may not know whether the provider accepted the message.

The approved contract must define how to reconcile that uncertainty before retrying.

The system must not blindly retry an operation when doing so could produce duplicate user-visible messages or other duplicate effects.

### 14.4 Graceful shutdown

The gateway's shutdown behavior must define how it stops accepting new work, handles in-flight operations, preserves valid session state, and reports incomplete work.

Exact shutdown timeouts and deployment controls remain to be approved.

## 15. Health checks and observability

The gateway should expose an authenticated health capability sufficient to distinguish service availability from WhatsApp connection status.

These are separate concerns:

- Gateway process health.
- Gateway-to-provider connectivity.
- WhatsApp session state.
- Inbound event processing.
- Outbound request processing.

The approved design must define health response fields, timeout behavior, freshness, and failure classification.

Logs and metrics should support troubleshooting without exposing message content, personal data, QR contents, or session credentials unnecessarily.

Correlation identifiers should connect the relevant stages of inbound processing, Laravel workflow execution, outbound requests, and delivery outcomes.

## 16. Proposed endpoint inventory

The complete implementation blueprint provides the following proposed internal endpoints:

| Operation | Proposed endpoint | Status |
|---|---|---|
| Check health | `GET /internal/v1/health` | Proposed; not approved |
| Read session status | `GET /internal/v1/session` | Proposed; not approved |
| Request QR | `POST /internal/v1/session/qr` | Proposed; not approved |
| Start connection | `POST /internal/v1/session/connect` | Proposed; not approved |
| Disconnect | `POST /internal/v1/session/disconnect` | Proposed; not approved |
| Logout | `POST /internal/v1/session/logout` | Proposed; not approved |
| Receive inbound events | Proposed authenticated internal callback to Laravel | Exact route not approved |
| Report connection changes | Proposed authenticated event callback or status response | Mechanism not approved |

The table preserves the source blueprint's proposed route names where provided.

These paths must not be implemented as final routes until transport, authentication, request/response schemas, authorization, idempotency, and status semantics are approved.

## 17. Testing and verification requirements

The approved gateway contract must be testable.

| Area | Expected result |
|---|---|
| Internal authentication | Unauthorized internal requests are rejected |
| Payload validation | Malformed or unsupported payloads do not mutate business data |
| Event deduplication | Replayed provider events do not repeat business operations |
| Correlation | Inbound and outbound operations can be traced safely |
| Session ownership | Baileys-specific session state remains owned by the gateway |
| QR expiry | Expired QR data is not presented as current |
| QR confidentiality | Unauthorized access and QR logging are prevented |
| Connection status | Connected is reported only when confirmed by the gateway |
| Session serialization | Conflicting lifecycle operations are prevented |
| Restart recovery | Valid sessions are reused where possible and failures are reported accurately |
| Outbound text | Request acceptance is distinguished from delivery confirmation |
| Outbound documents | The correct authorized document is used |
| Retry safety | Retries do not create duplicate business effects |
| Delivery callbacks | Callbacks are authenticated, validated, and correlated |
| Gateway outage | Laravel receives an appropriate failure outcome |
| Logging | Credentials and unnecessary sensitive content are not exposed |
| Shutdown | In-flight work and session state follow the approved recovery policy |

These are planned verification expectations, not evidence of tests already passing.

## 18. Open decisions and blockers

The following decisions must be resolved or explicitly recorded before this contract is approved.

1. Final internal transport and endpoint inventory.
2. Laravel-to-gateway and gateway-to-Laravel authentication.
3. Request integrity, replay protection, and credential rotation.
4. Final inbound payload schema and event identity.
5. Final outbound text and document payload schemas.
6. Event deduplication scope and persistence rules.
7. Idempotency requirements for send and session operations.
8. QR payload representation, expiry, and refresh behavior.
9. Exact session state identifiers and legal transitions.
10. Serialization and conflict behavior for session operations.
11. Connection-status update mechanism.
12. Timeouts, retry limits, backoff, and terminal-failure policy.
13. Delivery callback handling and uncertain send outcomes.
14. Document transfer mechanism and temporary-reference security.
15. Health response format and monitoring thresholds.
16. Graceful shutdown and restart-recovery behavior.
17. Deployment-specific network restrictions and TLS requirements.
18. Error response format and provider-error classification.
19. Traceability to all applicable requirements and acceptance scenarios.

Each decision must have a rationale, status, affected requirements, and approval owner recorded.

## 19. Traceability

This document primarily supports:

- `REQ-015` — Keep Baileys isolated from Laravel business logic.
- `REQ-016` — Deduplicate inbound provider events.
- `REQ-017` — Process inbound messages asynchronously.
- `REQ-018` — Interpret ambiguous messages through the approved AI boundary.
- `REQ-019` — Prevent unsupported AI actions and fabricated facts.

It also supports the identity, document delivery, reliability, administration, privacy, and security requirements that depend on the gateway contract.

The final traceability review must map the approved contract to all applicable source acceptance scenarios.

## 20. Approval record

**Document status:** Draft for review — not approved.

**Approval conditions:**

- Laravel and gateway responsibilities are agreed.
- Internal authentication and transport are defined.
- Inbound and outbound schemas are finalized.
- Deduplication, idempotency, and retry behavior are defined.
- QR and session lifecycle semantics are reviewed.
- The status update mechanism is selected and documented.
- Failure handling and recovery rules are testable.
- Security and privacy controls are reviewed.
- Traceability and dependencies are reconciled with the approved requirements.

**Approval decision:** Pending.

**Approval date:** Not recorded.

No gateway implementation or production integration is authorized by this draft.
