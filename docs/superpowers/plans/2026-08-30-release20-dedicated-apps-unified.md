# Release 20 — Dedicated Apps Unified Integration

**Pack:** `24. architex-dedicated-apps-unified-implementation-pack`

**Target:** PHP 8.3 and MariaDB on shared hosting, deployable by FTP, preserving the canonical V1 shell and using cron-safe workers rather than resident daemons.

**Release boundary:** Architex will implement the production integration spine and truthful operational control plane for all 12 dedicated capabilities. Signed Windows host agents, Revit/CAD plugins, native sync binaries, push/SMS/WhatsApp providers, signature providers and payment providers cannot be manufactured by the PHP deployment; they remain `integration_required` until a real binary or provider configuration is registered and verified.

## Implementation plan

- [ ] Add MariaDB tables for tenant/project-scoped dedicated-app installations, one-time credential provenance, workflow events, offline sync batches/conflicts and notification dispatch evidence.
- [ ] Encode the full 12-app registry in PHP with the supplied priority, runtime kind, repository anchors, spine outputs, offline/device needs, MVP capabilities and sensitive gates.
- [ ] Add governance routing that blocks missing tenant/actor/app scope, preserves `aiMayExecute=false`, and requires accountable humans for approval, signature, payment release, municipal submission, professional certification and closeout acceptance.
- [ ] Implement owner/admin installation registration, secret hashing, revocation, build/version/device provenance, heartbeat and readiness reporting without ever returning stored secrets.
- [ ] Implement authenticated project-scoped workflow ingestion with idempotency, ProjectRecord/FileManager/Inbox/Risk/Notification routing facts, human-gate state and immutable audit events.
- [ ] Implement ordered offline sync submission with optimistic base versions, explicit conflict records and no silent overwrite.
- [ ] Reuse the existing scheduled-job foundation for cron-safe worker/notification dispatch requests; unsupported providers must fail closed as `integration_required`.
- [ ] Publish dedicated-app catalogue, status, installation, event and sync APIs with tenant/role enforcement and route-contract tests.
- [ ] Add a responsive V1-native Dedicated Apps control centre showing all 12 capabilities, exact readiness, missing integration steps, provenance, sync conflicts, worker state and governance boundaries.
- [ ] Extend the mobile/site PWA installability contract with a manifest and service worker limited to safe static-shell/offline-draft support; authenticated API responses must never be cached.
- [ ] Verify focused contracts, connected disposable MariaDB workflows, idempotency, tenant isolation, stale/conflicting sync, sensitive human gates, token secrecy and provider fail-closed behavior.
- [ ] Certify desktop and 390 px mobile flows in persistent Chrome DevTools MCP with screenshots, console/network checks and Lighthouse.
- [ ] Commit Release 20 and build an independently extracted and verified FTP ZIP from its exact source commit.

## Shared-hosting operating model

- PHP requests perform bounded ingestion and state transitions.
- Shared-hosting cron invokes bounded queue dispatch; no resident Node or PHP daemon is assumed.
- Dedicated/native clients upload evidence through authenticated, scoped APIs; they never write directly to MariaDB or server folders.
- Large file payloads continue through FileManager; workflow events store references and checksums, not duplicate blobs.

## Truthfulness and safety

- Registration metadata is not proof that a native app or provider is online.
- `registered`, `online`, `degraded`, `integration_required`, `revoked` and `disabled` are distinct states.
- Architex never holds, transfers or controls funds.
- Selected-app-only remote desktop remains blocked until a signed host agent proves allowlist, window binding, input filtering and workspace confinement.
- Offline sync conflicts remain visible until an accountable user resolves them.
- GPS, device identity, photos and voice evidence remain tenant/project scoped and subject to POPIA consent/retention controls.
