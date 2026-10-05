# Architex capability map

> **Current edition: circular radar with full coverage views.** See [RADAR-GUIDE.md](RADAR-GUIDE.md). Build with `python tools/techmap/generate_radar.py`, not the rectangular v3 command below. The following describes the earlier rectangular edition; the radar generator now owns the shared HTML destinations.

## Purpose and scope

Applies the stage-and-discipline visual language of [Digital Reframe's shared TechMap](https://techmap-by-digital-reframe.vercel.app/s/zItTgqC5Xd93) to **this project's actual modules**. This is a navigable product capability map, not an adoption of the reference practice's third-party software stack and not a replacement for the application shell.

The reference has four project-stage columns, six discipline rows, skewed workflow bands, circular software marks, status rings, connections and handwritten notes. Architex retains that visual genre while using its own eight workflow stages plus a Platform-wide column and its nine registry groups. Do not relabel internal action families such as `review` as disciplines.

## Source of truth

- `app/public/assets/tool-registry.json`: names, discipline groups, status declarations, wider stage coverage and evidence descriptions.
- `app/public/assets/route-placement.json`: primary route placement and project-context requirement.
- `tools/techmap/annotations.json`: optional, explanatory notes. Do not add speculative integrations or professional sign-off claims.

At the time this adaptation was made, the source contains **49 tools: 38 live, 9 limited and 2 integration_required**. The generated page derives its counts on each build; this documentation's snapshot is not a separate registry.

**Status is registry-declared, not independently reverified by the map.** A status of live must not be read as production certification or professional approval. The evidence description is source metadata, not a newly executed test. Payments & Escrow requires a configured money-movement provider; Remote Desktop requires its host agent, broker, viewer and allowlist. The map does not enable either integration.

Columns locate each tool by its primary route. Tools may also support other stages; inspect their broader registry coverage in tool details. Workflow bands are visual placement/coverage, **not proof that two tools automatically exchange data**. No proven integration arrows are invented to match the reference's appearance.

## Generate and open

From the repository root:

```bash
python tools/techmap/generate_techmap_v3.py
python -m unittest discover -s tools/techmap -p 'test_*.py' -v
node tools/techmap/verify_techmap.cjs
```

One generator writes identical, self-contained offline HTML to:

- `docs/techmap/architex-techmap.html`
- `docs/techmap/index.html`
- `app/public/assets/techmap/index.html`

Open the first file directly in a browser. With the application's public directory served, the map is available at `/assets/techmap/index.html`. Deployment is a separate operation: regenerating the local files does not upload them to a production host.

## User guide

- **Map** shows tools by primary workflow stage and discipline.
- **List** provides a readable alternative for small screens and quick comparison.
- Search narrows the visible or highlighted modules; clear it to restore the full map.
- Open a tool with a click or keyboard to inspect its name, declared status, evidence and wider stage coverage.
- Use zoom controls, **Fit width** or **Fit all**. Fit width is intended for reading with vertical scrolling; Fit all is an overview, not a promise that a dense full map is legible at phone scale.
- Use the print control and your browser's **Save as PDF** destination. This invokes real browser printing; it is not a fabricated PDF download.

## Maintenance boundaries

Update the registries in their owning workflow and regenerate. Avoid hand-editing generated HTML, copying stale outputs manually, or running older v1/v2 generators over the v3 destinations. This change does not alter project permissions, database records, external providers, professional compliance decisions or the existing application navigation.

## Verification

Eight independent artifact-contract tests pass. The browser proof uses the existing Playwright package under `tools/v1-clone/node_modules` and the installed Chrome channel, avoiding an additional Chromium download. It exercises both Map and List tool details with click and Enter, search matches and zero matches, zoom, single-scale geometry, the real print invocation, and mobile layout at 390px. It reports zero page errors and writes screenshots to `docs/evidence/techmap/`. Status counts are checked against the registry, not manually synthesized.
