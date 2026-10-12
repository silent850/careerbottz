
# CareerBotTz Security, Testing and Operations Requirements

**Document:** `docs/SECURITY.md`
**Project:** CareerBotTz
**Phase:** P01 — Requirements and Architecture Approval
**Task:** P01-T07 — Security, Testing and Operations
**Status:** Draft for review — not approved
**Version:** 1.0
**Last updated:** 12 October 2026

---

## 1. Purpose

This document defines the proposed security, testing, reliability, monitoring, backup and operational requirements for CareerBotTz.

It establishes requirements for the Laravel application, WhatsApp Gateway, Gemini integration, database, Redis, generated CV documents and administrative dashboard.

This is a requirements document, not an implementation authorization.

No production credentials, migrations, application services or deployment configuration should be created solely on the basis of this draft.

## 2. Scope and Architecture Boundaries

The proposed architecture consists of:

- A Laravel modular monolith as the main application.
- MySQL 8.4 LTS as the authoritative database.
- Redis and Laravel Horizon for queue processing.
- A separate Node.js WhatsApp Gateway using Baileys.
- Gemini for supported AI interpretation, extraction, clarification and English CV writing.
- Private document storage for generated CV PDFs.
- An administrative dashboard for WhatsApp session management and operational visibility.

Security responsibilities must be defined across these components without allowing one component to bypass the controls of another.

## 3. Security Principles

The following principles are proposed:

1. Least privilege: each component receives only the permissions it needs.
2. Defense in depth: sensitive operations must not depend on a single security control.
3. Explicit authorization: administrative and document operations require appropriate authorization.
4. Data minimization: collect, transmit and retain only necessary information.
5. Secure defaults: deny access unless explicitly authorized.
6. No secrets in source control: credentials and private keys must remain outside committed source files.
7. Fail safely: errors must not expose credentials, private documents or unnecessary personal information.
8. Auditability: sensitive administrative and session operations must be traceable.
9. Data integrity: confirmed CV snapshots must not be silently modified.
10. AI factual integrity: AI-generated content must not invent personal, educational, employment or other CV facts.

## 4. Authentication and Authorization

### 4.1 Administrative Access

The administrative dashboard must require authenticated access.

The final design must define:

- Administrator identity and authentication.
- Role and permission boundaries.
- Session expiration and invalidation.
- Protection against unauthorized administrative actions.
- Appropriate protection against cross-site request forgery where applicable.
- Login abuse controls and rate limiting.
- Secure password storage if application-managed passwords are selected.

Administrative access to WhatsApp QR codes, session lifecycle operations, webhook configuration, logs and private documents must be restricted to authorized personnel.

### 4.2 Service-to-Service Authentication

Communication between Laravel and the WhatsApp Gateway must use an approved authentication mechanism.

The contract must define:

- Credential provisioning and storage.
- Authentication headers or another agreed authentication mechanism.
- Credential rotation and revocation.
- Request integrity and replay protection where required.
- Authorization of each internal operation.
- Failure behavior for missing, invalid or expired credentials.

An endpoint must not be considered trusted merely because it is named `internal`.

### 4.3 Webhook Authentication

Inbound Gateway events must be authenticated before they are processed.

The approved contract must specify:

- How the sender is authenticated.
- How message integrity is verified where supported.
- How timestamps, event identifiers and replay attempts are handled.
- How duplicate events are rejected or safely ignored.
- How unauthorized requests are recorded without logging secrets.

The exact mechanism remains subject to approval in the Gateway contract.

## 5. Secrets and Configuration

Secrets may include:

- Gemini API credentials.
- Laravel application secrets.
- Database credentials.
- Redis credentials where authentication is configured.
- Gateway authentication credentials.
- WhatsApp session credentials and cryptographic material.
- Storage credentials and other deployment secrets.

Requirements:

- Do not commit secrets to Git.
- Do not place real credentials in example configuration files.
- Provide sanitized example configuration where needed.
- Keep production secrets separate from development and staging secrets.
- Restrict access to secret-bearing files and deployment settings.
- Define a process for rotation and revocation.
- Avoid printing secrets in application errors, terminal output or logs.
- Treat WhatsApp session credentials as sensitive authentication material.
- Define secure recovery procedures if credentials are exposed.

