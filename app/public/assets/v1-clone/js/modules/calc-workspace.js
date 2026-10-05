(function (root) {
  'use strict';
  // Engineering calculator workspace — wires the REAL registered backend calculators
  // (beam_quick_check, concrete/masonry/finishes quantity, tender rate build-up,
  // labour productivity, fenestration XA, R-value thermal, refuse area, voltage drop,
  // Rational Method runoff) into a usable form-driven UI. Advisory outputs only —
  // every result carries the backend's advisoryOnly boundary; never a certified result.
  const CALCS = {
    beam_quick_check: { label: 'Beam quick check', discipline: 'structural',
      fields: [['spanM','Span (m)','number',6],['udlKnPerM','UDL (kN/m)','number',10],['widthMm','Width (mm)','number',300],['depthMm','Depth (mm)','number',500],['allowableStressMpa','Allowable stress (MPa)','number',10],['deflectionLimitMm','Deflection limit (mm)','number',24]] },
    concrete_quantity_calculator: { label: 'Concrete quantity', discipline: 'structural',
      fields: [['elements','Elements (lengthM,widthM,thicknessM per line) — use a single element','lines',null]] },
    masonry_quantity_calculator: { label: 'Masonry quantity', discipline: 'structural',
      fields: [['walls','Walls (lengthM,heightM,thicknessM) — one per line','lines',null]] },
    finishes_quantity_calculator: { label: 'Finishes quantity', discipline: 'finishes',
      fields: [['surfaces','Surfaces (lengthM,heightM) — one per line','lines',null]] },
    tender_rate_build_up_calculator: { label: 'Tender rate build-up', discipline: 'tender',
      fields: [['directCostPerUnit','Direct cost / unit','number',100],['overheadPercent','Overhead %','number',10],['profitPercent','Profit %','number',8],['riskPercent','Risk %','number',3],['quantity','Quantity','number',100]] },
    labour_productivity_calculator: { label: 'Labour productivity', discipline: 'labour',
      fields: [['totalQuantity','Total quantity','number',100],['outputPerCrewDay','Output per crew-day','number',10],['crewSize','Crew size','number',5],['hoursPerDay','Hours per day','number',9]] },
    fenestration_xa_calculator: { label: 'Fenestration XA (SANS 10400-XA)', discipline: 'xa',
      fields: [['zone','Energy zone','number',4],['elements','Elements (areaM2,uValue,shgc,orientation) — one per line','lines',null]] },
    r_value_thermal_calculator: { label: 'R-value / thermal', discipline: 'xa',
      fields: [['zone','Energy zone','number',4],['assemblies','Assemblies (layers as r=value separated by +) — one per line','lines',null]] },
    refuse_area_calculator: { label: 'Refuse area (municipal)', discipline: 'municipal',
      fields: [['zone','Zoning / use','number',1],['occupancy','Occupancy (persons)','number',50],['generationLpd','Generation (L/person/day)','number',2],['collectionDays','Collection interval (days)','number',7]] },
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
    function show(id) {
      currentCalc = id;
      const c = CALCS[id]; const panel = root.document.querySelector('#main [data-calc-detail]'); if (!panel || !c) return;
      const fields = c.fields.map(([name, label, type, def]) => {
        if (type === 'select') return `<label>${esc(label)}<select data-calc-field="${esc(name)}">${def.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('')}</select></label>`;
        if (type === 'lines') return `<label>${esc(label)}<textarea data-calc-field="${esc(name)}" rows="3"></textarea></label>`;
        return `<label>${esc(label)}<input type="number" step="any" data-calc-field="${esc(name)}" value="${esc(def)}"></label>`;
      }).join('');
      panel.hidden = false;
      panel.innerHTML = `<h2>${esc(c.label)}</h2><div class="calc-form">${fields}</div>
        <button class="btn" data-calc-run>Run calculation</button>
        <pre class="calc-result" data-calc-result></pre>`;
      panel.querySelector('[data-calc-run]').addEventListener('click', () => run(id, c, panel));
    }
    async function run(id, c, panel) {
      const values = {};
      panel.querySelectorAll('[data-calc-field]').forEach(f => {
        const v = f.value; values[f.getAttribute('data-calc-field')] = f.tagName === 'TEXTAREA' ? v : (f.tagName === 'SELECT' ? v : Number(v));
      });
      const btn = panel.querySelector('[data-calc-run]'); btn.disabled = true;
      try {
        const method = ADAPTER_METHOD[id];
        const record = await adapter[method](values);
        const out = record?.output || record?.results || record?.result || record;
        panel.querySelector('[data-calc-result]').textContent = JSON.stringify(out, null, 2);
        notice(c.label + ': calculation persisted (advisory). Verified in Toolbox history.');
      } catch (e) { notice(e.message + ' No success claimed.'); }
      finally { btn.disabled = false; }
    }
    async function apply(toolId) {
      if (!TOOLS[toolId]) return { applied: false, reason: 'not-calc' };
      active = toolId; const token = ++generation;
      if (disposed || token !== generation) return { applied: false, reason: 'stale' };
      list();
      return { applied: true, tool: toolId };
    }
    function dispose() { disposed = true; ++generation; active = null; }
    return { apply, dispose, calculators: CALCS };
  }
  root.V1CloneCalcWorkspace = { install };
})(window);
