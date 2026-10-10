"""Inline the Architex SVG icon pack into the v1.html icon language.

v1.html (.ax-icon) renders icon geometry as THREE class roles and takes ALL colour
from CSS, so the icon inherits the surrounding theme:

    .ax-icon .outline  -> body:  fill #fff, stroke var(--ink), 1.8px
    .ax-icon .line     -> detail: fill none, stroke var(--ink), 1.8px
    .ax-icon .fold     -> accent: fill var(--teal), stroke var(--deep), .7px

The supplied pack (architex_svg_icon_pack-2) hard-codes stroke="#102033",
stroke-width="2" and fill="#19B7B0" on every element, which is why the pack could
only be shown through <img> tags and could not follow theme, tone or hover.

This script rewrites every pack icon into the v1.html role model: geometry only,
no hard-coded colour, no hard-coded stroke width. Output is the JS shape registry
the clone's icon() helper consumes, plus normalised standalone SVGs.

Canonical role contract
    Registry roles explicitly preserve reviewed body/detail/fold intent in XML
    geometry order. No path bounding-box estimation or implicit path closing.
    Standalone conversion accepts explicit roles; default first closed unfilled
    geometry is the body, teal is fold, and remaining geometry is detail.
"""
import json, re, shutil, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / 'app/public/assets/v1-clone' # canonical original SVGs + registry
ASSETS = SRC
OUT_JS = ASSETS / 'js' / 'architex-icon-pack.js'
OUT_INLINE = ASSETS / 'icons' / 'inline'
OUT_REPORT = ASSETS / 'icons/conversion-report.json'
OUT_CSS = ASSETS / 'css' / 'reference-style-005.css'

GEOM = ('x', 'y', 'width', 'height', 'rx', 'ry', 'cx', 'cy', 'r', 'd', 'points',
        'x1', 'y1', 'x2', 'y2')
CLOSED_TAGS = ('rect', 'circle', 'ellipse', 'polygon')


