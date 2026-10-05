#!/usr/bin/env python
"""Architex capability map — TechMap-genre generator (v3).

Renders the Architex module registry as a stage x discipline capability map in the
genre of the TechMap reference (stages x rows, skewed banners, fractional workflow
bands, per-node status marks + text, hand-authored notes).

    rows    = concrete registry `group` disciplines (9)
    columns = workflow stages from route-placement (8) + a Platform-wide rail (9)
    nodes   = registry tools, placed on their row's workflow band at the stage the
              route placement declares

Everything positional is DERIVED from the registries. The only non-derived content
is annotations.json (optional). Stage bands are a WORKFLOW-PLACEMENT cue taken from
route-placement; they are NOT claimed data integrations.

    python tools/techmap/generate_techmap_v3.py            # build + write outputs
    python tools/techmap/generate_techmap_v3.py --check    # build, validate, verify
                                                           # outputs are reproducible
"""
from __future__ import annotations

import html
import json
import sys
import textwrap
from collections import OrderedDict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / "app" / "public" / "assets"
OUTPUTS = [
    ROOT / "docs" / "techmap" / "architex-techmap.html",
    ROOT / "docs" / "techmap" / "index.html",
    ROOT / "app" / "public" / "assets" / "techmap" / "index.html",
]
NOTES_FILE = Path(__file__).resolve().parent / "annotations.json"

# ---- geometry -------------------------------------------------------------
TARGET_W = 1900          # canvas budget; columns narrow to hit it (skill step 4)
GUTTER = 186             # left discipline rail
HEAD = 132               # skewed stage-banner band
ROW_TOP = 44
ROW_BOTTOM = 46
ROW_V = 70               # vertical spacing between stacked nodes in one column
ROW_MIN = 132
NODE_R = 11
BAND_H = 26
SKEW = 8                 # parallelogram skew for banners + bands

# ---- status vocabulary (must match the registry exactly) ------------------
STATUS = OrderedDict([
    ("live",                 {"label": "Live",                 "colour": "#0f6f9c", "mark": "solid", "tint": "#eaf3f9"}),
    ("limited",              {"label": "Limited",              "colour": "#c0392b", "mark": "dash",  "tint": "#fbe9e4"}),
    ("integration_required", {"label": "Integration required", "colour": "#7c3aed", "mark": "dash",  "tint": "#f0eafd"}),
])


def mark_for(tool_id: str, name: str) -> str:
    words = [w for w in name.replace("/", " ").replace("&", " ").split() if w]
    if len(words) >= 3:
        return "".join(w[0] for w in words[:3]).upper()
    if len(words) == 2:
        return (words[0][:2] + words[1][0]).upper()
    return (words[0][:3] if words else tool_id[:3]).upper()


def wrap_text(name: str, width: int = 22, max_lines: int = 3) -> list[str]:
    lines = textwrap.wrap(name, width=width) or [name]
    if len(lines) > max_lines:                       # never silently drop words:
        head = lines[: max_lines - 1]                # merge the tail into the last line
        head.append(" ".join(lines[max_lines - 1:]))
        lines = head
    return lines[:max_lines]


# ---- data -----------------------------------------------------------------
def load():
    """Return (tools, place, stages, notes). tools/place keyed by id."""
    reg = json.loads((ASSETS / "tool-registry.json").read_text(encoding="utf-8"))
    rp = json.loads((ASSETS / "route-placement.json").read_text(encoding="utf-8"))
    for label, entries in [('registry tools', reg['tools']), ('route placements', rp['tools']), ('stages', rp['stages'])]:
        raw_ids = [entry['id'] for entry in entries]
        if len(raw_ids) != len(set(raw_ids)):
            raise ValueError(f'Duplicate ids in {label}')
    tools = {t["id"]: t for t in reg["tools"]}
    place = {t["id"]: t for t in rp["tools"]}
    stages = [(s["id"], s["label"], s.get("order", i * 10))
              for i, s in enumerate(sorted(rp["stages"], key=lambda s: s.get("order", 0)))]
    notes = json.loads(NOTES_FILE.read_text(encoding="utf-8")) if NOTES_FILE.exists() else {}
    return tools, place, stages, notes


