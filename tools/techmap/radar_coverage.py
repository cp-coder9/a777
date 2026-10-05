"""Source-backed information layers for the offline technology radar."""
import html
import hashlib
import json
import re
from pathlib import Path
from capture_source_evidence import safe_source


def load_coverage(root, tool_ids, strict_sources=False):
    path = root / 'tools/techmap/coverage.json'
    data = json.loads(path.read_text(encoding='utf-8'))
    entries = data['tools']
    ids = [entry['id'] for entry in entries]
    if len(ids) != len(set(ids)) or set(ids) != set(tool_ids):
        raise ValueError('Coverage manifest must cover every registry tool exactly once')
    for entry in entries:
        for key in ['usageNote', 'boundary', 'replacement', 'sourceRefs']:
            if not entry.get(key):
                raise ValueError(f'Missing {key} for {entry["id"]}')
        if entry['replacement']['state'] not in ['documented', 'not_documented']:
            raise ValueError('Unknown replacement evidence state')
    refs = []
    for entry in entries:
        refs.extend(entry['sourceRefs'])
        refs.extend(entry['replacement'].get('sourceRefs', []))
    for link in data['relationships']:
        if link['from'] not in tool_ids or link['to'] not in tool_ids:
            raise ValueError('Unknown relationship endpoint')
        if link['type'] not in ['data_flow', 'shared_backend', 'workflow'] or not link['sourceRefs']:
            raise ValueError('Relationship needs an evidence type and sources')
        refs.extend(link['sourceRefs'])
    for group in data['groups']:
        if not group['members'] or not set(group['members']) <= set(tool_ids) or not group['sourceRefs']:
            raise ValueError('Invalid source-backed group')
        refs.extend(group['sourceRefs'])
    snapshot = json.loads((root / 'tools/techmap/source-evidence.json').read_text(encoding='utf-8'))
    if snapshot.get('schemaVersion') != 1:
        raise ValueError('Unsupported source evidence snapshot')
    for ref in refs:
        source = safe_source(root, ref['path'])
        if type(ref['line']) is not int or ref['line'] < 1:
            raise ValueError(f'Invalid source reference: {ref}')
        record = snapshot['files'].get(ref['path'])
        if not record or not re.fullmatch(r'[0-9a-f]{64}', record.get('sha256', '')):
            raise ValueError(f'Missing or invalid captured source evidence: {ref}')
        excerpt = record.get('lines', {}).get(str(ref['line']))
        if not excerpt or not isinstance(excerpt.get('text'), str):
            raise ValueError(f'Missing captured source line: {ref}')
        if strict_sources and (not source.is_file() or hashlib.sha256(source.read_bytes()).hexdigest() != record['sha256']):
            raise ValueError(f'Current source differs from captured inspection evidence: {ref["path"]}')
    return data


