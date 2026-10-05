"""Capture referenced source excerpts without adding unrelated backend changes.

Run only when deliberately refreshing the inspection evidence. The snapshot can
reproduce the radar from a clean checkout even if a referenced module is pending
in another workstream. It is evidence of source inspection, not a runtime audit.
"""
import hashlib
import json
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).parent / 'source-evidence.json'


def safe_source(root, path):
    logical = PurePosixPath(path)
    if logical.is_absolute() or '..' in logical.parts or '\\' in path or ':' in path:
        raise ValueError(f'Unsafe evidence source path: {path}')
    if not path.startswith('app/') or any(part.startswith('.') for part in logical.parts):
        raise ValueError(f'Unexpected evidence source path: {path}')
    resolved_root = Path(root).resolve()
    source = (resolved_root / path).resolve()
    if not source.is_relative_to(resolved_root):
        raise ValueError(f'Evidence source escapes root: {path}')
    if not source.is_relative_to(resolved_root / 'app') or any(part.startswith('.') for part in source.relative_to(resolved_root).parts):
        raise ValueError(f'Resolved evidence source is outside the permitted app tree: {path}')
    return source


def capture():
    manifest = json.loads((OUT.parent / 'coverage.json').read_text(encoding='utf-8'))
    refs = []
    for entry in manifest['tools']:
        refs.extend(entry['sourceRefs'])
        refs.extend(entry['replacement'].get('sourceRefs', []))
    for collection in ['relationships', 'groups']:
        for entry in manifest[collection]:
            refs.extend(entry['sourceRefs'])
    files = {}
    for ref in refs:
        relative = ref['path']
        source = safe_source(ROOT, relative)
        raw = source.read_bytes()
        lines = raw.decode('utf-8', errors='replace').splitlines()
        line = ref['line']
        if not isinstance(line, int) or line < 1 or line > len(lines):
            raise ValueError(f'Invalid source line: {ref}')
        record = files.setdefault(relative, {'sha256':hashlib.sha256(raw).hexdigest(), 'lines':{}})
        record['lines'][str(line)] = {'text':lines[line-1], 'contextStart':max(1,line-2), 'context':lines[max(0,line-3):min(len(lines),line+2)]}
    payload = {'schemaVersion':1, 'provenance':'Repository working-tree source inspection snapshot; uncommitted backend work is not part of the radar PR or a runtime certification.', 'files':files}
    OUT.write_text(json.dumps(payload,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
    print(f'Captured {len(refs)} references across {len(files)} source files')


if __name__ == '__main__':
    capture()
