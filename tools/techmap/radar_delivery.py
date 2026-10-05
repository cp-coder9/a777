"""Optional, truthful release workstream tracking for the technology radar."""
import html
import json
from pathlib import Path


def extend_document(document):
    path = Path(__file__).parent / 'delivery-status.json'
    if not path.is_file():
        return document
    status = json.loads(path.read_text(encoding='utf-8'))
    escape = lambda value: html.escape(str(value), quote=True)
    items = status.get('workstreams', [])
    names = [item['name'] for item in items]
    if len(names) != len(set(names)):
        raise ValueError('Duplicate release workstream names')
    cards = ''.join('<article class="coverage-card delivery-card"><span class="delivery-state">'+escape(item['state'])+'</span><h3>'+escape(item['name'])+'</h3><p>'+escape(item['scope'])+'</p><small>Owner · '+escape(item['owner'])+'</small></article>' for item in items)
    panel = '<div id="coverage-delivery" hidden><h2>'+escape(status['title'])+'</h2><p class="delivery-summary">'+escape(status['summary'])+'</p><div class="coverage-grid">'+cards+'</div></div>'
    document = document.replace('</nav><div id="coverage-notes"', '<button data-coverage-tab="delivery">Release work</button></nav>'+panel+'<div id="coverage-notes"', 1)
    css = '<style>.delivery-state{display:inline-block;border-radius:20px;background:#eaf2f6;color:#356276;padding:4px 10px;font-size:11px;margin-bottom:12px}.delivery-card small,.delivery-summary{color:#667783}.delivery-card h3{margin:0;font-size:16px}#coverage-delivery h2{font-size:23px;letter-spacing:-.5px;margin:8px 0}.delivery-summary{max-width:900px;margin-bottom:24px}</style>'
    document = document.replace('</head>', css+'</head>')
    js = '''<script>document.querySelectorAll('[data-coverage-tab]').forEach(function(button){button.addEventListener('click',function(){document.getElementById('coverage-delivery').hidden=button.dataset.coverageTab!=='delivery';});});</script>'''
    return document.replace('</body>', js+'</body>')