def extend_document(doc, coverage, registry, placements, stages, coords):
    esc = lambda value: html.escape(str(value), quote=True)
    tools = {t['id']: t for t in registry}
    entries = {t['id']: t for t in coverage['tools']}
    stage_labels = {sid: label for sid, label, _ in stages}
    names = {tid: t['name'] for tid, t in tools.items()}

    def refs_html(refs):
        return '<details class="source-disclosure"><summary>Source references ('+str(len(refs))+')</summary><ul class="source-refs">' + ''.join('<li><code>'+esc(r['path'])+':'+str(r['line'])+'</code></li>' for r in refs)+'</ul></details>'

    cards = []
    for tid, t in tools.items():
        c = entries[tid]
        relationships = [r for r in coverage['relationships'] if tid in [r['from'], r['to']]]
        relations = ''.join('<li>'+esc(names[r['from']])+' → '+esc(names[r['to']])+' · <b>'+esc(r['type'].replace('_',' '))+'</b>: '+esc(r['description'])+refs_html(r['sourceRefs'])+'</li>' for r in relationships)
        cards.append('<article class="coverage-card" data-coverage-id="'+esc(tid)+'"><h3>'+esc(t['name'])+'</h3><p class="annotation">'+esc(c['usageNote'])+'</p><p><b>Boundary:</b> '+esc(c['boundary'])+'</p><p><b>Stage coverage:</b> '+esc(t.get('stage',''))+'</p><p><b>Replacement:</b> '+esc(c['replacement']['text'])+'</p>'+refs_html(c['sourceRefs'])+'<details><summary>Relationships ('+str(len(relationships))+')</summary><ul>'+relations+'</ul></details></article>')

    workflow = []
    for tid, t in tools.items():
        declared = [s.strip() for s in t.get('stage','').split('/')]
        all_stages = t.get('stage') == 'All stages'
        primary = placements[tid]['stage']
        cells = ''
        for sid, label, _ in stages:
            hit = all_stages or label in declared
            cells += '<td class="stage-cell '+('covered' if hit else '')+'">'+('●' if hit else '—')+('<small>Primary</small>' if primary==sid else '')+'</td>'
        workflow.append('<tr data-coverage-id="'+esc(tid)+'"><th>'+esc(t['name'])+'</th>'+cells+'<td>'+esc(t.get('stage',''))+'</td></tr>')
    group_cards = []
    for group in coverage['groups']:
        members = ''.join('<li><button class="tool-link" data-open-tool="'+esc(tid)+'">'+esc(names[tid])+'</button></li>' for tid in group['members'])
        group_cards.append('<details class="group-card"><summary>'+esc(group['label'])+' ('+str(len(group['members']))+')</summary><p>'+esc(group['description'])+'</p><ul>'+members+'</ul>'+refs_html(group['sourceRefs'])+'</details>')
    # Every module participates in a discipline parent even if no narrower subsystem is documented.
    disciplines = {}
    for tid, t in tools.items():
        disciplines.setdefault(t['group'], []).append(tid)
    for group, members in disciplines.items():
        if any(existing['label'] == group for existing in coverage['groups']):
            continue
        group_cards.append('<details class="group-card"><summary>'+esc(group)+' ('+str(len(members))+')</summary><p>Discipline parent from the tool registry; not a claim of technical dependency.</p><ul>'+''.join('<li><button class="tool-link" data-open-tool="'+esc(tid)+'">'+esc(names[tid])+'</button></li>' for tid in members)+'</ul></details>')
    link_cards = ''.join('<article class="relationship-card"><h3>'+esc(names[r['from']])+' → '+esc(names[r['to']])+'</h3><p><b>'+esc(r['type'].replace('_',' '))+'</b> · '+esc(r['label'])+'</p><p>'+esc(r['description'])+'</p>'+refs_html(r['sourceRefs'])+'</article>' for r in coverage['relationships'])
    panels = '<section id="coverage-view" hidden><nav class="coverage-nav"><button data-coverage-tab="notes">Tool notes</button><button data-coverage-tab="workflow">Workflow coverage</button><button data-coverage-tab="connections">Connections</button><button data-coverage-tab="groups">Nested groups</button></nav><div id="coverage-notes" class="coverage-grid">'+''.join(cards)+'</div><div id="coverage-workflow" hidden><p>Filled cells show the registry’s declared stage coverage, not automatic integrations. Handover and platform-only scope remain preserved in the source column.</p><table class="workflow-table"><thead><tr><th>Module</th>'+''.join('<th>'+esc(label)+'</th>' for _,label,_ in stages)+'<th>Declared source scope</th></tr></thead><tbody>'+''.join(workflow)+'</tbody></table></div><div id="coverage-connections" hidden><p>Source-backed code relationships, not runtime verification. Shared backend means shared implementation, not automatic data exchange.</p>'+link_cards+'</div><div id="coverage-groups" hidden>'+''.join(group_cards)+'</div></section>'
    doc = doc.replace('<section id="list-view" hidden>', panels+'<section id="list-view" hidden>')
    doc = doc.replace('</div>\n  <span class="shared">', '<button id="coverage-tab" class="tab" role="tab" aria-selected="false" onclick="showView(\'coverage\')">Coverage</button></div>\n  <span class="shared">', 1)
    doc = doc.replace('<button id="print-map"', '<button id="connections-toggle" class="fit" onclick="toggleConnections()">Connections</button><button id="print-map"')
    css = '''<style>
#coverage-view{position:absolute;inset:96px 0 48px;overflow:auto;padding:18px;background:#f8fafb}
.coverage-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.coverage-nav button,.tool-link{border:1px solid #ccd7df;background:white;border-radius:6px;padding:7px 12px;cursor:pointer;color:#183548}.coverage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}.coverage-card,.relationship-card,.group-card{padding:16px;background:white;border:1px solid #dde5eb;border-radius:10px;margin-bottom:12px}.coverage-card h3,.relationship-card h3{margin:0 0 8px;font-size:15px}.annotation{font-style:italic;color:#186675}.source-refs{font-size:10px;color:#667783;overflow-wrap:anywhere;padding-left:18px}.workflow-table{border-collapse:collapse;background:white;min-width:1050px}.workflow-table th,.workflow-table td{padding:8px;border:1px solid #e0e6ea;text-align:left}.stage-cell.covered{background:#e1f3ef;color:#126551}.stage-cell small{display:block;font-size:9px}.relationship-wire{pointer-events:none;opacity:.45}.relationship-wire.selected{stroke-width:3;opacity:1}.tool-link{text-align:left}
@media(max-width:720px){#coverage-view{top:118px}.coverage-grid{grid-template-columns:1fr}}
@media print{#coverage-view{display:none!important}}
</style>'''
    doc = doc.replace('</head>', css+'</head>')
    # Connections are normally hidden to preserve readability; selecting a tool isolates its evidence-backed connections.
    wires = []
    for i, r in enumerate(coverage['relationships']):
        x1,y1 = coords[r['from']]; x2,y2 = coords[r['to']]
        colour = {'data_flow':'#287991','shared_backend':'#9273b8','workflow':'#b9772e'}[r['type']]
        dash = '' if r['type']=='data_flow' else 'stroke-dasharray="6 5"'
        wires.append(f'<path class="relationship-wire" data-from="{esc(r["from"])}" data-to="{esc(r["to"])}" d="M {x1:.2f} {y1:.2f} Q 510 535 {x2:.2f} {y2:.2f}" fill="none" stroke="{colour}" stroke-width="1.5" {dash}><title>{esc(r["label"])} ({esc(r["type"])})</title></path>')
    # Place paths behind the first blip; they remain opt-in and do not obscure marks.
    doc = doc.replace('<g class="node"', '<g id="relationship-layer" style="display:none">'+''.join(wires)+'</g><g class="node"', 1)
    payload=json.dumps(coverage,ensure_ascii=False).replace('</','<\\/')
    js='''<script>
var COVERAGE=PAYLOAD;
var previousShowView=showView;
showView=function(which){previousShowView(which);document.getElementById('list-view').hidden=which!=='list';document.getElementById('coverage-view').hidden=which!=='coverage';['map','list','coverage'].forEach(function(view){var tab=document.getElementById(view+'-tab');tab.classList.toggle('on',view===which);tab.setAttribute('aria-selected',String(view===which));});};
function positionPanels(){var top=document.querySelector('header').getBoundingClientRect().bottom+6;['stage','list-view','coverage-view'].forEach(function(id){document.getElementById(id).style.top=top+'px'});}
new ResizeObserver(positionPanels).observe(document.querySelector('header'));positionPanels();
document.getElementById('details').addEventListener('close',function(){document.getElementById('relationship-layer').style.display='none';document.getElementById('connections-toggle').setAttribute('aria-pressed','false');});
var previousOpenDetail=openDetail;
openDetail=function(id){previousOpenDetail(id);var c=COVERAGE.tools.find(function(t){return t.id===id});if(!c)return;var body=document.getElementById('detail-body');[['Usage note',c.usageNote],['Boundary',c.boundary],['Replacement evidence',c.replacement.text],['Source references',c.sourceRefs.map(function(r){return r.path+':'+r.line}).join('; ')]].forEach(function(row){var dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=row[0];dd.textContent=row[1];body.append(dt,dd)});var relations=COVERAGE.relationships.filter(function(r){return r.from===id||r.to===id});var dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent='Connections';dd.textContent=relations.length?relations.map(function(r){return r.from+' → '+r.to+' ('+r.type+'): '+r.description}).join('\n'):'No source-backed connection documented in this manifest.';body.append(dt,dd);document.querySelectorAll('.relationship-wire').forEach(function(p){var hit=p.dataset.from===id||p.dataset.to===id;p.classList.toggle('selected',hit);p.style.display=hit?'':'none';});document.getElementById('relationship-layer').style.display=relations.length?'':'none';document.getElementById('connections-toggle').setAttribute('aria-pressed',String(relations.length>0));};
function toggleConnections(){var layer=document.getElementById('relationship-layer');var on=layer.style.display==='none';layer.style.display=on?'':'none';document.querySelectorAll('.relationship-wire').forEach(function(p){p.style.display='';p.classList.remove('selected')});document.getElementById('connections-toggle').setAttribute('aria-pressed',String(on));}
document.querySelectorAll('[data-coverage-tab]').forEach(function(b){b.addEventListener('click',function(){['notes','workflow','connections','groups'].forEach(function(name){document.getElementById('coverage-'+name).hidden=name!==b.dataset.coverageTab;});});});
document.querySelectorAll('[data-open-tool]').forEach(function(b){b.addEventListener('click',function(){openDetail(b.dataset.openTool)});});
var previousFilter=applyFilter;applyFilter=function(){previousFilter();var q=document.getElementById('search').value.toLowerCase().trim();document.querySelectorAll('[data-coverage-id]').forEach(function(el){var t=TOOLS.find(function(t){return t.id===el.dataset.coverageId});el.hidden=!!q&&(t.name+' '+t.group+' '+t.stage+' '+t.statusLabel).toLowerCase().indexOf(q)<0;});};
</script>'''.replace('PAYLOAD',payload)
    js = js.replace(".join('\n')", ".join('; ')")
    doc=doc.replace('</body>',js+'</body>')
    return doc
