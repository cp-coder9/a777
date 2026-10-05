"""Generate the circular Architex technology radar from the owning registries.

Uses the existing offline map controls and details, replacing only the geometry.
Rings encode declared capability status, not maturity scores or adoption advice.
"""
import html
import math
import re
import sys
from collections import Counter
import generate_techmap_v3 as base
import radar_coverage
import radar_polish

W, H = 1490, 1120
CX, CY = 510, 535
RADII = {'live': (135, 268), 'limited': (286, 354), 'integration_required': (375, 437)}
NODE_COORDS = {}


def point(radius, angle):
    a = math.radians(angle)
    return CX + radius * math.cos(a), CY + radius * math.sin(a)


def text(x, y, value, **attrs):
    attr = ' '.join(f'{k.rstrip("_").replace("_", "-")}="{html.escape(str(v), quote=True)}"' for k, v in attrs.items())
    return f'<text x="{x:.2f}" y="{y:.2f}" {attr}>{html.escape(str(value))}</text>'


def radar_svg(tools):
    groups = list(dict.fromkeys(t['group'] for t in tools.values()))
    numbers = {tid: i + 1 for i, tid in enumerate(sorted(tools, key=lambda tid: tools[tid]['name'].lower()))}
    step = 360 / len(groups)
    out = ['<style>.node{cursor:pointer}.node:focus{outline:none}.node:focus circle{stroke:#111;stroke-width:4}.radar-label{font:600 13px system-ui;fill:#293746}.radar-key{font:13px system-ui;fill:#263548}</style>']
    out.append('<rect width="1490" height="1120" fill="#fff"/>')
    out.append(text(30, 35, 'ARCHITEX / TECHNOLOGY RADAR', font_size=23, font_weight=750, fill='#152638'))
    out.append(text(30, 62, 'Disciplines around the radar · registry-declared capability status from centre outward', font_size=13, fill='#657482'))
    # Outer-to-inner discs leave three distinct, labelled concentric bands.
    for radius, fill in [(447, '#f2edfb'), (363, '#fff1e9'), (277, '#e9f4f7'), (125, '#fff')]:
        out.append(f'<circle cx="{CX}" cy="{CY}" r="{radius}" fill="{fill}" stroke="#d5dfe5" stroke-width="1.2"/>')
    for gi, group in enumerate(groups):
        angle = -90 + gi * step
        x, y = point(447, angle)
        ix, iy = point(125, angle)
        out.append(f'<path d="M {ix:.2f} {iy:.2f} L {x:.2f} {y:.2f}" stroke="#becbd4" stroke-dasharray="3 5"/>')
        mid = angle + step / 2
        lx, ly = point(487, mid)
        words = base.wrap_text(group, 23, 3)
        anchor = 'start' if lx < 100 else ('end' if lx > 930 else 'middle')
        for i, line in enumerate(words):
            out.append(text(lx, ly + (i - (len(words)-1)/2)*15, line, text_anchor=anchor, class_='radar-label'))
        for status, (rmin, rmax) in RADII.items():
            ids = sorted([tid for tid,t in tools.items() if t['group']==group and t['status']==status], key=lambda tid: tools[tid]['name'])
            # A deterministic staggered grid occupies only the interior of the sector.
            candidates = []
            radius = rmin + 14
            while radius <= rmax - 10:
                angular_gap = math.degrees(2 * math.asin(15 / radius))
                slots = max(1, int((step - 12) / angular_gap) + 1)
                for col in range(slots):
                    theta = angle + (step / 2 if slots == 1 else 6 + (step - 12) * col / (slots - 1))
                    candidates.append((radius, theta))
                radius += 32
            if len(ids) > len(candidates):
                raise ValueError(f'Radar sector too crowded: {group} / {status}')
            for i, tid in enumerate(ids):
                radius, theta = candidates[i]
                nx, ny = point(radius, theta)
                NODE_COORDS[tid] = (nx, ny)
                spec = base.STATUS[status]
                fill = spec['colour'] if status == 'live' else '#fff'
                dash = '' if status == 'live' else 'stroke-dasharray="3 2"'
                t = tools[tid]
                label = f"{numbers[tid]}. {t['name']} — {spec['label']} — {group}"
                out.append(f'<g class="node" data-tool-id="{html.escape(tid,quote=True)}" tabindex="0" role="button" aria-label="{html.escape(label,quote=True)}"><title>{html.escape(label)}</title><circle cx="{nx:.2f}" cy="{ny:.2f}" r="13" fill="{fill}" stroke="{spec["colour"]}" stroke-width="2" {dash}/>' + text(nx, ny+4, numbers[tid], text_anchor='middle', font_size=10, font_weight=700, fill='#fff' if status=='live' else spec['colour']) + '</g>')
    out.append(text(CX, CY-14, 'ARCHITEX', text_anchor='middle', font_size=23, font_weight=750, fill='#152638'))
    out.append(text(CX, CY+10, f'{len(tools)} modules', text_anchor='middle', font_size=15, fill='#657482'))
    out.append(text(CX, CY+32, f'{len(groups)} disciplines', text_anchor='middle', font_size=12, fill='#657482'))
    # Numbered source-derived key keeps long labels readable without collisions.
    out.append(text(1050, 118, 'MODULE KEY', font_size=15, font_weight=750, fill='#152638'))
    out.append(text(1050, 141, 'Select a radar blip, or use List for full details.', font_size=12, fill='#657482'))
    for tid, number in sorted(numbers.items(), key=lambda kv: kv[1]):
        t=tools[tid]; y=164 + (number-1)*17.5
        colour=base.STATUS[t['status']]['colour']
        out.append(f'<g class="key-item" data-open-tool="{html.escape(tid,quote=True)}" tabindex="0" role="button" aria-label="Open {html.escape(t["name"],quote=True)}">')
        out.append(text(1050,y,number,font_size=11,font_weight=750,fill=colour))
        out.append(text(1077,y,t['name'],class_='radar-key'))
        out.append('</g>')
    counts=Counter(t['status'] for t in tools.values())
    for i,status in enumerate(RADII):
        spec=base.STATUS[status]; x=36+i*335
        out.append(f'<circle cx="{x+8}" cy="1031" r="7" fill="{spec["colour"] if status=="live" else "#fff"}" stroke="{spec["colour"]}" stroke-width="2"'+(' stroke-dasharray="3 2"' if status!='live' else '')+'/>')
        out.append(text(x+25,1035,f'{["INNER", "MIDDLE", "OUTER"][i]} · {spec["label"]} ({counts[status]})',font_size=13,font_weight=650,fill='#293746'))
    out.append(text(36,1070,'Rings indicate declared status, not independently verified readiness, scores or connected integrations.',font_size=13,fill='#657482'))
    out.append(text(36,1093,'Numbers match the module key. Click or press Enter on a blip for evidence and workflow-stage coverage.',font_size=12,fill='#657482'))
    return ''.join(out)


