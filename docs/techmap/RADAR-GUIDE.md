# Complete Architex technology radar

## Published edition

Live: https://cp-coder9.github.io/architex-technology-radar/

Artifact-only repository: https://github.com/cp-coder9/architex-technology-radar

The polished edition is hosted on GitHub Pages, with no temporary-preview expiry. Only the generated HTML, `.nojekyll` and a short publication README are published; the PHP application and credentials are not uploaded. HTTP 200, exact SHA-256 artifact equality and the desktop/mobile browser proof were verified against the live site. Redeploy after regeneration with `python tools/techmap/deploy_pages.py`; verify with `node tools/techmap/verify_techmap.cjs https://cp-coder9.github.io/architex-technology-radar/`.

The first Cloudflare temporary preview was blocked by a security-verification page and is not the recommended link.

The current edition is circular: disciplines are sectors; registry-declared capability statuses are concentric rings. Numbered blips correspond to the full-name module key. The rectangular v3 generator remains a reusable base but is not the command to build the current radar.

## Information coverage

- **Radar and List:** every source registry module, full name, declared status, discipline and primary route placement.
- **Coverage → Tool notes:** every tool has a usage note, boundary, wider stage scope, replacement evidence state and source locations.
- **Coverage → Workflow coverage:** full eight-stage matrix using the registry's wider stage scope. Primary route placement is marked separately. Extra scope such as Handover is preserved in the source column rather than silently normalised.
- **Coverage → Connections:** source-backed relationship descriptions, types and code references. Radar lines are opt-in; opening a module highlights its recorded connections.
- **Coverage → Nested groups:** source-backed implementation groupings and registry discipline parents. Members open full details.
- **Replacement evidence:** every module has an explicit evidence state. Not documented means the repository does not establish a replacement; it does not invent adoption of Digital Reframe's software choices.

Completeness means all information categories and all registry modules are represented. It does not certify every possible architectural relationship or infer undocumented practice choices. A missing recorded connection is not proof that no connection exists.

## Evidence semantics

The registry currently declares 49 modules: 38 live, nine limited and two requiring integration. These are not fresh professional sign-offs or production certifications.

`data_flow` identifies an implemented flow from inspected source code. `shared_backend` identifies shared implementation, not automatic exchange. `workflow` identifies a documented workflow relationship. Source locations are validated during generation. No relationship is presented as newly runtime-certified.

The coverage source is `tools/techmap/coverage.json`; module ownership remains `app/public/assets/tool-registry.json` and `route-placement.json`.

`tools/techmap/source-evidence.json` preserves the referenced lines and surrounding context with SHA-256 file identities from the inspected working tree. Some referenced backend/adapters are pending in other workstreams and are deliberately not included in this radar-only PR. A clean checkout validates against these immutable inspection excerpts, not a claim that all those implementations are part of the PR. To explicitly compare the current checkout with the captured sources, run `python tools/techmap/generate_radar.py --verify-sources --check`. Refresh the evidence only deliberately with `python tools/techmap/capture_source_evidence.py` after reviewing the actual source changes.

## Build and verification

```bash
python tools/techmap/generate_radar.py
python tools/techmap/generate_radar.py --check
python -m unittest discover -s tools/techmap -p 'test_*.py' -v
node tools/techmap/verify_techmap.cjs
```

For a fresh checkout, install the browser test dependency with `npm ci --prefix tools/techmap`. The browser proof defaults to installed Google Chrome. Alternatively, run `npx --prefix tools/techmap playwright install chromium`, then `ARCHITEX_BROWSER_CHANNEL=bundled node tools/techmap/verify_techmap.cjs`. No local `tools/v1-clone/node_modules` folder is required; it is only a compatibility fallback in the existing development checkout.

The CI recipe is provided at `tools/techmap/technology-radar.workflow.yml`. It is not active: the publishing GitHub token lacks `workflow` scope, so this PR does not add `.github/workflows` files or request broader authentication. An authorised maintainer can place the recipe at `.github/workflows/technology-radar.yml` to enable fresh-checkout artifact, Python and Chromium verification.

Four identical self-contained outputs are produced: `docs/techmap/architex-radar.html`, `docs/techmap/architex-techmap.html`, `docs/techmap/index.html`, and `app/public/assets/techmap/index.html`.

Open the radar HTML directly in Chrome. The application's public route is `/assets/techmap/index.html` when that directory is served. This is a local build, not a production upload.

Tests cover exact module coverage, duplicate IDs, controls, preserved names/evidence, identical outputs, validated source paths/lines, information layers and non-overlapping blips. The existing Playwright package and installed Chrome are used for desktop/mobile interaction proofs. Screenshots are written to `docs/evidence/techmap/`.

Use List and Coverage for readable small-screen access. Search filters Radar, List, tool notes and workflow rows; clear it to restore all modules. Fit all is an overview, not a promise of full phone-scale legibility. Print / PDF opens real browser printing; choose Save as PDF there.

No database records, external integrations, permissions or professional decisions are changed by this visualisation.