The exact secret-management service and deployment mechanism remain open decisions.

## 6. Personal Data and Privacy

CareerBotTz processes personal information supplied during conversations and CV creation.

The design must define:

- Which personal data is collected and why.
- Which data is stored in MySQL, Redis, logs and document storage.
- Which information is transmitted to Gemini or the WhatsApp Gateway.
- Which data is retained and for how long.
- How authorized deletion requests are handled.
- How data access is restricted.
- How backups and exported records are protected.
- How personal information is removed from logs where it is unnecessary.

Data retention periods, deletion procedures and applicable compliance obligations require explicit review and approval.

## 7. AI Safety and CV Factual Integrity

Gemini may support interpretation, extraction, clarification, consistency checks and professional English CV writing.

The system must:

1. Distinguish user-provided facts from AI-generated wording.
2. Ask for clarification when required information is missing or ambiguous.
3. Never invent qualifications, employers, job titles, dates, achievements or contact information.
4. Preserve the intended meaning of user-provided information.
5. Avoid treating untrusted user content as privileged system instructions.
6. Validate AI output against the expected schema before persistence or document generation.
7. Handle malformed, incomplete or unavailable AI responses safely.
8. Require the established review and explicit confirmation process before treating a CV as confirmed.
9. Preserve confirmed snapshots according to the approved lifecycle design.
10. Avoid exposing one user's information in another user's conversation or generated document.

AI-generated output must not bypass application authorization, validation or lifecycle rules.

## 8. WhatsApp Gateway and Session Security

The Gateway owns the Baileys authentication and session lifecycle.

Requirements:

- Keep WhatsApp authentication material out of public web directories.
- Restrict access to session storage.
- Never expose session credentials through the admin dashboard.
- Treat QR codes as temporary sensitive pairing material.
- Restrict QR retrieval to authorized administrators.
- Prevent unauthorized concurrent session lifecycle operations.
- Validate lifecycle requests before execution.
- Define safe handling for authentication failure, disconnection and reconnection.
- Avoid logging QR payloads or session credentials.
- Define secure logout, cleanup and recovery behavior.
- Prevent the Gateway from sending arbitrary messages or documents without an authorized application request.

The Gateway must report its operational status without disclosing authentication material.

## 9. Document and PDF Security

Generated CV PDFs may contain sensitive personal information.

Requirements:

- Store generated PDFs in private storage.
- Do not expose permanent public document URLs by default.
- Authorize document access before retrieval or delivery.
- Use short-lived, scoped document references or another approved internal transfer mechanism for Gateway delivery.
- Do not allow the Gateway to fetch arbitrary user-supplied URLs.
- Validate document references and enforce access boundaries.
- Validate filenames and avoid path traversal.
- Set the expected MIME type and PDF filename.
- Prevent one user from retrieving another user's CV.
- Define temporary-file cleanup and document retention behavior.
- Ensure failed document delivery does not expose the document publicly.

A successful Gateway acceptance response must not be interpreted as proof that the recipient received or opened the document.

## 10. Input Validation and Application Security

All external and inter-service inputs must be validated at the application boundary.

Validation must cover, where applicable:

- WhatsApp sender identifiers and normalized phone numbers.
- Message type and payload size.
- Webhook event identifiers.
- Correlation and request identifiers.
- Document references and filenames.
- AI response structures.
- Administrative form submissions.
- Session lifecycle commands.
- Pagination and query parameters.

The application must use safe database access patterns and appropriate output escaping.

Error responses must not disclose stack traces, SQL details, secrets or private data to unauthorized callers.

Specific limits, validation rules and middleware choices remain to be defined during implementation planning.

## 11. Rate Limiting and Abuse Prevention

The design must establish limits for:

- Incoming webhook requests.
- Repeated or abusive conversation activity.
- Expensive Gemini operations.
- Administrative login attempts.
- Gateway lifecycle commands.
- Outbound message and document requests.
- Repeated document retrieval attempts.

Limits must be configurable where appropriate and must not silently discard legitimate work without an observable outcome.

The exact thresholds and enforcement locations require approval.

## 12. Idempotency and Data Integrity

Webhook events and outbound requests may be retried or delivered more than once.

The system must:

