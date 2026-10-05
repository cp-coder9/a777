(function (root) {
  'use strict';
  // Municipal Refuse Area Calculator — the screen.
  //
  // Figures on this page are never computed here. The server holds the arithmetic and the rule
  // pack; this module renders what it returns. That is deliberate: a second implementation of the
  // formula in the browser is how a screen starts disagreeing with the record it claims to show.
  //
  // Every verdict row carries the basis the server attached to it, so a pass can always be traced
  // to the by-law clause or the arithmetic that produced it — and an unverified rule pack shows as
  // "not assessed" rather than a green tick.
  const TOOL = 'refuse_calculator';
  const SECTIONS = [
    { id: 'municipality', label: 'Municipality profile', icon: 'municipalityProfile' },
    { id: 'building', label: 'Building type', icon: 'buildingType' },
    { id: 'volume', label: 'Waste volume', icon: 'wasteVolume' },
    { id: 'storage', label: 'Refuse storage area', icon: 'refuseStorageArea' },
    { id: 'bins', label: 'Bins', icon: 'binAllocation' },
    { id: 'access', label: 'Vehicle access', icon: 'collectionVehicleAccess' },
    { id: 'ventilation', label: 'Ventilation', icon: 'ventilationRequirement' },
    { id: 'export', label: 'Export report', icon: 'exportPdfReport' },
  ];
  const VERDICT = {
    pass: { label: 'Pass', className: 'is-pass' },
    warning: { label: 'Warning', className: 'is-warning' },
    fail: { label: 'Fail', className: 'is-fail' },
    not_assessed: { label: 'Not assessed', className: 'is-unassessed' },
  };

  function install({ context }) {
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    const adapter = root.V1CloneRefuseAdapter.create(context);
    const esc = value => String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const icon = (key, size) => (typeof root.icon === 'function' ? root.icon(key, size || 18) : '');
    let generation = 0, disposed = false, active = false;
    let catalogue = null, records = [], selected = null, rulesOpen = false, message = 'Ready.';
    // True while the user has deliberately started a blank calculation. Without it, any refresh
    // re-selects the newest record and silently discards that intent.
    let blank = false;

    const notice = text => { if (disposed || !ownsRoute(TOOL)) return;
      message = text;
      root.showToast?.(text);
      const node = root.document.querySelector('#main [data-refuse-status]');
      if (node) node.textContent = text;
    };

    // ---- data ---------------------------------------------------------------------------
    async function refresh(forceCatalogue, token = generation) {
      const loadedCatalogue = await adapter.loadCatalogue(forceCatalogue);
      if (disposed || !ownsRoute(TOOL) || token !== generation) return;
      const scope = context.getState();
      const loadedRecords = scope.mode === 'standalone' || !/^[a-f0-9]{32}$/i.test(scope.selectedProjectId || '') ? [] : await adapter.listForProject();
      if (disposed || !ownsRoute(TOOL) || token !== generation) return;
      catalogue = loadedCatalogue; records = loadedRecords;
      if (selected) {
        const still = records.find(r => r.id === selected.id);
        selected = still || null;
      }
      if (!selected && !blank && records.length) selected = records[0];
    }

    // ---- rendering ----------------------------------------------------------------------
    function kpiStrip(record) {
      const results = record?.results || null;
      const cell = (key, label, value, unit) => `
        <li class="refuse-kpi">
          <span class="refuse-kpi-icon">${icon(key, 22)}</span>
          <span class="refuse-kpi-copy"><b>${esc(label)}</b>
            <strong>${value === null ? '—' : esc(value)}</strong>${ui(unit)}</span>
        </li>`;
      const ui = unit => (unit ? `<span class="refuse-kpi-unit">${esc(unit)}</span>` : '');
      return `<ul class="refuse-kpis" data-refuse-kpis>
        ${cell('wasteVolume', 'Waste volume', results ? results.wasteVolumeM3PerDay : null, 'm³/day')}
        ${cell('refuseStorageArea', 'Storage area required', results ? results.requiredStorageAreaM2 : null, 'm²')}
        ${cell('binAllocation', 'Bins required', results ? `${results.binsRequired} × ${results.binCapacityLitres}` : null, 'litre')}
      </ul>
      ${results ? '' : '<p class="refuse-hint">No calculation saved yet, so there are no figures to show. Enter the parameters and save — the server does the arithmetic and these cards show what it recorded.</p>'}`;
    }

    function rail() {
      return `<nav class="refuse-rail" aria-label="Calculator sections">
        ${SECTIONS.map(section => `<button type="button" class="refuse-rail-item" data-refuse-goto="${section.id}">
          <span class="refuse-rail-icon">${icon(section.icon, 16)}</span>${esc(section.label)}</button>`).join('')}
      </nav>`;
    }

    // The record exposes a few fields under the name the database uses, while the form uses the
    // name the API accepts. Without this map, reopening a saved calculation would silently show
    // defaults for those two fields and the next save would overwrite them.
    const FIELD_ALIAS = { generationRateLitresPerPersonDay: 'generationRate', ventilationType: 'ventilationType' };

    function value(name, fallback) {
      const source = selected || {};
      const input = source.inputs || {};
      const alias = FIELD_ALIAS[name] || name;
      const candidates = [source[alias], source[name], input[name]];
      const raw = candidates.find(candidate => candidate !== undefined && candidate !== null);
      return raw === undefined ? (fallback === undefined ? '' : fallback) : raw;
    }

    function parameters() {
      const defaults = catalogue?.defaults || {};
      return `
      <section class="refuse-panel" id="refuse-municipality">
        <h2>${icon('municipalityProfile', 16)} Municipality profile</h2>
        <div class="refuse-grid">
          <label class="field"><span>Municipality</span><input data-refuse-field="municipality" value="${esc(value('municipality'))}" placeholder="City of Example" required></label>
          <label class="field"><span>Building type</span><input data-refuse-field="buildingType" value="${esc(value('buildingType'))}" placeholder="Residential apartment" required></label>
          <label class="field"><span>Title</span><input data-refuse-field="title" value="${esc(value('title'))}" placeholder="Refuse store — block A" required></label>
        </div>
        <p class="refuse-hint">The municipality name selects the rule pack. Slug: <code data-refuse-slug>${esc(slug(value('municipality')))}</code></p>
      </section>

      <section class="refuse-panel" id="refuse-building">
        <h2>${icon('buildingType', 16)} Occupancy</h2>
        <div class="refuse-grid">
          <label class="field"><span>Occupancy (persons)</span><input data-refuse-field="occupancy" type="number" min="1" step="1" value="${esc(value('occupancy'))}" required></label>
          <label class="field"><span>Gross floor area (m², recorded)</span><input data-refuse-field="grossFloorAreaM2" type="number" min="0" step="0.01" value="${esc(value('grossFloorAreaM2'))}"></label>
          <label class="field"><span>Refuse generation rate (L/person/day)</span><input data-refuse-field="generationRateLitresPerPersonDay" type="number" min="0.1" step="0.1" value="${esc(value('generationRateLitresPerPersonDay', defaults.generationRateLitresPerPersonDay))}" required></label>
        </div>
      </section>

      <section class="refuse-panel" id="refuse-volume">
        <h2>${icon('wasteVolume', 16)} Collection</h2>
        <div class="refuse-grid">
          <label class="field"><span>Collection interval (days)</span><input data-refuse-field="collectionIntervalDays" type="number" min="1" max="31" step="1" value="${esc(value('collectionIntervalDays', defaults.collectionIntervalDays))}" required></label>
          <label class="field"><span>Bin capacity (litres)</span><input data-refuse-field="binCapacityLitres" type="number" min="30" step="10" value="${esc(value('binCapacityLitres', defaults.binCapacityLitres))}" required></label>
        </div>
        <p class="refuse-hint">Volume and bin count are arithmetic on occupancy, rate and interval. Severity of the interval itself is judged only against a verified criterion.</p>
      </section>

      <section class="refuse-panel" id="refuse-storage">
        <h2>${icon('refuseStorageArea', 16)} Refuse storage area provided</h2>
        <div class="refuse-grid">
          <label class="field"><span>Storage area provided (m²)</span><input data-refuse-field="providedStorageAreaM2" type="number" min="0" step="0.01" value="${esc(value('providedStorageAreaM2'))}"></label>
          <label class="field"><span>Bins provided</span><input data-refuse-field="binsProvided" type="number" min="0" step="1" value="${esc(value('binsProvided'))}"></label>
        </div>
        <p class="refuse-hint">Leave these blank and the comparison rows will say so rather than guess.</p>
      </section>

      <section class="refuse-panel" id="refuse-access">
        <h2>${icon('collectionVehicleAccess', 16)} Access and services</h2>
        <div class="refuse-grid">
          <label class="field"><span>Access width (m)</span><input data-refuse-field="accessWidthM" type="number" min="0" step="0.01" value="${esc(value('accessWidthM'))}"></label>
          <label class="field"><span>Ventilation</span><select data-refuse-field="ventilationType">
            <option value="">Not recorded</option>
            ${(catalogue?.ventilationStrategies || []).map(s => `<option value="${esc(s)}"${value('ventilationType') === s ? ' selected' : ''}>${esc(s)}</option>`).join('')}
          </select></label>
          <label class="field checkbox"><input type="checkbox" data-refuse-field="drainageProvided"${value('drainageProvided') ? ' checked' : ''}> <span>Wash-down drainage provided</span></label>
        </div>
      </section>

      <section class="refuse-panel" id="refuse-ventilation">
        <h2>${icon('ventilationRequirement', 16)} Calculation</h2>
        <div class="refuse-form-actions">
          <button class="btn" type="button" data-refuse-save>${selected ? 'Recalculate and save' : 'Save calculation'}</button>
          <span class="pill" data-refuse-status role="status">${esc(message)}</span>
        </div>
      </section>`;
    }

    function binSpec() {
      return `<section class="refuse-panel refuse-spec" id="refuse-bins">
        <h2>${icon('binAllocation', 16)} Typical 240 L wheelie bin</h2>
        <div class="refuse-spec-body">
          <svg class="refuse-bin" viewBox="0 0 120 150" role="img" aria-label="Indicative 240 litre wheeled bin">
            <rect class="outline" x="18" y="34" width="84" height="96" rx="6"></rect>
            <rect class="line" x="12" y="24" width="96" height="12" rx="5"></rect>
            <path class="line" d="M46 24v-6h28v6"></path>
            <path class="line" d="M30 52v64M60 52v64M90 52v64"></path>
            <circle class="line" cx="34" cy="136" r="9"></circle>
            <circle class="line" cx="86" cy="136" r="9"></circle>
            <path class="fold" d="M12 24h96l-8 8H20z"></path>
          </svg>
          <dl class="refuse-spec-list">
            <div><dt>Capacity</dt><dd>240 L</dd></div>
            <div><dt>Footprint</dt><dd>600 × 730 mm</dd></div>
            <div><dt>Height</dt><dd>1,070 mm</dd></div>
            <div><dt>Standing area</dt><dd>0.438 m²</dd></div>
          </dl>
        </div>
        <p class="refuse-hint">Standard bin dimensions. The rule pack supplies the footprint the calculation uses, so a municipality that differs can say so.</p>
      </section>`;
    }

    function layoutSketch(record) {
      const bins = record?.results?.binsRequired;
      const area = record?.results?.requiredStorageAreaM2;
      return `<section class="refuse-panel refuse-sketch">
        <h2>${icon('areaDimensions', 16)} Indicative refuse area layout (not to scale)</h2>
        <svg viewBox="0 0 360 190" role="img" aria-label="Indicative refuse store layout, not to scale">
          <rect class="outline" x="12" y="24" width="336" height="150" rx="8"></rect>
          <path class="line" d="M12 174h336"></path>
          <path class="line" d="M40 60h56v46H40zM108 60h56v46h-56zM176 60h56v46h-56z"></path>
          <path class="fold" d="M40 60h56l-8 8H48zM108 60h56l-8 8h-48zM176 60h56l-8 8h-48z"></path>
          <path class="line" d="M262 44v96"></path>
          <circle class="line" cx="150" cy="146" r="24"></circle>
          <path class="line" d="M126 146h48"></path>
          <text class="refuse-sketch-label" x="20" y="20">wash-down tap + floor drain</text>
          <text class="refuse-sketch-label" x="120" y="186">turning circle</text>
        </svg>
        <p class="refuse-hint">${bins ? `${bins} bin positions at the required area of ${esc(area)} m².` : 'Save a calculation to size this layout.'} This sketch illustrates arrangement only — it asserts no dimension.</p>
      </section>`;
    }

    function resultsMatrix(record) {
      if (!record) {
        return `<section class="refuse-panel" id="refuse-export"><h2>${icon('advisoryDisclaimer', 16)} Calculation results</h2>
          <p class="refuse-hint">Nothing to show yet.</p></section>`;
      }
      const rows = (record.assessments || []).map(assessment => {
        const verdict = VERDICT[assessment.verdict] || VERDICT.not_assessed;
        return `<tr>
          <td><b>${esc(assessment.label)}</b><br><small>${esc(assessment.detail)}</small></td>
          <td><span class="refuse-verdict ${verdict.className}">${esc(verdict.label)}</span><br><small class="refuse-basis">${esc(assessment.basis)}</small></td>
        </tr>`;
      }).join('');
      const packLine = record.rulePackVersion
        ? `Rule pack <b>${esc(record.rulePackVersion)}</b> — ${record.rulePackStatus === 'verified' ? 'verified' : 'NOT verified'}`
        : 'No rule pack loaded for this municipality';
      return `<section class="refuse-panel" id="refuse-results">
        <h2>${icon('advisoryDisclaimer', 16)} Calculation results</h2>
        <p class="refuse-packline">${packLine}</p>
        <table class="table refuse-matrix">
          <thead><tr><th>Assessment</th><th>Result and basis</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
        <p class="refuse-advisory">${esc(record.advisory || '')}</p>
      </section>`;
    }

    function signOff(record) {
      const judged = record ? (record.assessments || []).filter(row => row.verdict !== 'not_assessed') : [];
      const declared = record?.status === 'declared';
      const reason = declared
        ? `Declared by ${esc(record.declaredBy ? 'the responsible professional' : '—')} on ${esc(record.declaredAt || '')}: ${esc(record.declarationNote || '')}`
        : judged.length === 0
          ? 'Nothing in this calculation could be judged, so it cannot be declared. Record what is being compared, or load and verify a rule pack.'
          : `${judged.length} of ${(record?.assessments || []).length} rows were assessed. Declaring records your responsibility for these figures.`;
      return `<section class="refuse-panel refuse-signoff">
        <h2>${icon('professionalSignOff', 16)} Professional sign-off</h2>
        <p class="refuse-hint">${reason}</p>
        ${declared
          ? `<p class="refuse-declared">${esc(record.declarationNote || '')}</p><button class="btn" type="button" disabled title="A declared calculation is immutable and stays on the record.">Declared — locked</button>`
          : `<div class="refuse-form-actions">
               <input class="refuse-note" data-refuse-declaration placeholder="Basis of the declaration (required)" maxlength="500"${judged.length === 0 ? ' disabled' : ''}>
               <button class="btn" type="button" data-refuse-declare${judged.length === 0 ? ' disabled' : ''}${record ? '' : ' disabled'}>Request sign-off</button>
             </div>`}
      </section>`;
    }

    function exportPanel() {
      // There is no configured PDF provider in this deployment. Saying so is the honest control;
      // a button that produces nothing would be the fake-data failure we are ruling out.
      return `<section class="refuse-panel refuse-export" id="refuse-export">
        <h2>${icon('exportPdfReport', 16)} Export report</h2>
        <p class="refuse-hint">${icon('lockedUntilSignOff', 14)} PDF export is <b>unavailable</b>: no PDF provider is configured for this deployment. The figures remain in the record and can be read from the API.</p>
        <button class="btn" type="button" disabled title="No PDF provider is configured. This control stays unavailable rather than producing an empty file.">Export PDF — provider not configured</button>
      </section>`;
    }

    function savedTable() {
      if (!records.length) return '<p class="refuse-hint">No calculations saved for this project yet.</p>';
      return `<table class="table refuse-saved">
        <thead><tr><th>Calculation</th><th>Status</th><th>Bins</th><th>Area req.</th><th>Rule pack</th><th>Revision</th><th>Actions</th></tr></thead>
        <tbody>${records.map(record => `
          <tr${selected && selected.id === record.id ? ' class="is-selected"' : ''}>
            <td><b>${esc(record.title)}</b><br><small>${esc(record.municipality)} · ${esc(record.buildingType)}</small></td>
            <td><span class="pill">${esc(record.status)}</span></td>
            <td>${esc(record.results?.binsRequired ?? '—')} × ${esc(record.results?.binCapacityLitres ?? '')}</td>
            <td>${esc(record.results?.requiredStorageAreaM2 ?? '—')} m²</td>
            <td>${record.rulePackVersion ? esc(record.rulePackVersion) + (record.rulePackStatus === 'verified' ? '' : ' (unverified)') : '—'}</td>
            <td>rev ${esc(record.revision)}</td>
            <td class="refuse-rowactions">
              <button class="btn" data-refuse-select="${esc(record.id)}">Open</button>
              <button class="btn" data-refuse-delete="${esc(record.id)}"${record.status === 'declared' ? ' disabled title="A declared calculation cannot be deleted."' : ''}>Delete</button>
            </td>
          </tr>`).join('')}</tbody>
      </table>`;
    }

    function ruleDrawer() {
      if (!rulesOpen) return '';
      const packs = (catalogue?.municipalities || []);
      return `<section class="refuse-panel refuse-rules" data-refuse-rules>
        <h2>${icon('municipalityProfile', 16)} Rule packs</h2>
        <p class="refuse-hint">Criteria are applied only once verified. Until then the calculator reports the arithmetic and refuses to judge against them.</p>
        ${packs.length ? `<table class="table"><thead><tr><th>Municipality</th><th>Version</th><th>Status</th><th>Source</th><th>Decision</th></tr></thead><tbody>
          ${packs.map(pack => `<tr>
            <td>${esc(pack.municipalityName)}</td><td>${esc(pack.version)}</td>
            <td><span class="pill">${esc(pack.status)}</span></td>
            <td><small>${esc(pack.sourceReference)}</small></td>
            <td class="refuse-rowactions">
              ${pack.status === 'verified'
                ? `<button class="btn" data-refuse-withdraw="${esc(pack.id)}">Withdraw</button>`
                : `<input data-refuse-review-reason="${esc(pack.id)}" placeholder="Reason (required)" maxlength="500"><button class="btn" data-refuse-verify="${esc(pack.id)}">Verify</button>`}
            </td></tr>`).join('')}
        </tbody></table>` : '<p class="refuse-hint">No rule packs stored yet.</p>'}
        <details class="refuse-newrule">
          <summary>Add a rule pack</summary>
          <div class="refuse-grid">
            <label class="field"><span>Municipality name</span><input data-rule="municipalityName" placeholder="City of Example"></label>
            <label class="field"><span>Version</span><input data-rule="version" placeholder="2026.1"></label>
            <label class="field"><span>Source (required)</span><input data-rule="sourceReference" placeholder="Solid Waste By-law, schedule 3"></label>
            <label class="field"><span>Max collection interval (days)</span><input data-rule="max_collection_interval_days" type="number" min="1" max="31"></label>
            <label class="field"><span>Min vehicle access width (m)</span><input data-rule="min_vehicle_access_width_m" type="number" min="0" step="0.01"></label>
            <label class="field"><span>Accepted ventilation</span><select data-rule="ventilation_strategies">
              <option value="natural,mechanical">natural + mechanical</option>
              <option value="natural">natural</option>
              <option value="mechanical">mechanical</option>
            </select></label>
            <label class="field checkbox"><input type="checkbox" data-rule="drainage_required"> <span>Wash-down drainage required</span></label>
          </div>
          <div class="refuse-form-actions"><button class="btn" type="button" data-refuse-save-rule>Store rule pack</button></div>
        </details>
      </section>`;
    }

    function render() { if (disposed || !ownsRoute(TOOL)) return;
      const main = root.document.querySelector('#main');
      if (!main) return;
      const state = context.getState();
      if (state.mode === 'standalone' || !/^[a-f0-9]{32}$/i.test(state.selectedProjectId || '')) {
        main.innerHTML = '<section class="refuse-workspace" data-refuse-tool="refuse_calculator" data-refuse-blocked="no-project"><h1>Municipal Refuse Area Calculator</h1><section class="panel" style="padding:16px"><h2>Select a project to continue</h2><p>The refuse catalogue is available. Saved calculations and professional declarations belong to an authorised project.</p><p>Use Attach project or the shared project selector to choose a project explicitly. No project records have been loaded.</p></section></section>';
        return;
      }
      const project = state.projects?.find(p => p.id === state.selectedProjectId)?.name || 'Selected project';
      main.innerHTML = `
        <div class="refuse-workspace" data-refuse-tool="${TOOL}">
          <header class="refuse-hero">
            <div class="refuse-title">
              <span class="refuse-hero-icon">${icon('municipalRefuseCalculator', 26)}</span>
              <div><h1>Municipal Refuse Area Calculator</h1>
                <p>Project: <strong>${esc(project)}</strong> · ${records.length} saved calculation(s)</p></div>
            </div>
            <div class="refuse-hero-actions">
              <button class="btn" type="button" data-refuse-reset>New calculation</button>
              <button class="btn" type="button" data-refuse-rules-toggle>Rule packs</button>
            </div>
          </header>
          ${kpiStrip(selected)}
          <div class="refuse-body">
            ${rail()}
            <div class="refuse-columns">
              <form class="refuse-form" data-refuse-form>${parameters()}</form>
              <div class="refuse-side">
                ${resultsMatrix(selected)}
                ${binSpec()}
                ${layoutSketch(selected)}
                ${signOff(selected)}
                ${exportPanel()}
              </div>
            </div>
          </div>
          ${ruleDrawer()}
          <section class="refuse-panel">
            <h2>Saved calculations</h2>
            ${savedTable()}
          </section>
        </div>`;
      bind(main);
    }

    // ---- interaction --------------------------------------------------------------------
    function slug(text) {
      return String(text || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function readForm(main) {
      const fields = {};
      main.querySelectorAll('[data-refuse-field]').forEach(input => {
        const name = input.getAttribute('data-refuse-field');
        fields[name] = input.type === 'checkbox' ? input.checked : String(input.value || '').trim();
      });
      return fields;
    }

    function payload(fields) {
      const body = {
        title: fields.title, municipality: fields.municipality, buildingType: fields.buildingType,
        occupancy: Number(fields.occupancy), generationRateLitresPerPersonDay: Number(fields.generationRateLitresPerPersonDay),
        collectionIntervalDays: Number(fields.collectionIntervalDays), binCapacityLitres: Number(fields.binCapacityLitres),
        drainageProvided: fields.drainageProvided === true,
      };
      // Optional fields are only sent when the professional actually recorded them; sending "" for
      // an unanswered question is how a "not assessed" row turns into a wrong one.
      if (fields.grossFloorAreaM2 !== '') body.grossFloorAreaM2 = Number(fields.grossFloorAreaM2);
      if (fields.providedStorageAreaM2 !== '') body.providedStorageAreaM2 = Number(fields.providedStorageAreaM2);
      if (fields.binsProvided !== '') body.binsProvided = Number(fields.binsProvided);
      if (fields.accessWidthM !== '') body.accessWidthM = Number(fields.accessWidthM);
      if (fields.ventilationType !== '') body.ventilationType = fields.ventilationType;
      return body;
    }

    async function guarded(button, work, success) {
      if (button) button.disabled = true;
      try {
        await work();
        // Refresh and re-paint first, then announce: announcing before the re-render left the
        // success message sitting above the previous figures.
        await refresh(true);
        render();
        notice(success);
      } catch (error) {
        notice(error.message + ' Nothing was saved.');
        if (button) button.disabled = false;
      }
    }

    function bind(main) {
      main.querySelectorAll('[data-refuse-goto]').forEach(button => button.addEventListener('click', () => {
        const target = main.querySelector('#refuse-' + button.getAttribute('data-refuse-goto'));
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        main.querySelectorAll('[data-refuse-goto]').forEach(other => other.classList.toggle('is-active', other === button));
      }));

      const municipality = main.querySelector('[data-refuse-field="municipality"]');
      const slugNode = main.querySelector('[data-refuse-slug]');
      municipality?.addEventListener('input', () => { if (slugNode) slugNode.textContent = slug(municipality.value); });

      main.querySelector('[data-refuse-save]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const fields = readForm(main);
        if (!fields.municipality || !fields.title || !fields.buildingType) { notice('Municipality, building type and title are required.'); return; }
        if (selected && selected.status !== 'declared') {
          const body = payload(fields);
          body.expectedRevision = selected.revision;
          await guarded(button, () => adapter.update(selected.id, body), 'Recalculated and saved; the figures below are from the server.');
          return;
        }
        if (selected && selected.status === 'declared') {
          notice('This calculation is declared and cannot be recalculated. Start a new calculation.');
          return;
        }
        const body = payload(fields);
        body.clientRequestId = 'refuse_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        await guarded(button, async () => { selected = await adapter.create(body); blank = false; }, 'Calculation saved; the figures below are from the server.');
      });

      main.querySelector('[data-refuse-reset]')?.addEventListener('click', () => { selected = null; blank = true; notice('New calculation — nothing saved until you save it.'); render(); });
      main.querySelector('[data-refuse-rules-toggle]')?.addEventListener('click', () => { rulesOpen = !rulesOpen; render(); });

      main.querySelectorAll('[data-refuse-select]').forEach(button => button.addEventListener('click', () => {
        selected = records.find(r => r.id === button.getAttribute('data-refuse-select')) || null;
        blank = selected === null;
        notice('Loaded ' + (selected?.title || '') + '.');
        render();
      }));

      main.querySelectorAll('[data-refuse-delete]').forEach(button => button.addEventListener('click', async () => {
        const record = records.find(r => r.id === button.getAttribute('data-refuse-delete'));
        if (!record) return;
        if (!root.confirm?.(`Delete "${record.title}"? This removes the calculation permanently.`)) return;
        await guarded(button, () => adapter.remove(record.id, record.revision), 'Calculation deleted and confirmed gone.');
      }));

      main.querySelector('[data-refuse-declare]')?.addEventListener('click', async event => {
        if (!selected) return;
        const note = String(main.querySelector('[data-refuse-declaration]')?.value || '').trim();
        if (!note) { notice('State the basis of the declaration first.'); return; }
        if (!root.confirm?.('Declare these figures? The declaration is recorded against your name and the calculation becomes immutable.')) return;
        await guarded(event.currentTarget, () => adapter.declare(selected.id, note, selected.revision), 'Declared and locked; the declaration is on the record.');
      });

      main.querySelectorAll('[data-refuse-verify]').forEach(button => button.addEventListener('click', async () => {
        const id = button.getAttribute('data-refuse-verify');
        const reason = String(main.querySelector(`[data-refuse-review-reason="${id}"]`)?.value || '').trim();
        if (!reason) { notice('A verification reason is required — it is recorded on the pack.'); return; }
        await guarded(button, () => adapter.reviewRulePack(id, 'verify', reason), 'Rule pack verified. Criteria from this version are now applied.');
      }));

      main.querySelectorAll('[data-refuse-withdraw]').forEach(button => button.addEventListener('click', async () => {
        const id = button.getAttribute('data-refuse-withdraw');
        const reason = root.prompt?.('Reason for withdrawing this rule pack') || '';
        if (!reason.trim()) return;
        await guarded(button, () => adapter.reviewRulePack(id, 'withdraw', reason), 'Rule pack withdrawn.');
      }));

      main.querySelector('[data-refuse-save-rule]')?.addEventListener('click', async event => {
        const read = name => String(main.querySelector(`[data-rule="${name}"]`)?.value || '').trim();
        const name = read('municipalityName');
        const criteria = {};
        if (read('max_collection_interval_days')) criteria.max_collection_interval_days = { value: Number(read('max_collection_interval_days')), source: read('sourceReference') };
        if (read('min_vehicle_access_width_m')) criteria.min_vehicle_access_width_m = { value: Number(read('min_vehicle_access_width_m')), source: read('sourceReference') };
        const strategies = read('ventilation_strategies');
        if (strategies) criteria.ventilation_strategies = { value: strategies.split(','), source: read('sourceReference') };
        if (main.querySelector('[data-rule="drainage_required"]')?.checked) criteria.drainage_required = { value: true, source: read('sourceReference') };
        await guarded(event.currentTarget, () => adapter.saveRulePack({
          municipalityKey: slug(name), municipalityName: name, version: read('version'),
          sourceReference: read('sourceReference'), criteria: criteria,
        }), 'Rule pack stored as review_required. Verify it before its criteria are applied.');
      });
    }

    // ---- lifecycle ----------------------------------------------------------------------
    async function apply(toolId) {
      if (disposed || !ownsRoute(toolId)) return { applied: false, reason: 'stale' };
      if (toolId !== TOOL) return { applied: false, reason: 'not-refuse-calculator' };
      active = true;
      const token = ++generation;
      try {
        await refresh();
        if (disposed || !ownsRoute(TOOL) || token !== generation) return { applied: false, reason: 'stale' };
        render();
        return { applied: true, tool: TOOL, records: records.length };
      } catch (error) {
        if (disposed || !ownsRoute(TOOL) || token !== generation) return { applied: false, reason: 'stale' };
        notice(error.message + ' The calculator could not be loaded.');
        return { applied: false, reason: 'error', message: error.message };
      }
    }

    const unsubscribe = typeof context.subscribe === 'function' ? context.subscribe(() => { if (active && !disposed) void apply(TOOL); }) : null;
    function dispose() { disposed = true; ++generation; active = false; if (typeof unsubscribe === 'function') unsubscribe(); }

    return { apply, dispose, supports: id => id === TOOL };
  }
  root.V1CloneRefuseCalculator = { install };
})(window);
