# Pack 22 authenticated learner browser check

Chrome DevTools MCP, local server http://127.0.0.1:8810/, isolated context cpd-recert.

Created a repeatable five-role fixture using tools/seed-cpd-browser.php. The script requires the existing disposable database guard (explicit opt-in, test environment, loopback host, task-owned test database name) and a password supplied through CPD_BROWSER_PASSWORD. It creates a reserved synthetic organization and accounts; it updates passwords only for those synthetic fixture addresses. No credentials are stored in this report or script.

Executed real browser sign-in using the learner account. The resulting session user ID matched the fixture learner. Opened CPD through the application navigation function. Observed:

- Title: CPD Learning Hub — Architex OS.
- Persisted profile name: Browser learner.
- Creator form hidden: true.
- Role manager hidden: true.
- Document horizontal overflow: 0 pixels.
- Workspace-unavailable notices: none.

This verifies authenticated learner loading only. It does not certify course enrollment, assessments, reviewer decisions, certificates, other role screens, mobile layout or the complete Pack 22 workflow. No completed-module screenshot is claimed. The isolated Chrome tab remains open for continued testing.