- Identify inbound events using a stable event identifier where available.
- Prevent duplicate event processing from creating duplicate business effects.
- Use unique request identifiers for outbound requests.
- Define the idempotency behavior of retryable operations.
- Record relevant processing and delivery states.
- Distinguish request acceptance from actual provider delivery confirmation.
- Preserve audit history for important lifecycle changes.
- Prevent confirmed CV snapshots from being silently overwritten.

The final database constraints and transaction boundaries must agree with the approved ERD and lifecycle design.

## 13. Queue and Failure Handling

Redis and Laravel Horizon are proposed for asynchronous work.

The implementation requirements must define:

- Which operations execute asynchronously.
- Queue names and worker responsibilities.
- Retryable versus non-retryable failures.
- Bounded retry counts and backoff behavior.
- Timeout limits.
- Failed-job visibility and recovery.
- Duplicate execution protection.
- Handling of temporary Gemini, database, storage or Gateway outages.
- Prevention of sensitive payload leakage through job logs.
- Safe handling of jobs that cannot complete.

Retries must be bounded. Permanent validation or authorization failures must not be retried indefinitely.

## 14. Logging, Monitoring and Audit

The application must provide sufficient operational visibility to diagnose failures while protecting personal information.

Logs and metrics should distinguish:

- Application health.
- Database and Redis connectivity.
- Queue backlog and failed jobs.
- Gateway reachability and session state.
- Gemini request failures and latency.
- PDF generation failures.
- Outbound send attempts and delivery outcomes.
- Webhook authentication and processing failures.
- Administrative session lifecycle actions.

Use correlation identifiers to connect related events across Laravel, queues and the Gateway.

Logs must not contain API keys, session credentials, QR payloads, full authentication headers or unnecessary CV contents.

The design must specify log retention, access permissions, alert thresholds and the audit events that require durable storage.

## 15. Health Checks and Recovery

Health checks must distinguish between:

- Process liveness.
- Application readiness.
- Dependency availability.
- Gateway connectivity.
- WhatsApp session state.

A healthy process must not automatically be reported as a connected WhatsApp session.

Recovery procedures must cover, at minimum:

- Laravel application restart.
- Queue worker restart and failed-job recovery.
- Redis interruption.
- Database unavailability.
- Gateway process failure.
- WhatsApp session disconnection or authentication failure.
- Gemini service interruption.
- PDF generation or private-storage failure.

The final operational design must identify which failures require automated recovery and which require administrator intervention.

## 16. Database Backups and Disaster Recovery

MySQL is the authoritative data store.

The operations plan must define:

- Backup frequency and retention.
- Backup encryption and access control.
- Storage location and separation from the primary system.
- Restore verification.
- Recovery Point Objective (RPO).
- Recovery Time Objective (RTO).
- Handling of private document storage backups.
- Recovery of WhatsApp session state.
- Procedures for validating data integrity after recovery.

RPO, RTO, retention periods and backup schedules are not approved in this draft.

Redis must not be treated as the authoritative source for business records.

## 17. Testing Strategy

Testing must be planned before implementation and expanded as components are introduced.

### 17.1 Unit Tests

Test individual business rules, validation rules, state transitions and service behavior in isolation.

### 17.2 Integration Tests

Test boundaries between:

- Laravel and MySQL.
- Laravel and Redis/queues.
- Laravel and the Gateway.
- Laravel and Gemini.
- PDF generation and private storage.

Use controlled test doubles or sandbox integrations where appropriate.

### 17.3 Contract Tests

Verify that Laravel and the Gateway agree on:

- Request and response schemas.
- Schema versions.
- Authentication behavior.
- Error codes and error structures.
- Idempotency behavior.
- QR and session status representation.
- Document delivery references.
- Delivery lifecycle events.
- Correlation identifiers.

Contract tests must be aligned with the approved `docs/GATEWAY_CONTRACT.md`.

### 17.4 Security Tests

Test:

- Unauthorized access to administrative functions.
- Invalid service credentials.
- Invalid and replayed webhook events.
- Cross-user document access.
- Input validation and payload size limits.
- Rate limiting.
- Secret leakage in logs and error responses.
- Unauthorized session lifecycle commands.
- Duplicate processing and unsafe retries.

### 17.5 End-to-End Tests

The planned end-to-end scenarios must cover:

