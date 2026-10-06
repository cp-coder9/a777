# Production integration contract

This candidate implements the application side of `architex.integration.v1`: a provider-neutral HTTPS merchant bridge and a separately provisioned desktop broker. Neither bridge service, merchant account, Windows host agent nor streaming viewer is supplied by this worktree. Configure only a legitimately authorized bridge implementing this contract. Adapter installation does not make a module live.

## Configuration and transport

Application startup already applies `EnvironmentLoader`; adapters consume its process environment without reading configuration files themselves. Required keys:

| Boundary | Prefix | Required suffixes |
| --- | --- | --- |
| Financial provider | `FINANCE_BRIDGE_<UPPERCASE_PROVIDER_ID>` | `_URL`, `_TOKEN`, `_WEBHOOK_SECRET` |
| Desktop tenant | `DESKTOP_BRIDGE_<UPPERCASE_ORGANISATION_ID>` | `_URL`, `_TOKEN`, `_WEBHOOK_SECRET` |

IDs are 32 hexadecimal characters. Use separate tenant/provider credentials. Existing provider-key-based webhook secret names are intentionally not accepted: they can collide between tenants.

URL must be HTTPS, port 443, with a public DNS hostname and an optional simple path prefix. No URL credentials, query, fragment, IP-literal host or arbitrary path escapes. Production transport resolves IPv4 addresses, rejects non-global answers, pins the selected address, verifies TLS certificate/hostname, disables proxies and redirects, times out connection after 5 seconds and transfer after 20 seconds, and caps response at 256 KiB. IPv6-only brokers need an IPv4 endpoint. Tokens and callback secrets must contain at least 32 bytes; bearer tokens cannot contain whitespace/control characters.

Every request sends `Authorization: Bearer <configured token>`, JSON accept/content-type headers. POST bodies contain `contract: "architex.integration.v1"`; GET scope fields are query parameters. Responses are direct JSON objects containing the same `contract` and exact typed values for every supplied binding field: `organisationId`, `projectId`, `actorId`, `providerId`, `releaseRequestId`, `certificateRevisionId`, `sessionId`, `hostId`, `appId`, `bookingReference`, `brokerReference`, `policySha256`, `amountCents`, `currency`. Binding fields not supplied by that operation need not be returned. No application-envelope wrapper.

Only 2xx responses with valid JSON, contract and scope are accepted. A transport error, redirect, malformed response or mismatched scope produces an explicit integration error. The bridge must not report acceptance until the real downstream operation is recorded durably.

## Financial provider

Application routes preserve existing session, tenant, permission and CSRF middleware:

- `GET /api/v1/financial-providers/{providerId}/capabilities`: registered/active provider scoped to caller's organisation; authenticated discovery only.
- `POST /api/v1/provider-release-requests/{releaseRequestId}/submit`: owner/admin; body `{ "humanConfirmed": true, "expectedVersion": <current version> }`.
- Existing `POST /api/v1/webhooks/finance/{providerId}`: provider-signed callback; session cookie and `X-Organisation-Id` are unnecessary.

Bridge endpoints relative to configured URL:

| Method/path | Request binding | Additional response |
| --- | --- | --- |
| `GET /capabilities` | `organisationId`, `providerId` | `capabilities` array, `idempotentRelease: true`, `signedCallbacks: true` |
| `POST /releases` | organisation/project/actor/provider/release/certificate IDs, integer `amountCents`, three-letter `currency` | `status: "accepted"`, real `providerReference` |

Capabilities use existing vocabulary: `collect`, `escrow_hold`, `release`, `payout`, `webhook_status`. Submission requires both `release` and `webhook_status` in database registration and authenticated transport discovery. Registration's `live_configured` flag alone is neither authorization nor transport evidence; this candidate does not automatically relabel it.

The merchant bridge must bind configured credentials to the designated tenant/provider/account, resolve the real authorized beneficiary from the certificate/release identity, and enforce real account limits and provider rules. A release request does not accept a caller-supplied bank account or beneficiary. The bridge must refuse missing or unauthorized beneficiary mappings.

