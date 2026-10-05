"""Polished, self-contained presentation layer for the complete radar."""
from collections import Counter
from pathlib import Path
import re


def polish_document(doc, tools, coverage):
    counts = Counter(t['status'] for t in tools.values())
    groups = len(set(t['group'] for t in tools.values()))
    links = len(coverage['relationships'])
    mark = '<span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="12" fill="none" stroke="currentColor" opacity=".4"/><circle cx="16" cy="16" r="7" fill="none" stroke="currentColor" opacity=".6"/><path d="M16 4v12l9 7" fill="none" stroke="currentColor"/><circle cx="12" cy="20" r="2" fill="currentColor"/><circle cx="21" cy="12" r="2" fill="currentColor"/></svg></span>'
    header = f'''<header>
<div class="brand-block">{mark}<div><div class="brand-name">ARCHITEX</div><div class="brand-title">Technology radar</div></div></div>
<nav class="tabs" role="tablist" aria-label="Explore capabilities">
<button aria-controls="stage" tabindex="0" id="map-tab" class="tab on" role="tab" aria-selected="true" onclick="showView('map')">Radar</button>
<button aria-controls="list-view" tabindex="-1" id="list-tab" class="tab" role="tab" aria-selected="false" onclick="showView('list')">Module list</button>
<button aria-controls="coverage-view" tabindex="-1" id="coverage-tab" class="tab" role="tab" aria-selected="false" onclick="showView('coverage')">Full coverage</button>
</nav>
<div class="right"><label class="sr-only" for="search">Search tools</label><input id="search" class="search" type="search" placeholder="Search modules, disciplines, stages…" aria-label="Search tools by name, discipline, stage or status" oninput="applyFilter()"><span id="match-count" role="status" aria-live="polite">{len(tools)} of {len(tools)} tools shown</span></div>
<div class="header-context"><div class="status-key" aria-label="Registry-declared status legend"><span class="status-pill"><i class="status-dot live-dot"></i>{counts['live']} Live</span><span class="status-pill"><i class="status-dot limited-dot"></i>{counts['limited']} Limited</span><span class="status-pill"><i class="status-dot integration-dot"></i>{counts['integration_required']} Integration required</span></div><span class="context-caption"><strong>{len(tools)} modules · {groups} disciplines · {links} connections</strong> &nbsp; / &nbsp; Source-backed, not a readiness certification</span></div>
</header>'''
    doc = re.sub(r'<header>.*?</header>', lambda _: header, doc, count=1, flags=re.S)
    css = (Path(__file__).parent / 'radar-polish.css').read_text(encoding='utf-8')
    doc = doc.replace('</head>', '<meta name="description" content="Explore the Architex technology radar: modules, capability boundaries, workflow coverage and source-backed connections."><meta name="theme-color" content="#183746"><style>'+css+'</style></head>')
    script = '''<script>
function comfortableFit(){if(window.innerWidth>720){fitToWindow();}else{fitToWidth();if(scale<.35){scale=.35;apply();}}}
window.addEventListener('load',function(){requestAnimationFrame(comfortableFit);});
window.addEventListener('resize',function(){requestAnimationFrame(comfortableFit);});
document.addEventListener('keydown',function(event){if(event.key==='/'&&!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)&&!document.getElementById('details').open){event.preventDefault();document.getElementById('search').focus();}});
document.querySelectorAll('.key-item[data-open-tool]').forEach(function(el){el.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();openDetail(el.dataset.openTool);}});});
document.querySelectorAll('[data-coverage-tab]').forEach(function(button){button.addEventListener('click',function(){document.querySelectorAll('[data-coverage-tab]').forEach(function(b){b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',String(b===button));});});});
document.querySelector('[data-coverage-tab="notes"]').classList.add('active');
document.querySelector('[data-coverage-tab="notes"]').setAttribute('aria-pressed','true');
document.getElementById('connections-toggle').setAttribute('aria-pressed','false');
var radarViews=['map','list','coverage'], radarPanels=['stage','list-view','coverage-view'];
radarViews.forEach(function(view,i){var panel=document.getElementById(radarPanels[i]);panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',view+'-tab');});
var accessibleShowView=showView;
showView=function(which){accessibleShowView(which);radarViews.forEach(function(view){document.getElementById(view+'-tab').tabIndex=view===which?0:-1;});};
document.querySelector('.tabs').addEventListener('keydown',function(event){var index=radarViews.findIndex(function(view){return event.target.id===view+'-tab';});if(index<0)return;var next=index;if(event.key==='ArrowRight')next=(index+1)%3;else if(event.key==='ArrowLeft')next=(index+2)%3;else if(event.key==='Home')next=0;else if(event.key==='End')next=2;else return;event.preventDefault();showView(radarViews[next]);document.getElementById(radarViews[next]+'-tab').focus();});
</script>'''
    return doc.replace('</body>', script+'</body>')