1. A user starts a conversation.
2. The system identifies or establishes the user according to approved rules.
3. The system gathers CV information.
4. Ambiguous or missing information triggers clarification.
5. The user reviews and edits a draft.
6. The user explicitly confirms the CV.
7. The system creates an immutable confirmed snapshot.
8. PDF generation completes asynchronously.
9. The PDF is delivered through the authorized Gateway flow.
10. Delivery outcomes and failures are recorded correctly.

These scenarios must not imply that unapproved product behavior has already been finalized.

## 18. Operational Environments and Deployment

The deployment plan must distinguish development, staging and production.

Requirements:

- Separate configuration and credentials by environment.
- Avoid production personal data in development and test environments unless explicitly authorized and appropriately protected.
- Define deployment and rollback procedures.
- Define database migration review and execution controls.
- Verify health checks after deployment.
- Verify queue workers and Gateway connectivity.
- Define dependency update and vulnerability review practices.
- Document access permissions for operators.
- Keep deployment secrets out of Git history.

Specific hosting, CI/CD and infrastructure choices remain open until the relevant architecture decisions are approved.

## 19. Security Incident Handling

An incident procedure must define how to respond to:

- Exposed API keys or service credentials.
- Compromised WhatsApp session material.
- Unauthorized document access.
- Suspicious webhook activity.
- Personal data exposure.
- Repeated abuse or denial of service.
- Data corruption or failed recovery.

The procedure must identify responsible operators, containment actions, credential revocation, recovery steps, evidence preservation and required notifications.

Notification obligations and incident response targets require explicit review.

## 20. Proposed Acceptance Criteria

The following are proposed acceptance criteria for P01-T07.

- **SEC-01:** Trust boundaries and security responsibilities are documented.
- **SEC-02:** Administrative authentication and authorization requirements are documented.
- **SEC-03:** Laravel-to-Gateway and webhook authentication requirements are documented.
- **SEC-04:** Secrets management and credential rotation requirements are documented.
- **SEC-05:** Personal data handling and retention decisions are identified.
- **SEC-06:** AI factual-integrity and validation requirements are documented.
- **SEC-07:** WhatsApp session and QR confidentiality requirements are documented.
- **SEC-08:** Private PDF storage and authorized delivery requirements are documented.
- **SEC-09:** Input validation, rate limiting and abuse prevention requirements are documented.
- **SEC-10:** Idempotency, queue retries and failure handling requirements are documented.
- **SEC-11:** Logging, monitoring and audit requirements are documented.
- **SEC-12:** Backup, restore, RPO and RTO decisions are identified.
- **SEC-13:** Unit, integration, contract, security and end-to-end testing are planned.
- **SEC-14:** Deployment, rollback and incident response requirements are documented.
- **SEC-15:** Unresolved decisions and their approval owners are recorded.

These criteria are proposals and must be reconciled with the project's approved requirements and acceptance criteria.

## 21. Open Decisions

The following items remain open until explicitly reviewed and approved:

1. Authentication mechanism for internal service calls.
2. Webhook authentication and replay-protection mechanism.
3. Administrator authentication and role model.
4. Secrets management and rotation mechanism.
5. Personal data retention and deletion rules.
6. Rate limits and abuse thresholds.
7. Log retention and alerting thresholds.
8. Backup schedule and retention.
9. RPO and RTO targets.
10. Hosting and deployment environment.
11. CI/CD and rollback strategy.
12. Security incident ownership and notification procedure.
13. Required security testing tools and automated checks.
14. Exact acceptance criteria and evidence required for the P01 approval gate.

Do not silently resolve these decisions by introducing implementation defaults.

## 22. Traceability

This document must be reviewed alongside:

- `docs/REQUIREMENTS.md`
- `docs/IMPLEMENTATION_PLAN.md`
- `docs/PROGRESS.md`
- `docs/DATABASE_ERD.md`
- `docs/ARCHITECTURE.md`
- `docs/AI_CONVERSATION.md`
- `docs/GATEWAY_CONTRACT.md`

Any conflicting requirement must be recorded and resolved through the P01 review process.

## 23. Approval Record

**Document status:** Draft for review — not approved

**Security review:** Pending
**Architecture review:** Pending
**Requirements traceability review:** Pending
**P01 gate approval:** Pending

No Laravel implementation, database migrations or production deployment is authorized by this document alone.