def convert(svg_text, key, roles=None):
    """Return (shape_html, meta) or (None, reason); reject executable source."""
    import xml.etree.ElementTree as ET
    if '<!DOCTYPE' in svg_text.upper() or '<!ENTITY' in svg_text.upper():
        return None, 'unsafe XML declaration'
    try:
        tree = ET.fromstring(svg_text)
    except ET.ParseError:
        return None, 'malformed SVG'
    allowed = {'svg', 'g', 'title', 'path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'line'}
    safe_attrs = set(GEOM) | {'xmlns', 'viewBox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule', 'opacity', 'class'}
    for node in tree.iter():
        if node is not tree and node.tag.split('}')[-1] == 'svg':
            return None, 'nested SVG viewport is unsupported'
        if node.tag.split('}')[-1] not in allowed:
            return None, 'unsafe SVG element'
        for attr, value in node.attrib.items():
            if attr not in safe_attrs or any(c in value for c in '<>&\"') or 'url(' in value.lower():
                return None, 'unsafe SVG attribute'
    if tree.tag.split('}')[-1] != 'svg' or tree.attrib.get('viewBox') != '0 0 24 24':
        return None, 'requires viewBox 0 0 24 24'
    # Flatten groups without losing their inherited compound-path fill semantics.
    # Keep absent rules absent so canonical output remains byte-for-byte stable.
    effective_rules = {}
    def collect_rules(node, inherited=None):
        rule = node.attrib.get('fill-rule', inherited)
        if rule is not None and rule not in ('evenodd', 'nonzero'):
            raise ValueError('unsupported fill-rule')
        effective_rules[node] = rule
        for child in node:
            collect_rules(child, rule)
    try:
        collect_rules(tree)
    except ValueError as error:
        return None, str(error)
    nodes = [n for n in tree.iter() if n.tag.split('}')[-1] not in ('svg', 'g', 'title')]
    if roles is not None and len(roles) != len(nodes):
        return None, 'canonical role count mismatch'
    parts, counts = [], {'outline': 0, 'line': 0, 'fold': 0}
    for i, node in enumerate(nodes):
        tag, a = node.tag.split('}')[-1], node.attrib
        fill = a.get('fill', '').strip().lower()
        teal = fill in ('#19b7b0', 'var(--teal)', '#14b8a6')
        role = roles[i] if roles is not None else a.get('class')
        if role is None:
            closed = tag in CLOSED_TAGS or (tag == 'path' and re.search(r'[zZ]\s*$', a.get('d', '')))
            role = 'fold' if teal else ('outline' if closed and fill == 'none' and not counts['outline'] else 'line')
        if role not in counts or (role == 'fold') != teal:
            return None, 'invalid canonical role/fold'
        if role == 'outline' and not (tag in CLOSED_TAGS or (tag == 'path' and re.search(r'[zZ]\s*$', a.get('d', '')))):
            return None, 'outline must be closed canonical geometry'
        geom = ' '.join('%s="%s"' % (k, a[k]) for k in GEOM if k in a)
        if not geom:
            return None, 'missing geometry'
        if effective_rules[node] is not None:
            geom += ' fill-rule="%s"' % effective_rules[node]
        parts.append('<%s class="%s" %s/>' % (tag, role, geom))
        counts[role] += 1
    if not parts:
        return None, 'no geometry'
    if counts['fold'] == 0:
        return None, 'no teal fold'
    if counts['fold'] != 1 or counts['outline'] == 0:
        return None, 'requires one fold and an outline body'
    counts['line_only'] = False
    return ''.join(parts), counts


def coverage(shapes, meta, candidate):
    """Execute actual data assembly, not a literal-only approximation of tabs."""
    import subprocess, hashlib
    program = r'''
const fs=require('fs'),vm=require('vm'),path=require('path');
const dir=process.argv[1],read=n=>fs.readFileSync(path.join(dir,n),'utf8');
const c=vm.createContext({window:{},setInterval(){},clearInterval(){}});
const first=read('reference-001.js'),second=read('reference-002.js');
vm.runInContext(first.match(/const iconShapes=\{.*?\n\};/s)[0],c);
vm.runInContext(first.match(/const TOOLS=.*?;(?=\s*const )/s)[0],c);
vm.runInContext(second.match(/const pmTabs=.*?;/s)[0]+second.match(/TOOLS.practice=.*?;/s)[0],c);
for(const [file,id]of [['reference-003.js','meetings'],['reference-004.js','engineering_calc']])vm.runInContext(read(file).match(new RegExp('TOOLS\\.'+id+'=\\{.*?\\n\\s*\\};','s'))[0],c);
vm.runInContext(fs.readFileSync(process.argv[2],'utf8'),c);
vm.runInContext(read('modules/icon-map.js'),c);
vm.runInContext(first.match(/function iconForTab\(.*?\n\}/s)[0],c);
vm.runInContext(first.match(/const globalItems=\[.*?\];/s)[0],c);
const globalItems=vm.runInContext('globalItems',c);
const tools=vm.runInContext('TOOLS',c),tabs=[];
for(const [id,t]of Object.entries(tools))for(const tab of t.tabs||[])tabs.push({tool:id,label:tab.label,explicit:tab.icon||null,key:vm.runInContext(`iconForTab(${JSON.stringify(tab)},${JSON.stringify(t.icon)})`,c)});
const keys=new Set();const files=fs.readdirSync(dir).filter(f=>/^reference-.*\.js$/.test(f));files.push(...fs.readdirSync(path.join(dir,'modules')).filter(f=>f.endsWith('.js')).map(f=>'modules/'+f));
for(const f of files)for(const m of read(f).matchAll(/["']?\bicon["']?\s*:\s*["']([A-Za-z][A-Za-z0-9_]*)["']|\bicon\(\s*["']([A-Za-z][A-Za-z0-9_]*)["']/g))keys.add(m[1]||m[2]);
const literalKeys=[...keys].sort();for(const t of Object.values(tools))keys.add(t.icon);for(const t of tabs)keys.add(t.key);
for(const item of globalItems)keys.add(item.icon);
console.log(JSON.stringify({globalItems,cloneTools:Object.keys(tools).sort(),toolIcons:c.window.__architexIconMap.toolIcons,tabs,literalKeys,requiredKeys:[...keys].sort(),scannedFiles:files.sort()}));
'''
    try:
        result = subprocess.run(['node', '-e', program, str(SRC / 'js'), str(candidate)], check=True, capture_output=True, text=True, timeout=90)
    except subprocess.CalledProcessError as error:
        raise ValueError('coverage execution failed: ' + error.stderr) from error
    data = json.loads(result.stdout)
    production = json.loads((SRC.parent / 'tool-registry.json').read_text())['tools']
    ids = sorted(t['id'] for t in production)
    if len(ids) != 49 or set(ids) != set(data['toolIcons']):
        raise ValueError('production tool mapping must match all 49 IDs exactly')
    data['productionTools'] = ids
    data['productionOnly'] = sorted(set(ids) - set(data['cloneTools']))
    data['requiredKeys'] = sorted(set(data['requiredKeys']) | set(data['toolIcons'].values()))
    for key in data['requiredKeys']:
        if key not in shapes or not meta[key]['provenance']['source']:
            raise ValueError('unresolved canonical geometry/provenance: ' + key)
    for tab in data['tabs']:
        if tab['explicit'] and tab['explicit'] != tab['key']:
            raise ValueError('silent tab fallback: ' + str(tab))
    data['resolved'] = {key: {'file': meta[key]['file'], 'provenance': meta[key]['provenance'],
                             'geometry_sha256': hashlib.sha256(shapes[key].encode()).hexdigest()}
                        for key in data['requiredKeys']}
    return json.dumps(data, indent=2, sort_keys=True) + '\n'
    print('coverage:', len(data['cloneTools']), 'clone tools;', len(ids), 'production tools;', len(data['tabs']), 'tabs;', len(data['requiredKeys']), 'resolved keys; production-only:', data['productionOnly'])


def build(stage, output_root=ROOT):
    OUT_JS = stage / "js/architex-icon-pack.js"
    OUT_INLINE = stage / "icons/inline"
    OUT_REPORT = stage / "icons/conversion-report.json"
    OUT_JS.parent.mkdir(parents=True, exist_ok=True)
    reg = json.loads((SRC / 'icons/registry.json').read_text(encoding='utf-8'))
    OUT_INLINE.mkdir(parents=True, exist_ok=True)
    shapes, meta, report, problems = {}, {}, {}, []
    for key, info in sorted(reg.items()):
        if not re.fullmatch(r'[A-Za-z][A-Za-z0-9_]*', key) or key == 'logo':
            problems.append((key, 'invalid or protected registry key')); continue
        f = (SRC / info['file']).resolve()
        if f.parent != (SRC / 'icons').resolve():
            problems.append((key, 'source must be a canonical icons/*.svg file')); continue
        if not f.exists():
            problems.append((key, 'missing file ' + info['file'])); continue
        shape, counts = convert(f.read_text(encoding='utf-8'), key, info.get('roles'))
        if shape is None:
            problems.append((key, counts)); continue
        shapes[key] = shape
        meta[key] = {'label': info.get('label', key), 'category': info.get('category', 'general'),
                     'tone': info.get('tone', 'core'), 'priority': info.get('priority', 'P1'),
                     'file': info.get('file'),
                     'provenance': info.get('provenance', {'source': info['file'], 'intent': 'Canonical original Architex origami SVG'})}
        report[key] = counts
    if problems:
        print('refused before generating output:', problems)
        return 1
    for key, shape in shapes.items():
        (OUT_INLINE / (key + '.svg')).write_text(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="ax-icon" '
            'data-architex-icon="%s" aria-hidden="true">%s</svg>\n' % (key, shape), encoding='utf-8', newline='\n')

    js = ["/* Architex icon pack, inlined into the v1.html .ax-icon role model.",
          " * Generated by tools/v1-clone/build_architex_icons.py - do not hand-edit.",
          " * Geometry only: colour and stroke width come from CSS so icons inherit",
          " * the theme, the module tone and the hover behaviour.",
          " *",
          " * The shell declares `const iconShapes` in a classic script, so it lives in the",
          " * global lexical scope rather than on window; the merge must target that binding",
          " * or new keys silently never reach icon(). Approved registry keys replace existing geometry; unregistered keys are untouched, so",
          " * the original logo and external extensions remain untouched.",
          " */",
          "(function(){",
          "  function shapesObject(){",
          "    try{ if(typeof iconShapes==='object'&&iconShapes){return iconShapes} }catch(e){}",
          "    return (typeof window.iconShapes==='object'&&window.iconShapes)||null;",
          "  }",
          "  var meta=%s, shapes=%s;" % (json.dumps(meta, indent=0, sort_keys=True), json.dumps(shapes, indent=0, sort_keys=True)),
          "  var added=[], kept=[];",
          "  window.ARCHITEX_ICON_PACK={meta:meta,shapes:shapes,added:added,keptByShell:kept,",
          "    effective:function(key){var s=shapesObject();if(s&&s[key])return s[key];return shapes[key]||null},",
          "    sourceOf:function(key){if(kept.indexOf(key)>=0)return 'shell';return shapes[key]?'pack':null}};",
          "  function install(){",
          "    var s=shapesObject(); if(!s) return false;",
          "    Object.keys(shapes).forEach(function(k){",
          "      if(k==='logo') {kept.push(k);return;} if(added.indexOf(k)<0)added.push(k); s[k]=shapes[k];",
          "    });",
          "    window.ARCHITEX_ICON_PACK.installed=true;",
          "    return true;",
          "  }",
          "  if(!install()){var n=0,t=setInterval(function(){if(install()||++n>200){clearInterval(t)}},25)}",
          "})();"]
    OUT_JS.write_text('\n'.join(js) + '\n', encoding='utf-8', newline='\n')

    report_sorted = {k: report[k] for k in sorted(report)}
    OUT_REPORT.write_text(json.dumps({
        'source': str(SRC.name),
        'generated_from': 'canonical icons/*.svg and icons/registry.json',
        'role_model': {'outline': 'white body + ink outline', 'line': 'ink detail stroke',
                       'fold': 'single solid teal accent'},
        'icon_count': len(shapes),
        'role_totals': {r: sum(v[r] for v in report.values()) for r in ('outline', 'line', 'fold')},
        'per_icon': report_sorted,
        'problems': problems,
    }, indent=1), encoding='utf-8', newline='\n')

    import html
    cards = []
    for key in shapes:
        samples = ''.join('<div data-icon-key="%s" data-size="%s"><svg class="ax-icon" width="%s" height="%s" viewBox="0 0 24 24" aria-hidden="true">%s</svg><small>%spx</small></div>' % (key, size, size, size, shapes[key], size) for size in (20, 24, 32, 48))
        cards.append('<figure class="icon-tile module-%s"><figcaption><b>%s</b><code>%s</code></figcaption><div class="samples">%s</div></figure>' % (meta[key]['tone'], html.escape(meta[key]['label']), key, samples))
    sheet = stage / 'contact-sheet.html'
    sheet.parent.mkdir(parents=True, exist_ok=True)
    sheet.write_text('''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Architex origami contact sheet</title>
<link rel="stylesheet" href="../../app/public/assets/v1-clone/css/reference-shell.css"><link rel="stylesheet" href="../../app/public/assets/v1-clone/css/reference-style-005.css">
<style>html,body{height:auto;overflow:auto}body{padding:32px}h1{font-size:32px;margin:0 0 8px}header,main.sheet{position:relative}header{margin-bottom:24px}.sheet{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}.samples{display:flex;justify-content:space-between;align-items:center;min-height:86px;background:color-mix(in srgb,var(--tone) 8%,white);border-radius:16px;padding:12px}.samples>div{display:grid;justify-items:center;gap:9px}small,code{font-size:11px;color:var(--muted)}code{display:block;margin-top:5px}.icon-tile{border-radius:24px}</style>
<header><h1>Folded paper, precise purpose.</h1><p>Architex canonical origami family — ''' + str(len(shapes)) + ''' registered icons at 20 / 24 / 32 / 48px. Original ink, teal, type and module tones; no stock glyphs. Generated by build_architex_icons.py.</p></header><main class="sheet">''' + ''.join(cards) + '</main></html>\n', encoding='utf-8', newline='\n')
    receipt = coverage(shapes, meta, OUT_JS)
    (stage / "icons/coverage-report.json").write_text(receipt, encoding="utf-8", newline="\n")
    publish(stage, output_root)
    print('icons converted:', len(shapes), '/', len(reg))
    print('role totals:', {r: sum(v[r] for v in report.values()) for r in ('outline', 'line', 'fold')})
    print('multi-fold:', [k for k, v in report.items() if v['fold'] != 1])
    print('no-body:', [k for k, v in report.items() if v['outline'] == 0])
    print('problems:', problems)
    print('wrote', OUT_JS, OUT_JS.stat().st_size)
    return 0 if not problems else 1


def publish(stage, output_root=ROOT):
    """Atomic per-file replacement, with rollback on caught publication errors.

    Not cross-file atomic: readers can see a mix during publish; abrupt process
    termination/power loss is outside the rollback guarantee. Run one publisher.
    """
    import os, tempfile
    stage = stage.resolve()
    output_root = Path(output_root).resolve()
    assets = output_root / 'app/public/assets/v1-clone'
    pairs = [(stage / 'js/architex-icon-pack.js', assets / 'js/architex-icon-pack.js'),
             (stage / 'icons/conversion-report.json', assets / 'icons/conversion-report.json'),
             (stage / 'icons/coverage-report.json', assets / 'icons/coverage-report.json'),
             (stage / 'contact-sheet.html', output_root / 'docs/material3-expressive/icon-contact-sheet.html')]
    pairs += [(p, assets / 'icons/inline' / p.name) for p in sorted((stage / 'icons/inline').glob('*.svg'))]
    for source, dest in pairs:
        if source.is_symlink() or not source.is_file() or not source.resolve().is_relative_to(stage):
            raise ValueError('invalid staged output: ' + str(source))
        if not dest.resolve().is_relative_to(output_root) or dest.is_symlink():
            raise ValueError('output escapes output root: ' + str(dest))
    originals = {dest: dest.read_bytes() if dest.exists() else None for _, dest in pairs}
    changed = []
    def replace(dest, data):
        dest.parent.mkdir(parents=True, exist_ok=True)
        fd, name = tempfile.mkstemp(prefix='.icon-publish-', dir=dest.parent)
        try:
            with os.fdopen(fd, 'wb') as stream:
                stream.write(data)
            os.replace(name, dest)
        finally:
            if os.path.exists(name):
                os.unlink(name)
    try:
        for source, dest in pairs:
            replace(dest, source.read_bytes())
            changed.append(dest)
    except BaseException:
        for dest in reversed(changed):
            if originals[dest] is None:
                dest.unlink()
            else:
                replace(dest, originals[dest])
        raise


def main():
    import tempfile, os, argparse
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-root', type=Path, default=ROOT,
                        help='destination repository-shaped root; inputs remain beside this script')
    args = parser.parse_args()
    scratch = Path(os.environ.get('TMPDIR', 'E:/Hermes/home/cache/scratch'))
    scratch.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='icon-stage-', dir=scratch) as stage:
        return build(Path(stage), args.output_root)


if __name__ == '__main__':
    sys.exit(main())
