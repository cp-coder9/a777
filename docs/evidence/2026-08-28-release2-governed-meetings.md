# Release 2 governed meetings evidence — 2026-08-28

## Scope

This is local implementation evidence for the PHP 8.3 source runtime and the isolated local MariaDB target. It is not staging or remote shared-hosting certification.

## Automated evidence

- `MeetingServiceTest` and authenticated `MeetingHttpTest`: 3 tests, 29 assertions, passing (combined expected total after the added phase-validation case).
- Meeting workspace plus JSON request-boundary tests: 6 tests, 29 assertions, passing.
- Canonical shell JavaScript syntax and PHP controller syntax: passing.
- Live database migration status after `0015_governed_meetings.sql`: 15 applied, 0 pending.

The request-boundary suite now proves that a valid empty JSON object (`{}`) is accepted while top-level JSON lists remain outside the object-payload contract. The HTTP meeting test also proves that start is blocked with an actionable message until every required attendee consent is recorded.

## Chrome DevTools MCP evidence

Persistent page: `http://127.0.0.1:8790/`, project `af189b7f799637ca7918b1a3d6858292`.

The visible governed workspace completed this exact lifecycle:

1. Scheduled `Release 2 governed coordination` with recording, transcription, consent, retention and confidentiality policy.
2. Attempted start before consent; server rejected the transition and left the meeting scheduled.
3. Recorded the named attendee's explicit consent.
4. Started the governed record lifecycle while the UI explicitly stated that external media remained separate.
5. Concluded using an externally supplied transcript source and created draft minutes with one pending decision.
6. Human reviewer accepted the outcome with a reason and confirmed the exact sources.
7. Published immutable minutes revision 1.

Visible published SHA-256:

`1fe6f83e1e1b3eaa4769216d7ba5256dba963eac43083f10be9c5d14d400e7f9`

Chrome DevTools mobile emulation at 390 x 844 reported `innerWidth=390`, `documentElement.scrollWidth=390`, and `body.scrollWidth=390`. Desktop was restored to 1440 x 900. The final console warning/error query returned no messages. The authenticated page remains open in Chrome.

## Independent MariaDB readback

- Meeting ID: `815921949f1094409748e01a212d1817`
- Meeting status: `published`
- Minutes revision/status: `1 / published`
- Database SHA-256 matches the browser-visible hash exactly.
- Accepted outcome write-backs: exactly 1.
- Meeting audit events: 6.
- Issued `meeting_minutes` project records: exactly 1.

## Boundary

The implementation governs meeting metadata, policy, consent, externally sourced transcripts, human outcome decisions, immutable minutes, correction revisions and controlled write-backs. It deliberately does not claim a bundled WebRTC call, recording, storage or speech-to-text provider.