def render_document():
    tools, place, stages, _ = base.load()
    base.validate(tools, place, stages)
    doc=base.render_document()
    svg=f'<svg id="map" width="{W}" height="{H}" viewBox="0 0 {W} {H}" role="group" aria-label="Architex technology radar: disciplines as sectors and declared statuses as rings">{radar_svg(tools)}</svg>'
    doc=re.sub(r'<svg id="map".*?</svg>',lambda _:svg,doc,count=1,flags=re.S)
    doc=re.sub(r'var W = \d+, H = \d+;',f'var W = {W}, H = {H};',doc)
    doc=doc.replace('Architex capability map — by stage &amp; discipline','Architex Technology Radar')
    doc=doc.replace('Architex Capability Map','Architex Technology Radar')
    doc=doc.replace('by stage &amp; discipline · generated from the module registry','by discipline &amp; capability status · source-derived')
    doc=doc.replace('>Map</button>','>Radar</button>')
    coverage = radar_coverage.load_coverage(base.ROOT, tools)
    doc = radar_coverage.extend_document(doc, coverage, list(tools.values()), place, stages, NODE_COORDS)
    from radar_delivery import extend_document
    doc = extend_document(doc)
    doc = radar_polish.polish_document(doc, tools, coverage)
    return doc


def main():
    if '--verify-sources' in sys.argv:
        tools, _, _, _ = base.load()
        radar_coverage.load_coverage(base.ROOT, tools, strict_sources=True)
        print('PASS: current source files match captured inspection evidence')
    doc=render_document()
    outputs=base.OUTPUTS+[base.ROOT/'docs/techmap/architex-radar.html']
    if '--check' in sys.argv:
        for dest in outputs:
            if not dest.exists() or dest.read_text(encoding='utf-8')!=doc:
                raise SystemExit(f'STALE: {dest}')
        print('PASS: all four radar outputs reproduce exactly')
    else:
        for dest in outputs:
            dest.parent.mkdir(parents=True,exist_ok=True)
            dest.write_text(doc,encoding='utf-8')
            print(f'wrote {dest}')
    tools,_,_,_=base.load()
    print(f'{len(tools)} modules; {len(set(t["group"] for t in tools.values()))} sectors; '+str(dict(Counter(t['status'] for t in tools.values()))))


if __name__=='__main__':
    main()
