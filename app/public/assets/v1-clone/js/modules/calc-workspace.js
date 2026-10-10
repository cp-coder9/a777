(function (root) {
  'use strict';
  // Engineering calculator workspace — wires the REAL registered backend calculators
  // (beam_quick_check, concrete/masonry/finishes quantity, tender rate build-up,
  // labour productivity, fenestration XA, R-value thermal, refuse area, voltage drop,
  // Rational Method runoff) into a usable form-driven UI. Advisory outputs only —
  // every result carries the backend's advisoryOnly boundary; never a certified result.
  const CALCS = {
    beam_quick_check: { label: 'Beam quick check', discipline: 'structural',
      fields: [["spanM", "Span (m)", "number", 6], ["udlKnPerM", "UDL (kN/m)", "number", 10], ["pointLoadKn", "Point load (kN)", "number", 0], ["elasticModulusMpa", "Elastic modulus (MPa)", "number", 200000], ["inertiaMm4", "Second moment of area (mm⁴)", "number", 100000000], ["sectionModulusMm3", "Section modulus (mm³)", "number", 400000], ["allowableStressMpa", "Allowable stress (MPa)", "number", 150], ["deflectionLimitRatio", "Deflection limit span ratio", "number", 250]] },
    concrete_quantity_calculator: { label: 'Concrete quantity', discipline: 'structural',
      fields: [["elements", "Elements (JSON array; reference, lengthM, widthM, depthM, quantity)", "json-array", "[{\"reference\": \"Element 1\", \"lengthM\": 6, \"widthM\": 3, \"depthM\": 0.15, \"quantity\": 1}]"], ["wastePercent", "Waste (%)", "number", 5]] },
    masonry_quantity_calculator: { label: 'Masonry quantity', discipline: 'structural',
      fields: [["grossWallAreaM2", "Gross wall area (m²)", "number", 100], ["openingsAreaM2", "Openings area (m²)", "number", 10], ["unitLengthM", "Unit length including joint (m)", "number", 0.23], ["unitHeightM", "Unit height including joint (m)", "number", 0.085], ["wastePercent", "Waste (%)", "number", 5]] },
    finishes_quantity_calculator: { label: 'Finishes quantity', discipline: 'finishes',
      fields: [["mode", "Finish mode", "select", ["paint", "tile"]], ["surfaceAreaM2", "Surface area (m²)", "number", 100], ["wastePercent", "Waste (%)", "number", 5], ["coats", "Paint coats", "number", 2], ["coverageM2PerLitre", "Paint coverage (m²/L)", "number", 10], ["packCoverageM2", "Tile pack coverage (m²)", "number", 1]] },
    tender_rate_build_up_calculator: { label: 'Tender rate build-up', discipline: 'tender',
      fields: [["quantity", "Quantity", "number", 100], ["unit", "Quantity unit", "text", "m²"], ["materialsPerUnit", "Materials / unit", "number", 100], ["labourPerUnit", "Labour / unit", "number", 20], ["plantPerUnit", "Plant / unit", "number", 0], ["subcontractPerUnit", "Subcontract / unit", "number", 0], ["overheadsPercent", "Overheads (%)", "number", 10], ["profitPercent", "Profit (%)", "number", 8], ["riskPercent", "Risk (%)", "number", 3]] },
    labour_productivity_calculator: { label: 'Labour productivity', discipline: 'labour',
      fields: [["quantity", "Quantity", "number", 100], ["unit", "Quantity unit", "text", "m²"], ["productivityPerCrewDay", "Productivity / crew-day", "number", 10], ["parallelCrews", "Parallel crews", "number", 1], ["hoursPerDay", "Hours per day", "number", 9]] },
    fenestration_xa_calculator: { label: 'Fenestration XA (SANS 10400-XA)', discipline: 'xa',
      fields: [["project", "Actual project description (JSON object)", "json-object", "{\"projectName\": \"\", \"reference\": \"\", \"address\": \"\", \"climateZone\": null, \"storeys\": null, \"floorArea\": null, \"buildingType\": \"residential\", \"sansNCheck\": false}"], ["ruleVersionId", "Registered rule version ID (32 hex)", "text", ""], ["elements", "Actual fenestration elements (JSON array)", "json-array", "[{\"id\": \"\", \"storey\": 1, \"room\": \"\", \"roomUse\": \"habitable\", \"orientation\": \"N\", \"frameType\": \"alu\", \"elementType\": \"fenestration\", \"glassArea\": null, \"openableArea\": null, \"wallArea\": null, \"roomFloorArea\": null, \"uValue\": null, \"shgc\": null, \"shadingFactor\": null, \"description\": \"\"}]"]] },
    r_value_thermal_calculator: { label: 'R-value / thermal', discipline: 'xa',
      fields: [["project", "Actual project description (JSON object)", "json-object", "{\"projectName\": \"\", \"reference\": \"\", \"address\": \"\", \"climateZone\": null, \"storeys\": null, \"floorArea\": null, \"buildingType\": \"residential\", \"sansNCheck\": false}"], ["ruleVersionId", "Registered rule version ID (32 hex)", "text", ""], ["assemblies", "Actual thermal assemblies (JSON array)", "json-array", "[{\"id\": \"\", \"type\": \"roof\", \"description\": \"\", \"climateZone\": null, \"area\": null, \"layers\": [{\"material\": \"\", \"thicknessMm\": null, \"rValue\": null}]}]"]] },
    refuse_area_calculator: { label: 'Refuse area (municipal)', discipline: 'municipal',
      fields: [["project", "Actual project description (JSON object)", "json-object", "{\"projectName\": \"\", \"reference\": \"\", \"address\": \"\", \"climateZone\": null, \"storeys\": null, \"floorArea\": null, \"buildingType\": \"residential\", \"sansNCheck\": false}"], ["ruleVersionId", "Registered rule version ID (32 hex)", "text", ""], ["occupancyUse", "Registered occupancy use", "text", ""], ["quantity", "Occupancy quantity", "number", null], ["collectionIntervalDays", "Collection interval (days)", "number", null], ["contingencyPercent", "Contingency (%)", "number", null], ["binType", "Registered bin type", "text", ""], ["proposedStorageAreaM2", "Proposed storage area (m²)", "number", null], ["facilities", "Actual facilities (JSON object)", "json-object", "{}"], ["assumptions", "Recorded project assumptions", "text", ""]] },
    voltage_drop_calculator: { label: 'Voltage drop / cable sizing', discipline: 'electrical',
      fields: [['phase','Phase (single_phase|three_phase)','select',['three_phase','single_phase']],['nominalVoltageV','Nominal voltage (V)','number',400],['currentA','Current (A)','number',32],['routeLengthM','Route length (m)','number',40],['resistanceOhmPerKm','Resistance (ohm/km)','number',0.524],['reactanceOhmPerKm','Reactance (ohm/km)','number',0.08],['powerFactor','Power factor','number',0.9],['maximumDropPercent','Max drop (%)','number',5]] },
    rational_runoff_calculator: { label: 'Rational Method stormwater', discipline: 'civil',
      fields: [['coefficient','Runoff coefficient (C)','number',0.8],['intensity','Rainfall intensity (mm/h)','number',80],['areaHa','Catchment area (ha)','number',0.5]] },
  };
  const ADAPTER_METHOD = {
    beam_quick_check: 'saveBeamCheck', concrete_quantity_calculator: 'saveConcreteQuantity',
    masonry_quantity_calculator: 'saveMasonryQuantity', finishes_quantity_calculator: 'saveFinishesQuantity',
    tender_rate_build_up_calculator: 'saveTenderRateBuildUp', labour_productivity_calculator: 'saveLabourProductivity',
    fenestration_xa_calculator: 'saveFenestrationXa', r_value_thermal_calculator: 'saveRValueThermal',
    refuse_area_calculator: 'saveRefuseArea', voltage_drop_calculator: 'saveVoltageDrop',
    rational_runoff_calculator: 'saveRational',
  };
  // Only the refuse_calculator SCAFFOLD is routed to this workspace (it had no UI in
  // v1.html). The native engineering_calc module keeps its original v1 frame — the
  // real calculators are also reachable programmatically via the site-tools adapter.
  const TOOLS = { refuse_calculator: true };
  function install({ context }) {
    const adapter = root.V1CloneSiteToolsAdapter.create(context);
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let generation = 0, disposed = false, active = null, currentCalc = null;
    function notice(m) { root.showToast?.(m); const el = root.document.querySelector('#main [data-calc-status]'); if (el) el.textContent = m; }
    function list() {
      const main = root.document.querySelector('#main'); if (!main) return;
      const items = Object.entries(CALCS).map(([id, c]) => `<button class="btn" data-calc-id="${esc(id)}">${esc(c.label)}</button>`).join('');
      main.innerHTML = `<div class="calc-workspace" data-calc-tool="${active}">
        <div class="rfi-hero"><div><h1>Engineering &amp; Discipline Calculators</h1><p>${Object.keys(CALCS).length} real backend engines — advisory results, professional verification required.</p></div></div>
        <p class="pill" data-calc-status role="status">Advisory only — not a certified or statutory calculation.</p>
        <section class="panel"><h2>Calculators</h2><div class="calc-list">${items}</div></section>
        <section class="panel" data-calc-detail hidden></section></div>`;
      root.document.querySelectorAll('[data-calc-id]').forEach(b => b.addEventListener('click', () => show(b.getAttribute('data-calc-id'))));
    }
    let mountedScope = null, stale = false, busy = false;
    const snapshot = () => context.getState?.() || {};
    function sameScope() { const s = snapshot(); return mountedScope && s.ready === mountedScope.ready && s.session === mountedScope.session && s.organisationId === mountedScope.organisationId && s.selectedProjectId === mountedScope.selectedProjectId; }
    function availability(panel) {
      const s = snapshot(), rule = panel.querySelector('[data-calc-field="ruleVersionId"]');
      const reason = disposed || stale ? 'Scope changed; reload this workspace.' : s.readOnly ? 'Read-only session: calculations cannot be saved.' : !s.ready || !s.session || !s.selectedProjectId ? 'Select an authenticated project.' : rule && !/^[a-f0-9]{32}$/i.test(rule.value) ? 'Enter a registered 32-hex rule version ID from the actual rule source.' : '';
      panel.querySelector('[data-calc-run]').disabled = busy || !!reason;
      if (reason) notice(reason);
      return !reason && !busy;
    }
    const unsubscribe = context.subscribe?.(() => {
      if (active && !sameScope()) { stale = true; ++generation; busy = false; }
      const panel = root.document.querySelector('#main [data-calc-detail]');
      if (panel?.querySelector('[data-calc-run]')) { if (stale) panel.querySelector('[data-calc-result]').textContent = ''; availability(panel); }
    });
    function show(id) {
      ++generation; busy = false;
      currentCalc = id;
      const c = CALCS[id]; const panel = root.document.querySelector('#main [data-calc-detail]'); if (!panel || !c) return;
      const fields = c.fields.map(([name, label, type, def]) => {
        if (type === 'select') return `<label>${esc(label)}<select data-calc-field="${esc(name)}">${def.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('')}</select></label>`;
        if (type === 'json-array' || type === 'json-object') return `<label>${esc(label)}<textarea data-calc-field="${esc(name)}" data-calc-json="${esc(type)}" aria-describedby="calc-help-${esc(name)}" rows="5" placeholder="${esc(def)}"></textarea></label><small id="calc-help-${esc(name)}">Enter a JSON ${type === 'json-array' ? 'array' : 'object'} using the displayed field names. Use actual project values, not template placeholders. ${name === 'facilities' ? 'Include every requiredFacilities key from the selected registered rule source, with a true/false value.' : name === 'elements' && id === 'fenestration_xa_calculator' ? 'Areas are m²; U-value is W/(m²·K); description is required.' : name === 'assemblies' ? 'Area is m²; thickness is mm; R-value is m²·K/W (or supply conductivity W/(m·K)).' : ''}</small>`;
        if (type === 'lines') return `<label>${esc(label)}<textarea data-calc-field="${esc(name)}" rows="3"></textarea></label>`;
        return `<label>${esc(label)}<input type="${type === 'text' ? 'text' : 'number'}" step="any" data-calc-field="${esc(name)}" value="${esc(def)}"></label>`;
      }).join('');
      panel.hidden = false;
      panel.innerHTML = `<h2>${esc(c.label)}</h2><div class="calc-form">${fields}</div>
        <button class="btn" data-calc-run data-technical-primary>Run calculation</button>
        <button class="btn" data-calc-cancel>Cancel / reset display</button>
        <pre class="calc-result" data-calc-result role="status" aria-live="polite" aria-label="Advisory calculation result"></pre>`;
      panel.querySelector('[data-calc-run]').addEventListener('click', () => run(id, c, panel));
      panel.querySelectorAll('[data-calc-field]').forEach(f => f.addEventListener('input', () => availability(panel)));
      panel.querySelector('[data-calc-cancel]').addEventListener('click', () => {
        const pending = busy; ++generation; busy = false;
        panel.querySelectorAll('textarea').forEach(f => { f.value = ''; });
        panel.querySelector('[data-calc-result]').textContent = '';
        notice(pending ? 'Display cancelled. An already-sent calculation may still persist; check Toolbox history before retrying.' : 'Input display reset.');
        availability(panel);
      });
      availability(panel);
    }
    async function run(id, c, panel) {
      if (!sameScope()) { stale = true; availability(panel); return; }
      if (!availability(panel)) return;
      const token = ++generation;
      const valid = () => !disposed && !stale && token === generation && sameScope() && currentCalc === id;
      panel.querySelector('[data-calc-result]').textContent = '';
      try {
        const values = {};
        for (const f of panel.querySelectorAll('[data-calc-field]')) {
          const name = f.getAttribute('data-calc-field'), kind = f.getAttribute('data-calc-json');
          if (kind) {
            let value;
            try { value = JSON.parse(f.value); } catch (_) { throw Error(name + ': enter valid JSON.'); }
            if (kind === 'json-array' ? !Array.isArray(value) : !value || typeof value !== 'object' || Array.isArray(value)) throw Error(name + ': expected a JSON ' + (kind === 'json-array' ? 'array.' : 'object.'));
            values[name] = value;
          } else if (f.type === 'number') {
            if (!f.value.trim() || !Number.isFinite(Number(f.value))) throw Error(name + ': enter a finite number.');
            values[name] = Number(f.value);
          } else values[name] = f.value;
        }
        busy = true; availability(panel);
        const record = await adapter[ADAPTER_METHOD[id]](values);
        if (!valid()) return;
        const out = record?.output;
        if (out?.advisoryOnly !== true) throw Error('Advisory output boundary not verified.');
        panel.querySelector('[data-calc-result]').textContent = JSON.stringify(out, null, 2);
        notice(c.label + ': calculation persisted (advisory). Verified in Toolbox history.');
      } catch (e) { if (valid()) notice(e.message + ' No success claimed.'); }
      finally { if (valid()) { busy = false; availability(panel); } }
    }
    async function apply(toolId) {
      if (!TOOLS[toolId]) return { applied: false, reason: 'not-calc' };
      active = toolId; mountedScope = { ...snapshot() }; stale = false; busy = false; const token = ++generation;
      if (disposed || token !== generation) return { applied: false, reason: 'stale' };
      list();
      return { applied: true, tool: toolId };
    }
    function dispose() { disposed = true; ++generation; busy = false; active = null; unsubscribe?.(); const panel = root.document.querySelector('#main [data-calc-detail]'); if (panel?.querySelector('[data-calc-run]')) { panel.querySelector('[data-calc-result]').textContent = ''; availability(panel); } }
    return { apply, dispose, calculators: CALCS };
  }
  root.V1CloneCalcWorkspace = { install };
})(window);