Submission checks active owner/admin authority, explicit human confirmation, expected version, current non-disputed/non-voided certificate revision and two distinct currently authorized approvers: client plus appointed lead professional. It persists exact request JSON/hash before dispatch, then locks the release while validating/sending. `Idempotency-Key` is `release-<releaseId>`; retries reuse exact persisted bytes and key. The merchant bridge must preserve that idempotency mapping durably, including across timeouts, restarts and account-provider retries; no short expiration that could permit a second transfer.

Acceptance stores provider reference and `submitted_to_provider`; it does not set paid amount. Uncertain responses leave a durable `prepared`/`unknown` dispatch for an explicit authorized retry/reconciliation. Reference reuse is rejected by a per-provider unique constraint. Do not create a new release identity to retry an uncertain transfer.

The underlying claim, certificate, all certificate revisions, release requests and dispatch rows are transaction-locked before recertification, replacement or submission. Any persisted dispatch (including prepared/unknown/accepted), provider reference, submitted/paid state or payment history across any revision blocks a replacement with `commercial_obligation_reconciliation_required`. This candidate has no replacement-unlock reconciliation API: do not delete or reset dispatch history to work around the refusal. Safe pre-dispatch correction can create a new revision, but the superseded revision's release cannot be submitted.

Callbacks authenticate exact raw body with:

```text
X-Architex-Provider-Timestamp: <10-digit Unix seconds>
X-Architex-Provider-Signature: <hex HMAC-SHA256(secret, timestamp + "." + raw_body)>
```

Maximum clock skew/age: 300 seconds. Body fields: `organisationId`, `providerId`, `eventId`, `eventType`, `providerReference`, integer `amountCents`, `currency`. Event types: `payment_processing`, `payment_paid`, `payment_failed`, `payment_refunded`. Amount/currency must exactly match the immutable release. Paid follows submitted; refund follows paid; terminal paid/failed/refunded states cannot regress. Repeated same-state notifications do not increment release version.

Provider lock serializes replay lookup and event insertion. Identical event/body repeats are idempotent; changed body under the same event ID returns conflict. Unknown reference returns 409 `financial_provider_webhook_reference_pending` **without consuming the event ID**, allowing retry after submission commits. Callbacks must be asynchronous, delivered after bridge response, and retried on 409/5xx with fresh timestamp/signature and the same body/event ID. Never synchronously wait for callback completion before answering `/releases`.

This endpoint submits certificate-backed release requests. Separate retention-release records remain governed metadata and have no transport submission in this candidate; they must not be represented as paid. No escrow custody, settlement guarantee or compliance sign-off is implied.

## Desktop broker, viewer and host agent

Application routes:

- `GET /api/v1/projects/{projectId}/remote-desktop/capabilities`
- `POST /api/v1/projects/{projectId}/remote-desktop/sessions`
- `GET /api/v1/remote-desktop/sessions/{sessionId}`
- `POST /api/v1/remote-desktop/sessions/{sessionId}/viewer-ticket`
- `POST /api/v1/remote-desktop/sessions/{sessionId}/close`
- `POST /api/v1/webhooks/remote-desktop/{organisationId}`

Browser routes require session/tenant scope and existing project read/write permissions. Service checks active membership plus owner/admin authority or active project-team assignment. Session access additionally requires its exact actor, even for another tenant administrator. Mutations retain CSRF; create, viewer ticket and close require `humanConfirmed: true`.

Create body also requires `hostId`, `appId`, `bookingReference`, `clientRequestId`: bounded external identifiers, not URLs, commands or file paths. Request-key reuse cannot change project, host, app or booking. Local session is persisted before provider call; maximum lifetime is 900 seconds. Explicit retry uses `desktop-<sessionId>` with unchanged binding, policy and expiry.

Bridge endpoints:

| Method/path | Request | Response |
| --- | --- | --- |
| `GET /desktop/capabilities` | organisation/project/actor binding | `hosts` list |
| `POST /desktop/sessions` | full session binding, `bookingReference`, `expiresAt` Unix seconds, `policySha256`, `humanConfirmed: true` | `status: "ready"`, `bookingAuthorized: true`, real `brokerReference`, exact policy/expiry |
| `POST /desktop/viewer-tickets` | full session binding, broker reference, policy, requested short expiry | `viewerUrl`, `viewerToken`, `expiresAt` |
| `POST /desktop/close` | full session binding and broker reference | `status: "closed"`, same broker reference |
| `POST /desktop/cancel-by-identity` | full persisted session binding, booking, expiry, policy, `creationIdempotencyKey: "desktop-<sessionId>"`; close idempotency key `desktop-close-<sessionId>` | exact echo of every identity field, `status: "closed"`, `creationKeyTombstoned: true` |

