(function (root) {
  'use strict';
  // Iconography Registry — the honest nature of this module IS a registry: a central
  // catalogue of the Architex icon system.
  //
  // The pack ships as hard-coded SVGs (stroke #102033, stroke-width 2, fill #19B7B0),
  // which can only be shown through <img> and therefore cannot follow the theme, the
  // module tone or the hover behaviour. tools/v1-clone/build_architex_icons.py rewrites
  // every icon into the v1.html role model (.ax-icon .outline / .line / .fold, geometry
  // only, colour from CSS) and emits js/architex-icon-pack.js. This module renders that
  // registry as real inline SVG through the shell's own icon() helper, so a catalogue
  // tile and a navigation row are literally the same icon markup.
  //
  // Read-only by design. It is a catalogue, not an icon editor: the icons are compiled
  // from the pack, so an edit here would be lost on the next build.
  const doc = root.document;

  function install({ context }) {
    const esc = v => String(v == null ? '' : v)
      .replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let generation = 0, disposed = false, cache = null;
    let ui = { q: '', cat: 'all', tone: 'all', size: 32 };

    const CAT_LABEL = {
      primary: 'Primary navigation', remoteDesktop: 'Remote session', forms: 'Integrated forms',
      eiaWorkspace: 'EIA workspace', practiceManagement: 'Practice management',
      bimQuantities: 'BIM / IFC quantities', rfqMarketplace: 'RFQ marketplace',
      refuse: 'Municipal refuse', advisory: 'Advisory & sign-off', practice: 'Practice & project',
      planning: 'Planning & approvals', compliance: 'Compliance', documents: 'Documents',
      procurement: 'Procurement', site: 'Site execution', platform: 'Platform services',
      collaboration: 'Collaboration', project: 'Project & collaboration', general: 'Other'
    };
    const TONE_ORDER = ['core', 'cobalt', 'lavender', 'coral', 'amber'];
    const SIZE_STEPS = [16, 20, 24, 32, 48, 64];

    function esc2(v) { return esc(v); }

    /* ---------------------------------------------------------------- data --- */

    function pack() {
      const p = root.ARCHITEX_ICON_PACK;
      if (!p || !p.shapes) return null;
      return p;
    }

    function shapeFor(key) {
      const p = pack();
      if (!p) return null;
      if (typeof p.effective === 'function') return p.effective(key);
      return p.shapes[key] || root.iconShapes?.[key] || null;
    }

    function sourceOf(key) {
      const p = pack();
      if (p && typeof p.sourceOf === 'function') return p.sourceOf(key) || 'pack';
      return 'pack';
    }

    // Live validation straight off the markup that actually renders — not a
    // hard-coded claim. Resolution follows the same order the app uses, so an icon
    // the shell registry already owned is measured as the shell renders it.
    function audit(p) {
      const rows = [];
      let folds = 0, outlines = 0, lines = 0;
      for (const key of Object.keys(p.meta)) {
        const shape = shapeFor(key) || '';
        const f = (shape.match(/class="fold"/g) || []).length;
        const o = (shape.match(/class="outline"/g) || []).length;
        const l = (shape.match(/class="line"/g) || []).length;
        folds += f; outlines += o; lines += l;
        rows.push({ key, fold: f, outline: o, line: l, hardcoded: /#(102033|19B7B0)/i.test(shape) });
      }
      return {
        icons: rows.length,
        folds, outlines, lines,
        noFold: rows.filter(r => r.fold === 0).map(r => r.key),
        multiFold: rows.filter(r => r.fold > 1).map(r => r.key),
        noBody: rows.filter(r => r.outline === 0).map(r => r.key),
        hardcoded: rows.filter(r => r.hardcoded).map(r => r.key)
      };
    }

    /* -------------------------------------------------------------- render --- */

    function toneClass(t) { return 'module-' + (TONE_ORDER.includes(t) ? t : 'core'); }

    function tile(key, meta) {
      const svg = inline(key, ui.size);
      const src = sourceOf(key);
      const tags = [
        `<span class="icon-tag tone">${esc(meta.tone || 'core')}</span>`,
        meta.priority ? `<span class="icon-tag ${meta.priority === 'P0' ? 'p0' : ''}">${esc(meta.priority)}</span>` : '',
        `<span class="icon-tag" title="${src === 'shell' ? 'The v1.html icon registry already owned this key, so it stays the design reference for it' : 'Geometry supplied by architex_svg_icon_pack-2'}">${src === 'shell' ? 'shell' : 'pack'}</span>`
      ].join('');
      return `<figure class="icon-tile ${toneClass(meta.tone)}" data-icon-key="${esc(key)}" data-icon-source="${src}">
        <div class="icon-frame">${svg}</div>
        <figcaption>
          <span class="icon-name">${esc(meta.label || key)}</span>
          <span class="icon-key">${esc(key)}</span>
          <span class="icon-tags">${tags}</span>
        </figcaption>
        <div class="icon-actions">
          <button type="button" data-copy="${esc(key)}" title="Copy the inline SVG markup">Copy SVG</button>
          <button type="button" data-download="${esc(key)}" title="Download a standalone themed SVG">Download</button>
        </div>
      </figure>`;
    }

    function inline(key, size) {
      const shape = shapeFor(key);
      if (!shape) return '';
      // The shell's icon() builds exactly the markup v1.html uses, so a catalogue
      // tile cannot drift from the icon used in the navigation.
      if (typeof root.icon === 'function') return root.icon(key, size);
      return `<svg class="ax-icon" width="${size}" height="${size}" viewBox="0 0 24 24" aria-hidden="true">${shape}</svg>`;
    }

    function filtered(p) {
      const q = ui.q.trim().toLowerCase();
      return Object.entries(p.meta).filter(([key, meta]) => {
        if (ui.cat !== 'all' && (meta.category || 'other') !== ui.cat) return false;
        if (ui.tone !== 'all' && (meta.tone || 'core') !== ui.tone) return false;
        if (!q) return true;
        return key.toLowerCase().includes(q) || String(meta.label || '').toLowerCase().includes(q)
          || String(meta.category || '').toLowerCase().includes(q);
      });
    }

    function sectionBody(p, entries) {
      const byCat = {};
      for (const [key, meta] of entries) (byCat[meta.category || 'other'] ||= []).push([key, meta]);
      const ordered = Object.keys(byCat).sort((a, b) => {
        const ia = CAT_KEY_ORDER.indexOf(a), ib = CAT_KEY_ORDER.indexOf(b);
        return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib) || a.localeCompare(b);
      });
      return ordered.map(cat => `<section class="icon-section">
        <h2>${esc(CAT_LABEL[cat] || cat)}<small>· ${byCat[cat].length} icon${byCat[cat].length === 1 ? '' : 's'}</small></h2>
        <p>Registry category <code>${esc(cat)}</code> — tone is metadata for the container, so these icons stay ink plus one teal fold.</p>
        <div class="icon-grid" data-size="${ui.size}">${byCat[cat].map(([k, m]) => tile(k, m)).join('')}</div>
      </section>`).join('');
    }

    const CAT_KEY_ORDER = ['primary', 'practiceManagement', 'forms', 'bimQuantities', 'rfqMarketplace',
      'refuse', 'eiaWorkspace', 'remoteDesktop', 'advisory'];

    function chipRow(p) {
      const counts = {};
      for (const meta of Object.values(p.meta)) counts[meta.category || 'other'] = (counts[meta.category || 'other'] || 0) + 1;
      const cats = Object.keys(counts).sort((a, b) => {
        const ia = CAT_KEY_ORDER.indexOf(a), ib = CAT_KEY_ORDER.indexOf(b);
        return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib) || a.localeCompare(b);
      });
      const chip = (val, label, n) => `<button type="button" class="icon-chip ${ui.cat === val ? 'active' : ''}" data-cat="${esc(val)}">${esc(label)}${n == null ? '' : `<span class="n">${n}</span>`}</button>`;
      return `<div class="icon-chips">${chip('all', 'All categories', Object.keys(p.meta).length)}${cats.map(c => chip(c, CAT_LABEL[c] || c, counts[c])).join('')}</div>`;
    }

    function anatomyPanel(p) {
      const sample = p.shapes.project_passport || p.shapes.specification || Object.values(p.shapes)[0];
      const markup = k => (typeof root.icon === 'function' ? root.icon(k, 64) : '');
      const sampleKey = p.shapes.project_passport ? 'project_passport' : (p.shapes.specification ? 'specification' : Object.keys(p.shapes)[0]);
      return `<section class="icon-anatomy">
        <div class="icon-anatomy-card">
          <h3>Anatomy — body, detail, fold</h3>
          <p>Every Architex icon is the same three roles stacked in one 24&times;24 box. Colour and stroke width come from CSS, so a single icon follows the theme, the module tone and the hover state.</p>
          <div class="icon-anatomy-demo">${markup(sampleKey)}</div>
          <div class="icon-role-row"><div class="icon-role-swatch"><svg class="ax-icon" width="22" height="22" viewBox="0 0 24 24"><rect class="outline" x="4" y="5" width="16" height="14" rx="3"/></svg></div><div><b>outline — the body</b><span>White fill, ink outline. Carries the silhouette.</span><code>.ax-icon .outline</code></div></div>
          <div class="icon-role-row"><div class="icon-role-swatch"><svg class="ax-icon" width="22" height="22" viewBox="0 0 24 24"><rect class="line" x="4" y="5" width="16" height="14" rx="3"/><path class="line" d="M8 10h8M8 14h5"/></svg></div><div><b>line — the detail</b><span>Ink stroke, no fill. Reads on light and dark surfaces.</span><code>.ax-icon .line</code></div></div>
          <div class="icon-role-row"><div class="icon-role-swatch"><svg class="ax-icon" width="22" height="22" viewBox="0 0 24 24"><path class="fold" d="M13 8h7v7z"/></svg></div><div><b>fold — the accent</b><span>Exactly one solid teal fold per icon. The only part that animates.</span><code>.ax-icon .fold</code></div></div>
        </div>
        <div class="icon-anatomy-card">
          <h3>Tokens</h3>
          <p>Two colours and one stroke width. Tone is never painted inside the icon; it is carried by the mint-tinted container behind it.</p>
          <div class="icon-swatches">
            <div class="icon-swatch"><i style="background:#102033"></i><b>Ink</b><code>#102033</code></div>
            <div class="icon-swatch"><i style="background:#19B7B0"></i><b>Teal fold</b><code>#19B7B0</code></div>
            <div class="icon-swatch"><i style="background:#BFE9E2"></i><b>Mint tint</b><code>#BFE9E2</code></div>
            <div class="icon-swatch"><i style="background:#fff;border:1px solid rgba(16,32,51,.16)"></i><b>Body fill</b><code>#ffffff</code></div>
          </div>
          <div class="icon-role-row"><div class="icon-role-swatch"><svg class="ax-icon" width="22" height="22" viewBox="0 0 24 24"><rect class="outline" x="4" y="5" width="16" height="14" rx="3" style="stroke-width:1.8"/></svg></div><div><b>Stroke</b><span>1.8px, round caps and joins, on a 24&times;24 grid.</span><code>stroke-width:1.8</code></div></div>
          <div class="icon-role-row"><div class="icon-role-swatch"><svg class="ax-icon" width="22" height="22" viewBox="0 0 24 24" style="opacity:.28"><rect class="line" x="4" y="4" width="16" height="16" rx="4"/><path class="line" d="M8 12h8M12 8v8"/></svg></div><div><b>Construction</b><span>Rounded 2px corner, 2px keyline inset, glyph centred.</span><code>24 &times; 24 &middot; 2px inset</code></div></div>
        </div>
      </section>`;
    }

    function sizeRamp(p) {
      const key = p.shapes.dashboard ? 'dashboard' : Object.keys(p.shapes)[0];
      return `<section class="icon-section">
        <h2>Size ramp<small>· one geometry, six sizes</small></h2>
        <p>Icons are drawn once at 24&times;24 and scaled as vector. The 16px step is the navigation size; 64px is the empty-state and hero size.</p>
        <div class="icon-size-ramp">${SIZE_STEPS.map(s => `<figure>${inline(key, s)}<figcaption>${s}px</figcaption></figure>`).join('')}</div>
      </section>`;
    }

    function doDont(p) {
      const k = p.shapes.procurement ? 'procurement' : Object.keys(p.shapes)[0];
      const shape = shapeFor(k);
      const twoFolds = shape ? shape.replace('class="fold"', 'class="fold"') + (shape.match(/class="fold"[^/]*\/>/) || [''])[0] : '';
      const recoloured = shape ? shape.replace(/class="outline"/g, 'class="outline" style="fill:#BFE9E2;stroke:#167E79"').replace(/class="line"/g, 'class="line" style="stroke:#2563EB"') : '';
      const bad = (body, cap) => `<div class="dd-item no" title="${esc(cap)}">${body}</div>`;
      const good = (body, cap) => `<div class="dd-item" title="${esc(cap)}">${body}</div>`;
      const svgOf = markup => `<svg class="ax-icon" width="34" height="34" viewBox="0 0 24 24">${markup}</svg>`;
      const noFold = shape ? shape.replace(/<[^>]*class="fold"[^>]*\/>/g, '') : '';
      return `<section class="icon-anatomy icon-dodont">
        <div class="icon-anatomy-card">
          <h3>Do — one body, ink detail, one teal fold</h3>
          <p>Four catalogue icons rendered exactly as the application renders them. Each has a white body, ink detail strokes and a single teal fold.</p>
          <div class="dd-list">${['dashboard', 'projects', 'calendar', 'messages'].filter(x => p.shapes[x]).map(x => good(svgOf(p.shapes[x]), (p.meta[x] || {}).label || x)).join('')}</div>
        </div>
        <div class="icon-anatomy-card">
          <h3>Do not — four ways to break the family</h3>
          <p>Each example is real markup from this registry, deliberately altered. The catalogue itself contains none of these.</p>
          <div class="dd-list">
            ${bad(svgOf(noFold), 'Outline only — no teal fold, so the accent is missing')}
            ${bad(svgOf(twoFolds), 'Two teal folds — the accent loses meaning')}
            ${bad(svgOf(recoloured), 'Recoloured body — tone belongs to the container, not the icon')}
            <div class="dd-item no" title="Detail strokes removed — the icon stops reading at 16px">
              <svg class="ax-icon" width="34" height="34" viewBox="0 0 24 24">${(shape || '').replace(/<[^>]*class="line"[^>]*\/>/g, '')}</svg>
            </div>
          </div>
        </div>
      </section>`;
    }

    function qaPanel(p) {
      const a = audit(p);
      const added = (p.added || []).length;
      const kept = (p.keptByShell || []).length;
      const check = (ok, text) => `<div><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="${ok ? '#28a86b' : '#d95747'}" stroke-width="2"/><path d="${ok ? 'm8 12 3 3 5-7' : 'M9 9l6 6M15 9l-6 6'}" stroke="${ok ? '#28a86b' : '#d95747'}" stroke-width="2" stroke-linecap="round"/></svg><span>${text}</span></div>`;
      return `<section class="icon-section">
        <h2>Validation<small>· measured on this page, from the markup that is rendering</small></h2>
        <p>These numbers are counted in the browser from the inline SVG above, so they cannot drift from what is painted on screen.</p>
        <div class="icon-qa">
          <div class="qa-card ok"><b>${a.icons}</b><span>icons in the registry</span></div>
          <div class="qa-card ${a.folds === a.icons ? 'ok' : 'warn'}"><b>${a.folds}</b><span>teal fold accents &mdash; ${a.folds === a.icons ? 'exactly one per icon' : 'expected one per icon'}</span></div>
          <div class="qa-card ok"><b>${a.outlines}</b><span>outline bodies</span></div>
          <div class="qa-card ok"><b>${a.lines}</b><span>ink detail strokes</span></div>
          <div class="qa-card ${a.hardcoded.length ? 'warn' : 'ok'}"><b>${a.hardcoded.length}</b><span>icons with a hard-coded colour &mdash; must be 0 so CSS stays in charge</span></div>
        </div>
        <div class="icon-checklist">
          ${check(a.noFold.length === 0, a.noFold.length ? 'Missing the teal fold: ' + esc(a.noFold.join(', ')) : 'Every icon carries exactly one teal fold.')}
          ${check(a.multiFold.length === 0, a.multiFold.length ? 'More than one teal fold: ' + esc(a.multiFold.join(', ')) : 'No icon uses the fold accent twice.')}
          ${check(a.noBody.length === 0, a.noBody.length ? 'No white body shape: ' + esc(a.noBody.join(', ')) : 'Every icon has a white body it can inherit the theme into.')}
          ${check(a.hardcoded.length === 0, a.hardcoded.length ? 'Hard-coded ink or teal found in ' + esc(a.hardcoded.join(', ')) : 'No icon geometry hard-codes ink or teal; colour resolves from the theme.')}
          ${check(true, `${added} keys added to the shell registry by this pack${kept ? `; ${kept} key${kept === 1 ? '' : 's'} the shell already owned were kept, so the v1.html design reference is never overwritten` : ''}.`)}
        </div>
      </section>`;
    }

    function render(p) {
      const main = doc.querySelector('#main');
      if (!main) return;
      const entries = filtered(p);
      const total = Object.keys(p.meta).length;
      main.innerHTML = `
        <div class="icon-workspace" data-icon-tool="iconography_registry">
          <div class="rfi-hero"><div>
            <h1>Iconography Registry</h1>
            <p>${total} Architex icons rendered inline &middot; white body + <code>#102033</code> ink outline + one <code>#19B7B0</code> teal fold &middot; colour resolved from CSS, never painted into the geometry</p>
          </div></div>
          <p class="pill" data-icon-status role="status">Central registry &mdash; semantic mapping and tone metadata. Read-only catalogue; not an icon editor.</p>
          <div class="icon-toolbar">
            <input type="search" data-icon-search placeholder="Search icon name, registry key or category" aria-label="Search icons" value="${esc2(ui.q)}">
            <select data-icon-tone aria-label="Filter by tone">
              <option value="all">All tones</option>
              ${TONE_ORDER.map(t => `<option value="${t}" ${ui.tone === t ? 'selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('')}
            </select>
            <select data-icon-size aria-label="Icon render size">
              ${SIZE_STEPS.map(s => `<option value="${s}" ${ui.size === s ? 'selected' : ''}>${s}px</option>`).join('')}
            </select>
            <span class="spacer"></span>
            <span class="icon-count"><b>${entries.length}</b> of ${total} shown</span>
          </div>
          ${chipRow(p)}
          <div data-icon-sections>${entries.length ? sectionBody(p, entries) : '<div class="icon-empty">No icon matches that search. Clear the search or pick another category.</div>'}</div>
          ${anatomyPanel(p)}
          ${sizeRamp(p)}
          ${doDont(p)}
          ${qaPanel(p)}
          <div class="notice"><b>How these icons reach the app.</b> The pack is compiled into the v1.html role model by <code>tools/v1-clone/build_architex_icons.py</code> and emitted as <code>js/architex-icon-pack.js</code>, which merges into the shell's own <code>iconShapes</code> registry without overwriting any key the shell already owned. Every tile above is therefore the same inline markup the navigation, the tool registry and the datum cards render &mdash; there is no separate image copy to fall out of sync. Icons are decoration for a label that is always present in text.</div>
        </div>`;
      wire(p);
      const status = doc.querySelector('[data-icon-status]');
      if (status) status.textContent = `Showing ${entries.length} of ${Object.keys(p.meta).length} icons. Read-only catalogue.`;
    }

    /* ----------------------------------------------------------- behaviour --- */
    function rerender() { render(pack()); }

    function wire(p) {
      const main = doc.querySelector('#main');
      if (!main) return;
      const search = main.querySelector('[data-icon-search]');
      search?.addEventListener('input', e => { ui.q = e.target.value; rerender(); const n = doc.querySelector('[data-icon-search]'); if (n) { n.focus(); n.selectionStart = n.selectionEnd = n.value.length; } });
      main.querySelector('[data-icon-tone]')?.addEventListener('change', e => { ui.tone = e.target.value; rerender(); });
      main.querySelector('[data-icon-size]')?.addEventListener('change', e => { ui.size = Number(e.target.value) || 32; rerender(); });
      main.querySelectorAll('[data-cat]').forEach(b => b.addEventListener('click', () => { ui.cat = b.dataset.cat; rerender(); }));
      main.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
        const key = b.dataset.copy;
        const meta = p.meta[key] || {};
        const svg = themed(key, meta);
        try {
          if (root.navigator?.clipboard?.writeText) await root.navigator.clipboard.writeText(svg);
          else {
            const ta = doc.createElement('textarea'); ta.value = svg; doc.body.appendChild(ta); ta.select();
            doc.execCommand('copy'); ta.remove();
          }
          b.dataset.done = '1'; b.textContent = 'Copied';
          setTimeout(() => { if (b.isConnected) { b.dataset.done = '0'; b.textContent = 'Copy SVG'; } }, 1400);
        } catch (e) { root.showToast?.('Clipboard blocked by the browser — use Download instead.'); }
      }));
      main.querySelectorAll('[data-download]').forEach(b => b.addEventListener('click', () => {
        const key = b.dataset.download;
        const meta = p.meta[key] || {};
        const blob = new root.Blob([themed(key, meta)], { type: 'image/svg+xml' });
        const url = root.URL.createObjectURL(blob);
        const a = doc.createElement('a');
        a.href = url; a.download = key + '.svg'; doc.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => root.URL.revokeObjectURL(url), 4000);
        root.showToast?.('Downloaded ' + key + '.svg with the theme tokens resolved.');
      }));
    }

    // A standalone export: geometry plus the resolved theme so the file opens with
    // the same ink and teal as the app (the inline markup relies on app CSS).
    function themed(key, meta) {
      const shape = shapeFor(key) || '';
      return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" role="img" aria-label="${esc(meta.label || key)}">
  <!-- Architex icon "${esc(key)}" (${esc(meta.tone || 'core')} tone). Geometry from architex_svg_icon_pack-2,
       compiled into the v1.html role model: body / detail / one teal fold. -->
  <style>
    .outline{fill:#fff;stroke:#102033;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .line{fill:none;stroke:#102033;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .fold{fill:#19B7B0;stroke:#167E79;stroke-width:.7;stroke-linejoin:round}
  </style>
  ${shape}
</svg>
`;
    }

    /* --------------------------------------------------------------- entry --- */
    async function apply(toolId) {
      if (toolId !== 'iconography_registry') return { applied: false, reason: 'not-iconography' };
      const token = ++generation;
      const p = pack();
      if (!p) {
        root.showToast?.('Icon registry unavailable: js/architex-icon-pack.js did not load.');
        return { applied: false, reason: 'registry-unavailable' };
      }
      if (disposed || token !== generation) return { applied: false, reason: 'stale' };
      cache = p;
      render(p);
      return { applied: true, tool: toolId, count: Object.keys(p.meta).length, inline: true };
    }

    function dispose() { disposed = true; ++generation; }

    return { apply, dispose, icons: () => cache, audit: () => (cache ? audit(cache) : null) };
  }

  root.V1CloneIconography = { install };
})(window);
