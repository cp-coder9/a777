/* Practice projections use only the tenant-scoped Practice workspace response. */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.ArchitexPracticeWorkspace = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';
  const statuses = ['open', 'in_progress', 'blocked', 'completed', 'cancelled'];
  const list = value => Array.isArray(value) ? value : [];
  function instant(value) {
    if (typeof value !== 'string' || !value.trim()) return null;
    // PracticeService stores UTC SQL datetimes without an offset.
    const normalized = /^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d(?:\.\d+)?$/.test(value)
      ? value.replace(' ', 'T').replace(/(\.\d{3})\d+$/, '$1') + 'Z' : value;
    const date = new Date(normalized);
    return Number.isFinite(date.getTime()) ? date : null;
  }
  function dayKey(date, timeZone) {
    const parts = new Intl.DateTimeFormat('en-CA', {timeZone, year:'numeric', month:'2-digit', day:'2-digit'}).formatToParts(date);
    const get = type => parts.find(p => p.type === type).value;
    return `${get('year')}-${get('month')}-${get('day')}`;
  }
  function project(workspace = {}, options = {}) {
    const timeZone = options.timeZone || Intl.DateTimeFormat().resolvedOptions().timeZone;
    const now = new Date(options.now || Date.now());
    const tasks = list(workspace.tasks);
    const board = Object.fromEntries([...statuses, 'other'].map(status => [status, []]));
    const calendar = {}, overdue = [], undated = [];
    for (const task of tasks) {
      board[statuses.includes(task.status) ? task.status : 'other'].push(task);
      const date = instant(task.dueAt);
      if (!date) { undated.push(task); continue; }
      const day = dayKey(date, timeZone);
      (calendar[day] ||= []).push(task);
      if (!['completed','cancelled'].includes(task.status) && date < now) overdue.push(task);
    }
    const pipeline = list(workspace.pipelineItems), timesheets = list(workspace.timesheets);
    const sum = (items, field) => items.reduce((total, row) => total + (Number.isFinite(Number(row[field])) ? Number(row[field]) : 0), 0);
    const open = pipeline.filter(row => ['lead','qualified','proposal','negotiation'].includes(row.stage));
    const forecast = {openValueCents:sum(open,'forecastValueCents'), openWeightedCents:sum(open,'weightedValueCents'), wonValueCents:sum(pipeline.filter(row => row.stage === 'won'),'forecastValueCents')};
    const time = {recordedMinutes:sum(timesheets,'minutes'), approvedBillableMinutes:sum(timesheets.filter(row => row.status === 'approved' && row.billable === true),'minutes')};
    return {tasks, board, calendar, overdue, undated, timeZone, forecast, time};
  }
  const views = {overview:'Overview', programme:'Programme / due dates', board:'Task board', calendar:'Calendar', resources:'Resources', timesheets:'Timesheets', forecast:'Pipeline forecast', operations:'Office operations'};
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const label = value => String(value || 'Not recorded').replace(/_/g,' ');
  const amount = cents => new Intl.NumberFormat('en-ZA',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(cents || 0)/100);
  const hours = minutes => `${Number((Number(minutes || 0)/60).toFixed(2))} h`;
  const notice = text => `<p class="practice-notice">${esc(text)}</p>`;
  function render(workspace, options = {}) {
    const view = Object.hasOwn(views, options.view) ? options.view : 'overview';
    const tabs = `<div class="practice-tabs" role="tablist" aria-label="Practice views">${Object.entries(views).map(([key,title]) => `<button type="button" role="tab" id="practice-tab-${key}" aria-selected="${view===key}" aria-controls="practice-view-panel" tabindex="${view===key?0:-1}" data-practice-view="${key}">${title}</button>`).join('')}</div>`;
    let content;
    if (options.error) content = `<p class="practice-notice" role="alert">Could not load Practice records: ${esc(options.error)}</p>`;
    else if (options.loading) content = '<p class="practice-notice" role="status">Loading project Practice records…</p>';
    else if (!workspace) content = notice('Select a project to load its Practice records. Project-owned saves require a selected project.');
    else {
      const w = workspace, m = project(w,options);
      const dateTime = value => {const date=instant(value);return date ? new Intl.DateTimeFormat('en-ZA',{timeZone:m.timeZone,dateStyle:'medium',timeStyle:'short'}).format(date) : 'No valid due date';};
      const taskCard = task => `<article class="practice-record"><h4>${esc(task.title)}</h4><p>${esc(label(task.status))} · ${esc(label(task.priority))}${m.overdue.includes(task)?' · <strong>Overdue</strong>':''}</p><p>Due ${esc(dateTime(task.dueAt))}</p><small>Assignee ${esc(task.assignedUserId || 'Not recorded')} · SLA ${esc(task.slaHours ?? 'Not recorded')} hours</small></article>`;
      const taskList = tasks => tasks.length ? tasks.map(taskCard).join('') : notice('No tasks in this view.');
      const cards = items => `<div class="practice-metrics">${items.map(([name,value])=>`<article><span>${esc(name)}</span><strong>${esc(value)}</strong></article>`).join('')}</div>`;
      const table = (headers,rows) => rows.length ? `<div class="practice-table-scroll"><table><thead><tr>${headers.map(h=>`<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(cell=>`<td>${esc(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` : notice('No records in this view yet.');
      const boundary = notice('Pipeline forecast is not actual revenue or profitability. Costs, billing rates, earned revenue and cash receipts are not supplied by this API. Won values are opportunity outcomes, not invoices.');
      if (view === 'overview') content = cards([['Recorded tasks',m.tasks.length],['Overdue active tasks',m.overdue.length],['Open weighted forecast (R)',amount(m.forecast.openWeightedCents)],['Approved billable time',hours(m.time.approvedBillableMinutes)]]) + '<h3>Due attention</h3>' + taskList(m.overdue) + boundary;
      if (view === 'programme') content = notice('Due-date programme only. Task start dates, dependencies, durations and critical-path calculations are unavailable; SLA is not a task duration.') + taskList([...m.tasks].sort((a,b)=>(instant(a.dueAt)?.getTime() ?? Infinity)-(instant(b.dueAt)?.getTime() ?? Infinity)));
      if (view === 'board') content = '<div class="practice-board">' + Object.entries(m.board).filter(([key,rows])=>key!=='other'||rows.length).map(([status,rows])=>`<section class="practice-lane"><h3>${esc(label(status))} <span>${rows.length}</span></h3>${taskList(rows)}</section>`).join('') + '</div>' + notice('Use Office operations for governed task transitions. Drag-and-drop does not change task state.');
      if (view === 'calendar') content = notice(`Task due-date agenda · ${m.timeZone}. Date-only records elsewhere retain their recorded calendar date.`) + (Object.keys(m.calendar).length ? Object.keys(m.calendar).sort().map(day=>`<section class="practice-day"><h3><time datetime="${day}">${day}</time></h3>${taskList(m.calendar[day])}</section>`).join('') : notice('No tasks with valid due dates.')) + (m.undated.length ? '<h3>Unscheduled / invalid due dates</h3>'+taskList(m.undated) : '');
      if (view === 'resources') content = notice('Capacity is the recorded allocation-window capacity, not an inferred person-wide utilisation rate. Overlapping windows are not deduplicated or summed.') + table(['User ID','Starts','Ends','Allocated','Capacity','Review'],list(w.resourceAllocations).map(r=>[r.userId,r.startsOn,r.endsOn,hours(r.allocatedMinutes),hours(r.capacityMinutes),r.overAllocated?'Over allocated':label(r.status)]));
      if (view === 'timesheets') content = cards([['All recorded time',hours(m.time.recordedMinutes)],['Approved billable time',hours(m.time.approvedBillableMinutes)]]) + notice('Recorded time includes drafts and rejected entries. Only approved billable time contributes to invoice-readiness time; no invoice or payment is created.') + table(['Work date','User ID','Workstage','Description','Time','Billing','Review'],list(w.timesheets).map(r=>[r.workDate,r.userId,r.workstage,r.description,hours(r.minutes),r.billable?'Billable':'Non-billable',label(r.status)]));
      if (view === 'forecast') content = cards([['Open forecast (R)',amount(m.forecast.openValueCents)],['Open weighted forecast (R)',amount(m.forecast.openWeightedCents)],['Won opportunity value (R)',amount(m.forecast.wonValueCents)]]) + boundary + notice('Open totals include lead, qualified, proposal and negotiation only. Weighted values are the persisted server values. No expected-close dates are provided, so this is not a time-phased forecast.') + table(['Opportunity','Stage','Probability','Forecast (R)','Weighted (R)'],list(w.pipelineItems).map(r=>[r.title,label(r.stage),`${r.probabilityPercent}%`,amount(r.forecastValueCents),amount(r.weightedValueCents)]));
      if (view === 'operations') content = notice('Office operations below retain creation, transitions, resource planning, timesheet submission/review, supervision, invoice readiness, registration reminders and controlled templates. Server authorisation and human confirmation remain required.');
      content = notice(`Task date/time display: ${m.timeZone}`) + content;
    }
    return `<section class="practice-experience">${tabs}${options.readOnly?notice('Read-only membership. Operational writes require an authorised role.'):''}<div id="practice-view-panel" role="tabpanel" aria-labelledby="practice-tab-${view}" tabindex="0"><h2>${views[view]}</h2>${content}</div></section>`;
  }
  function mount(host, workspace, options = {}) {
    let data = workspace, settings = {...options}, view = Object.hasOwn(views, options.view) ? options.view : 'overview';
    function paint(focus = false) {
      host.innerHTML = render(data, {...settings,view});
      if (settings.operations) settings.operations.hidden = view !== 'operations' || !data || !!settings.loading || !!settings.error;
      if (focus) host.querySelector(`#practice-tab-${view}`)?.focus();
    }
    function select(next, focus = false) {
      if (!Object.hasOwn(views,next)) return;
      view = next; paint(focus); settings.onViewChange?.(view);
    }
    host.onclick = event => {const tab=event.target.closest('[data-practice-view]');if(tab)select(tab.dataset.practiceView,true);};
    host.onkeydown = event => {
      const tab=event.target.closest('[data-practice-view]'); if(!tab)return;
      const keys=Object.keys(views), index=keys.indexOf(tab.dataset.practiceView);
      const next = {ArrowRight:keys[(index+1)%keys.length],ArrowLeft:keys[(index+keys.length-1)%keys.length],Home:keys[0],End:keys[keys.length-1]}[event.key];
      if(next){event.preventDefault();select(next,true);}
    };
    paint();
    return {select, update(next, extra = {}) {data=next;settings={...settings,loading:false,error:null,...extra};paint();}, destroy(){host.onclick=null;host.onkeydown=null;}};
  }
  return {project, render, mount, views: Object.freeze({...views})};
});