Null-reference lost-create cleanup must use authenticated cancellation by identity, never create/retry launch to discover a reference. The broker must atomically tombstone the original creation key, cancel a session already created under it, and prevent an in-flight/delayed create from resurrecting a stream. Cleanup is actor/tenant/session ownership-bound but is allowed after local eligibility or host policy revocation. Ready/active callbacks recheck current organisation/user/membership/project assignment and authenticated host policy; closed/failed/expired cleanup callbacks do not require launch eligibility.

Each host descriptor supplies `hostId`, `apps` allowlist, `platform: "windows"`, `policySha256` (64 lowercase hex) and all these booleans **true**: `available`, `signedAgent`, `hostConsentVerified`, `processBinding`, `inputFiltering`, `workspaceConfinement`, `selectedAppOnly`, `autoExpire`. Missing/false fields fail closed. The broker must derive them from authenticated host-agent registration/attestation and current owner consent, not browser assertions. Browser capability responses project only approved host/app/policy identifiers; provider secrets/arbitrary metadata are not forwarded.

The broker must verify the real booking, renter/project access, required owner approval and any necessary paid entitlement. `bookingAuthorized` requires those checks. It must bind streaming/input to the approved application process/window and workspace, enforce filtering and expiration on the signed Windows agent, and reject broad desktop/shell access. Application-side booleans cannot enforce OS isolation themselves.

Viewer ticket issuance rechecks current host policy and session state. Tickets are only for ready/active, unexpired sessions; expiry must be positive, no more than 120 seconds away and within session lifetime. Viewer URL must be HTTPS on configured broker hostname/443, without credentials, query or fragment. Tokens use URL-safe opaque characters, at least 43 characters; returned under `Cache-Control: no-store` and `Referrer-Policy: no-referrer`. Tokens/URLs are never persisted by this candidate. The separately provisioned browser viewer must exchange the token securely with that broker and render its actual authenticated host stream; never put tokens in URL queries or logs. Broker must enforce one-use actor/session/host/app/policy-bound ticket redemption and reject revoked/closing/expired sessions.

Close persists `closing` before transport, immediately blocking new tickets; unknown close response remains closing for explicit retry using `desktop-close-<sessionId>`. Only verified broker response or signed callback records closed. Local access expiry can persist `expired`; it is not proof of OS teardown. Host-agent `autoExpire` remains mandatory even if callbacks/application requests stop.

Desktop callbacks use the same timestamp/HMAC headers and exact raw-body verification. Body includes organisation/project/actor/session/host/app binding, `brokerReference`, `policySha256`, `eventId`, `status`. Accepted statuses are `ready`, `active`, `closed`, `failed`, `expired`, constrained by current lifecycle. Tenant lock serializes event replay; changed event body conflicts; terminal sessions cannot reopen. Callback metadata cannot change a session's binding, policy, booking or expiry.

## Source-dependent integration status

Provider/account and host-agent sources are external prerequisites, verified through these authenticated bridge contracts. Signature/identity, CAD/BIM/native exports, drive sync and OCR/source providers have no implemented downstream contracts in this candidate and keep their existing integration-required status. Do not advertise these as connected because the shared HTTPS client exists. Source adapters for those domains require separately documented provider/agent contracts and appropriate ownership before wiring them.

## Operational prerequisites

1. Parent reconciles migration numbering and applies `0067_production_integration_lifecycles.sql` through the approved release process before enabling routes.
2. Parent provisions legitimate merchant bridge/account/beneficiary mapping and tenant-bound broker credentials through the existing configuration loader. Configure per-provider/per-tenant callback secrets with rotation coordinated with senders.
3. Provision broker-host attestation, approved signed Windows agent, real resource booking authorization and real browser viewer. No host capture/agent or public session was installed by this work.
4. Keep clocks synchronized; bridge must deliver asynchronous retryable callbacks and preserve durable idempotency keys.
5. Existing database audit events provide lifecycle traceability. No OpenTelemetry bootstrap/dependency exists in inspected PHP source; OTel onboarding remains separate work.