def validate(tools, place, stages) -> bool:
    ids = list(tools)
    if len(set(ids)) != len(ids):
        raise ValueError("duplicate tool ids in registry")
    unexpected = set(place) - set(tools)
    if unexpected:
        raise ValueError(f'Unexpected route placement ids: {sorted(unexpected)}')
    known = set(STATUS)
    stage_ids = {s[0] for s in stages}
    for tid, t in tools.items():
        st = t.get("status")
        if st not in known:                                   # no silent default
            raise ValueError(f"tool {tid!r} has unknown status {st!r}; "
                             f"expected one of {sorted(known)}")
        if not t.get("group"):
            raise ValueError(f"tool {tid!r} has no group")
        p = place.get(tid)
        if p is None:
            raise ValueError(f"tool {tid!r} has no route placement")
        sid = p.get("stage")
        if sid not in stage_ids and sid != "platform":
            raise ValueError(f"tool {tid!r} placed on unknown stage {sid!r}")
    return True


def compute(tools, place, stages):
    stage_ids = [s[0] for s in stages]
    stage_label = {sid: lbl for sid, lbl, _ in stages}
    columns = [(sid, lbl) for sid, lbl, _ in stages] + [("platform", "Platform-wide")]
    n_cols = len(columns)
    stage_w = max(150, min(380, (TARGET_W - GUTTER) // n_cols))
    width = GUTTER + n_cols * stage_w

    rows: OrderedDict = OrderedDict()
    for tid, t in tools.items():
        grp = t["group"]
        sid = place[tid].get("stage")
        if sid not in stage_ids:
            sid = "platform"
        rows.setdefault(grp, OrderedDict((c, []) for c, _ in columns))[sid].append(tid)
    for grp in rows:
        for c in rows[grp]:
            rows[grp][c].sort(key=lambda i: tools[i]["name"])
    return rows, columns, stage_ids, stage_label, stage_w, n_cols, width


def band_tint(ids, tools):
    st = {tools[i]["status"] for i in ids}
    if "integration_required" in st:
        return STATUS["integration_required"]["tint"]
    if "limited" in st:
        return STATUS["limited"]["tint"]
    return STATUS["live"]["tint"]


# ---- svg ------------------------------------------------------------------
def svg_body(rows, columns, stage_ids, stage_label, stage_w, tools):
    o: list[str] = []
    o.append('''<defs>
  <style>
    .stage-band { fill:#2b2b2b; }
    .stage-code { fill:#c7ccd3; font:600 11px system-ui,sans-serif; }
    .stage-title { fill:#fff; font:700 13px system-ui,sans-serif; }
    .row-name { fill:#12161a; font:700 12.5px system-ui,sans-serif; }
    .row-sub { fill:#8b939d; font:500 10px system-ui,sans-serif; }
    .rail-line { stroke:#eceff2; stroke-width:1; }
    .col-line { stroke:#eef1f4; stroke-width:1; }
    .node-mark { font:700 9px system-ui,sans-serif; }
    .node-name { fill:#1f2937; font:600 9px system-ui,sans-serif; }
    .node-status { font:600 7.5px system-ui,sans-serif; letter-spacing:.3px; }
    .node { cursor:pointer; }
    .node:focus { outline:none; }
    .node-focus { opacity:0; }
    .node:focus .node-focus { opacity:1; }
    .zone-title { fill:#9aa2ad; font:700 10px system-ui,sans-serif; letter-spacing:.8px; }
    .legend-txt { fill:#5b6470; font:500 10.5px system-ui,sans-serif; }
    .legend-strong { fill:#12161a; font:600 10.5px system-ui,sans-serif; }
    .note-pen { fill:#1a6b7a; font:italic 13px "Segoe Script","Bradley Hand",cursive; }
    .note-card-txt { fill:#1a6b7a; font:italic 11.5px system-ui,sans-serif; }
    .halo { fill:#fff; opacity:.92; }
  </style>
</defs>''')

    for ci in range(1, len(columns)):                      # column separators
        x = GUTTER + ci * stage_w
        o.append(f'<line class="col-line" x1="{x}" y1="{HEAD}" x2="{x}" y2="{HEAD}"/>')

    for ci, (_sid, lbl) in enumerate(columns):             # skewed stage banners
        x = GUTTER + ci * stage_w + 8
        x1 = GUTTER + (ci + 1) * stage_w - 8
        y0, hh = 50, 56
        o.append(
            f'<polygon class="stage-band" points="{x+SKEW},{y0} {x1+SKEW},{y0} {x1},{y0+hh} {x},{y0+hh}"/>'
            f'<text class="stage-code" x="{x+SKEW+10}" y="{y0+20}">{ci+1}</text>'
            f'<text class="stage-title" x="{x+SKEW+10}" y="{y0+42}">{html.escape(lbl)}</text>')

    y = HEAD
    for grp, cells in rows.items():
        maxcell = max((len(v) for v in cells.values()), default=1)
        h = max(ROW_MIN, ROW_TOP + (maxcell - 1) * ROW_V + ROW_BOTTOM)
        band_y = y + h / 2

        # workflow band spanning the stages this discipline's tools occupy
        staged = [ci for ci, (cid, _) in enumerate(columns)
                  if cid in stage_ids and cells.get(cid)]
        if staged:
            bx0 = GUTTER + (min(staged) + 0.12) * stage_w
            bx1 = GUTTER + (max(staged) + 0.88) * stage_w
            band_ids = [i for cid, _ in columns if cid in stage_ids for i in cells.get(cid, [])]
            tint = band_tint(band_ids, tools)
            by = band_y - BAND_H / 2
            o.append(
                f'<polygon class="band" data-band="{html.escape(grp)}" '
                f'points="{bx0+SKEW},{by} {bx1+SKEW},{by} {bx1},{by+BAND_H} {bx0},{by+BAND_H}" '
                f'fill="{tint}" stroke="#dfe6ec" stroke-width="1"/>')

        # rail name + grounded coverage text
        o.append(f'<line class="rail-line" x1="0" y1="{y}" x2="{GUTTER}" y2="{y}"/>')
        name_lines = wrap_text(grp, 22, 3)
        for i, ln in enumerate(name_lines):
            o.append(f'<text class="row-name" x="14" y="{y+22+i*14}">{html.escape(ln)}</text>')
        idlist = [i for cid, _ in columns if cid in stage_ids for i in cells.get(cid, [])]
        if idlist:
            present = [stage_label[cid] for cid, _ in columns if cid in stage_ids and cells.get(cid)]
            cover = " \u2192 ".join(present) if present else "Platform-wide"
            o.append(f'<text class="row-sub" x="14" y="{y+22+len(name_lines)*14+4}">'
                     f'{html.escape(cover)} · {len(idlist)} tool{"s" if len(idlist)!=1 else ""}</text>')

        # nodes centered on the band
        for ci, (cid, _cl) in enumerate(columns):
            ids = cells.get(cid, [])
            k = len(ids)
            cx = GUTTER + ci * stage_w + stage_w / 2
            for ni, tid in enumerate(ids):
                t = tools[tid]
                spec = STATUS[t["status"]]
                colour = spec["colour"]
                ny = band_y + (ni - (k - 1) / 2) * ROW_V
                if spec["mark"] == "solid":
                    ring = (f'<circle class="node-circle" cx="{cx}" cy="{ny}" r="{NODE_R}" '
                            f'fill="{colour}" stroke="{colour}"/>')
                    markfill = "#fff"
                else:
                    ring = (f'<circle class="node-circle" cx="{cx}" cy="{ny}" r="{NODE_R}" '
                            f'fill="#fff" stroke="{colour}" stroke-width="1.8" stroke-dasharray="4 3"/>')
                    markfill = colour

                nlines = wrap_text(t["name"])
                ly = ny + NODE_R + 13
                name_svg = "".join(
                    f'<text class="node-name" x="{cx}" y="{ly+i*10.5}" text-anchor="middle">'
                    f'{html.escape(ln)}</text>' for i, ln in enumerate(nlines))
                status_svg = (f'<text class="node-status" x="{cx}" y="{ly+len(nlines)*10.5+3}" '
                              f'text-anchor="middle" fill="{colour}">{html.escape(spec["label"])}</text>')
                stage_txt = stage_label.get(cid, "Platform-wide")
                aria = (f'{t["name"]} — {t["group"]} — {stage_txt} — '
                        f'status {spec["label"]} (registry-declared)')
                o.append(
                    f'<g class="node" data-tool-id="{html.escape(tid)}" tabindex="0" role="button" '
                    f'aria-label="{html.escape(aria)}">'
                    f'<circle class="node-focus" cx="{cx}" cy="{ny}" r="{NODE_R+4}" fill="none" '
                    f'stroke="#2b6cb0" stroke-width="2"/>'
                    f'<circle class="halo" cx="{cx}" cy="{ny}" r="{NODE_R+2}"/>'
                    f'{ring}'
                    f'<text class="node-mark" x="{cx}" y="{ny+3}" text-anchor="middle" fill="{markfill}">'
                    f'{html.escape(mark_for(tid, t["name"]))}</text>'
                    f'{name_svg}{status_svg}</g>')
        y += h

    return "".join(o), y - HEAD


# ---- document -------------------------------------------------------------
def render_document() -> str:
    """Return the complete self-contained HTML document as a string."""
    tools, place, stages, notes = load()
    validate(tools, place, stages)
    rows, columns, stage_ids, stage_label, stage_w, n_cols, width = compute(tools, place, stages)
    body, content_h = svg_body(rows, columns, stage_ids, stage_label, stage_w, tools)

    # ---- legend / basis
    ly = HEAD + content_h + 30
    o: list[str] = [body]
    o.append(f'<text class="zone-title" x="14" y="{ly}">STATUS (registry-declared)</text>')
    lx = 14
    for _st, spec in STATUS.items():
        if spec["mark"] == "solid":
            g = f'<circle cx="{lx+10}" cy="{ly+20}" r="9" fill="{spec["colour"]}"/>'
        else:
            g = (f'<circle cx="{lx+10}" cy="{ly+20}" r="9" fill="#fff" stroke="{spec["colour"]}" '
                 f'stroke-width="1.8" stroke-dasharray="4 3"/>')
        o.append(f'<g>{g}<text class="legend-txt" x="{lx+26}" y="{ly+24}">'
                 f'{html.escape(spec["label"])}</text></g>')
        lx += 26 + 7.0 * len(spec["label"]) + 34
    o.append(f'<polygon points="{lx+SKEW},{ly+10} {lx+54+SKEW},{ly+10} {lx+54},{ly+34} {lx},{ly+34}" '
             f'fill="{STATUS["live"]["tint"]}" stroke="#dfe6ec"/>'
             f'<text class="legend-strong" x="{lx+66}" y="{ly+20}">Workflow band</text>'
             f'<text class="legend-txt" x="{lx+66}" y="{ly+32}">'
             f'stage coverage from route placement — not a verified integration</text>')
    basis = ("Positions derive from tool-registry.json (group, status, evidence) and "
             "route-placement.json (declared workflow stage). Status is registry-declared and "
             "not independently verified by this map.")
    o.append(f'<text class="legend-txt" x="14" y="{ly+54}">{html.escape(basis)}</text>')
    legend_h = 74

    # ---- notes (pen / card), optional, authored in annotations.json
    ny0 = ly + legend_h + 24
    n_cursor = 24
    have_notes = bool(notes.get("notes"))
    for n in (notes.get("notes") or []):
        txt = n.get("text", "")
        style = n.get("style", "card")
        tilt = n.get("tilt", -1)
        if style == "pen":
            lines = wrap_text(txt, 46, 4)
            o.append(f'<g transform="rotate({tilt} {n_cursor} {ny0})">')
            for i, l in enumerate(lines):
                o.append(f'<text class="note-pen" x="{n_cursor}" y="{ny0+i*18}">{html.escape(l)}</text>')
            o.append('</g>')
            n_cursor += 500
        else:
            lines = wrap_text(txt, 42, 6)
            hh = 30 + len(lines) * 16
            o.append(f'<g transform="rotate({tilt} {n_cursor} {ny0})">'
                     f'<rect x="{n_cursor}" y="{ny0}" width="320" height="{hh}" rx="3" '
                     f'fill="#fffdf5" stroke="#e6e0c8"/>'
                     f'<rect x="{n_cursor}" y="{ny0}" width="320" height="5" fill="#e08a2e"/>')
            for i, l in enumerate(lines):
                o.append(f'<text class="note-card-txt" x="{n_cursor+12}" y="{ny0+26+i*16}">'
                         f'{html.escape(l)}</text>')
            o.append('</g>')
            n_cursor += 344
        if n_cursor > width - 360:
            n_cursor = 24
            ny0 += 150
    notes_h = (ny0 - (ly + legend_h)) + (150 if have_notes else 40)
    total_h = int(ly + legend_h + notes_h)

    seps = "".join(f'<line class="col-line" x1="{GUTTER+ci*stage_w}" y1="{HEAD}" '
                   f'x2="{GUTTER+ci*stage_w}" y2="{ly-14}"/>' for ci in range(1, n_cols))
    svg_inner = seps + "".join(o)

    # ---- registry-derived data for List view + detail dialog
    reg_tools = []
    for tid, t in sorted(tools.items(), key=lambda kv: kv[1]["name"].lower()):
        sid = place[tid].get("stage")
        reg_tools.append({
            "id": tid, "name": t["name"], "group": t["group"], "status": t["status"],
            "statusLabel": STATUS[t["status"]]["label"],
            "stage": stage_label.get(sid, "Platform-wide"),
            "declaredStage": t.get("stage", ""),
            "evidence": t.get("evidence", ""),
        })
    tool_json = json.dumps(reg_tools, ensure_ascii=False).replace("</", "<\\/")

    n = len(tools)
    n_groups = len(rows)
    n_stages = len(stage_ids)
    counts = OrderedDict((st, sum(1 for t in tools.values() if t["status"] == st)) for st in STATUS)
    list_rows = "".join(
        f'<tr class="tool-row" data-tool-id="{html.escape(r["id"])}" tabindex="0">'
        f'<td>{html.escape(r["name"])}</td><td>{html.escape(r["group"])}</td>'
        f'<td>{html.escape(r["stage"])}</td>'
        f'<td><span class="dot" style="background:{STATUS[r["status"]]["colour"]}"></span>'
        f'{html.escape(r["statusLabel"])}</td></tr>' for r in reg_tools)

    return f'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Architex capability map — by stage &amp; discipline</title>
<style>
  :root {{ --ink:#12161a; --dim:#6b7280; --line:#e8ebef; --bg:#fff; }}
  * {{ box-sizing:border-box; }}
  html,body {{ margin:0; height:100%; background:var(--bg); color:var(--ink);
    font:13px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }}
  header {{ position:fixed; inset:0 0 auto 0; z-index:30; background:#fff;
    border-bottom:1px solid var(--line); padding:8px 16px; display:flex;
    align-items:center; gap:12px; flex-wrap:wrap; }}
  .brand {{ font-weight:700; font-size:14px; }}
  .tabs {{ display:flex; gap:2px; background:#f1f3f5; border-radius:7px; padding:2px; }}
  .tab {{ padding:5px 12px; border-radius:5px; font-size:12.5px; color:var(--dim);
    border:0; background:transparent; cursor:pointer; }}
  .tab.on {{ background:#2b2b2b; color:#fff; font-weight:600; }}
  .shared {{ color:var(--dim); font-size:12px; }}
  .right {{ margin-left:auto; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }}
  .search {{ border:1px solid var(--line); border-radius:7px; padding:6px 11px;
    font-size:12.5px; width:190px; }}
  .stats {{ font-size:12px; color:var(--dim); }}
  .stats b {{ color:var(--ink); }}
  #match-count {{ font-size:12px; color:var(--dim); }}
  #stage {{ position:absolute; top:96px; left:0; right:0; bottom:0; overflow:auto; background:#fff; }}
  #map-wrap {{ position:relative; transform-origin:0 0; }}
  #map {{ display:block; transform-origin:0 0; }}
  .zoom {{ position:fixed; right:16px; bottom:18px; z-index:40; display:flex; gap:6px;
    background:#fff; border:1px solid var(--line); border-radius:9px; padding:5px;
    box-shadow:0 2px 10px rgba(0,0,0,.07); }}
  .zoom button {{ min-width:32px; height:30px; border:0; background:#f4f6f8; border-radius:6px;
    font-size:14px; cursor:pointer; color:#374151; }}
  .zoom button:hover {{ background:#e8ebef; }}
  .zoom .fit {{ width:auto; padding:0 11px; font-size:12px; font-weight:600; }}
  #list-view {{ position:absolute; top:96px; left:0; right:0; bottom:0; overflow:auto;
    padding:16px; background:#fff; }}
  #list-view table {{ border-collapse:collapse; width:100%; max-width:1100px; }}
  #list-view th, #list-view td {{ text-align:left; padding:7px 10px;
    border-bottom:1px solid var(--line); font-size:12.5px; }}
  #list-view th {{ color:var(--dim); font-weight:600; position:sticky; top:0; background:#fff; }}
  #list-view tr.tool-row:hover {{ background:#f8fafc; }}
  .dot {{ display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:6px; }}
  footer {{ position:fixed; inset:auto 0 0 0; z-index:25; background:#fff;
    border-top:1px solid var(--line); padding:7px 16px; font-size:11px; color:var(--dim);
    display:flex; gap:14px; flex-wrap:wrap; }}
  dialog#details {{ border:1px solid var(--line); border-radius:10px; padding:0; max-width:440px;
    width:calc(100% - 32px); box-shadow:0 12px 40px rgba(0,0,0,.18); }}
  dialog#details .dh {{ padding:14px 16px; border-bottom:1px solid var(--line); font-weight:700; }}
  dialog#details .db {{ padding:14px 16px; }}
  dialog#details .kv {{ display:grid; grid-template-columns:150px 1fr; gap:6px 10px;
    font-size:12.5px; }}
  dialog#details .kv dt {{ color:var(--dim); }}
  dialog#details .kv dd {{ margin:0; }}
  .badge {{ display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px;
    font-weight:600; }}
  dialog#details .df {{ padding:10px 16px; border-top:1px solid var(--line); text-align:right; }}
  dialog#details button {{ border:1px solid var(--line); background:#f4f6f8; border-radius:7px;
    padding:6px 14px; cursor:pointer; font-size:12.5px; }}
  .sr-only {{ position:absolute; width:1px; height:1px; padding:0; margin:-1px;
    overflow:hidden; clip:rect(0,0,0,0); border:0; }}
  @media (max-width:720px) {{
    .shared {{ display:none; }}
    #stage, #list-view {{ top:118px; }}
    .search {{ width:130px; }}
  }}
  @media print {{
    header, .zoom, footer, #list-view {{ display:none !important; }}
    #stage {{ position:static; overflow:visible; top:0; }}
    #map-wrap {{ transform:none !important; width:auto !important; height:auto !important; }}
    #map {{ transform:none !important; }}
  }}
</style>
</head>
<body>
<header>
  <span class="brand">Architex Capability Map</span>
  <div class="tabs" role="tablist" aria-label="View">
    <button id="map-tab" class="tab on" role="tab" aria-selected="true" onclick="showView('map')">Map</button>
    <button id="list-tab" class="tab" role="tab" aria-selected="false" onclick="showView('list')">List</button>
  </div>
  <span class="shared">by stage &amp; discipline · generated from the module registry</span>
  <div class="right">
    <label class="sr-only" for="search">Search tools</label>
    <input id="search" class="search" type="search" placeholder="Search tools…"
           aria-label="Search tools by name, discipline, stage or status" oninput="applyFilter()">
    <span id="match-count" role="status" aria-live="polite">{n} of {n} tools shown</span>
    <span class="stats"><b>{n}</b> tools · <b>{n_groups}</b> disciplines · <b>{n_stages}</b> stages</span>
  </div>
</header>

<div id="stage">
  <div id="map-wrap">
    <svg id="map" width="{width}" height="{total_h}" viewBox="0 0 {width} {total_h}" role="img"
         aria-label="Architex capability map: {n} tools across {n_stages} workflow stages and {n_groups} disciplines">
{svg_inner}
    </svg>
  </div>
</div>

<section id="list-view" hidden>
  <table>
    <thead><tr><th>Tool</th><th>Discipline</th><th>Stage</th><th>Status</th></tr></thead>
    <tbody>{list_rows}</tbody>
  </table>
</section>

<div class="zoom">
  <button id="zoom-out" onclick="zoomBy(1/1.2)" title="Zoom out" aria-label="Zoom out">−</button>
  <button id="zoom-in" onclick="zoomBy(1.2)" title="Zoom in" aria-label="Zoom in">+</button>
  <button id="fit-width" class="fit" onclick="fitToWidth()">Fit width</button>
  <button id="fit-all" class="fit" onclick="fitToWindow()">Fit all</button>
  <button id="print-map" class="fit" onclick="window.print()" title="Print or save as PDF">Print / PDF</button>
</div>

<dialog id="details" aria-labelledby="detail-title">
  <div class="dh" id="detail-title">Tool</div>
  <div class="db"><dl class="kv" id="detail-body"></dl></div>
  <div class="df"><button id="detail-close"
       onclick="document.getElementById('details').close()">Close</button></div>
</dialog>

<footer>
  <span>{counts['live']} live · {counts['limited']} limited · {counts['integration_required']} integration required</span>
  <span>Derived from <code>tool-registry.json</code> + <code>route-placement.json</code>.
    Status is registry-declared, not independently verified.</span>
</footer>

<script>
var TOOLS = {tool_json};
var svg = document.getElementById('map');
var wrap = document.getElementById('map-wrap');
var stage = document.getElementById('stage');
var W = {width}, H = {total_h};
var scale = 1;

function apply() {{
  svg.style.transform = 'scale(' + scale + ')';   /* single scale: SVG stays W x H */
  wrap.style.width = (W * scale) + 'px';          /* wrapper carries the scaled box */
  wrap.style.height = (H * scale) + 'px';
}}
function zoomBy(f) {{
  scale = Math.min(6, Math.max(0.1, scale * f));
  apply();
}}
function fitToWidth() {{
  scale = Math.min(6, Math.max(0.1, (stage.clientWidth - 24) / W));
  apply();
}}
function fitToWindow() {{
  var aw = stage.clientWidth - 24, ah = stage.clientHeight - 40;
  scale = Math.min(6, Math.max(0.1, Math.min(aw / W, ah / H)));
  apply();
}}
function showView(which) {{
  var isMap = which === 'map';
  document.getElementById('map-tab').classList.toggle('on', isMap);
  document.getElementById('list-tab').classList.toggle('on', !isMap);
  document.getElementById('map-tab').setAttribute('aria-selected', String(isMap));
  document.getElementById('list-tab').setAttribute('aria-selected', String(!isMap));
  stage.hidden = !isMap;
  document.getElementById('list-view').hidden = isMap;
  if (isMap) {{ apply(); }}
}}
function norm(s) {{ return (s || '').toLowerCase().trim(); }}
function escapeText(s) {{
  var el = document.createElement('span');
  el.textContent = String(s == null ? '' : s);
  return el.innerHTML;
}}
function applyFilter() {{
  var q = norm(document.getElementById('search').value);
  var hits = {{}}, shown = 0;
  TOOLS.forEach(function (t) {{
    var hay = norm(t.name + ' ' + t.group + ' ' + t.stage + ' ' + t.statusLabel);
    if (!q || hay.indexOf(q) !== -1) {{ hits[t.id] = true; shown++; }}
  }});
  document.querySelectorAll('#map g.node').forEach(function (g) {{
    g.style.opacity = hits[g.getAttribute('data-tool-id')] ? '1' : '0.12';
  }});
  document.querySelectorAll('#list-view tr.tool-row').forEach(function (tr) {{
    tr.hidden = !hits[tr.getAttribute('data-tool-id')];
  }});
  document.getElementById('match-count').textContent = shown + ' of ' + TOOLS.length + ' tools shown';
}}
function openDetail(id) {{
  var t = null;
  for (var i = 0; i < TOOLS.length; i++) {{ if (TOOLS[i].id === id) {{ t = TOOLS[i]; break; }} }}
  if (!t) return;
  var col = {{live:'#0f6f9c', limited:'#c0392b', integration_required:'#7c3aed'}}[t.status] || '#374151';
  var rows = [
    ['Discipline', escapeText(t.group)],
    ['Workflow stage (route placement)', escapeText(t.stage)],
    ['Declared stage(s) (registry)', escapeText(t.declaredStage || '—')],
    ['Status', '<span class="badge" style="background:' + col + '22; color:' + col + '">' +
      escapeText(t.statusLabel) + '</span> <small>registry-declared — not independently verified</small>'],
    ['Source evidence', escapeText(t.evidence || '—')],
    ['Registry id', '<code>' + escapeText(t.id) + '</code>']
  ];
  document.getElementById('detail-body').innerHTML = rows.map(function (r) {{
    return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>';
  }}).join('');
  document.getElementById('detail-title').textContent = t.name;
  var d = document.getElementById('details');
  if (d.showModal) {{ d.showModal(); }} else {{ d.setAttribute('open', ''); }}
}}
document.querySelectorAll('#map g.node, #list-view tr.tool-row').forEach(function (g) {{
  var id = g.getAttribute('data-tool-id');
  g.addEventListener('click', function () {{ openDetail(id); }});
  g.addEventListener('keydown', function (e) {{
    if (e.key === 'Enter' || e.key === ' ') {{ e.preventDefault(); openDetail(id); }}
  }});
}});
window.addEventListener('load', function () {{
  fitToWidth();
  if (scale < 0.35) {{ scale = 0.35; apply(); }}   /* keep the first view readable on phones */
  applyFilter();
}});
window.addEventListener('resize', function () {{ fitToWidth(); }});
</script>
</body>
</html>
'''


def main(argv: list[str] | None = None) -> int:
    argv = list(sys.argv[1:] if argv is None else argv)
    tools, place, stages, _notes = load()
    validate(tools, place, stages)
    doc = render_document()
    counts = OrderedDict((st, sum(1 for t in tools.values() if t["status"] == st)) for st in STATUS)
    groups = OrderedDict()
    for t in tools.values():
        groups[t["group"]] = groups.get(t["group"], 0) + 1

    if "--check" in argv:
        ok = True
        for out in OUTPUTS:
            if not out.exists():
                print(f"  MISSING {out}"); ok = False; continue
            if out.read_text(encoding="utf-8") != doc:
                print(f"  STALE   {out}"); ok = False
            else:
                print(f"  ok      {out}")
        print(f"  {len(tools)} tools · {len(groups)} disciplines · {len(stages)} stages · "
              f"{counts['live']} live / {counts['limited']} limited / "
              f"{counts['integration_required']} integration")
        return 0 if ok else 1

    for out in OUTPUTS:
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(doc, encoding="utf-8")
        print(f"  wrote {out}")
    print(f"  {len(tools)} tools · {len(groups)} disciplines · {len(stages)} stages")
    print(f"  {counts['live']} live / {counts['limited']} limited / "
          f"{counts['integration_required']} integration required")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
