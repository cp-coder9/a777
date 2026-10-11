# Material 3 Expressive — menu and module implementation PRD

This is a source-inspected implementation contract, not a completed rollout. Every live/visual/security journey remains pending; local service execution is blocked by the reported 503.

## Universal operating rules

1. Sign in; confirm active organisation, effective membership, saved profile and selected shared project. Profile and demo lens are presentation/recommendation only, never privilege grants.
2. Inspect actual loaded records, source references, revision/version, scope and accountable reviewer. Empty, not loaded, denied and unavailable are different states.
3. Use only controls actually mounted by runtime. Original scaffold gaps describe the original prototype; enhanced modules can supersede those gaps. An API existing does not establish a dashboard action.
4. For a write, supply required fields and current version, review confirmation, save once, then reload the exact record. A toast or learning checkpoint is not a persistence receipt.
5. A denial requires permission/appointment review, not switching the presentation lens. On stale version reload and reconcile changes; on missing source/provider keep the result unavailable. On network/503 retain unsaved inputs safely and do not mark complete or blindly replay writes.
6. Payments are internal certificate/release/provider-paid metadata, not funds custody or settlement. Municipal readiness is not external lodgement or approval. CPD records and training completion are not accreditation.

Membership: owner = organisation.manage, members.manage, projects.read, projects.write, tools.use, verification.review; admin lacks organisation.manage; member = projects.read, projects.write, tools.use; viewer = projects.read only. Unknown memberships fail closed. Source operations independently enforce tenant, appointment, professional evidence and human gates.

## Source-grounded menu operating matrix

The 30 `menuOccurrences` are literal calls only, not an exhaustive menu. `generatedMenuAcceptance` contains **668 renderer-aware candidate rows** from isolated execution of the trusted reference-001 → reference-002 → reference-003 → reference-004 assembly: **144 / 134 / 135 / 255** respectively. Executed surfaces are project datum (**183** rows), project tool groups (**209**), standalone tool groups (**230**) and global menu (**46**). Superseded declarations inside one script are not separately executed or counted; library/registry pages are not part of this execution matrix. `generatedMenuTemplates` retains source map/onclick templates, including ancillary controls, without claiming they were executed.

`rendererContexts` records **840** role/stage/version contexts: 168 each for the base datum, `datumV6` and `mtgDatumCard`, and 336 for `v8DatumCard` (ordinary and God Mode). Card keys come from actual datum-card article click handlers, not labels or stage-name text matches. Tests independently derive stage maps, role priorities and common-tool inclusion from the reference sources, including the 10-card ordinary and 12-card God Mode limits. Observed maxima are 9 cards for base/v6/v7 and 11 for v8; limits are ceilings, not promised counts. Timesheets and engineering tabs are not project-stage selectors. All four reference sources have observed byte hashes in coverage.json; reference-002/003/004 additionally have fixed SHA-256 assertions in coverage.test.cjs, including reference-003 meeting datum rows. The v8 ordinary selector includes only stage-relevant role-access and common tools, not every stage tool; God Mode uses the stage list with its 12-card ceiling.

Earlier renderer versions are provenance, not simultaneous visible menus. Candidate workspace rows retain actual role/stage contexts; role prioritisation differs across renderer versions and God Mode presentation is not permission. All **210** shared role-guide links, **51** public landing controls and **8** generated canvas actions remain accounted for. Verify the final runtime renderer and scoped catalogue before asserting live reachability; isolated execution is not browser, persistence or workflow proof.

| Menu | Actual user inputs and controls | Supported operating path and limitation | Source |
|---|---|---|---|
| command | Open project datum; workspace shortcut cards; selected authorised project | Inspect scoped command cards, then open their actual source workspace. No aggregate metric or shortcut is a save/approval receipt. | reference-001.js renderGlobalPage; modules/shell-data.js:103–105 |
| projects | Project selector; Project/Standalone switch; stage choices; datum workspace cards | Choose an authorised project; selector uses context.selectProject, rolls back failed selection. Choose stage to change placement only, then a tool. Progress and fixture card metrics are replaced by Not loaded until backed by source. | modules/shell-data.js:20–42,54,92–101; reference-002/004 renderProjectSpace |
| tools | Standalone switch; tool groups/cards; registry entries; attach-project controls inside tools | Open a permitted catalogue tool; use its module guide below. Standalone does not waive a runtime project requirement. Empty catalogue and unavailable discovery differ. | reference-001.js:100–124,436,471; runtime.js discovery |
| inbox | Collaboration workspace cards; source-module links | Follow real inbox/action source; do not approve through a badge. Global collaboration badges are Not loaded, not 7 real decisions. Operate inbox_action only when its mounted controls load records. | modules/shell-data.js:59,107; inbox_action guide |
| documents | Global document destination; project/source workspace links | Global scope does not load document records. Select a project and open documents_drawings to use title/type/discipline/revision fields and separate issue gate. | modules/shell-data.js:108; documents_drawings guide |
| finance | Global finance destination; project commercial workspace links | Global scope does not load payment records. Select a project and use contract_admin or payments_escrow; release/provider controls remain unavailable without actual configuration. | modules/shell-data.js:108; modules/commercial.js:9–14 |
| knowledge | Knowledge/CPD destination and workspace links | Navigate to cpd_learning to inspect supported learning records; no public concept catalogue card is an accredited course or saved CPD receipt. | reference-001.js globalItems; cpd_learning guide |
| cpd | CPD route alias, returned registry destination | Route placement is not a separate mounted form. Follow cpd_learning; inspect course/activity/source and verify any saved record independently. | route-placement.json; cpd_learning guide |
| feedback | Feedback destination; feedback entry controls | Follow the mounted feedback module, supply actual issue/evidence/context, and verify saved feedback. The global Loop badge is illustrative, not submission status. | reference-001.js globalItems; feedback guide |
| dedicated | Dedicated-tools route alias | Open returned workspace-tools catalogue and a supported tool. No independent dedicated editor is established by this alias. | route-placement.json; runtime.js discovery |
| completeness | Admin destination alias | Follow admin_review and inspect actual review evidence. Membership verification.review is required; a profile label or God Mode is not authorisation. | route-placement.json; ProductionToolRegistry.php; admin_review guide |
| settings | Settings destination/administration links | Inspect returned admin-settings surface; do not invent a global settings save form. Changes must use a separately mounted, permitted source operation. | reference-001.js renderGlobalPage; route-placement.json |

All menu paths: confirm scope after navigation; observe no-project, unavailable and Not loaded truthfully; retain unsaved inputs on recoverable failure; after an actual write reopen its exact target. No live validation is claimed.

## Actual public landing preview3

**Pinned entry:** app/public/app.php:24 embeds `/preview3.html` in `#publicLandingFrame`. This is distinct from the v1-clone dashboard. `coverage.json.publicLanding.controls` enumerates static controls individually with exact line/attributes; `generatedDatumActions` expands all eight canvas CTA states. Inspect the parent public-access bridge separately because it may intercept authentication/start actions; the embedded concept alone does not authenticate or save.

| Surface/control family | Inputs | Actual output / error / operating journey | Source preview3.html |
|---|---|---|---|
| Skip, brand, section navigation and public-space cards | Home/Marketplace/Professionals/Knowledge/Tools target | Scroll to section or anchor; no directory query or project write. Professionals navigation targets Marketplace. | 857–876;1191 |
| About, Sign in, mobile menu, Explore all, Browse all topics | Activate button, then Close or Continue | Information dialog; Continue closes and displays prototype-workflow toast, not authentication. Keyboard focus/dialog dismissal must be checked live. | 1193–1213 |
| Architect, Engineer, Contractor, Quotes, Specialists, Professionals directory | Choose service card | Local explanatory modal with proposed workflow bullets. Open workflow only closes/toasts; no real filters, profiles, quote requests or appointment. | 1215–1252 |
| Knowledge topic cards | Choose one of the ten topics | Local topic explanation and planned authoritative answer-page text. View answer pages does not fetch a published answer. | 1047–1076;1254–1258 |
| Knowledge search | knowledgeQuery text; Search | AND-match every whitespace-separated word against local card text/keywords; blank shows all. Zero match gives No exact topic matched toast, not AI search. Clear query and search again to restore cards. | 1358–1371 |
| Start a Project (all repeated buttons) | Required projectType (New house, Alteration or extension, Second dwelling or granny flat, Commercial project, As-built or retrospective approval, Other); required projectLocation; optional projectNeed | Open dialog, fill required fields, Generate preliminary path. Browser required-field validation is the only blank guard. Local output says no information submitted; optional need is not used in the path. Opening again rebuilds fields. Cancel/Close discards concept fields; no server/API/network-error contract exists here. | 1260–1301 |
| Public Wingman send and three prompt chips | Free-text question or second-dwelling/carport/cost prompt | Trim; blank silently returns. Append local question and deterministic keyword response after timeout. No AI/provider request, provenance or persisted conversation. Public Wingman shortcut scrolls and focuses input. | 1303–1355 |
| Datum canvas moving CTA | Pointer position selects one of eight node actions; click active CTA | Start a Project opens concept form; Marketplace/Knowledge scroll; Wingman/Professional focus; About/Sign Up/Sign In show information modal. Signup is not account creation. Test all eight labels, keyboard alternative, reduced motion and touch independently. | 1393–1440 |
| Dialog controls | Close, Cancel, Continue | Close without submission; recover by reopening. No supplied credential/password/card field belongs to this concept. Authentication is a separate parent surface. | 1124–1164;1209–1213;1289 |

**Acceptance journey:** anonymous entry → choose a section/service → search a topic (including blank/no-match) → open and cancel brief → reopen, supply required type/location and optional need → inspect explicitly unsaved preliminary path → send blank and meaningful Wingman question → exercise each of eight datum CTAs → verify parent authentication handoff separately. Expected prototype outputs are not execution evidence. Input interpolation into preliminary-path HTML also requires security review; do not claim it sanitises arbitrary location text.

## Mount association corrections

### Drawing navigator association

`drawing_compliance_navigator` has a legacy public-app entry in assets/js/shell.js:897 and standalone route detection at :1648; its native form/controller is :1626–1647. Inputs include building description, boundary-wall facts, drawing package/types/register, reference/evidence and site context. Run screen posts `/drawing-compliance/checklist`; Save governed snapshot posts `/drawing-compliance/reviews`; outputs include Part K/occupancy, missing drawing/evidence, source status and professional-review gate. The checklist is stateless; snapshot save requires exact-record readback. Neither gives approval or verified reliance on illustrative sources.

For **v1-clone**, runtime.js:283 installs modules/compliance.js, whose supported keys at :14–18 are only compliance_hub, contractor_compliance and insurance_register. Its drawing screen is under compliance_hub, not an association with drawing_compliance_navigator. Therefore this registry tool is **unsupported-in-v1-clone** until an explicit mount exists; do not use the generic openTool path as proof of native controls. Backend and legacy support are separate evidence.

### Valuations association

Legacy assets/js/shell.js:740 aliases TOOLS.valuations and :908 renders financeCommercialWorkspace for valuations/payments_escrow/contract_admin. The shared commercial workflow uses baseline/version, variation reference/title/amount/time impact, claim evidence/amount/reason, review/certificate and retention metadata. Outputs are internal claims/certificates and human-review status, never settlement.

For **v1-clone**, runtime.js:287 installs modules/commercial.js; its TOOLS at :14 explicitly contains only contract_admin and payments_escrow. Thus valuations is **unsupported-in-v1-clone**, not an independently mounted valuation editor. Use the contract_admin or payments_escrow source-backed controls where permitted, retaining certificate revision, cumulative claim basis and reviewer. A registry live label cannot bridge this missing association.

## Global menus and eight-stage navigation

- `command` → `command-centre`; family `orient`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `projects` → `projects`; family `orient`; stage `platform`; placement requires project: `True`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `tools` → `workspace-tools`; family `find`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `inbox` → `collaboration`; family `review`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `documents` → `projects`; family `find`; stage `design`; placement requires project: `True`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `knowledge` → `knowledge-cpd`; family `find`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `finance` → `projects`; family `review`; stage `pay`; placement requires project: `True`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `cpd` → `knowledge-cpd`; family `find`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `feedback` → `knowledge-cpd`; family `create`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `dedicated` → `workspace-tools`; family `find`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `completeness` → `admin-settings`; family `review`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.
- `settings` → `admin-settings`; family `administer`; stage `platform`; placement requires project: `False`. Preserve selected scope; show authentication, no-project, loading, empty, denied, error and unavailable independently. Test every actual menu occurrence in coverage.json.

Stages in exact order: Brief → Appoint → Design → Comply → Procure → Build → Pay → Close-out. Placement is navigation, not a lifecycle-transition permission.

<a id="practice"></a>

## practice — Practice & Project Management

**Placement:** command-centre / platform / family orient. **Open:** `openTool('practice', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** PracticeRoutes, MariaDB workspace and focused tests.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


Source `app/public/assets/v1-clone/js/modules/iconography.js`:

- L343: `async function apply(toolId) {`
- L348: `root.showToast?.('Icon registry unavailable: js/architex-icon-pack.js did not load.');`
- L349: `return { applied: false, reason: 'registry-unavailable' };`

Source `app/public/assets/v1-clone/js/modules/practice.js`:

- L12: `async function attach(){try{if(await context.setMode('project')===false)return;if(projectRequired())return notice('No authorised project is available.');root.openTool?.('practice','project');}catch(e){notice(e.message);}}`
- L19: `async function refresh(){const token=++generation,key=scopeKey();if(projectRequired()){records={tasks:[],pipelineItems:[],timesheets:[],resourceAllocations:[],programme:[],milestones:[],fees:[]};loaded=true;error='';inject();repaint();return false;}try{const value=await adapter.workspace();if(token!==generation||key!==scopeKey())return false;records=value;loaded=true;error='';inject();repaint();return true;}catch(e){if(token===generation&&key===scopeKey()){loaded=false;error=e.message;records={tasks:[],pipelineItems:[],timesheets:[],resourceAllocations:[]};inject();repaint();}return false;}}`
- L20: `async function save(operation){if(pending)return notice('A practice save is already pending.');pending=true;apply('practice');const submittedDraft=draft,key=scopeKey();try{const saved=await operation();if(key!==scopeKey())throw Error('Project changed; refresh the original project before retrying.');if(draft===submittedDraft){draft=null;drafts.delete(key);}await refresh();notice('Saved record verified by read-back.'+(loaded?'':' Workspace refresh failed; refresh before further changes.'));return saved;}catch(e){notice(e.message+' No verified success. Refresh before retrying an uncertain write.');return null;}finally{pending=false;apply('practice');}}`
- L23: `async function transition(i,status){const task=records.tasks[i];if(!task||!loaded)return notice('Refresh the task before changing it.');if(['completed','cancelled'].includes(task.status))return notice('This task is closed; reopening is not supported by the service.');const p=ask('transition:'+task.id,[['reason','Reason for changing task to '+status]]);if(!p||!root.confirm?.('Change this task to '+status+'?'))return;return save(()=>adapter.transitionTask(task.id,{status,reason:p.reason,humanConfirmed:true}));}`
- L24: `root.completeTask=(i,checked)=>checked?transition(i,'completed'):notice('Reopening tasks is unavailable.');`
- L28: `async function allocation(i,percent){const r=records.resourceAllocations[i];if(!r)return;const hours=r.capacityMinutes/60;const p={userId:r.userId,startsOn:r.startsOn,endsOn:r.endsOn,capacityHours:hours,allocatedHours:hours*Number(percent)/100,humanConfirmed:true};if(!root.confirm?.('Create an additional allocation record for '+p.allocatedHours+' hours? Existing allocations are retained.'))return;return save(()=>adapter.allocateResource(p));}`
- L29: `const unavailable=()=>notice('This control has no connected write contract. No change was saved.');`
- L41: `const name=id=>{const s=context.getState().session;return id===s?.userId?s.email:(id||'Unassigned');};`
- L60: `const billed=known(f.invoiced)&&known(fee)&&fee>0?Math.round(f.invoiced/fee*100)+'% billed':'Billing percentage unavailable';`
- L66: `'Planned margin':[known(fee)&&fee>0&&known(f.planned)?Math.round((fee-f.planned)/fee*100)+'%':'Not calculated',known(fee)&&known(f.planned)?money(fee-f.planned):'Fee and delivery budget required'],`
- L71: `'Current margin':['Not calculated','Complete delivery costing required'],`
- L72: `'Contribution margin':['Not calculated','Complete delivery costing required'],`
- L92: `const nav=one('#navigator .nav-sub');if(nav)nav.textContent='Project attachment required';`
- L100: `function inspect(){if(ui().tool!=='practice'||!projectRequired())return;all('#inspectBody .inspect-block').forEach(block=>{if(['Scope','Current scope'].includes(block.querySelector('h4')?.textContent)&&block.querySelector('p'))block.querySelector('p').textContent='Project attachment required. No project records are loaded.';});}`

Source `app/public/assets/v1-clone/js/adapters/practice.js`:

- L5: `const required=(v,label)=>{if(typeof v!=='string'||!v.trim())throw Error(label+' is required.');return v.trim();};`
- L8: `function scope(write=false){const s=context.getState();if(!s.ready||!s.session||!s.organisationId)throw Error('An authenticated project is required.');if(projectRequired())throw Error('Select an authorised project before using practice.');identity(s.selectedProjectId);if(write&&!['owner','admin','member'].includes(s.session.memberships?.find(m=>m.organisationId===s.organisationId)?.role))throw Error('Practice is read-only for this membership.');return s;}`
- L10: `async function workspace(){const result=await context.read(path());for(const k of ['tasks','pipelineItems','resourceAllocations','timesheets'])if(!Array.isArray(result?.[k]))throw Error('Invalid practice workspace: '+k);return result;}`
- L11: `async function write(route,payload,collection){const s=scope(true);return context.write(route,{...payload,projectId:s.selectedProjectId},{projectScoped:true,readBackPath:path(),readBackCollection:collection});}`
- L12: `const confirmed=p=>{if(p.humanConfirmed!==true)throw Error('Human confirmation is required.');};`
- L13: `async function createTask(p){scope(true);required(p.title,'Task title');identity(p.assignedUserId);required(p.dueAt,'Due date');return write('/projects/'+scope().selectedProjectId+'/practice/tasks',p,'tasks');}`
- L14: `async function transitionTask(id,p){scope(true);identity(id);confirmed(p);required(p.reason,'Transition reason');return write('/practice/tasks/'+id+'/transitions',p,'tasks');}`
- L15: `async function captureTimesheet(p){scope(true);required(p.workstage,'Work stage');required(p.workDate,'Work date');required(p.description,'Description');if(!Number.isInteger(p.minutes)||p.minutes<1||p.minutes>1440||typeof p.billable!=='boolean')throw Error('Valid minutes and billable choice are required.');return write('/projects/'+scope().selectedProjectId+'/practice/timesheets',p,'timesheets');}`
- L16: `async function allocateResource(p){scope(true);confirmed(p);identity(p.userId);required(p.startsOn,'Start date');required(p.endsOn,'End date');return write('/projects/'+scope().selectedProjectId+'/practice/resource-allocations',p,'resourceAllocations');}`
- L17: `async function createProgramme(p){scope(true);required(p.title,'Workstream title');required(p.ownerRole,'Owner role');if(!Number.isInteger(p.weeks)||p.weeks<1)throw Error('A positive number of weeks is required.');if(!Number.isInteger(p.progress)||p.progress<0||p.progress>100)throw Error('Progress must be 0-100.');return write('/projects/'+scope().selectedProjectId+'/practice/programme',p,'programme');}`
- L18: `async function updateProgramme(id,p){scope(true);identity(id);return write('/practice/programme/'+id,p,'programme');}`
- L19: `async function createMilestone(p){scope(true);required(p.title,'Milestone title');required(p.targetDate,'Target date (YYYY-MM-DD)');return write('/projects/'+scope().selectedProjectId+'/practice/milestones',p,'milestones');}`
- L20: `async function setMilestoneCompletion(id,completed){scope(true);identity(id);return write('/projects/'+scope().selectedProjectId+'/practice/milestones/'+id+'/completion',{completed:!!completed},'milestones');}`
- L21: `async function saveFee(p){scope(true);return write('/projects/'+scope().selectedProjectId+'/practice/fees',p,'fees');}`
- L22: `function viewModel(w){const s=context.getState(),name=id=>escape(id===s.session?.userId?s.session.email:id||'Unassigned');const fee=(w.fees||[])[0]||{};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="wingman"></a>

## wingman — Wingman AI Workspace

**Placement:** workspace-tools / platform / family create. **Open:** `openTool('wingman', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** WingmanRoutes, persisted conversations and server-owned model adapter.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/wingman.js`:

- L16: `previewRow.querySelector('.message-footer').textContent='Unsaved preview · human review required';`
- L91: `scope(state){const project=state.projects?.find(p=>p.id===state.selectedProjectId);const name=project?.name||'Standalone context';pills[0].textContent=state.ready?'Authenticated context':'Context unavailable';pills[2].textContent=name;projectItems[0].textContent=name;projectItems[1].textContent='Phase: '+(project?.phase||project?.currentPhase||project?.stage||'Not supplied');projectItems[2].textContent='Role: '+(state.session?.memberships?.find(m=>m.organisationId===state.organisationId)?.role||'Not supplied');},`
- L106: `async function refresh(){const ticket=revision;try{const data=await adapter.bootstrap();if(!valid(ticket))return;view.threads(data.conversations);view.availability(data.availability);view.notice(data.availability?.configured===false?'Wingman AI is unavailable: provider configuration required. Review saved conversations or keep a draft; no AI request has been sent.':'Authenticated Wingman ready. '+(data.availability?.configured===true?'Provider configured; a successful response is verified only after a request. ':'Provider configuration was not reported. ')+(data.withheldConversations?'Unverified conversation history is withheld. ':'')+'Only cited sources are evidence; all outputs require human review.');}catch(e){if(valid(ticket)){view.availability({configured:false});view.notice(e.message);}}}`
- L127: `else throw Error('This suggested destination is unavailable in this dashboard. Use the workspace navigation.');`
- L147: `return {supports:id=>id==='wingman',prepareSource(id,html){return id==='wingman'?html.replace(/<\/body>/i,'<script data-v1-wingman>('+frameBootstrap.toString()+')();<\/script></body>'):html;},mountFrame,`

Source `app/public/assets/v1-clone/js/adapters/wingman.js`:

- L19: `async function verify(saved, scope) {`
- L20: `const record = await context.read(conversationPath(saved.conversationId)); check(scope.key);`
- L30: `async function bootstrap() {`
- L32: `const result = await context.read('/api/v1/wingman/bootstrap?route=wingman' + (scope.projectId ? '&projectId=' + encodeURIComponent(scope.projectId) : ''));`
- L42: `async function send({prompt,capability = ''}) {`
- L45: `if (scope.data.availability?.configured === false) throw Error('configuration_required: Wingman AI is not configured on this server. Your draft has been kept.');`
- L59: `saved = await context.write('/api/v1/wingman/messages',{`
- L74: `async function load(id) {`
- L80: `const record = await context.read(conversationPath(id));check(scope.key);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="planning"></a>

## planning — Town Planning Tracker

**Placement:** workspace-tools / brief / family review. **Open:** `openTool('planning', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** TownPlanningRoutes, source evidence and MariaDB tests.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/compliance-tools.js`:

- L3: `const warning='Advisory working records only — not statutory approval, certification or municipal lodgement. Current official-source verification and appointed professional review remain required.';`
- L16: `const protectedText=/advisory|does not|must be reviewed|not constitute|professional.*required|formula|clause|table \d|XA \d|SANS 10400/i;`
- L28: `if(id==='planning'&&(/\d.*(?:Jul|Jun|May)|Sarah van|SACPLAN|No appeal lodged|Statutory basis:|Heritage consultant|Draft HIA|objection period|screening mandatory|pre-application meeting required/i.test(t)))el.textContent=note;`
- L42: `const supports=id=>['planning','municipal','xa'].includes(id);`
- L43: `function prepareSource(id,html){if(!supports(id))return html;const script='<script data-v1-compliance-bridge>('+initialise.toString()+')('+JSON.stringify(id)+');<\/script>';return html.includes('</body>')?html.replace('</body>',script+'</body>'):html+script;}`
- L45: `if(!supports(id))return false;`
- L54: `const s=context.getState(),project=s.projects?.find(p=>p.id===s.selectedProjectId),name=project?.name||project?.projectName||'Selected project';`
- L68: `const findings=d.querySelectorAll('#tab-overview .list-row');findings.forEach((row,i)=>{row.querySelector('.row-label').textContent=a?([...a.blockers,...a.professionalReviews][i]||'No stored blockers; professional review still required'):'No assessment loaded';});`
- L76: `async function refresh(){const token=++epoch;rows=[];selected=null;d.querySelectorAll('tbody').forEach(body=>{if(!body.querySelector('input,select'))body.replaceChildren();});text('.stat-value,.dept-score,#detail-title,#detail-header-title,#detail-header-meta','Not loaded');render();status('Loading project evidence.');try{const result=await adapter.list(id);if(token!==epoch)return;rows=result;render();if(id==='planning')status('Loaded '+rows.length+' stored applications.');}catch(e){if(token===epoch)status(e.message);}}`
- L77: `async function operate(operation,payload){if(busy)throw Error('Wait for the current operation to finish.');busy=true;status('Saving; awaiting server readback.');try{const result=await adapter.operate(id,operation,payload);await refresh();status('Server operation and readback completed.');return result;}catch(e){status(e.message);throw e;}finally{busy=false;}}`
- L78: `function ask(label){const value=w.prompt(label,'');if(value===null)throw Error('Cancelled. No record was changed.');if(!value.trim())throw Error('A value is required: '+label);return value.trim();}`
- L79: `function json(label){try{return JSON.parse(ask(label));}catch(e){throw Error('Valid JSON is required. '+e.message);}}`
- L80: `async function action(label){`
- L82: `if(id==='planning'&&/Submit Response/i.test(label)){if(!selected)throw Error('Select a stored application first.');return operate('entry',{applicationId:selected,entryType:'participation',title:ask('Response title'),status:'responded',required:false,basis:ask('Evidence/source basis'),dueAt:null,amountCents:null,payload:{response:ask('Response text')}});}`
- L83: `if(id==='municipal'&&/Run Validation|Re-Run/i.test(label)){const matrices=await context.read('/api/v1/municipal-requirement-versions');const items=matrices?.data??matrices;const matrixVersionId=ask('Matrix version ID. Available records: '+(Array.isArray(items)?items.map(m=>m.id+' '+m.municipality+' '+m.status).join('; '):'none'));const input={matrixVersionId,occupancyType:ask('occupancyType: single_residential, multi_residential, commercial, public_assembly, mixed_use'),estimatedConstructionValueZar:Number(ask('estimatedConstructionValueZar'))};for(const key of ['changesLoadBearing','changesDrainageOrStormwater','publicAccessOrAssembly','envelopeEnergyImpact','zoningKnown','coverageOrParkingRisk','boundaryOrServitudeUnclear','heritagePotential','environmentalSensitivity','trafficImpact','nbrAdvisoryReviewed','clientAuthorityConfirmed']){const value=ask(key+' (true or false)');if(!['true','false'].includes(value))throw Error('Enter true or false explicitly.');input[key]=value==='true';}input.availableSupportingDocuments=json('availableSupportingDocuments: JSON array of evidence identifiers (or [])');input.professionalSignoffs=json('professionalSignoffs: JSON array of actual recorded professional disciplines (or [])');return operate('assess',input);}`
- L93: `return {supports,prepareSource,mountFrame};`

Source `app/public/assets/v1-clone/js/modules/iconography.js`:

- L343: `async function apply(toolId) {`
- L348: `root.showToast?.('Icon registry unavailable: js/architex-icon-pack.js did not load.');`
- L349: `return { applied: false, reason: 'registry-unavailable' };`

Source `app/public/assets/v1-clone/js/adapters/compliance-tools.js`:

- L13: `async function list(id){const s=scope(), rows=unwrap(await context.read(path(id)));if(!Array.isArray(rows))throw Error('Invalid compliance records response.');if(scope().selectedProjectId!==s.selectedProjectId||scope().organisationId!==s.organisationId)throw Error('Project scope changed; reload records.');return scoped(id,rows,s);}`
- L16: `async function operate(id,operation,input={}){`
- L23: `payload={...pick(input,operation==='entry'?['entryType','title','status','required','basis','dueAt','amountCents','payload']:['humanConfirmed','professionalRole','reason','externalReference','toStage']),expectedVersion:app.version};`
- L24: `if(operation==='entry')Object.assign(verification,{readBackCollection:'entries',readBackParentId:app.id,retryPrecondition:'expectedVersion'});`
- L25: `if(operation!=='entry'&&payload.humanConfirmed!==true)throw Error('Explicit human confirmation is required; this does not assert authority acceptance.');`
- L29: `const row=(await list(id)).find(a=>a.id===input.assessmentId);if(!row)throw Error('Assessment does not belong to the selected project.');if(input.humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L30: `back='/api/v1/submission-readiness/'+row.id;target=back+'/approve';payload=pick(input,['humanConfirmed','reviewReason']);`
- L32: `if(!tools.includes(input.toolId))throw Error('XA calculator is not supported.');target='/api/v1/toolbox/runs';back=path(id);payload={toolId:input.toolId,input:input.input,assignment:{mode:'internal_project',projectId:s.selectedProjectId,notes:''},issueImmediately:false,issueReason:null};`
- L33: `}else throw Error(\`${id}: ${operation} is not supported by this embedded control. No record was changed.\`);`
- L35: `return context.write(target,payload,{...verification,readBackPath:back,projectScoped:true});`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="municipal"></a>

## municipal — Municipal Approval Readiness

**Placement:** workspace-tools / comply / family review. **Open:** `openTool('municipal', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** MunicipalReadinessRoutes, versioned matrices and evidence packs.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/calc-workspace.js`:

- L53: `<div class="rfi-hero"><div><h1>Engineering &amp; Discipline Calculators</h1><p>${Object.keys(CALCS).length} real backend engines — advisory results, professional verification required.</p></div></div>`
- L73: `async function run(id, c, panel) {`
- L88: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/modules/compliance-tools.js`:

- L3: `const warning='Advisory working records only — not statutory approval, certification or municipal lodgement. Current official-source verification and appointed professional review remain required.';`
- L16: `const protectedText=/advisory|does not|must be reviewed|not constitute|professional.*required|formula|clause|table \d|XA \d|SANS 10400/i;`
- L28: `if(id==='planning'&&(/\d.*(?:Jul|Jun|May)|Sarah van|SACPLAN|No appeal lodged|Statutory basis:|Heritage consultant|Draft HIA|objection period|screening mandatory|pre-application meeting required/i.test(t)))el.textContent=note;`
- L42: `const supports=id=>['planning','municipal','xa'].includes(id);`
- L43: `function prepareSource(id,html){if(!supports(id))return html;const script='<script data-v1-compliance-bridge>('+initialise.toString()+')('+JSON.stringify(id)+');<\/script>';return html.includes('</body>')?html.replace('</body>',script+'</body>'):html+script;}`
- L45: `if(!supports(id))return false;`
- L54: `const s=context.getState(),project=s.projects?.find(p=>p.id===s.selectedProjectId),name=project?.name||project?.projectName||'Selected project';`
- L68: `const findings=d.querySelectorAll('#tab-overview .list-row');findings.forEach((row,i)=>{row.querySelector('.row-label').textContent=a?([...a.blockers,...a.professionalReviews][i]||'No stored blockers; professional review still required'):'No assessment loaded';});`
- L76: `async function refresh(){const token=++epoch;rows=[];selected=null;d.querySelectorAll('tbody').forEach(body=>{if(!body.querySelector('input,select'))body.replaceChildren();});text('.stat-value,.dept-score,#detail-title,#detail-header-title,#detail-header-meta','Not loaded');render();status('Loading project evidence.');try{const result=await adapter.list(id);if(token!==epoch)return;rows=result;render();if(id==='planning')status('Loaded '+rows.length+' stored applications.');}catch(e){if(token===epoch)status(e.message);}}`
- L77: `async function operate(operation,payload){if(busy)throw Error('Wait for the current operation to finish.');busy=true;status('Saving; awaiting server readback.');try{const result=await adapter.operate(id,operation,payload);await refresh();status('Server operation and readback completed.');return result;}catch(e){status(e.message);throw e;}finally{busy=false;}}`
- L78: `function ask(label){const value=w.prompt(label,'');if(value===null)throw Error('Cancelled. No record was changed.');if(!value.trim())throw Error('A value is required: '+label);return value.trim();}`
- L79: `function json(label){try{return JSON.parse(ask(label));}catch(e){throw Error('Valid JSON is required. '+e.message);}}`
- L80: `async function action(label){`
- L82: `if(id==='planning'&&/Submit Response/i.test(label)){if(!selected)throw Error('Select a stored application first.');return operate('entry',{applicationId:selected,entryType:'participation',title:ask('Response title'),status:'responded',required:false,basis:ask('Evidence/source basis'),dueAt:null,amountCents:null,payload:{response:ask('Response text')}});}`
- L83: `if(id==='municipal'&&/Run Validation|Re-Run/i.test(label)){const matrices=await context.read('/api/v1/municipal-requirement-versions');const items=matrices?.data??matrices;const matrixVersionId=ask('Matrix version ID. Available records: '+(Array.isArray(items)?items.map(m=>m.id+' '+m.municipality+' '+m.status).join('; '):'none'));const input={matrixVersionId,occupancyType:ask('occupancyType: single_residential, multi_residential, commercial, public_assembly, mixed_use'),estimatedConstructionValueZar:Number(ask('estimatedConstructionValueZar'))};for(const key of ['changesLoadBearing','changesDrainageOrStormwater','publicAccessOrAssembly','envelopeEnergyImpact','zoningKnown','coverageOrParkingRisk','boundaryOrServitudeUnclear','heritagePotential','environmentalSensitivity','trafficImpact','nbrAdvisoryReviewed','clientAuthorityConfirmed']){const value=ask(key+' (true or false)');if(!['true','false'].includes(value))throw Error('Enter true or false explicitly.');input[key]=value==='true';}input.availableSupportingDocuments=json('availableSupportingDocuments: JSON array of evidence identifiers (or [])');input.professionalSignoffs=json('professionalSignoffs: JSON array of actual recorded professional disciplines (or [])');return operate('assess',input);}`
- L93: `return {supports,prepareSource,mountFrame};`

Source `app/public/assets/v1-clone/js/adapters/compliance-tools.js`:

- L13: `async function list(id){const s=scope(), rows=unwrap(await context.read(path(id)));if(!Array.isArray(rows))throw Error('Invalid compliance records response.');if(scope().selectedProjectId!==s.selectedProjectId||scope().organisationId!==s.organisationId)throw Error('Project scope changed; reload records.');return scoped(id,rows,s);}`
- L16: `async function operate(id,operation,input={}){`
- L23: `payload={...pick(input,operation==='entry'?['entryType','title','status','required','basis','dueAt','amountCents','payload']:['humanConfirmed','professionalRole','reason','externalReference','toStage']),expectedVersion:app.version};`
- L24: `if(operation==='entry')Object.assign(verification,{readBackCollection:'entries',readBackParentId:app.id,retryPrecondition:'expectedVersion'});`
- L25: `if(operation!=='entry'&&payload.humanConfirmed!==true)throw Error('Explicit human confirmation is required; this does not assert authority acceptance.');`
- L29: `const row=(await list(id)).find(a=>a.id===input.assessmentId);if(!row)throw Error('Assessment does not belong to the selected project.');if(input.humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L30: `back='/api/v1/submission-readiness/'+row.id;target=back+'/approve';payload=pick(input,['humanConfirmed','reviewReason']);`
- L32: `if(!tools.includes(input.toolId))throw Error('XA calculator is not supported.');target='/api/v1/toolbox/runs';back=path(id);payload={toolId:input.toolId,input:input.input,assignment:{mode:'internal_project',projectId:s.selectedProjectId,notes:''},issueImmediately:false,issueReason:null};`
- L33: `}else throw Error(\`${id}: ${operation} is not supported by this embedded control. No record was changed.\`);`
- L35: `return context.write(target,payload,{...verification,readBackPath:back,projectScoped:true});`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="xa"></a>

## xa — SANS 10400-XA Energy

**Placement:** workspace-tools / comply / family review. **Open:** `openTool('xa', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** ComplianceRoutes, versioned sources and governed tool runs.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "tools.use"}; admin: {"read": true, "write": true, "review": true, "permission": "tools.use"}; member: {"read": true, "write": true, "review": false, "permission": "tools.use"}; viewer: {"read": false, "write": false, "review": false, "permission": "tools.use"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/calc-workspace.js`:

- L53: `<div class="rfi-hero"><div><h1>Engineering &amp; Discipline Calculators</h1><p>${Object.keys(CALCS).length} real backend engines — advisory results, professional verification required.</p></div></div>`
- L73: `async function run(id, c, panel) {`
- L88: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/modules/compliance-tools.js`:

- L3: `const warning='Advisory working records only — not statutory approval, certification or municipal lodgement. Current official-source verification and appointed professional review remain required.';`
- L16: `const protectedText=/advisory|does not|must be reviewed|not constitute|professional.*required|formula|clause|table \d|XA \d|SANS 10400/i;`
- L28: `if(id==='planning'&&(/\d.*(?:Jul|Jun|May)|Sarah van|SACPLAN|No appeal lodged|Statutory basis:|Heritage consultant|Draft HIA|objection period|screening mandatory|pre-application meeting required/i.test(t)))el.textContent=note;`
- L42: `const supports=id=>['planning','municipal','xa'].includes(id);`
- L43: `function prepareSource(id,html){if(!supports(id))return html;const script='<script data-v1-compliance-bridge>('+initialise.toString()+')('+JSON.stringify(id)+');<\/script>';return html.includes('</body>')?html.replace('</body>',script+'</body>'):html+script;}`
- L45: `if(!supports(id))return false;`
- L54: `const s=context.getState(),project=s.projects?.find(p=>p.id===s.selectedProjectId),name=project?.name||project?.projectName||'Selected project';`
- L68: `const findings=d.querySelectorAll('#tab-overview .list-row');findings.forEach((row,i)=>{row.querySelector('.row-label').textContent=a?([...a.blockers,...a.professionalReviews][i]||'No stored blockers; professional review still required'):'No assessment loaded';});`
- L76: `async function refresh(){const token=++epoch;rows=[];selected=null;d.querySelectorAll('tbody').forEach(body=>{if(!body.querySelector('input,select'))body.replaceChildren();});text('.stat-value,.dept-score,#detail-title,#detail-header-title,#detail-header-meta','Not loaded');render();status('Loading project evidence.');try{const result=await adapter.list(id);if(token!==epoch)return;rows=result;render();if(id==='planning')status('Loaded '+rows.length+' stored applications.');}catch(e){if(token===epoch)status(e.message);}}`
- L77: `async function operate(operation,payload){if(busy)throw Error('Wait for the current operation to finish.');busy=true;status('Saving; awaiting server readback.');try{const result=await adapter.operate(id,operation,payload);await refresh();status('Server operation and readback completed.');return result;}catch(e){status(e.message);throw e;}finally{busy=false;}}`
- L78: `function ask(label){const value=w.prompt(label,'');if(value===null)throw Error('Cancelled. No record was changed.');if(!value.trim())throw Error('A value is required: '+label);return value.trim();}`
- L79: `function json(label){try{return JSON.parse(ask(label));}catch(e){throw Error('Valid JSON is required. '+e.message);}}`
- L80: `async function action(label){`
- L82: `if(id==='planning'&&/Submit Response/i.test(label)){if(!selected)throw Error('Select a stored application first.');return operate('entry',{applicationId:selected,entryType:'participation',title:ask('Response title'),status:'responded',required:false,basis:ask('Evidence/source basis'),dueAt:null,amountCents:null,payload:{response:ask('Response text')}});}`
- L83: `if(id==='municipal'&&/Run Validation|Re-Run/i.test(label)){const matrices=await context.read('/api/v1/municipal-requirement-versions');const items=matrices?.data??matrices;const matrixVersionId=ask('Matrix version ID. Available records: '+(Array.isArray(items)?items.map(m=>m.id+' '+m.municipality+' '+m.status).join('; '):'none'));const input={matrixVersionId,occupancyType:ask('occupancyType: single_residential, multi_residential, commercial, public_assembly, mixed_use'),estimatedConstructionValueZar:Number(ask('estimatedConstructionValueZar'))};for(const key of ['changesLoadBearing','changesDrainageOrStormwater','publicAccessOrAssembly','envelopeEnergyImpact','zoningKnown','coverageOrParkingRisk','boundaryOrServitudeUnclear','heritagePotential','environmentalSensitivity','trafficImpact','nbrAdvisoryReviewed','clientAuthorityConfirmed']){const value=ask(key+' (true or false)');if(!['true','false'].includes(value))throw Error('Enter true or false explicitly.');input[key]=value==='true';}input.availableSupportingDocuments=json('availableSupportingDocuments: JSON array of evidence identifiers (or [])');input.professionalSignoffs=json('professionalSignoffs: JSON array of actual recorded professional disciplines (or [])');return operate('assess',input);}`
- L93: `return {supports,prepareSource,mountFrame};`

Source `app/public/assets/v1-clone/js/adapters/compliance-tools.js`:

- L13: `async function list(id){const s=scope(), rows=unwrap(await context.read(path(id)));if(!Array.isArray(rows))throw Error('Invalid compliance records response.');if(scope().selectedProjectId!==s.selectedProjectId||scope().organisationId!==s.organisationId)throw Error('Project scope changed; reload records.');return scoped(id,rows,s);}`
- L16: `async function operate(id,operation,input={}){`
- L23: `payload={...pick(input,operation==='entry'?['entryType','title','status','required','basis','dueAt','amountCents','payload']:['humanConfirmed','professionalRole','reason','externalReference','toStage']),expectedVersion:app.version};`
- L24: `if(operation==='entry')Object.assign(verification,{readBackCollection:'entries',readBackParentId:app.id,retryPrecondition:'expectedVersion'});`
- L25: `if(operation!=='entry'&&payload.humanConfirmed!==true)throw Error('Explicit human confirmation is required; this does not assert authority acceptance.');`
- L29: `const row=(await list(id)).find(a=>a.id===input.assessmentId);if(!row)throw Error('Assessment does not belong to the selected project.');if(input.humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L30: `back='/api/v1/submission-readiness/'+row.id;target=back+'/approve';payload=pick(input,['humanConfirmed','reviewReason']);`
- L32: `if(!tools.includes(input.toolId))throw Error('XA calculator is not supported.');target='/api/v1/toolbox/runs';back=path(id);payload={toolId:input.toolId,input:input.input,assignment:{mode:'internal_project',projectId:s.selectedProjectId,notes:''},issueImmediately:false,issueReason:null};`
- L33: `}else throw Error(\`${id}: ${operation} is not supported by this embedded control. No record was changed.\`);`
- L35: `return context.write(target,payload,{...verification,readBackPath:back,projectScoped:true});`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="forms"></a>

## forms — Integrated Form System

**Placement:** workspace-tools / design / family create. **Open:** `openTool('forms', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** FormRoutes, templates, project instances, exports and audit records.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/document-tools.js`:

- L13: `const unavailable=()=>notice('Unavailable: no configured service for this action. Nothing was saved, signed, sent or exported.');`
- L20: `text('.collab-avatar','—');text('.collab-text','Live collaboration unavailable');`
- L36: `const controls=qa('.field-input');controls.forEach((e,i)=>{e.value='';e.removeAttribute('value');e.readOnly=false;e.dataset.fieldKey='';const field=template?.fields?.[i];const label=e.closest('.field-group')?.querySelector('.field-label');if(field){e.dataset.fieldKey=field.key;if(label)label.textContent=field.label;e.required=field.required===true;e.value=values?.[field.key]??draft?.values?.[field.key]??'';e.title='Verified template field: '+field.key;}else{e.title='Original control retained; no corresponding field in selected template. Not submitted.';}});`
- L39: `qa('.two-col > div:last-child .panel > div').forEach(e=>{if(!e.querySelector('button')){e.textContent='Not verified — signature and live auto-fill providers unavailable';}});`
- L41: `qa('button').forEach(b=>{if(/Save Draft/.test(b.textContent)){b.removeAttribute('onclick');b.onclick=()=>{const values={};controls.forEach(e=>{if(e.dataset.fieldKey)values[e.dataset.fieldKey]=e.value;});run('saveForm',{draft,template,values});};}else if(/Export PDF|Preview|Municipal/.test(b.textContent))blocked(b,'PDF, preview integration and municipal submission are unavailable. Issued forms expose HTML/JSON records, not PDF.');});`
- L46: `text('.sig-pad','Digital signature unavailable');qa('.sig-pad').forEach(e=>e.classList.remove('signed'));`
- L72: `w.showToast=unavailable;`
- L75: `['submitNewProject','switchProject','handleProjectChange','changeRole','handleSmartSearch','showUrlAnalysis','useAiResult','useUrlResult','simulateAiScan','simulateAiScanInline','addAllDrawingSuggestions','addAllDrawingsSuggestions','movePipelineItem','createIssueSnapshot','executeIssue'].forEach(n=>w[n]=unavailable);`
- L76: `w.openNewProjectModal=unavailable;`
- L88: `qa('button').forEach(e=>{if(!e.closest('nav,.tool-nav')&&!e.getAttribute('onclick')?.includes('switchView'))blocked(e,'QS certification, tender generation and export routes are unavailable. Use the advisory BoQ form to save explicit line items.');});`
- L89: `text('.ai-body','BoQ takeoffs are advisory Toolbox records. Enter measured quantities and rates explicitly. QS certification, tender generation and exports are unavailable.');`
- L92: `const dz=q('#dropZone');if(dz){dz.addEventListener('click',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);dz.addEventListener('drop',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);}`
- L94: `w.selectProject=unavailable;clean();`
- L97: `<form data-boq-form><label>Title <input class="form-input" name="title" required></label>`
- L98: `<label>Currency <input class="form-input" name="currency" required></label>`
- L99: `<label>Contingency % <input class="form-input" name="contingencyPercent" type="number" min="0" max="100" step="any" required></label>`
- L105: `function addLine(){const row=d.createElement('fieldset');row.dataset.boqLine='';row.innerHTML=\`<legend>Measured line</legend><label>Description <input class="form-input" name="description" required></label><label>Quantity <input class="form-input" name="quantity" type="number" min="0.00000001" step="any" required></label><label>Unit <input class="form-input" name="unit" required></label><label>Rate <input class="form-input" name="rate" type="number" min="0" step="any" required></label><button class="btn btn-secondary" type="button">Remove line</button>\`;row.querySelector('button').onclick=()=>row.remove();lines.append(row);}`
- L108: `const field=name=>form.elements.namedItem(name).value;`
- L115: `for(const record of data.boq||[]){const item=d.createElement('article');item.className='panel';item.dataset.recordId=record.id;const heading=d.createElement('h4');heading.textContent=(record.input?.title||record.id)+' — '+(record.status||'Status unavailable');item.append(heading);`
- L124: `const ws=next.workspace||{};Object.assign(SAMPLE_WORKSPACE,{id:ws.id||'',projectId:ws.projectId||'',projectName:next.projectName||'',municipality:next.municipality||'',stage:next.stage||'',revision:ws.revisionCode||'',issueStatus:ws.status||'unavailable',team:[],sections:ws.sections||[],items:(ws.items||[]).map(i=>({...i,budgetAllowance:i.budgetAllowanceCents/100,estimatedCost:i.estimatedCostCents/100,clientDecision:i.requiresClientDecision,drawingRefs:i.drawingReferences||[],clauseRefs:i.clauseReferences||[],comments:[],image:'',sourceRevision:ws.revisionCode,supersededBy:null}))});currentRole=next.role||'';w.renderAll();`
- L149: `function supports(id){return ids.includes(id);}`
- L150: `function prepareSource(id,html){if(!supports(id))return html;`
- L154: `const blank={id:'',projectId:'',projectName:'',municipality:'',stage:'',profile:'',revision:'',issueStatus:'unavailable',team:[],sections:[],items:[]};`
- L161: `async function mountFrame(id,frame){if(!supports(id))return false;const win=frame.contentWindow;if(!win?.__v1DocumentFrame)throw Error('Document frame was not prepared before srcdoc.');const previous=frames.get(frame);previous?.dispose?.();let generation=0,selected='',current=null,disposed=false;const key=()=>{const s=context.getState();return [s.session?.userId,s.organisationId,s.selectedProjectId,!!s.session].join('|');};let formScope=key();`
- L164: `async function refresh(){const ticket=++generation,scopeKey=key();if(!active())return;if(id==='forms'&&formScope!==scopeKey){formScope=scopeKey;win.__v1DocumentFrame.restoreForm(formDrafts.get(formScope));}current=null;win.__v1DocumentFrame.apply({templates:[],drafts:[],workspaces:[],error:'Loading authenticated project records…'});try{const result=await adapter.load(id);if(!active()||ticket!==generation||scopeKey!==key())return;const s=context.getState(),p=s.projects?.find(p=>p.id===s.selectedProjectId)||{};current=result;const ws=result.workspaces?.find(w=>w.id===selected)||result.workspaces?.[0];selected=ws?.id||'';current.workspace=ws;win.__v1DocumentFrame.apply((id==='specforge'?root.V1CloneDocumentToolsAdapter.safe:x=>x)({...result,workspace:ws,projectName:p.name||p.projectName||'',municipality:p.municipality||'',stage:p.currentPhase||'',role:s.projectRole||s.role||'',readOnly:s.readOnly===true,error:result.available===false?result.reason:null}));}catch(e){if(ticket===generation&&active()&&scopeKey===key())win.__v1DocumentFrame.apply({error:e.message,templates:[],drafts:[],workspaces:[]});}}`
- L165: `win.__v1DocumentCommand=async(action,payload)=>{if(!active())throw Error('Document frame is no longer active.');if(action==='selectWorkspace'){selected=payload.id;await refresh();return;}if(!current)throw Error('Load current project records first.');const ws=current.workspace;if(action==='exportBoq'){if(id!=='bom')throw Error('Unavailable advisory artifact action.');const record=current.boq?.find(row=>row.id===payload.id);if(!record)throw Error('Select a saved BoQ in this project.');const scopeKey=key(),ticket=generation,artifact=await adapter.downloadBoq(record,payload.format);if(!active()||scopeKey!==key()||ticket!==generation)throw Error('Scope changed before the download.');const url=win.URL.createObjectURL(artifact.blob),link=win.document.createElement('a');link.href=url;link.download=artifact.filename;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Verified server '+payload.format.toUpperCase()+' artifact retrieved; download requested. Not certification, a tender or PDF.');return artifact;}if(action==='saveBoq'){if(id!=='bom')throw Error('Unavailable BoQ action.');const scopeKey=key(),ticket=generation;await adapter.saveBoq(payload);if(!active()||scopeKey!==key()||ticket!==generation)return;await refresh();win.__v1DocumentFrame.notice('Advisory BoQ saved and read back. Not certified, issued, tendered or exported.');return;}if(action==='transitionForm'||action==='exportForm'){const draft=current.drafts?.find(d=>d.id===payload.id);if(!draft)throw Error('Select a saved draft in My Drafts first.');if(action==='exportForm'){const artifact=await adapter.exportArtifact(draft,payload.format);const blob=new win.Blob([artifact.content],{type:artifact.format==='html'?'text/html;charset=utf-8':'application/json'});const url=win.URL.createObjectURL(blob),link=win.document.createElement('a');link.href=url;link.download='form-'+draft.id+'.'+artifact.format;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Server '+artifact.format.toUpperCase()+' artifact retrieved; download requested. No PDF or digital signature claimed.');return;}const transition={draft:'submit-review',review_required:'approve',approved:'issue'}[draft.status];if(!transition)throw Error('This form has no pending review transition.');const reason=win.prompt('Reason to '+transition+' the exact saved form');if(!reason?.trim()||!win.confirm('Confirm '+transition+' of '+draft.title+' revision '+draft.revision+'?'))return;const saved=await adapter.formTransition(draft,transition,{humanConfirmed:true,reason});await refresh();win.__v1DocumentFrame.selectDraft(saved);win.__v1DocumentFrame.notice('Server form state: '+saved.status+' — verified by readback.');return;}if(action==='saveForm'){const selectedTemplate=current.templates?.find(t=>t.id===payload.template?.id);const selectedDraft=payload.draft?current.drafts?.find(d=>d.id===payload.draft.id):null;if(!selectedTemplate||payload.draft&&!selectedDraft)throw Error('Select a verified template and current draft.');const reason=selectedDraft?win.prompt('Reason for creating a new revision (required)'):null;if(selectedDraft&&!reason?.trim())return;const submitted={template:selectedTemplate,draft:selectedDraft||undefined,values:{...payload.values}},scopeKey=key(),ticket=generation;const saved=await adapter.saveForm({...submitted,reason});const retained=formDrafts.get(scopeKey);if(retained&&retained.template?.id===submitted.template.id&&retained.draft?.id===submitted.draft?.id)formDrafts.set(scopeKey,{...retained,draft:saved,template:saved.template});if(!active()||scopeKey!==key()||ticket!==generation)return;current.drafts=[...(current.drafts||[]).filter(d=>d.id!==saved.id),saved];win.__v1DocumentFrame.completeSave(submitted,saved);return;}if(['section','item','decision','issue'].includes(action)){`
- L166: `let p=payload;if(action==='decision'||action==='issue'){const reason=win.prompt('Reason for '+action+' (required)');if(!reason?.trim())return;const role=win.prompt('Your assigned project discipline (server verifies authority)');if(!role?.trim()||!win.confirm('Confirm '+action+' of the exact current server record?'))return;p=action==='issue'?{humanConfirmed:true,issueReason:reason,professionalRole:role}:{action:payload.action,humanConfirmed:true,reason,actingRole:role};}`
- L171: `return {supports,prepareSource,mountFrame,dispose(){frames.forEach(x=>x.dispose());frames.clear();formDrafts.clear();}};`

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


Source `app/public/assets/v1-clone/js/modules/iconography.js`:

- L343: `async function apply(toolId) {`
- L348: `root.showToast?.('Icon registry unavailable: js/architex-icon-pack.js did not load.');`
- L349: `return { applied: false, reason: 'registry-unavailable' };`

Source `app/public/assets/v1-clone/js/adapters/document-tools.js`:

- L9: `async function load(id){const s=scope(),p=s.selectedProjectId;if(id==='bom'){const runs=rows(await context.read('/toolbox/runs?limit=100'));return {available:true,boq:runs.filter(r=>r.toolId==='boq_takeoff'&&r.assignment?.projectId===p),boundaries:{qsCertification:false,tenderGeneration:false,exports:['json','csv','html','pdf'],reason:'BoQ takeoff runs and checksum-verified JSON, CSV, HTML and PDF artifacts persist in Toolbox. QS certification and tender generation are unavailable here.'}};}if(id==='specforge')return {workspaces:rows(await context.read('/projects/'+p+'/specforge-workspaces')).filter(w=>w.projectId===p)};if(id==='forms'){const [t,d]=await Promise.all([context.read('/form-templates'),context.read('/form-drafts?projectId='+p)]);return {templates:rows(t),drafts:rows(d).filter(x=>x.projectId===p)};}throw Error('Unsupported document tool.');}`
- L10: `async function saveBoq({title,currency,contingencyPercent,items}){`
- L14: `if(!title?.trim()||!currency?.trim()||!Number.isFinite(contingency)||contingency<0||contingency>100)throw Error('Title, currency and valid contingency are required.');`
- L16: `const lines=items.map(i=>{const quantity=number(i.quantity),rate=number(i.rate);if(!i.description?.trim()||!i.unit?.trim()||!Number.isFinite(quantity)||quantity<=0||!Number.isFinite(rate)||rate<0)throw Error('Invalid BoQ line: description, unit, positive quantity and non-negative rate are required.');return {description:i.description.trim(),unit:i.unit.trim(),quantity,rate};});`
- L20: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100',projectScoped:true}));`
- L21: `const history=rows(await context.read('/toolbox/runs?limit=100')),now=scope();`
- L27: `async function downloadBoq(run,format){`
- L32: `const list=rows(await context.read('/toolbox/runs/'+run.id+'/exports'));`
- L39: `function identifier(id){if(!/^[a-f0-9]{32}$/i.test(id||''))throw Error('A real record identifier is required.');return id;}`
- L42: `async function persist(path,payload,back,verification={}){if(writing)throw Error('A write is already pending.');const s=scope(),key=s.selectedProjectId+'|'+s.organisationId;writing=true;try{await context.write(path,payload,{...verification,readBackPath:back,projectScoped:true});const result=unwrap(await context.read(back));const n=scope();if(key!==n.selectedProjectId+'|'+n.organisationId)throw Error('Project changed; reload to verify the write.');return result;}finally{writing=false;}}`
- L43: `async function specWrite(ws,action,payload,itemId){record(ws);if(ws.status!=='draft'||!Number.isInteger(ws.version)||ws.version<1)throw Error('A current draft version is required.');if(!['sections','items','decisions','issue'].includes(action))throw Error('Unavailable specification operation.');if(['decisions','issue'].includes(action)){if(payload.humanConfirmed!==true)throw Error('Human confirmation is required.');const reason=action==='issue'?payload.issueReason:payload.reason;const role=action==='issue'?payload.professionalRole:payload.actingRole;if(!reason?.trim()||!role?.trim())throw Error('A reason and verified acting role are required.');}if(action==='issue'&&(!ws.items?.length||ws.items.some(i=>i.status!=='approved')))throw Error('Issue blocked: all items must be approved.');if(action==='decisions'&&!ws.items.some(i=>i.id===itemId))throw Error('Item is not in this workspace.');const back='/specforge-workspaces/'+ws.id;const path=action==='decisions'?'/specforge-items/'+identifier(itemId)+'/decisions':back+'/'+action;const verification={readBackParentId:ws.id};if(action!=='issue')verification.readBackCollection=action==='sections'?'sections':'items';const result=await persist(path,{...payload,expectedVersion:ws.version},back,verification);if(result?.id!==ws.id||result.projectId!==ws.projectId||result.version<=ws.version)throw Error('Write readback could not verify the new workspace version.');return result;}`
- L44: `async function saveForm({draft,template,values,reason}){const s=scope(),projectId=s.selectedProjectId,organisationId=s.organisationId;if(draft){record(draft);if(!reason?.trim())throw Error('A revision reason is required.');}identifier(template?.id);if(writing)throw Error('A write is already pending.');writing=true;try{const path=draft?'/form-drafts/'+draft.id+'/revisions':'/form-drafts';const payload=draft?{values,reason}:{projectId,templateId:template.id,title:template.name,values};const result=unwrap(await context.write(path,payload,{readBackPath:'/form-drafts?projectId='+projectId,projectScoped:true}));identifier(result?.id);const verified=unwrap(await context.read('/form-drafts/'+result.id));const n=scope();if(n.selectedProjectId!==projectId||n.organisationId!==organisationId||verified?.id!==result.id||verified?.projectId!==projectId)throw Error('Form readback failed or project changed.');return verified;}finally{writing=false;}}`
- L45: `async function formTransition(draft,action,payload){record(draft);const from={'submit-review':'draft',approve:'review_required',issue:'approved'},to={'submit-review':'review_required',approve:'approved',issue:'issued'};if(!from[action]||draft.status!==from[action])throw Error('Form state does not allow this action.');if(!payload.reason?.trim()||(action!=='submit-review'&&payload.humanConfirmed!==true))throw Error('Explicit confirmation and reason required.');const back='/form-drafts/'+draft.id;const result=await persist(back+'/'+action,payload,back);if(result?.id!==draft.id||result.projectId!==draft.projectId||result.status!==to[action])throw Error('Form transition readback failed.');return result;}`
- L46: `async function exports(draft){record(draft);if(draft.status!=='issued')throw Error('Only issued forms have export artifacts.');return rows(await context.read('/form-drafts/'+draft.id+'/exports'));}`
- L47: `async function exportArtifact(draft,format){if(!['html','json'].includes(format))throw Error('PDF and digital signature providers are unavailable.');const list=await exports(draft),match=list.find(e=>e.format===format);identifier(match?.id);const result=unwrap(await context.read('/form-exports/'+match.id));record(draft);if(result?.id!==match.id||result.formDraftId!==draft.id||result.format!==format||typeof result.content!=='string')throw Error('Export artifact readback failed.');return result;}`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="specforge"></a>

## specforge — SpecForge

**Placement:** workspace-tools / design / family create. **Open:** `openTool('specforge', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** SpecForgeRoutes, MariaDB records and issue workflow.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/document-tools.js`:

- L13: `const unavailable=()=>notice('Unavailable: no configured service for this action. Nothing was saved, signed, sent or exported.');`
- L20: `text('.collab-avatar','—');text('.collab-text','Live collaboration unavailable');`
- L36: `const controls=qa('.field-input');controls.forEach((e,i)=>{e.value='';e.removeAttribute('value');e.readOnly=false;e.dataset.fieldKey='';const field=template?.fields?.[i];const label=e.closest('.field-group')?.querySelector('.field-label');if(field){e.dataset.fieldKey=field.key;if(label)label.textContent=field.label;e.required=field.required===true;e.value=values?.[field.key]??draft?.values?.[field.key]??'';e.title='Verified template field: '+field.key;}else{e.title='Original control retained; no corresponding field in selected template. Not submitted.';}});`
- L39: `qa('.two-col > div:last-child .panel > div').forEach(e=>{if(!e.querySelector('button')){e.textContent='Not verified — signature and live auto-fill providers unavailable';}});`
- L41: `qa('button').forEach(b=>{if(/Save Draft/.test(b.textContent)){b.removeAttribute('onclick');b.onclick=()=>{const values={};controls.forEach(e=>{if(e.dataset.fieldKey)values[e.dataset.fieldKey]=e.value;});run('saveForm',{draft,template,values});};}else if(/Export PDF|Preview|Municipal/.test(b.textContent))blocked(b,'PDF, preview integration and municipal submission are unavailable. Issued forms expose HTML/JSON records, not PDF.');});`
- L46: `text('.sig-pad','Digital signature unavailable');qa('.sig-pad').forEach(e=>e.classList.remove('signed'));`
- L72: `w.showToast=unavailable;`
- L75: `['submitNewProject','switchProject','handleProjectChange','changeRole','handleSmartSearch','showUrlAnalysis','useAiResult','useUrlResult','simulateAiScan','simulateAiScanInline','addAllDrawingSuggestions','addAllDrawingsSuggestions','movePipelineItem','createIssueSnapshot','executeIssue'].forEach(n=>w[n]=unavailable);`
- L76: `w.openNewProjectModal=unavailable;`
- L88: `qa('button').forEach(e=>{if(!e.closest('nav,.tool-nav')&&!e.getAttribute('onclick')?.includes('switchView'))blocked(e,'QS certification, tender generation and export routes are unavailable. Use the advisory BoQ form to save explicit line items.');});`
- L89: `text('.ai-body','BoQ takeoffs are advisory Toolbox records. Enter measured quantities and rates explicitly. QS certification, tender generation and exports are unavailable.');`
- L92: `const dz=q('#dropZone');if(dz){dz.addEventListener('click',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);dz.addEventListener('drop',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);}`
- L94: `w.selectProject=unavailable;clean();`
- L97: `<form data-boq-form><label>Title <input class="form-input" name="title" required></label>`
- L98: `<label>Currency <input class="form-input" name="currency" required></label>`
- L99: `<label>Contingency % <input class="form-input" name="contingencyPercent" type="number" min="0" max="100" step="any" required></label>`
- L105: `function addLine(){const row=d.createElement('fieldset');row.dataset.boqLine='';row.innerHTML=\`<legend>Measured line</legend><label>Description <input class="form-input" name="description" required></label><label>Quantity <input class="form-input" name="quantity" type="number" min="0.00000001" step="any" required></label><label>Unit <input class="form-input" name="unit" required></label><label>Rate <input class="form-input" name="rate" type="number" min="0" step="any" required></label><button class="btn btn-secondary" type="button">Remove line</button>\`;row.querySelector('button').onclick=()=>row.remove();lines.append(row);}`
- L108: `const field=name=>form.elements.namedItem(name).value;`
- L115: `for(const record of data.boq||[]){const item=d.createElement('article');item.className='panel';item.dataset.recordId=record.id;const heading=d.createElement('h4');heading.textContent=(record.input?.title||record.id)+' — '+(record.status||'Status unavailable');item.append(heading);`
- L124: `const ws=next.workspace||{};Object.assign(SAMPLE_WORKSPACE,{id:ws.id||'',projectId:ws.projectId||'',projectName:next.projectName||'',municipality:next.municipality||'',stage:next.stage||'',revision:ws.revisionCode||'',issueStatus:ws.status||'unavailable',team:[],sections:ws.sections||[],items:(ws.items||[]).map(i=>({...i,budgetAllowance:i.budgetAllowanceCents/100,estimatedCost:i.estimatedCostCents/100,clientDecision:i.requiresClientDecision,drawingRefs:i.drawingReferences||[],clauseRefs:i.clauseReferences||[],comments:[],image:'',sourceRevision:ws.revisionCode,supersededBy:null}))});currentRole=next.role||'';w.renderAll();`
- L149: `function supports(id){return ids.includes(id);}`
- L150: `function prepareSource(id,html){if(!supports(id))return html;`
- L154: `const blank={id:'',projectId:'',projectName:'',municipality:'',stage:'',profile:'',revision:'',issueStatus:'unavailable',team:[],sections:[],items:[]};`
- L161: `async function mountFrame(id,frame){if(!supports(id))return false;const win=frame.contentWindow;if(!win?.__v1DocumentFrame)throw Error('Document frame was not prepared before srcdoc.');const previous=frames.get(frame);previous?.dispose?.();let generation=0,selected='',current=null,disposed=false;const key=()=>{const s=context.getState();return [s.session?.userId,s.organisationId,s.selectedProjectId,!!s.session].join('|');};let formScope=key();`
- L164: `async function refresh(){const ticket=++generation,scopeKey=key();if(!active())return;if(id==='forms'&&formScope!==scopeKey){formScope=scopeKey;win.__v1DocumentFrame.restoreForm(formDrafts.get(formScope));}current=null;win.__v1DocumentFrame.apply({templates:[],drafts:[],workspaces:[],error:'Loading authenticated project records…'});try{const result=await adapter.load(id);if(!active()||ticket!==generation||scopeKey!==key())return;const s=context.getState(),p=s.projects?.find(p=>p.id===s.selectedProjectId)||{};current=result;const ws=result.workspaces?.find(w=>w.id===selected)||result.workspaces?.[0];selected=ws?.id||'';current.workspace=ws;win.__v1DocumentFrame.apply((id==='specforge'?root.V1CloneDocumentToolsAdapter.safe:x=>x)({...result,workspace:ws,projectName:p.name||p.projectName||'',municipality:p.municipality||'',stage:p.currentPhase||'',role:s.projectRole||s.role||'',readOnly:s.readOnly===true,error:result.available===false?result.reason:null}));}catch(e){if(ticket===generation&&active()&&scopeKey===key())win.__v1DocumentFrame.apply({error:e.message,templates:[],drafts:[],workspaces:[]});}}`
- L165: `win.__v1DocumentCommand=async(action,payload)=>{if(!active())throw Error('Document frame is no longer active.');if(action==='selectWorkspace'){selected=payload.id;await refresh();return;}if(!current)throw Error('Load current project records first.');const ws=current.workspace;if(action==='exportBoq'){if(id!=='bom')throw Error('Unavailable advisory artifact action.');const record=current.boq?.find(row=>row.id===payload.id);if(!record)throw Error('Select a saved BoQ in this project.');const scopeKey=key(),ticket=generation,artifact=await adapter.downloadBoq(record,payload.format);if(!active()||scopeKey!==key()||ticket!==generation)throw Error('Scope changed before the download.');const url=win.URL.createObjectURL(artifact.blob),link=win.document.createElement('a');link.href=url;link.download=artifact.filename;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Verified server '+payload.format.toUpperCase()+' artifact retrieved; download requested. Not certification, a tender or PDF.');return artifact;}if(action==='saveBoq'){if(id!=='bom')throw Error('Unavailable BoQ action.');const scopeKey=key(),ticket=generation;await adapter.saveBoq(payload);if(!active()||scopeKey!==key()||ticket!==generation)return;await refresh();win.__v1DocumentFrame.notice('Advisory BoQ saved and read back. Not certified, issued, tendered or exported.');return;}if(action==='transitionForm'||action==='exportForm'){const draft=current.drafts?.find(d=>d.id===payload.id);if(!draft)throw Error('Select a saved draft in My Drafts first.');if(action==='exportForm'){const artifact=await adapter.exportArtifact(draft,payload.format);const blob=new win.Blob([artifact.content],{type:artifact.format==='html'?'text/html;charset=utf-8':'application/json'});const url=win.URL.createObjectURL(blob),link=win.document.createElement('a');link.href=url;link.download='form-'+draft.id+'.'+artifact.format;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Server '+artifact.format.toUpperCase()+' artifact retrieved; download requested. No PDF or digital signature claimed.');return;}const transition={draft:'submit-review',review_required:'approve',approved:'issue'}[draft.status];if(!transition)throw Error('This form has no pending review transition.');const reason=win.prompt('Reason to '+transition+' the exact saved form');if(!reason?.trim()||!win.confirm('Confirm '+transition+' of '+draft.title+' revision '+draft.revision+'?'))return;const saved=await adapter.formTransition(draft,transition,{humanConfirmed:true,reason});await refresh();win.__v1DocumentFrame.selectDraft(saved);win.__v1DocumentFrame.notice('Server form state: '+saved.status+' — verified by readback.');return;}if(action==='saveForm'){const selectedTemplate=current.templates?.find(t=>t.id===payload.template?.id);const selectedDraft=payload.draft?current.drafts?.find(d=>d.id===payload.draft.id):null;if(!selectedTemplate||payload.draft&&!selectedDraft)throw Error('Select a verified template and current draft.');const reason=selectedDraft?win.prompt('Reason for creating a new revision (required)'):null;if(selectedDraft&&!reason?.trim())return;const submitted={template:selectedTemplate,draft:selectedDraft||undefined,values:{...payload.values}},scopeKey=key(),ticket=generation;const saved=await adapter.saveForm({...submitted,reason});const retained=formDrafts.get(scopeKey);if(retained&&retained.template?.id===submitted.template.id&&retained.draft?.id===submitted.draft?.id)formDrafts.set(scopeKey,{...retained,draft:saved,template:saved.template});if(!active()||scopeKey!==key()||ticket!==generation)return;current.drafts=[...(current.drafts||[]).filter(d=>d.id!==saved.id),saved];win.__v1DocumentFrame.completeSave(submitted,saved);return;}if(['section','item','decision','issue'].includes(action)){`
- L166: `let p=payload;if(action==='decision'||action==='issue'){const reason=win.prompt('Reason for '+action+' (required)');if(!reason?.trim())return;const role=win.prompt('Your assigned project discipline (server verifies authority)');if(!role?.trim()||!win.confirm('Confirm '+action+' of the exact current server record?'))return;p=action==='issue'?{humanConfirmed:true,issueReason:reason,professionalRole:role}:{action:payload.action,humanConfirmed:true,reason,actingRole:role};}`
- L171: `return {supports,prepareSource,mountFrame,dispose(){frames.forEach(x=>x.dispose());frames.clear();formDrafts.clear();}};`

Source `app/public/assets/v1-clone/js/adapters/document-tools.js`:

- L9: `async function load(id){const s=scope(),p=s.selectedProjectId;if(id==='bom'){const runs=rows(await context.read('/toolbox/runs?limit=100'));return {available:true,boq:runs.filter(r=>r.toolId==='boq_takeoff'&&r.assignment?.projectId===p),boundaries:{qsCertification:false,tenderGeneration:false,exports:['json','csv','html','pdf'],reason:'BoQ takeoff runs and checksum-verified JSON, CSV, HTML and PDF artifacts persist in Toolbox. QS certification and tender generation are unavailable here.'}};}if(id==='specforge')return {workspaces:rows(await context.read('/projects/'+p+'/specforge-workspaces')).filter(w=>w.projectId===p)};if(id==='forms'){const [t,d]=await Promise.all([context.read('/form-templates'),context.read('/form-drafts?projectId='+p)]);return {templates:rows(t),drafts:rows(d).filter(x=>x.projectId===p)};}throw Error('Unsupported document tool.');}`
- L10: `async function saveBoq({title,currency,contingencyPercent,items}){`
- L14: `if(!title?.trim()||!currency?.trim()||!Number.isFinite(contingency)||contingency<0||contingency>100)throw Error('Title, currency and valid contingency are required.');`
- L16: `const lines=items.map(i=>{const quantity=number(i.quantity),rate=number(i.rate);if(!i.description?.trim()||!i.unit?.trim()||!Number.isFinite(quantity)||quantity<=0||!Number.isFinite(rate)||rate<0)throw Error('Invalid BoQ line: description, unit, positive quantity and non-negative rate are required.');return {description:i.description.trim(),unit:i.unit.trim(),quantity,rate};});`
- L20: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100',projectScoped:true}));`
- L21: `const history=rows(await context.read('/toolbox/runs?limit=100')),now=scope();`
- L27: `async function downloadBoq(run,format){`
- L32: `const list=rows(await context.read('/toolbox/runs/'+run.id+'/exports'));`
- L39: `function identifier(id){if(!/^[a-f0-9]{32}$/i.test(id||''))throw Error('A real record identifier is required.');return id;}`
- L42: `async function persist(path,payload,back,verification={}){if(writing)throw Error('A write is already pending.');const s=scope(),key=s.selectedProjectId+'|'+s.organisationId;writing=true;try{await context.write(path,payload,{...verification,readBackPath:back,projectScoped:true});const result=unwrap(await context.read(back));const n=scope();if(key!==n.selectedProjectId+'|'+n.organisationId)throw Error('Project changed; reload to verify the write.');return result;}finally{writing=false;}}`
- L43: `async function specWrite(ws,action,payload,itemId){record(ws);if(ws.status!=='draft'||!Number.isInteger(ws.version)||ws.version<1)throw Error('A current draft version is required.');if(!['sections','items','decisions','issue'].includes(action))throw Error('Unavailable specification operation.');if(['decisions','issue'].includes(action)){if(payload.humanConfirmed!==true)throw Error('Human confirmation is required.');const reason=action==='issue'?payload.issueReason:payload.reason;const role=action==='issue'?payload.professionalRole:payload.actingRole;if(!reason?.trim()||!role?.trim())throw Error('A reason and verified acting role are required.');}if(action==='issue'&&(!ws.items?.length||ws.items.some(i=>i.status!=='approved')))throw Error('Issue blocked: all items must be approved.');if(action==='decisions'&&!ws.items.some(i=>i.id===itemId))throw Error('Item is not in this workspace.');const back='/specforge-workspaces/'+ws.id;const path=action==='decisions'?'/specforge-items/'+identifier(itemId)+'/decisions':back+'/'+action;const verification={readBackParentId:ws.id};if(action!=='issue')verification.readBackCollection=action==='sections'?'sections':'items';const result=await persist(path,{...payload,expectedVersion:ws.version},back,verification);if(result?.id!==ws.id||result.projectId!==ws.projectId||result.version<=ws.version)throw Error('Write readback could not verify the new workspace version.');return result;}`
- L44: `async function saveForm({draft,template,values,reason}){const s=scope(),projectId=s.selectedProjectId,organisationId=s.organisationId;if(draft){record(draft);if(!reason?.trim())throw Error('A revision reason is required.');}identifier(template?.id);if(writing)throw Error('A write is already pending.');writing=true;try{const path=draft?'/form-drafts/'+draft.id+'/revisions':'/form-drafts';const payload=draft?{values,reason}:{projectId,templateId:template.id,title:template.name,values};const result=unwrap(await context.write(path,payload,{readBackPath:'/form-drafts?projectId='+projectId,projectScoped:true}));identifier(result?.id);const verified=unwrap(await context.read('/form-drafts/'+result.id));const n=scope();if(n.selectedProjectId!==projectId||n.organisationId!==organisationId||verified?.id!==result.id||verified?.projectId!==projectId)throw Error('Form readback failed or project changed.');return verified;}finally{writing=false;}}`
- L45: `async function formTransition(draft,action,payload){record(draft);const from={'submit-review':'draft',approve:'review_required',issue:'approved'},to={'submit-review':'review_required',approve:'approved',issue:'issued'};if(!from[action]||draft.status!==from[action])throw Error('Form state does not allow this action.');if(!payload.reason?.trim()||(action!=='submit-review'&&payload.humanConfirmed!==true))throw Error('Explicit confirmation and reason required.');const back='/form-drafts/'+draft.id;const result=await persist(back+'/'+action,payload,back);if(result?.id!==draft.id||result.projectId!==draft.projectId||result.status!==to[action])throw Error('Form transition readback failed.');return result;}`
- L46: `async function exports(draft){record(draft);if(draft.status!=='issued')throw Error('Only issued forms have export artifacts.');return rows(await context.read('/form-drafts/'+draft.id+'/exports'));}`
- L47: `async function exportArtifact(draft,format){if(!['html','json'].includes(format))throw Error('PDF and digital signature providers are unavailable.');const list=await exports(draft),match=list.find(e=>e.format===format);identifier(match?.id);const result=unwrap(await context.read('/form-exports/'+match.id));record(draft);if(result?.id!==match.id||result.formDraftId!==draft.id||result.format!==format||typeof result.content!=='string')throw Error('Export artifact readback failed.');return result;}`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="bom"></a>

## bom — BoM / BoQ & Tender Builder

**Placement:** workspace-tools / procure / family create. **Open:** `openTool('bom', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** BoQ takeoff and procurement packages are live; legacy sample breadth is partial.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "tools.use"}; admin: {"read": true, "write": true, "review": true, "permission": "tools.use"}; member: {"read": true, "write": true, "review": false, "permission": "tools.use"}; viewer: {"read": false, "write": false, "review": false, "permission": "tools.use"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Advisory BoQ inputs, saved runs and checksum-verified JSON/CSV/HTML artifacts are connected. Procurement packages open in their own governed workspace. QS certification and legacy tender/PDF generation are not provided here.

**Production native next actions:** save-takeoff → bom (tools.use, review); download-advisory-output → bom (tools.use, review); procure → rfq_marketplace (projects.read, client_and_professional_approval)


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/document-tools.js`:

- L13: `const unavailable=()=>notice('Unavailable: no configured service for this action. Nothing was saved, signed, sent or exported.');`
- L20: `text('.collab-avatar','—');text('.collab-text','Live collaboration unavailable');`
- L36: `const controls=qa('.field-input');controls.forEach((e,i)=>{e.value='';e.removeAttribute('value');e.readOnly=false;e.dataset.fieldKey='';const field=template?.fields?.[i];const label=e.closest('.field-group')?.querySelector('.field-label');if(field){e.dataset.fieldKey=field.key;if(label)label.textContent=field.label;e.required=field.required===true;e.value=values?.[field.key]??draft?.values?.[field.key]??'';e.title='Verified template field: '+field.key;}else{e.title='Original control retained; no corresponding field in selected template. Not submitted.';}});`
- L39: `qa('.two-col > div:last-child .panel > div').forEach(e=>{if(!e.querySelector('button')){e.textContent='Not verified — signature and live auto-fill providers unavailable';}});`
- L41: `qa('button').forEach(b=>{if(/Save Draft/.test(b.textContent)){b.removeAttribute('onclick');b.onclick=()=>{const values={};controls.forEach(e=>{if(e.dataset.fieldKey)values[e.dataset.fieldKey]=e.value;});run('saveForm',{draft,template,values});};}else if(/Export PDF|Preview|Municipal/.test(b.textContent))blocked(b,'PDF, preview integration and municipal submission are unavailable. Issued forms expose HTML/JSON records, not PDF.');});`
- L46: `text('.sig-pad','Digital signature unavailable');qa('.sig-pad').forEach(e=>e.classList.remove('signed'));`
- L72: `w.showToast=unavailable;`
- L75: `['submitNewProject','switchProject','handleProjectChange','changeRole','handleSmartSearch','showUrlAnalysis','useAiResult','useUrlResult','simulateAiScan','simulateAiScanInline','addAllDrawingSuggestions','addAllDrawingsSuggestions','movePipelineItem','createIssueSnapshot','executeIssue'].forEach(n=>w[n]=unavailable);`
- L76: `w.openNewProjectModal=unavailable;`
- L88: `qa('button').forEach(e=>{if(!e.closest('nav,.tool-nav')&&!e.getAttribute('onclick')?.includes('switchView'))blocked(e,'QS certification, tender generation and export routes are unavailable. Use the advisory BoQ form to save explicit line items.');});`
- L89: `text('.ai-body','BoQ takeoffs are advisory Toolbox records. Enter measured quantities and rates explicitly. QS certification, tender generation and exports are unavailable.');`
- L92: `const dz=q('#dropZone');if(dz){dz.addEventListener('click',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);dz.addEventListener('drop',e=>{e.stopImmediatePropagation();e.preventDefault();unavailable();},true);}`
- L94: `w.selectProject=unavailable;clean();`
- L97: `<form data-boq-form><label>Title <input class="form-input" name="title" required></label>`
- L98: `<label>Currency <input class="form-input" name="currency" required></label>`
- L99: `<label>Contingency % <input class="form-input" name="contingencyPercent" type="number" min="0" max="100" step="any" required></label>`
- L105: `function addLine(){const row=d.createElement('fieldset');row.dataset.boqLine='';row.innerHTML=\`<legend>Measured line</legend><label>Description <input class="form-input" name="description" required></label><label>Quantity <input class="form-input" name="quantity" type="number" min="0.00000001" step="any" required></label><label>Unit <input class="form-input" name="unit" required></label><label>Rate <input class="form-input" name="rate" type="number" min="0" step="any" required></label><button class="btn btn-secondary" type="button">Remove line</button>\`;row.querySelector('button').onclick=()=>row.remove();lines.append(row);}`
- L108: `const field=name=>form.elements.namedItem(name).value;`
- L115: `for(const record of data.boq||[]){const item=d.createElement('article');item.className='panel';item.dataset.recordId=record.id;const heading=d.createElement('h4');heading.textContent=(record.input?.title||record.id)+' — '+(record.status||'Status unavailable');item.append(heading);`
- L124: `const ws=next.workspace||{};Object.assign(SAMPLE_WORKSPACE,{id:ws.id||'',projectId:ws.projectId||'',projectName:next.projectName||'',municipality:next.municipality||'',stage:next.stage||'',revision:ws.revisionCode||'',issueStatus:ws.status||'unavailable',team:[],sections:ws.sections||[],items:(ws.items||[]).map(i=>({...i,budgetAllowance:i.budgetAllowanceCents/100,estimatedCost:i.estimatedCostCents/100,clientDecision:i.requiresClientDecision,drawingRefs:i.drawingReferences||[],clauseRefs:i.clauseReferences||[],comments:[],image:'',sourceRevision:ws.revisionCode,supersededBy:null}))});currentRole=next.role||'';w.renderAll();`
- L149: `function supports(id){return ids.includes(id);}`
- L150: `function prepareSource(id,html){if(!supports(id))return html;`
- L154: `const blank={id:'',projectId:'',projectName:'',municipality:'',stage:'',profile:'',revision:'',issueStatus:'unavailable',team:[],sections:[],items:[]};`
- L161: `async function mountFrame(id,frame){if(!supports(id))return false;const win=frame.contentWindow;if(!win?.__v1DocumentFrame)throw Error('Document frame was not prepared before srcdoc.');const previous=frames.get(frame);previous?.dispose?.();let generation=0,selected='',current=null,disposed=false;const key=()=>{const s=context.getState();return [s.session?.userId,s.organisationId,s.selectedProjectId,!!s.session].join('|');};let formScope=key();`
- L164: `async function refresh(){const ticket=++generation,scopeKey=key();if(!active())return;if(id==='forms'&&formScope!==scopeKey){formScope=scopeKey;win.__v1DocumentFrame.restoreForm(formDrafts.get(formScope));}current=null;win.__v1DocumentFrame.apply({templates:[],drafts:[],workspaces:[],error:'Loading authenticated project records…'});try{const result=await adapter.load(id);if(!active()||ticket!==generation||scopeKey!==key())return;const s=context.getState(),p=s.projects?.find(p=>p.id===s.selectedProjectId)||{};current=result;const ws=result.workspaces?.find(w=>w.id===selected)||result.workspaces?.[0];selected=ws?.id||'';current.workspace=ws;win.__v1DocumentFrame.apply((id==='specforge'?root.V1CloneDocumentToolsAdapter.safe:x=>x)({...result,workspace:ws,projectName:p.name||p.projectName||'',municipality:p.municipality||'',stage:p.currentPhase||'',role:s.projectRole||s.role||'',readOnly:s.readOnly===true,error:result.available===false?result.reason:null}));}catch(e){if(ticket===generation&&active()&&scopeKey===key())win.__v1DocumentFrame.apply({error:e.message,templates:[],drafts:[],workspaces:[]});}}`
- L165: `win.__v1DocumentCommand=async(action,payload)=>{if(!active())throw Error('Document frame is no longer active.');if(action==='selectWorkspace'){selected=payload.id;await refresh();return;}if(!current)throw Error('Load current project records first.');const ws=current.workspace;if(action==='exportBoq'){if(id!=='bom')throw Error('Unavailable advisory artifact action.');const record=current.boq?.find(row=>row.id===payload.id);if(!record)throw Error('Select a saved BoQ in this project.');const scopeKey=key(),ticket=generation,artifact=await adapter.downloadBoq(record,payload.format);if(!active()||scopeKey!==key()||ticket!==generation)throw Error('Scope changed before the download.');const url=win.URL.createObjectURL(artifact.blob),link=win.document.createElement('a');link.href=url;link.download=artifact.filename;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Verified server '+payload.format.toUpperCase()+' artifact retrieved; download requested. Not certification, a tender or PDF.');return artifact;}if(action==='saveBoq'){if(id!=='bom')throw Error('Unavailable BoQ action.');const scopeKey=key(),ticket=generation;await adapter.saveBoq(payload);if(!active()||scopeKey!==key()||ticket!==generation)return;await refresh();win.__v1DocumentFrame.notice('Advisory BoQ saved and read back. Not certified, issued, tendered or exported.');return;}if(action==='transitionForm'||action==='exportForm'){const draft=current.drafts?.find(d=>d.id===payload.id);if(!draft)throw Error('Select a saved draft in My Drafts first.');if(action==='exportForm'){const artifact=await adapter.exportArtifact(draft,payload.format);const blob=new win.Blob([artifact.content],{type:artifact.format==='html'?'text/html;charset=utf-8':'application/json'});const url=win.URL.createObjectURL(blob),link=win.document.createElement('a');link.href=url;link.download='form-'+draft.id+'.'+artifact.format;link.click();win.setTimeout(()=>win.URL.revokeObjectURL(url),30000);win.__v1DocumentFrame.notice('Server '+artifact.format.toUpperCase()+' artifact retrieved; download requested. No PDF or digital signature claimed.');return;}const transition={draft:'submit-review',review_required:'approve',approved:'issue'}[draft.status];if(!transition)throw Error('This form has no pending review transition.');const reason=win.prompt('Reason to '+transition+' the exact saved form');if(!reason?.trim()||!win.confirm('Confirm '+transition+' of '+draft.title+' revision '+draft.revision+'?'))return;const saved=await adapter.formTransition(draft,transition,{humanConfirmed:true,reason});await refresh();win.__v1DocumentFrame.selectDraft(saved);win.__v1DocumentFrame.notice('Server form state: '+saved.status+' — verified by readback.');return;}if(action==='saveForm'){const selectedTemplate=current.templates?.find(t=>t.id===payload.template?.id);const selectedDraft=payload.draft?current.drafts?.find(d=>d.id===payload.draft.id):null;if(!selectedTemplate||payload.draft&&!selectedDraft)throw Error('Select a verified template and current draft.');const reason=selectedDraft?win.prompt('Reason for creating a new revision (required)'):null;if(selectedDraft&&!reason?.trim())return;const submitted={template:selectedTemplate,draft:selectedDraft||undefined,values:{...payload.values}},scopeKey=key(),ticket=generation;const saved=await adapter.saveForm({...submitted,reason});const retained=formDrafts.get(scopeKey);if(retained&&retained.template?.id===submitted.template.id&&retained.draft?.id===submitted.draft?.id)formDrafts.set(scopeKey,{...retained,draft:saved,template:saved.template});if(!active()||scopeKey!==key()||ticket!==generation)return;current.drafts=[...(current.drafts||[]).filter(d=>d.id!==saved.id),saved];win.__v1DocumentFrame.completeSave(submitted,saved);return;}if(['section','item','decision','issue'].includes(action)){`
- L166: `let p=payload;if(action==='decision'||action==='issue'){const reason=win.prompt('Reason for '+action+' (required)');if(!reason?.trim())return;const role=win.prompt('Your assigned project discipline (server verifies authority)');if(!role?.trim()||!win.confirm('Confirm '+action+' of the exact current server record?'))return;p=action==='issue'?{humanConfirmed:true,issueReason:reason,professionalRole:role}:{action:payload.action,humanConfirmed:true,reason,actingRole:role};}`
- L171: `return {supports,prepareSource,mountFrame,dispose(){frames.forEach(x=>x.dispose());frames.clear();formDrafts.clear();}};`

Source `app/public/assets/v1-clone/js/adapters/document-tools.js`:

- L9: `async function load(id){const s=scope(),p=s.selectedProjectId;if(id==='bom'){const runs=rows(await context.read('/toolbox/runs?limit=100'));return {available:true,boq:runs.filter(r=>r.toolId==='boq_takeoff'&&r.assignment?.projectId===p),boundaries:{qsCertification:false,tenderGeneration:false,exports:['json','csv','html','pdf'],reason:'BoQ takeoff runs and checksum-verified JSON, CSV, HTML and PDF artifacts persist in Toolbox. QS certification and tender generation are unavailable here.'}};}if(id==='specforge')return {workspaces:rows(await context.read('/projects/'+p+'/specforge-workspaces')).filter(w=>w.projectId===p)};if(id==='forms'){const [t,d]=await Promise.all([context.read('/form-templates'),context.read('/form-drafts?projectId='+p)]);return {templates:rows(t),drafts:rows(d).filter(x=>x.projectId===p)};}throw Error('Unsupported document tool.');}`
- L10: `async function saveBoq({title,currency,contingencyPercent,items}){`
- L14: `if(!title?.trim()||!currency?.trim()||!Number.isFinite(contingency)||contingency<0||contingency>100)throw Error('Title, currency and valid contingency are required.');`
- L16: `const lines=items.map(i=>{const quantity=number(i.quantity),rate=number(i.rate);if(!i.description?.trim()||!i.unit?.trim()||!Number.isFinite(quantity)||quantity<=0||!Number.isFinite(rate)||rate<0)throw Error('Invalid BoQ line: description, unit, positive quantity and non-negative rate are required.');return {description:i.description.trim(),unit:i.unit.trim(),quantity,rate};});`
- L20: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100',projectScoped:true}));`
- L21: `const history=rows(await context.read('/toolbox/runs?limit=100')),now=scope();`
- L27: `async function downloadBoq(run,format){`
- L32: `const list=rows(await context.read('/toolbox/runs/'+run.id+'/exports'));`
- L39: `function identifier(id){if(!/^[a-f0-9]{32}$/i.test(id||''))throw Error('A real record identifier is required.');return id;}`
- L42: `async function persist(path,payload,back,verification={}){if(writing)throw Error('A write is already pending.');const s=scope(),key=s.selectedProjectId+'|'+s.organisationId;writing=true;try{await context.write(path,payload,{...verification,readBackPath:back,projectScoped:true});const result=unwrap(await context.read(back));const n=scope();if(key!==n.selectedProjectId+'|'+n.organisationId)throw Error('Project changed; reload to verify the write.');return result;}finally{writing=false;}}`
- L43: `async function specWrite(ws,action,payload,itemId){record(ws);if(ws.status!=='draft'||!Number.isInteger(ws.version)||ws.version<1)throw Error('A current draft version is required.');if(!['sections','items','decisions','issue'].includes(action))throw Error('Unavailable specification operation.');if(['decisions','issue'].includes(action)){if(payload.humanConfirmed!==true)throw Error('Human confirmation is required.');const reason=action==='issue'?payload.issueReason:payload.reason;const role=action==='issue'?payload.professionalRole:payload.actingRole;if(!reason?.trim()||!role?.trim())throw Error('A reason and verified acting role are required.');}if(action==='issue'&&(!ws.items?.length||ws.items.some(i=>i.status!=='approved')))throw Error('Issue blocked: all items must be approved.');if(action==='decisions'&&!ws.items.some(i=>i.id===itemId))throw Error('Item is not in this workspace.');const back='/specforge-workspaces/'+ws.id;const path=action==='decisions'?'/specforge-items/'+identifier(itemId)+'/decisions':back+'/'+action;const verification={readBackParentId:ws.id};if(action!=='issue')verification.readBackCollection=action==='sections'?'sections':'items';const result=await persist(path,{...payload,expectedVersion:ws.version},back,verification);if(result?.id!==ws.id||result.projectId!==ws.projectId||result.version<=ws.version)throw Error('Write readback could not verify the new workspace version.');return result;}`
- L44: `async function saveForm({draft,template,values,reason}){const s=scope(),projectId=s.selectedProjectId,organisationId=s.organisationId;if(draft){record(draft);if(!reason?.trim())throw Error('A revision reason is required.');}identifier(template?.id);if(writing)throw Error('A write is already pending.');writing=true;try{const path=draft?'/form-drafts/'+draft.id+'/revisions':'/form-drafts';const payload=draft?{values,reason}:{projectId,templateId:template.id,title:template.name,values};const result=unwrap(await context.write(path,payload,{readBackPath:'/form-drafts?projectId='+projectId,projectScoped:true}));identifier(result?.id);const verified=unwrap(await context.read('/form-drafts/'+result.id));const n=scope();if(n.selectedProjectId!==projectId||n.organisationId!==organisationId||verified?.id!==result.id||verified?.projectId!==projectId)throw Error('Form readback failed or project changed.');return verified;}finally{writing=false;}}`
- L45: `async function formTransition(draft,action,payload){record(draft);const from={'submit-review':'draft',approve:'review_required',issue:'approved'},to={'submit-review':'review_required',approve:'approved',issue:'issued'};if(!from[action]||draft.status!==from[action])throw Error('Form state does not allow this action.');if(!payload.reason?.trim()||(action!=='submit-review'&&payload.humanConfirmed!==true))throw Error('Explicit confirmation and reason required.');const back='/form-drafts/'+draft.id;const result=await persist(back+'/'+action,payload,back);if(result?.id!==draft.id||result.projectId!==draft.projectId||result.status!==to[action])throw Error('Form transition readback failed.');return result;}`
- L46: `async function exports(draft){record(draft);if(draft.status!=='issued')throw Error('Only issued forms have export artifacts.');return rows(await context.read('/form-drafts/'+draft.id+'/exports'));}`
- L47: `async function exportArtifact(draft,format){if(!['html','json'].includes(format))throw Error('PDF and digital signature providers are unavailable.');const list=await exports(draft),match=list.find(e=>e.format===format);identifier(match?.id);const result=unwrap(await context.read('/form-exports/'+match.id));record(draft);if(result?.id!==match.id||result.formDraftId!==draft.id||result.format!==format||typeof result.content!=='string')throw Error('Export artifact readback failed.');return result;}`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="itp"></a>

## itp — Inspection Test Plans

**Placement:** workspace-tools / build / family review. **Open:** `openTool('itp', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** SiteExecutionRoutes, inspection records and professional issue gate.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/site-tools.js`:

- L5: `const supports=id=>Object.hasOwn(root.V1CloneSiteToolsAdapter.aliases,id);`
- L10: `button.title=review?'Unavailable: a governed engineering review workflow is not connected.':button.disabled?'Read-only membership: calculation saving requires write permission.':'Only edited Rational Method inputs can be saved as an advisory run.';`
- L18: `if(review||!active)return notice('Calculation not saved or sent to review: only Rational Method saving is mapped to Toolbox. Other calculators and professional review remain unavailable in this hub.');`
- L27: `const boundary='Internal records only — not statutory approval, safety certification or permission to work.';`
- L28: `const unavailable='Not available from the connected service.';`
- L30: `if(!supports(id))return html;`
- L42: `else if(!el.classList.contains('tab')&&!el.classList.contains('nav-item')&&!/activateTab/.test(el.getAttribute('onclick')||'')){el.dataset.siteUnavailable='';el.removeAttribute('onclick');el.title=unavailable;}`
- L45: `doc.querySelectorAll('tbody').forEach(el=>{const sample=el.querySelector('tr');if(sample){while(el.children.length>1)el.lastElementChild.remove();sample.querySelectorAll('td').forEach(cell=>{cell.textContent='—';});}el.dataset.siteRecords='unavailable';});`
- L55: `card.innerHTML='<h2 class="card-title">Internal safety inspections</h2><p>Draft findings only. Not safety certification or permission to work.</p><button class="action-btn" type="button" data-site-create>Draft safety inspection</button><table class="table"><thead><tr><th>Inspection</th><th>Findings</th><th>Revision</th><th>Status</th><th>Checklist</th><th>Follow-ups</th><th>Boundary</th></tr></thead><tbody data-site-records="inspections"><tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table>';`
- L60: `const status=doc.createElement('p');status.className=id==='itp'?'card-title':'card-title';status.dataset.siteToolsStatus='';status.setAttribute('role','status');status.textContent=id==='engineering_calc'?'Local advisory calculator — inputs and results are not saved; professional verification required.':('Loading project records. '+boundary);`
- L65: `const headers=kind==='inspections'?['Internal inspection','Findings','Revision unavailable','Workflow status','Checklist items','Follow-ups','Boundary']:kind==='plans'?['Subject','Reference','Recorded by','Recorded at','Evidence grade','Record status','Boundary']:['ID','Title','Severity','Status','Description'];`
- L69: `const values=r=>kind==='inspections'?[r.inspectionType+' inspection',r.findings?.join('; ')||'No findings recorded','—',r.status,r.checklist?.length??0,r.followUps?.join('; ')||'No follow-ups recorded','Not statutory approval']:kind==='plans'?[r.subjectName,r.referenceNumber||'—',r.createdBy||'—',r.createdAt||'—',r.grade,r.status,'Not permission to work']:[r.id,r.title,r.severity,r.status,r.description];`
- L73: `async function mountFrame(id,frame){`
- L74: `if(!supports(id))return false;`
- L75: `const doc=frame.contentDocument,win=frame.contentWindow;if(!doc||!win)throw Error('A loaded same-origin frame is required.');`
- L80: `doc.querySelectorAll('[data-site-unavailable]').forEach(button=>{button.disabled=true;button.title=unavailable;button.onclick=null;});`
- L85: `const target=[...doc.querySelectorAll('h2')].find(h=>h.textContent.includes(headings[index]||'Inspection Test Plans'));target?.scrollIntoView({block:'start'});setStatus(button.textContent+': '+(index===1?'Internal inspection records. ':unavailable+' ')+boundary);`
- L89: `doc.querySelectorAll('[data-site-create]').forEach(button=>{button.disabled=context.getState().readOnly===true;button.title=button.disabled?'Read-only membership: inspection creation requires write permission.':'Create an internal draft inspection.';});`
- L95: `doc.querySelectorAll('[data-site-records]').forEach(table=>{const kind=table.dataset.siteRecords;if(Array.isArray(model[kind]))bindTable(table,model[kind],kind);else{const cell=table.querySelector('td');if(cell)cell.textContent=unavailable;}});`
- L96: `setStatus('Project: '+model.projectName+'. '+boundary+' Unsupported registers are unavailable, not empty or compliant.');return true;`
- L115: `return {supports,prepareSource,mountFrame};`

Source `app/public/assets/v1-clone/js/adapters/site-tools.js`:

- L9: `async function load(id){`
- L11: `const s=scope(),site=unwrap(await context.read(path(s,'site-execution-workspace')));`
- L13: `if(id==='safety'){const trust=unwrap(await context.read(path(s,'trust-compliance-workspace')));model.plans=rows(trust,'complianceItems',s).filter(r=>r.documentType==='health_safety_plan');model.inspections=model.inspections.filter(r=>r.inspectionType==='safety');}`
- L17: `async function createInspection(input){`
- L19: `if(context.capabilities?.readBackCollection!==true)throw Error('Draft inspection saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L21: `const saved=unwrap(await context.write(path(s,'inspections'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'inspections'}));current(s);`
- L22: `const data=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L25: `async function saveRational(values){`
- L30: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L31: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L36: `async function saveVoltageDrop(values){`
- L41: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L42: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L51: `async function persistRun(toolId,input,outputKey){`
- L54: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L55: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L61: `if(!v||typeof v!=='object')throw Error('A compliance project description is required.');`
- L68: `function ruleVersion(v){if(!/^[a-f0-9]{32}$/i.test(v))throw Error('A registered 32-hex ruleVersionId is required for this calculator.');return v;}`
- L69: `async function saveBeamCheck(values){`
- L74: `async function saveConcreteQuantity(values){`
- L80: `async function saveMasonryQuantity(values){`
- L85: `async function saveFinishesQuantity(values){`
- L93: `async function saveTenderRateBuildUp(values){`
- L98: `async function saveLabourProductivity(values){`
- L103: `async function saveRefuseArea(values){`
- L108: `async function saveFenestrationXa(values){`
- L110: `const raw=[].concat(values.elements||[]);if(raw.length===0)throw Error('At least one fenestration element is required.');`
- L117: `async function saveRValueThermal(values){`
- L119: `const raw=[].concat(values.assemblies||[]);if(raw.length===0)throw Error('At least one thermal assembly is required.');`
- L129: `// read-back-verified, never fabricated success on an unavailable path).`
- L130: `async function createRfi(input){`
- L132: `if(input.humanConfirmed!==true)throw Error('RFI requires explicit human confirmation.');if(!input.dueAt)throw Error('A response due date is required — the record cannot be created without one.');`
- L133: `if(context.capabilities?.readBackCollection!==true)throw Error('RFI save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L135: `const body={subject:input.subject,question:input.question,discipline,dueAt:input.dueAt||null,linkedDocumentIds:input.linkedDocumentIds??[],humanConfirmed:true};`
- L136: `const saved=unwrap(await context.write(path(s,'rfis'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L137: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L140: `async function respondRfi(rfiId,{answer,requiresInstruction=false,humanConfirmed=false}={}){`
- L142: `if(humanConfirmed!==true)throw Error('RFI response requires explicit human confirmation.');`
- L143: `if(context.capabilities?.readBackCollection!==true)throw Error('RFI response save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L144: `const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/responses',{answer,requiresInstruction,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L145: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L148: `async function closeRfi(rfiId,{reason,humanConfirmed=false}={}){`
- L150: `if(humanConfirmed!==true)throw Error('RFI close requires explicit human confirmation.');`
- L151: `const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/close',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L152: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L157: `async function createNcr(input){`
- L159: `if(input.humanConfirmed!==true)throw Error('NCR requires explicit human confirmation.');`
- L160: `const body={title:input.title,description:input.description,severity:input.severity,responsibleParty:input.responsibleParty,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};`
- L161: `const saved=unwrap(await context.write(path(s,'ncrs'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L162: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L165: `async function submitNcrCorrectiveAction(ncrId,{correctiveAction,evidenceIds=[],humanConfirmed=false}={}){`
- L167: `if(humanConfirmed!==true)throw Error('NCR corrective action requires explicit human confirmation.');`
- L168: `const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/corrective-actions',{correctiveAction,evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L169: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L172: `async function decideNcr(ncrId,{decision,reason,humanConfirmed=false}={}){`
- L174: `if(humanConfirmed!==true||!['verified_closed','rejected'].includes(decision))throw Error('NCR decision requires confirmation and a valid decision.');`
- L175: `const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L176: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L179: `async function createSnag(input){`
- L181: `if(input.humanConfirmed!==true)throw Error('Snag requires explicit human confirmation.');if(!input.dueAt)throw Error('A due date is required — the record cannot be created without one.');`
- L182: `const body={location:input.location,description:input.description,priority:input.priority,responsibleParty:input.responsibleParty,dueAt:input.dueAt||null,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};`
- L183: `const saved=unwrap(await context.write(path(s,'snags'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L184: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L187: `async function allocateSnag(snagId,{humanConfirmed=false}={}){`
- L189: `if(humanConfirmed!==true)throw Error('Snag allocation requires explicit human confirmation.');`
- L190: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/allocate',{humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L191: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L194: `async function markSnagReady(snagId,{evidenceIds=[],humanConfirmed=false}={}){`
- L196: `if(humanConfirmed!==true)throw Error('Snag ready-for-reinspection requires explicit human confirmation.');`
- L197: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/ready-for-reinspection',{evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L198: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L201: `async function decideSnag(snagId,{decision,reason,humanConfirmed=false}={}){`
- L203: `if(humanConfirmed!==true||!['closed','rejected'].includes(decision))throw Error('Snag decision requires confirmation and a valid decision.');`
- L204: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L205: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L208: `async function createInstruction(input){`
- L210: `if(input.humanConfirmed!==true)throw Error('Site instruction requires explicit human confirmation.');`
- L211: `const body={title:input.title,instruction:input.instruction,costImpact:input.costImpact||'none',timeImpact:input.timeImpact||'none',linkedRfiId:input.linkedRfiId||null,humanConfirmed:true};`
- L212: `const saved=unwrap(await context.write(path(s,'site-instructions'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);`
- L213: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L216: `async function issueInstruction(instructionId,{reason,humanConfirmed=false}={}){`
- L218: `if(humanConfirmed!==true)throw Error('Site instruction issue requires explicit human confirmation.');`
- L219: `const saved=unwrap(await context.write('/site-instructions/'+encodeURIComponent(instructionId)+'/issue',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);`
- L220: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="safety"></a>

## safety — Health & Safety

**Placement:** workspace-tools / build / family review. **Open:** `openTool('safety', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Safety notes and field evidence persist; full safety-file sample is partial.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Safety inspection drafts and recorded plan evidence use the selected project. Permits, incidents, inductions and hazard registers remain unavailable; these records are not certification or permission to work.

**Production native next actions:** draft-safety-inspection → safety (projects.write, professional_review); evidence → compliance_hub (projects.read, professional_review)


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/site-tools.js`:

- L5: `const supports=id=>Object.hasOwn(root.V1CloneSiteToolsAdapter.aliases,id);`
- L10: `button.title=review?'Unavailable: a governed engineering review workflow is not connected.':button.disabled?'Read-only membership: calculation saving requires write permission.':'Only edited Rational Method inputs can be saved as an advisory run.';`
- L18: `if(review||!active)return notice('Calculation not saved or sent to review: only Rational Method saving is mapped to Toolbox. Other calculators and professional review remain unavailable in this hub.');`
- L27: `const boundary='Internal records only — not statutory approval, safety certification or permission to work.';`
- L28: `const unavailable='Not available from the connected service.';`
- L30: `if(!supports(id))return html;`
- L42: `else if(!el.classList.contains('tab')&&!el.classList.contains('nav-item')&&!/activateTab/.test(el.getAttribute('onclick')||'')){el.dataset.siteUnavailable='';el.removeAttribute('onclick');el.title=unavailable;}`
- L45: `doc.querySelectorAll('tbody').forEach(el=>{const sample=el.querySelector('tr');if(sample){while(el.children.length>1)el.lastElementChild.remove();sample.querySelectorAll('td').forEach(cell=>{cell.textContent='—';});}el.dataset.siteRecords='unavailable';});`
- L55: `card.innerHTML='<h2 class="card-title">Internal safety inspections</h2><p>Draft findings only. Not safety certification or permission to work.</p><button class="action-btn" type="button" data-site-create>Draft safety inspection</button><table class="table"><thead><tr><th>Inspection</th><th>Findings</th><th>Revision</th><th>Status</th><th>Checklist</th><th>Follow-ups</th><th>Boundary</th></tr></thead><tbody data-site-records="inspections"><tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table>';`
- L60: `const status=doc.createElement('p');status.className=id==='itp'?'card-title':'card-title';status.dataset.siteToolsStatus='';status.setAttribute('role','status');status.textContent=id==='engineering_calc'?'Local advisory calculator — inputs and results are not saved; professional verification required.':('Loading project records. '+boundary);`
- L65: `const headers=kind==='inspections'?['Internal inspection','Findings','Revision unavailable','Workflow status','Checklist items','Follow-ups','Boundary']:kind==='plans'?['Subject','Reference','Recorded by','Recorded at','Evidence grade','Record status','Boundary']:['ID','Title','Severity','Status','Description'];`
- L69: `const values=r=>kind==='inspections'?[r.inspectionType+' inspection',r.findings?.join('; ')||'No findings recorded','—',r.status,r.checklist?.length??0,r.followUps?.join('; ')||'No follow-ups recorded','Not statutory approval']:kind==='plans'?[r.subjectName,r.referenceNumber||'—',r.createdBy||'—',r.createdAt||'—',r.grade,r.status,'Not permission to work']:[r.id,r.title,r.severity,r.status,r.description];`
- L73: `async function mountFrame(id,frame){`
- L74: `if(!supports(id))return false;`
- L75: `const doc=frame.contentDocument,win=frame.contentWindow;if(!doc||!win)throw Error('A loaded same-origin frame is required.');`
- L80: `doc.querySelectorAll('[data-site-unavailable]').forEach(button=>{button.disabled=true;button.title=unavailable;button.onclick=null;});`
- L85: `const target=[...doc.querySelectorAll('h2')].find(h=>h.textContent.includes(headings[index]||'Inspection Test Plans'));target?.scrollIntoView({block:'start'});setStatus(button.textContent+': '+(index===1?'Internal inspection records. ':unavailable+' ')+boundary);`
- L89: `doc.querySelectorAll('[data-site-create]').forEach(button=>{button.disabled=context.getState().readOnly===true;button.title=button.disabled?'Read-only membership: inspection creation requires write permission.':'Create an internal draft inspection.';});`
- L95: `doc.querySelectorAll('[data-site-records]').forEach(table=>{const kind=table.dataset.siteRecords;if(Array.isArray(model[kind]))bindTable(table,model[kind],kind);else{const cell=table.querySelector('td');if(cell)cell.textContent=unavailable;}});`
- L96: `setStatus('Project: '+model.projectName+'. '+boundary+' Unsupported registers are unavailable, not empty or compliant.');return true;`
- L115: `return {supports,prepareSource,mountFrame};`

Source `app/public/assets/v1-clone/js/adapters/site-tools.js`:

- L9: `async function load(id){`
- L11: `const s=scope(),site=unwrap(await context.read(path(s,'site-execution-workspace')));`
- L13: `if(id==='safety'){const trust=unwrap(await context.read(path(s,'trust-compliance-workspace')));model.plans=rows(trust,'complianceItems',s).filter(r=>r.documentType==='health_safety_plan');model.inspections=model.inspections.filter(r=>r.inspectionType==='safety');}`
- L17: `async function createInspection(input){`
- L19: `if(context.capabilities?.readBackCollection!==true)throw Error('Draft inspection saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L21: `const saved=unwrap(await context.write(path(s,'inspections'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'inspections'}));current(s);`
- L22: `const data=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L25: `async function saveRational(values){`
- L30: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L31: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L36: `async function saveVoltageDrop(values){`
- L41: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L42: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L51: `async function persistRun(toolId,input,outputKey){`
- L54: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L55: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L61: `if(!v||typeof v!=='object')throw Error('A compliance project description is required.');`
- L68: `function ruleVersion(v){if(!/^[a-f0-9]{32}$/i.test(v))throw Error('A registered 32-hex ruleVersionId is required for this calculator.');return v;}`
- L69: `async function saveBeamCheck(values){`
- L74: `async function saveConcreteQuantity(values){`
- L80: `async function saveMasonryQuantity(values){`
- L85: `async function saveFinishesQuantity(values){`
- L93: `async function saveTenderRateBuildUp(values){`
- L98: `async function saveLabourProductivity(values){`
- L103: `async function saveRefuseArea(values){`
- L108: `async function saveFenestrationXa(values){`
- L110: `const raw=[].concat(values.elements||[]);if(raw.length===0)throw Error('At least one fenestration element is required.');`
- L117: `async function saveRValueThermal(values){`
- L119: `const raw=[].concat(values.assemblies||[]);if(raw.length===0)throw Error('At least one thermal assembly is required.');`
- L129: `// read-back-verified, never fabricated success on an unavailable path).`
- L130: `async function createRfi(input){`
- L132: `if(input.humanConfirmed!==true)throw Error('RFI requires explicit human confirmation.');if(!input.dueAt)throw Error('A response due date is required — the record cannot be created without one.');`
- L133: `if(context.capabilities?.readBackCollection!==true)throw Error('RFI save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L135: `const body={subject:input.subject,question:input.question,discipline,dueAt:input.dueAt||null,linkedDocumentIds:input.linkedDocumentIds??[],humanConfirmed:true};`
- L136: `const saved=unwrap(await context.write(path(s,'rfis'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L137: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L140: `async function respondRfi(rfiId,{answer,requiresInstruction=false,humanConfirmed=false}={}){`
- L142: `if(humanConfirmed!==true)throw Error('RFI response requires explicit human confirmation.');`
- L143: `if(context.capabilities?.readBackCollection!==true)throw Error('RFI response save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L144: `const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/responses',{answer,requiresInstruction,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L145: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L148: `async function closeRfi(rfiId,{reason,humanConfirmed=false}={}){`
- L150: `if(humanConfirmed!==true)throw Error('RFI close requires explicit human confirmation.');`
- L151: `const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/close',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L152: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L157: `async function createNcr(input){`
- L159: `if(input.humanConfirmed!==true)throw Error('NCR requires explicit human confirmation.');`
- L160: `const body={title:input.title,description:input.description,severity:input.severity,responsibleParty:input.responsibleParty,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};`
- L161: `const saved=unwrap(await context.write(path(s,'ncrs'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L162: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L165: `async function submitNcrCorrectiveAction(ncrId,{correctiveAction,evidenceIds=[],humanConfirmed=false}={}){`
- L167: `if(humanConfirmed!==true)throw Error('NCR corrective action requires explicit human confirmation.');`
- L168: `const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/corrective-actions',{correctiveAction,evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L169: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L172: `async function decideNcr(ncrId,{decision,reason,humanConfirmed=false}={}){`
- L174: `if(humanConfirmed!==true||!['verified_closed','rejected'].includes(decision))throw Error('NCR decision requires confirmation and a valid decision.');`
- L175: `const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L176: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L179: `async function createSnag(input){`
- L181: `if(input.humanConfirmed!==true)throw Error('Snag requires explicit human confirmation.');if(!input.dueAt)throw Error('A due date is required — the record cannot be created without one.');`
- L182: `const body={location:input.location,description:input.description,priority:input.priority,responsibleParty:input.responsibleParty,dueAt:input.dueAt||null,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};`
- L183: `const saved=unwrap(await context.write(path(s,'snags'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L184: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L187: `async function allocateSnag(snagId,{humanConfirmed=false}={}){`
- L189: `if(humanConfirmed!==true)throw Error('Snag allocation requires explicit human confirmation.');`
- L190: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/allocate',{humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L191: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L194: `async function markSnagReady(snagId,{evidenceIds=[],humanConfirmed=false}={}){`
- L196: `if(humanConfirmed!==true)throw Error('Snag ready-for-reinspection requires explicit human confirmation.');`
- L197: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/ready-for-reinspection',{evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L198: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L201: `async function decideSnag(snagId,{decision,reason,humanConfirmed=false}={}){`
- L203: `if(humanConfirmed!==true||!['closed','rejected'].includes(decision))throw Error('Snag decision requires confirmation and a valid decision.');`
- L204: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L205: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L208: `async function createInstruction(input){`
- L210: `if(input.humanConfirmed!==true)throw Error('Site instruction requires explicit human confirmation.');`
- L211: `const body={title:input.title,instruction:input.instruction,costImpact:input.costImpact||'none',timeImpact:input.timeImpact||'none',linkedRfiId:input.linkedRfiId||null,humanConfirmed:true};`
- L212: `const saved=unwrap(await context.write(path(s,'site-instructions'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);`
- L213: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L216: `async function issueInstruction(instructionId,{reason,humanConfirmed=false}={}){`
- L218: `if(humanConfirmed!==true)throw Error('Site instruction issue requires explicit human confirmation.');`
- L219: `const saved=unwrap(await context.write('/site-instructions/'+encodeURIComponent(instructionId)+'/issue',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);`
- L220: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="feedback"></a>

## feedback — Feedback Intelligence & Product Roadmap

**Placement:** knowledge-cpd / platform / family create. **Open:** `openTool('feedback', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Context capture UI exists; roadmap clustering remains non-authoritative.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Contextual feedback submission and user-scoped history persist. Roadmap clustering and product prioritisation are not authoritative workflows.

**Production native next actions:** submit-feedback → feedback (projects.read, none)


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/feedback.js`:

- L6: `// Requires context.read(path) and context.write(path, body, {readBackPath(id), projectScoped}).`
- L9: `async function install({context}) {`
- L46: `global.submitFeedback = async () => notify('Feedback is unavailable until the authenticated context is ready. Your text has been kept.');`
- L47: `if (!context?.read || !context?.write) throw Error('Feedback requires context.read and context.write.');`
- L48: `async function refresh() {`
- L51: `const loaded = unwrap(await context.read('/feedback'));`
- L63: `global.submitFeedback = async function () {`
- L80: `const result = unwrap(await context.write('/feedback',body,{readBackPath:saved => {`
- L87: `const verified = unwrap(await context.read('/feedback/' + result.id));`

Source `app/public/assets/v1-clone/js/modules/wingman.js`:

- L16: `previewRow.querySelector('.message-footer').textContent='Unsaved preview · human review required';`
- L91: `scope(state){const project=state.projects?.find(p=>p.id===state.selectedProjectId);const name=project?.name||'Standalone context';pills[0].textContent=state.ready?'Authenticated context':'Context unavailable';pills[2].textContent=name;projectItems[0].textContent=name;projectItems[1].textContent='Phase: '+(project?.phase||project?.currentPhase||project?.stage||'Not supplied');projectItems[2].textContent='Role: '+(state.session?.memberships?.find(m=>m.organisationId===state.organisationId)?.role||'Not supplied');},`
- L106: `async function refresh(){const ticket=revision;try{const data=await adapter.bootstrap();if(!valid(ticket))return;view.threads(data.conversations);view.availability(data.availability);view.notice(data.availability?.configured===false?'Wingman AI is unavailable: provider configuration required. Review saved conversations or keep a draft; no AI request has been sent.':'Authenticated Wingman ready. '+(data.availability?.configured===true?'Provider configured; a successful response is verified only after a request. ':'Provider configuration was not reported. ')+(data.withheldConversations?'Unverified conversation history is withheld. ':'')+'Only cited sources are evidence; all outputs require human review.');}catch(e){if(valid(ticket)){view.availability({configured:false});view.notice(e.message);}}}`
- L127: `else throw Error('This suggested destination is unavailable in this dashboard. Use the workspace navigation.');`
- L147: `return {supports:id=>id==='wingman',prepareSource(id,html){return id==='wingman'?html.replace(/<\/body>/i,'<script data-v1-wingman>('+frameBootstrap.toString()+')();<\/script></body>'):html;},mountFrame,`

Source `app/public/assets/v1-clone/js/adapters/wingman.js`:

- L19: `async function verify(saved, scope) {`
- L20: `const record = await context.read(conversationPath(saved.conversationId)); check(scope.key);`
- L30: `async function bootstrap() {`
- L32: `const result = await context.read('/api/v1/wingman/bootstrap?route=wingman' + (scope.projectId ? '&projectId=' + encodeURIComponent(scope.projectId) : ''));`
- L42: `async function send({prompt,capability = ''}) {`
- L45: `if (scope.data.availability?.configured === false) throw Error('configuration_required: Wingman AI is not configured on this server. Your draft has been kept.');`
- L59: `saved = await context.write('/api/v1/wingman/messages',{`
- L74: `async function load(id) {`
- L80: `const record = await context.read(conversationPath(id));check(scope.key);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="project-passport"></a>

## project_passport — Project Passport

**Placement:** projects / brief / family orient. **Open:** `openTool('project_passport', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** ProjectRoutes, lifecycle records and MariaDB project creation.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** No project editing or transition form exists in the original. Phase changes require targetPhase and expectedVersion.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}`; returned $object fields: id, projectCode, name, clientName, municipality, propertyReference, propertyUse, phase, version, effectiveRole.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/governance.js`:

- L23: `async function load(def) {`
- L26: `const p = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId)));`
- L35: `try { profile = unwrap(await context.read('/professional-profile')) || null; }`
- L37: `return { directory: unwrap(await context.read('/directory/search')) || [], profile };`
- L95: `<form data-review-form><label>Decision <select name="decision" required><option value="">Select a decision</option><option value="manually_reviewed">Manually reviewed</option><option value="externally_verified">Externally verified</option><option value="rejected">Rejected</option></select></label>`
- L96: `<label>Reason <textarea name="note" required maxlength="4000"></textarea></label>`
- L97: `<label>External provider <input name="provider" maxlength="160"></label><label>Provider evidence reference <input name="providerReference" maxlength="180"></label><p>External verification requires a real provider and evidence reference supplied by the reviewer.</p>`
- L98: `<label><input type="checkbox" name="humanConfirmed" required> I confirm this decision for the selected record.</label><button class="btn" type="submit">Record decision</button></form>\`;`
- L100: `fields.decision.onchange=()=>{fields.provider.required=fields.providerReference.required=fields.decision.value==='externally_verified';};`
- L103: `const decision=fields.decision.value,payload={note:fields.note.value,provider:fields.provider.value,providerReference:fields.providerReference.value,humanConfirmed:fields.humanConfirmed.checked};`
- L120: `async function refresh(def) { if (disposed || !ownsRoute(routeFor(def))) return false;`
- L126: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/modules/iconography.js`:

- L343: `async function apply(toolId) {`
- L348: `root.showToast?.('Icon registry unavailable: js/architex-icon-pack.js did not load.');`
- L349: `return { applied: false, reason: 'registry-unavailable' };`

Source `app/public/assets/v1-clone/js/adapters/governance.js`:

- L5: `// Follows the canonical site-tools.js pattern: scope()/current(), context.write with read-back`
- L6: `// verify, humanConfirmed guards, never fabricated success.`
- L20: `async function listTeam(){`
- L22: `const data=unwrap(await context.read(path(s,'team')));current(s);`
- L26: `async function invite({candidateId,discipline,roleLabel}){`
- L28: `if(!candidateId||!discipline)throw Error('A candidate and discipline are required.');`
- L29: `const saved=unwrap(await context.write(path(s,'team'),{candidateId,discipline,roleLabel:roleLabel||discipline},{readBackPath:path(s,'team')}));current(s);`
- L34: `async function removeMember(teamMemberId,reason){`
- L36: `if(!reason||!String(reason).trim())throw Error('A removal reason is required.');`
- L37: `await context.write('/projects/'+encodeURIComponent(s.selectedProjectId)+'/team/'+encodeURIComponent(teamMemberId),{reason:String(reason).trim(),humanConfirmed:true},{method:'DELETE',readBackPath:path(s,'team')});current(s);`
- L41: `async function searchDirectory(profession,serviceArea){`
- L44: `return unwrap(await context.read('/directory/search'+(q.length?'?'+q.join('&'):'')));`
- L46: `async function saveProfile({serviceAreas,directoryVisible}){`
- L48: `return unwrap(await context.write('/professional-profile',{serviceAreas:serviceAreas??[],directoryVisible:directoryVisible===true,humanConfirmed:true},{readBackPath:'/professional-profile'}));`
- L50: `async function submitVerification({kind='professional_registration',grade,registrationBody,registrationNumber,purpose,expiresOn,evidenceFileId,consentGiven,retentionUntil}={}){`
- L52: `if(consentGiven!==true)throw Error('Explicit consent is required before submitting a verification.');`
- L53: `if(!['self_declared','document_uploaded'].includes(grade))throw Error('A valid grade (self_declared or document_uploaded) is required.');`
- L54: `if(!['SACAP','ECSA','SACQSP','SACLAP','SACPCMP'].includes(String(registrationBody).toUpperCase()))throw Error('A recognised professional body is required (SACAP, ECSA, SACQSP, SACLAP, SACPCMP).');`
- L55: `if(!purpose||!String(purpose).trim())throw Error('A POPIA purpose is required.');`
- L56: `if(!retentionUntil)throw Error('A retention date is required.');`
- L57: `return unwrap(await context.write('/verifications',{kind,grade,registrationBody:String(registrationBody).toUpperCase(),registrationNumber:registrationNumber||null,purpose:String(purpose).trim(),expiresOn:expiresOn||null,evidenceFileId:evidenceFileId||null,consentGiven:true,retentionUntil},{readBackPath:'/verification-review-queue'}));`
- L59: `function reviewer(){const s=scope(false),member=s.session.memberships?.find(m=>m.organisationId===s.organisationId);if(!['owner','admin'].includes(member?.role)||(member.status&&member.status!=='active'))throw Error('An active owner or admin membership is required.');return s;}`
- L61: `async function reviewQueue(){const s=reviewer();const rows=verificationRows(await context.read('/verification-review-queue'));current(s);return rows;}`
- L62: `async function ownVerifications(){const s=scope(false);const rows=verificationRows(await context.read('/verifications'));current(s);return rows;}`
- L64: `async function reviewVerification(verificationId,decision,{provider,providerReference,note,humanConfirmed}={}){`
- L67: `if(humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L68: `if(!note?.trim())throw Error('A review reason is required.');`
- L69: `if(!['manually_reviewed','externally_verified','rejected'].includes(decision))throw Error('A valid decision is required.');`
- L74: `const saved=unwrap(await context.write('/verifications/'+verificationId+'/review',{decision,provider:provider?.trim()||null,providerReference:providerReference?.trim()||null,note:note.trim(),humanConfirmed:true},{projectScoped:false,readBackPath:'/verification-review-queue',readBackAbsentId:verificationId}));current(s);`
- L81: `async function analyticsWorkspace(){const s=scope(true);return unwrap(await context.read(path(s,'analytics-workspace')));}`
- L82: `async function refreshKpis({asOf}={}){`
- L85: `return unwrap(await context.write(path(s,'analytics/kpis/refresh'),body,{readBackPath:path(s,'analytics-workspace')}));`
- L87: `async function createAlertRule(rule){`
- L90: `return unwrap(await context.write(path(s,'analytics/alert-rules'),body,{readBackPath:path(s,'analytics-workspace')}));`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="project-explorer"></a>

## project_explorer — Project Explorer

**Placement:** projects / platform / family find. **Open:** `openTool('project_explorer', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Tenant projects and operating snapshots are live; asset-wide search is partial.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Search the bounded project-record projection and document metadata. Controlled document drafts use the document workflow; this is not an asset-wide archive or content search.

**Production native next actions:** draft-document → documents_drawings (projects.write, document_review)

**Original scaffold boundary (not enhanced-module verdict):** Original has no search, record editor or asset upload fields. Snapshot is a bounded record projection, not a complete archive.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/operating-snapshot`; returned records fields: id, title, sourceModule, recordType, status, createdAt.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/explorer.js`:

- L76: `async function createDocument(main) {`
- L83: `adapter.createDocument({ title: title.trim(), documentType: type, discipline, issuePurpose: 'for_review', revisionCode: rev.trim(), notes: 'Created from Project Explorer', humanConfirmed: true })`
- L95: `async function refresh() {`
- L107: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/explorer.js`:

- L40: `async function listRecords(){const s=scope();const records=snapshotRows(await context.read(snapshotPath(s)),s);current(s);return records;}`
- L41: `async function listDocuments(){const s=scope();const records=documentRows(await context.read(listPath(s)),s);current(s);return records;}`
- L42: `async function load(id){`
- L45: `const snap=await context.read(snapshotPath(s));`
- L46: `const docs=await context.read(listPath(s));`
- L54: `async function createDocument(input){`
- L56: `if(input.humanConfirmed!==true)throw Error('Document creation requires explicit human confirmation.');`
- L57: `if(context.capabilities?.readBackCollection!==true)throw Error('Draft document save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L61: `if(!type)throw Error('A valid document type is required.');`
- L62: `if(!discipline)throw Error('A valid discipline is required.');`
- L63: `if(!purpose)throw Error('A valid issue purpose is required.');`
- L64: `if(!input.title||!String(input.title).trim())throw Error('A document title is required.');`
- L65: `if(!input.revisionCode||!String(input.revisionCode).trim())throw Error('A revision code is required.');`
- L67: `const saved=unwrap(await context.write(listPath(s),body,{readBackPath:listPath(s)}));current(s);`
- L68: `const data=documentRows(await context.read(listPath(s)),s);current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="professional-directory"></a>

## professional_directory — Professional Directory

**Placement:** projects / appoint / family find. **Open:** `openTool('professional_directory', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** TrustRoutes, verified profiles and independent review records.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no directory filters or invitation fields. Inviting requires candidateId, discipline and roleLabel in Team API.

**Read input/output contracts:**
- `/api/v1/directory/search`; returned  fields: profileId, displayName, profession, verificationGrade, registrationBody.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/directory.js`:

- L29: `async function load(token = generation) {`
- L34: `try { loadedProfile = unwrap(await context.read('/professional-profile')) || null; }`
- L140: `async function apply(toolId) {`
- L160: `return { apply, dispose, supports: id => id === TOOL };`

Source `app/public/assets/v1-clone/js/modules/governance.js`:

- L23: `async function load(def) {`
- L26: `const p = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId)));`
- L35: `try { profile = unwrap(await context.read('/professional-profile')) || null; }`
- L37: `return { directory: unwrap(await context.read('/directory/search')) || [], profile };`
- L95: `<form data-review-form><label>Decision <select name="decision" required><option value="">Select a decision</option><option value="manually_reviewed">Manually reviewed</option><option value="externally_verified">Externally verified</option><option value="rejected">Rejected</option></select></label>`
- L96: `<label>Reason <textarea name="note" required maxlength="4000"></textarea></label>`
- L97: `<label>External provider <input name="provider" maxlength="160"></label><label>Provider evidence reference <input name="providerReference" maxlength="180"></label><p>External verification requires a real provider and evidence reference supplied by the reviewer.</p>`
- L98: `<label><input type="checkbox" name="humanConfirmed" required> I confirm this decision for the selected record.</label><button class="btn" type="submit">Record decision</button></form>\`;`
- L100: `fields.decision.onchange=()=>{fields.provider.required=fields.providerReference.required=fields.decision.value==='externally_verified';};`
- L103: `const decision=fields.decision.value,payload={note:fields.note.value,provider:fields.provider.value,providerReference:fields.providerReference.value,humanConfirmed:fields.humanConfirmed.checked};`
- L120: `async function refresh(def) { if (disposed || !ownsRoute(routeFor(def))) return false;`
- L126: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/governance.js`:

- L5: `// Follows the canonical site-tools.js pattern: scope()/current(), context.write with read-back`
- L6: `// verify, humanConfirmed guards, never fabricated success.`
- L20: `async function listTeam(){`
- L22: `const data=unwrap(await context.read(path(s,'team')));current(s);`
- L26: `async function invite({candidateId,discipline,roleLabel}){`
- L28: `if(!candidateId||!discipline)throw Error('A candidate and discipline are required.');`
- L29: `const saved=unwrap(await context.write(path(s,'team'),{candidateId,discipline,roleLabel:roleLabel||discipline},{readBackPath:path(s,'team')}));current(s);`
- L34: `async function removeMember(teamMemberId,reason){`
- L36: `if(!reason||!String(reason).trim())throw Error('A removal reason is required.');`
- L37: `await context.write('/projects/'+encodeURIComponent(s.selectedProjectId)+'/team/'+encodeURIComponent(teamMemberId),{reason:String(reason).trim(),humanConfirmed:true},{method:'DELETE',readBackPath:path(s,'team')});current(s);`
- L41: `async function searchDirectory(profession,serviceArea){`
- L44: `return unwrap(await context.read('/directory/search'+(q.length?'?'+q.join('&'):'')));`
- L46: `async function saveProfile({serviceAreas,directoryVisible}){`
- L48: `return unwrap(await context.write('/professional-profile',{serviceAreas:serviceAreas??[],directoryVisible:directoryVisible===true,humanConfirmed:true},{readBackPath:'/professional-profile'}));`
- L50: `async function submitVerification({kind='professional_registration',grade,registrationBody,registrationNumber,purpose,expiresOn,evidenceFileId,consentGiven,retentionUntil}={}){`
- L52: `if(consentGiven!==true)throw Error('Explicit consent is required before submitting a verification.');`
- L53: `if(!['self_declared','document_uploaded'].includes(grade))throw Error('A valid grade (self_declared or document_uploaded) is required.');`
- L54: `if(!['SACAP','ECSA','SACQSP','SACLAP','SACPCMP'].includes(String(registrationBody).toUpperCase()))throw Error('A recognised professional body is required (SACAP, ECSA, SACQSP, SACLAP, SACPCMP).');`
- L55: `if(!purpose||!String(purpose).trim())throw Error('A POPIA purpose is required.');`
- L56: `if(!retentionUntil)throw Error('A retention date is required.');`
- L57: `return unwrap(await context.write('/verifications',{kind,grade,registrationBody:String(registrationBody).toUpperCase(),registrationNumber:registrationNumber||null,purpose:String(purpose).trim(),expiresOn:expiresOn||null,evidenceFileId:evidenceFileId||null,consentGiven:true,retentionUntil},{readBackPath:'/verification-review-queue'}));`
- L59: `function reviewer(){const s=scope(false),member=s.session.memberships?.find(m=>m.organisationId===s.organisationId);if(!['owner','admin'].includes(member?.role)||(member.status&&member.status!=='active'))throw Error('An active owner or admin membership is required.');return s;}`
- L61: `async function reviewQueue(){const s=reviewer();const rows=verificationRows(await context.read('/verification-review-queue'));current(s);return rows;}`
- L62: `async function ownVerifications(){const s=scope(false);const rows=verificationRows(await context.read('/verifications'));current(s);return rows;}`
- L64: `async function reviewVerification(verificationId,decision,{provider,providerReference,note,humanConfirmed}={}){`
- L67: `if(humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L68: `if(!note?.trim())throw Error('A review reason is required.');`
- L69: `if(!['manually_reviewed','externally_verified','rejected'].includes(decision))throw Error('A valid decision is required.');`
- L74: `const saved=unwrap(await context.write('/verifications/'+verificationId+'/review',{decision,provider:provider?.trim()||null,providerReference:providerReference?.trim()||null,note:note.trim(),humanConfirmed:true},{projectScoped:false,readBackPath:'/verification-review-queue',readBackAbsentId:verificationId}));current(s);`
- L81: `async function analyticsWorkspace(){const s=scope(true);return unwrap(await context.read(path(s,'analytics-workspace')));}`
- L82: `async function refreshKpis({asOf}={}){`
- L85: `return unwrap(await context.write(path(s,'analytics/kpis/refresh'),body,{readBackPath:path(s,'analytics-workspace')}));`
- L87: `async function createAlertRule(rule){`
- L90: `return unwrap(await context.write(path(s,'analytics/alert-rules'),body,{readBackPath:path(s,'analytics-workspace')}));`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="team-workspace"></a>

## team_workspace — Team Workspace

**Placement:** projects / appoint / family administer. **Open:** `openTool('team_workspace', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** TeamRoutes, invitations, acceptance, removal and audit history.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no candidateId, discipline or roleLabel fields for invitations, or removal reason.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/team`; returned  fields: id, displayName, discipline, roleLabel, status.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/governance.js`:

- L23: `async function load(def) {`
- L26: `const p = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId)));`
- L35: `try { profile = unwrap(await context.read('/professional-profile')) || null; }`
- L37: `return { directory: unwrap(await context.read('/directory/search')) || [], profile };`
- L95: `<form data-review-form><label>Decision <select name="decision" required><option value="">Select a decision</option><option value="manually_reviewed">Manually reviewed</option><option value="externally_verified">Externally verified</option><option value="rejected">Rejected</option></select></label>`
- L96: `<label>Reason <textarea name="note" required maxlength="4000"></textarea></label>`
- L97: `<label>External provider <input name="provider" maxlength="160"></label><label>Provider evidence reference <input name="providerReference" maxlength="180"></label><p>External verification requires a real provider and evidence reference supplied by the reviewer.</p>`
- L98: `<label><input type="checkbox" name="humanConfirmed" required> I confirm this decision for the selected record.</label><button class="btn" type="submit">Record decision</button></form>\`;`
- L100: `fields.decision.onchange=()=>{fields.provider.required=fields.providerReference.required=fields.decision.value==='externally_verified';};`
- L103: `const decision=fields.decision.value,payload={note:fields.note.value,provider:fields.provider.value,providerReference:fields.providerReference.value,humanConfirmed:fields.humanConfirmed.checked};`
- L120: `async function refresh(def) { if (disposed || !ownsRoute(routeFor(def))) return false;`
- L126: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/governance.js`:

- L5: `// Follows the canonical site-tools.js pattern: scope()/current(), context.write with read-back`
- L6: `// verify, humanConfirmed guards, never fabricated success.`
- L20: `async function listTeam(){`
- L22: `const data=unwrap(await context.read(path(s,'team')));current(s);`
- L26: `async function invite({candidateId,discipline,roleLabel}){`
- L28: `if(!candidateId||!discipline)throw Error('A candidate and discipline are required.');`
- L29: `const saved=unwrap(await context.write(path(s,'team'),{candidateId,discipline,roleLabel:roleLabel||discipline},{readBackPath:path(s,'team')}));current(s);`
- L34: `async function removeMember(teamMemberId,reason){`
- L36: `if(!reason||!String(reason).trim())throw Error('A removal reason is required.');`
- L37: `await context.write('/projects/'+encodeURIComponent(s.selectedProjectId)+'/team/'+encodeURIComponent(teamMemberId),{reason:String(reason).trim(),humanConfirmed:true},{method:'DELETE',readBackPath:path(s,'team')});current(s);`
- L41: `async function searchDirectory(profession,serviceArea){`
- L44: `return unwrap(await context.read('/directory/search'+(q.length?'?'+q.join('&'):'')));`
- L46: `async function saveProfile({serviceAreas,directoryVisible}){`
- L48: `return unwrap(await context.write('/professional-profile',{serviceAreas:serviceAreas??[],directoryVisible:directoryVisible===true,humanConfirmed:true},{readBackPath:'/professional-profile'}));`
- L50: `async function submitVerification({kind='professional_registration',grade,registrationBody,registrationNumber,purpose,expiresOn,evidenceFileId,consentGiven,retentionUntil}={}){`
- L52: `if(consentGiven!==true)throw Error('Explicit consent is required before submitting a verification.');`
- L53: `if(!['self_declared','document_uploaded'].includes(grade))throw Error('A valid grade (self_declared or document_uploaded) is required.');`
- L54: `if(!['SACAP','ECSA','SACQSP','SACLAP','SACPCMP'].includes(String(registrationBody).toUpperCase()))throw Error('A recognised professional body is required (SACAP, ECSA, SACQSP, SACLAP, SACPCMP).');`
- L55: `if(!purpose||!String(purpose).trim())throw Error('A POPIA purpose is required.');`
- L56: `if(!retentionUntil)throw Error('A retention date is required.');`
- L57: `return unwrap(await context.write('/verifications',{kind,grade,registrationBody:String(registrationBody).toUpperCase(),registrationNumber:registrationNumber||null,purpose:String(purpose).trim(),expiresOn:expiresOn||null,evidenceFileId:evidenceFileId||null,consentGiven:true,retentionUntil},{readBackPath:'/verification-review-queue'}));`
- L59: `function reviewer(){const s=scope(false),member=s.session.memberships?.find(m=>m.organisationId===s.organisationId);if(!['owner','admin'].includes(member?.role)||(member.status&&member.status!=='active'))throw Error('An active owner or admin membership is required.');return s;}`
- L61: `async function reviewQueue(){const s=reviewer();const rows=verificationRows(await context.read('/verification-review-queue'));current(s);return rows;}`
- L62: `async function ownVerifications(){const s=scope(false);const rows=verificationRows(await context.read('/verifications'));current(s);return rows;}`
- L64: `async function reviewVerification(verificationId,decision,{provider,providerReference,note,humanConfirmed}={}){`
- L67: `if(humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L68: `if(!note?.trim())throw Error('A review reason is required.');`
- L69: `if(!['manually_reviewed','externally_verified','rejected'].includes(decision))throw Error('A valid decision is required.');`
- L74: `const saved=unwrap(await context.write('/verifications/'+verificationId+'/review',{decision,provider:provider?.trim()||null,providerReference:providerReference?.trim()||null,note:note.trim(),humanConfirmed:true},{projectScoped:false,readBackPath:'/verification-review-queue',readBackAbsentId:verificationId}));current(s);`
- L81: `async function analyticsWorkspace(){const s=scope(true);return unwrap(await context.read(path(s,'analytics-workspace')));}`
- L82: `async function refreshKpis({asOf}={}){`
- L85: `return unwrap(await context.write(path(s,'analytics/kpis/refresh'),body,{readBackPath:path(s,'analytics-workspace')}));`
- L87: `async function createAlertRule(rule){`
- L90: `return unwrap(await context.write(path(s,'analytics/alert-rules'),body,{readBackPath:path(s,'analytics-workspace')}));`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="inbox-action"></a>

## inbox_action — Inbox / Action Centre

**Placement:** collaboration / platform / family review. **Open:** `openTool('inbox_action', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Mobile decision inbox, role routing and confirmed transitions.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no selected inbox item or transition/reason/confirmation controls.

**Read input/output contracts:**
- `/api/v1/inbox`; returned  fields: id, title, detail, priority, status, projectId.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/approvals.js`:

- L29: `async function refresh() {`
- L88: `if (sensitive && !reason) { notice('A reason is required. Nothing was changed.'); return; }`
- L90: `adapter.transitionInbox(id, { status, reason, humanConfirmed: sensitive })`
- L104: `if (!route || !Number.isInteger(status)) { notice('A route and an integer status are required. Nothing was recorded.'); return; }`
- L111: `routeSmoke: [{ route, status }], dependencyVulnerabilityCount: 0, humanConfirmed: true,`
- L124: `async function apply(toolId) {`
- L132: `return { apply, dispose, tool: TOOL, supports: id => id === TOOL };`

Source `app/public/assets/v1-clone/js/modules/inbox-action.js`:

- L30: `async function refresh(token = generation) {`
- L93: `<label class="field"><span>Reason ${'' + (deciding.length ? '' : '')}<small>(required to resolve or dismiss)</small></span>`
- L125: `if (DECIDING.includes(status) && !reason) { notice('A reason is required to resolve or dismiss — the server will refuse it otherwise.'); return; }`
- L129: `await adapter.transition(selected.id, status, { reason: reason, humanConfirmed: confirmed });`
- L140: `async function apply(toolId) {`
- L160: `return { apply, dispose, supports: id => id === TOOL };`

Source `app/public/assets/v1-clone/js/adapters/approvals.js`:

- L26: `function identity(value,label){if(!/^[a-f0-9]{32}$/i.test(String(value||'')))throw Error('A valid '+label+' id is required.');return String(value).toLowerCase();}`
- L31: `function isApproval(decision){return decisionKind(decision)==='approval'||decision?.inboxType==='approval_required';}`
- L33: `async function readInbox(s){const data=unwrap(await context.read('/inbox'));current(s);return projectItems(data,s);}`
- L34: `async function readGates(s){const data=unwrap(await context.read('/release-gates'));current(s);if(!Array.isArray(data))throw Error('Invalid release-gate response.');return data;}`
- L37: `async function listInbox(){`
- L44: `async function listDecisions(){`
- L46: `const snapshot=unwrap(await context.read(path(s,'operating-snapshot')));current(s);`
- L58: `async function transitionInbox(inboxItemId,{status,reason,humanConfirmed=false}={}){`
- L64: `if(sensitive&&humanConfirmed!==true)throw Error('Resolving or dismissing a decision requires explicit human confirmation.');`
- L65: `if(sensitive&&!text)throw Error('A reason is required to resolve or dismiss a decision.');`
- L67: `if(sensitive)body.humanConfirmed=true;`
- L68: `unwrap(await context.write('/inbox/'+encodeURIComponent(id)+'/transition',body,{readBackPath:'/inbox'}));current(s);`
- L72: `return {id:record.id,status:record.status,eventType:record.eventType||null,humanGate:record.payload?.humanGate||null,humanConfirmed:sensitive,reason:text||null};`
- L74: `async function listReleaseGates(){`
- L81: `async function createReleaseGate(input){`
- L83: `if(input?.humanConfirmed!==true)throw Error('Recording a release gate requires explicit human confirmation.');`
- L84: `if(!ENVIRONMENTS.includes(input?.environment))throw Error('A valid environment is required ('+ENVIRONMENTS.join(', ')+').');`
- L86: `if(!repoBranch||!deployedBranch)throw Error('Both the reviewed and deployed branch names are required.');`
- L89: `const apiMode=String(input?.apiMode||'').trim();if(!apiMode)throw Error('An API mode is required.');`
- L90: `if(!input?.buildTimestamp)throw Error('A build timestamp is required.');`
- L93: `if(routes.length===0)throw Error('At least one route smoke probe is required.');`
- L96: `if(!Number.isInteger(vulnerabilities)||vulnerabilities<0)throw Error('A dependency vulnerability count (0 or more) is required.');`
- L97: `const body={environment:input.environment,repoBranch,repoCommit,deployedBranch,deployedCommit,buildTimestamp:input.buildTimestamp,apiMode,apiHealthOk:input.apiHealthOk,databaseReady:input.databaseReady,migrationsReady:input.migrationsReady,mailReady:input.mailReady,routeSmoke,dependencyVulnerabilityCount:vulnerabilities,humanConfirmed:true};`
- L98: `const saved=unwrap(await context.write('/release-gates',body,{readBackPath:'/release-gates'}));current(s);`
- L102: `return {id:record.id,environment:record.environment,status:record.status,summary:record.summary,checks:Array.isArray(record.checks)?record.checks:[],releaseActionsSupported:RELEASE_GATE_ACTIONS_SUPPORTED,humanConfirmed:true};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="issues-rfis"></a>

## issues_rfis — Issues / RFIs

**Placement:** collaboration / design / family review. **Open:** `openTool('issues_rfis', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** SiteExecutionRoutes, RFI lifecycle, responses and records.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no RFI title, question, due date, responsible role, evidence or response form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/site-execution-workspace`; returned rfis fields: id, title, status, dueOn, severity, priority.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/rfi.js`:

- L33: `const attr=' data-rfi-field="'+name+'" data-rfi-'+(options.hook||name)+' '+(options.required?'required ':'')+(options.max?'maxlength="'+options.max+'" ':'');`
- L43: `? field('subject','Subject',f.subject,{required:true,max:220})+field('question','Question and drawing/specification references',f.question,{required:true,area:true,max:12000})+'<label class="field"><span>Discipline</span><select data-rfi-field="discipline" data-rfi-discipline>'+DISCIPLINES.map(d=>'<option value="'+d+'"'+(d===f.discipline?' selected':'')+'>'+esc(d.replaceAll('_',' '))+'</option>').join('')+'</select></label>'+field('due','Response due date (17:00 South Africa time)',f.due,{required:true,type:'date'})`
- L44: `: kind==='respond'?field('answer','Response',f.answer,{required:true,area:true,max:12000})+'<label><input type="checkbox" data-rfi-field="requiresInstruction"'+(f.requiresInstruction?' checked':'')+'> Flag for formal instruction review</label>'`
- L45: `:field('reason','Close-out reason',f.reason,{required:true,area:true,max:1000,hook:'close-reason'});`
- L49: `'<form data-rfi-'+kind+'-form>'+inputs+'<label><input type="checkbox" data-rfi-field="confirmed" data-rfi-confirm required'+(f.confirmed?' checked':'')+'> I confirm this '+(kind==='create'?'RFI should be recorded':kind==='respond'?'response should be saved':'RFI should be closed with this reason')+'.</label>'+`
- L81: `async function submit(form) {`
- L89: `if(!f.subject.trim()||!f.question.trim()||!DISCIPLINES.includes(f.discipline)||!/^\d{4}-\d{2}-\d{2}$/.test(f.due)){status('Subject, question, discipline and response due date are required.');return;}`
- L90: `input={subject:f.subject.trim(),question:f.question.trim(),discipline:f.discipline,dueAt:f.due+'T17:00:00+02:00',linkedDocumentIds:[],humanConfirmed:true};`
- L95: `input=kind==='respond'?{answer:text,requiresInstruction:f.requiresInstruction,humanConfirmed:true}:{reason:text,humanConfirmed:true};`
- L115: `async function refresh() {`
- L119: `const s=scope(),site=unwrap(await context.read('/projects/'+encodeURIComponent(s.selectedProjectId)+'/site-execution-workspace'));`
- L127: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/modules/shell-data.js`:

- L80: `text(one('#navigator .nav-note'), 'Review this project’s RFIs, responses and due dates. Creating, responding and closing require write permission and confirmation.');`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="documents-drawings"></a>

## documents_drawings — Documents & Drawings

**Placement:** projects / design / family find. **Open:** `openTool('documents_drawings', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** DocumentRoutes, revisions, reviews, transmittals and private files.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no document metadata, drawing fields, file chooser, reason or human-confirmation controls.

**Read input/output contracts:**
- `/api/v1/documents?projectId={projectId}`; returned  fields: id, title, documentType, revisionCode, status, issuePurpose.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/documents.js`:

- L21: `const BOUNDARY='Internal controlled-document records only — not an external transmittal, statutory approval or permission to build.';`
- L25: `async function loadRecords(token=generation){const value=await adapter.list();if(disposed||!ownsRoute(TOOL)||token!==generation)return null;records=value;return records;}`
- L100: `const input={documentType:typeSelect?.value,discipline:value('discipline'),issuePurpose:value('issuePurpose'),title:value('title'),revisionCode:value('revisionCode'),notes:value('notes'),humanConfirmed:false};`
- L104: `input.humanConfirmed=true;submit.disabled=true;`
- L114: `let payload={reason:reason.trim(),humanConfirmed:false};`
- L117: `payload={revisionCode:code.trim(),reason:reason.trim(),humanConfirmed:false};`
- L120: `payload.humanConfirmed=true;btn.disabled=true;`
- L131: `async function refresh(){`
- L137: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/documents.js`:

- L19: `function identifier(id){if(!ID.test(id||''))throw Error('A real document revision identifier is required.');return id;}`
- L20: `async function list(){const s=scope();const records=rows(await context.read(listPath(s)),s);current(s);return records;}`
- L21: `async function load(){const s=scope();return {projectId:s.selectedProjectId,organisationId:s.organisationId,projectName:s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project',documents:await list()};}`
- L23: `async function create(input){`
- L25: `if(input.humanConfirmed!==true)throw Error('Document creation requires explicit human confirmation.');`
- L26: `if(context.capabilities?.readBackCollection!==true)throw Error('Draft document save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L30: `if(!type)throw Error('A valid document type is required.');`
- L31: `if(!discipline)throw Error('A valid discipline is required.');`
- L32: `if(!purpose)throw Error('A valid issue purpose is required.');`
- L33: `if(!input.title||!String(input.title).trim())throw Error('A document title is required.');`
- L34: `if(!input.revisionCode||!String(input.revisionCode).trim())throw Error('A revision code is required.');`
- L38: `if(!d.drawingNumber||!String(d.drawingNumber).trim())throw Error('A drawing number is required for a drawing document.');`
- L39: `if(!SHEETS.includes(d.sheetType))throw Error('A valid drawing sheet type is required.');`
- L42: `const saved=unwrap(await context.write(listPath(s),body,{readBackPath:listPath(s)}));current(s);`
- L43: `const data=rows(await context.read(listPath(s)),s);current(s);`
- L47: `async function createRevisionTransition(revisionId,input){`
- L49: `if(input.humanConfirmed!==true)throw Error('Creating a revision requires explicit human confirmation.');`
- L51: `if(!input.revisionCode||!String(input.revisionCode).trim())throw Error('A revision code is required.');`
- L52: `if(!input.reason||!String(input.reason).trim())throw Error('A revision reason is required.');`
- L54: `const saved=unwrap(await context.write(revisionPath(revisionId)+'/revisions',body,{readBackPath:rec=>revisionPath(rec?.id)}));current(s);`
- L55: `const record=unwrap(await context.read(revisionPath(saved?.id)));current(s);`
- L59: `return async function(revisionId,{reason,issuePurpose=null,humanConfirmed=false}={}){`
- L61: `if(humanConfirmed!==true)throw Error(tool+' requires explicit human confirmation.');`
- L63: `if(!reason||!String(reason).trim())throw Error('A reason is required for '+tool+'.');`
- L64: `const body={reason:String(reason).trim(),humanConfirmed:true};if(issuePurpose&&PURPOSES.includes(issuePurpose))body.issuePurpose=issuePurpose;`
- L65: `const saved=unwrap(await context.write(revisionPath(revisionId)+'/'+tool,body,{readBackPath:revisionPath(revisionId)}));current(s);`
- L66: `const record=unwrap(await context.read(revisionPath(revisionId)));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="fee-proposal"></a>

## fee_proposal — Fee Proposal Builder

**Placement:** workspace-tools / appoint / family create. **Open:** `openTool('fee_proposal', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** ProposalRoutes, fee definitions, acceptance and appointment conversion.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no fee scope, source calculation, client, stage allocation or issue confirmation form.

**Read input/output contracts:**
- `/api/v1/proposals`; returned  fields: id, title, status, projectId, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/proposals.js`:

- L36: `async function load(token=generation){`
- L44: `const rawDefs=await context.read('/fee-definitions');`
- L105: `<p class="pill" data-proposals-status role="status">Advisory proposal aid only — not a fixed tariff or legal opinion. Professional review required before issue.</p>`
- L151: `main.querySelector('[data-proposals-status]').textContent='Read-only access: review recorded proposals. Calculating, creating and issuing proposals requires write permission.';`
- L193: `humanConfirmed:main.querySelector('[data-proposals-human]')?.checked===true,`
- L222: `if(values.humanConfirmed!==true){notice('Confirm the proposal checkbox before issuing.');return;}`
- L232: `async function refresh(){`
- L251: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/proposals.js`:

- L27: `async function listProposals(){`
- L29: `const data=unwrap(await context.read('/proposals'));current(s);`
- L34: `async function listFeeRuns(){`
- L36: `const data=unwrap(await context.read(RUNS_PATH));current(s);`
- L59: `async function calculateProfessionalFee(values){`
- L62: `if(issueReason==='')throw Error('A reason is required to freeze a fee calculation as proposal evidence.');`
- L64: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:RUNS_PATH}));current(s);`
- L65: `const history=unwrap(await context.read(RUNS_PATH));current(s);`
- L71: `async function confirmProposal(values){`
- L73: `if(values?.humanConfirmed!==true)throw Error('Fee proposal requires explicit human confirmation.');`
- L75: `if(typeof feeRunId!=='string'||!/^[a-f0-9]{32}$/i.test(feeRunId))throw Error('An issued fee calculation is required.');`
- L77: `if(issueReason==='')throw Error('An issue reason is required to confirm this proposal.');`
- L81: `const draft=unwrap(await context.write('/proposals',draftBody,{readBackPath:'/proposals'}));current(s);`
- L83: `const list=unwrap(await context.read('/proposals'));current(s);`
- L86: `const issued=unwrap(await context.write(issuePath+'/issue',{professionalConfirmed:true,legalReviewConfirmed:true,issueReason},{readBackPath:issuePath}));current(s);`
- L87: `const detail=unwrap(await context.read(issuePath));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="council-navigator"></a>

## council_navigator — Council Drawing Navigator

**Placement:** workspace-tools / comply / family review. **Open:** `openTool('council_navigator', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Governed drawing navigator is live; municipal comment feeds need integration.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Prepare and inspect municipal submission-readiness categories. The governed drawing navigator is a separate native entry. No council comment feed or authority approval is inferred.

**Production native next actions:** readiness → municipal_tracker (projects.write, professional_review); drawing-precheck → drawing_compliance_navigator (tools.use, professional_review)

**Original scaffold boundary (not enhanced-module verdict):** Original has no drawing viewer, selected revision, comparison or municipal response fields. Internal packs do not claim external submission.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/municipal-submission-packs`; returned  fields: id, status, createdAt.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/municipal.js`:

- L126: `if(!reason||!reason.trim()){notice('A professional review reason is required. Nothing was submitted.');return;}`
- L129: `try{await adapter.transitionSubmission(id,{status:adapter.statuses.approved,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Professional review approval verified in project records.');}`
- L134: `async function refresh(){`
- L143: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/municipal.js`:

- L5: `// scope(), current(), context.write with readBackPath, a humanConfirmed guard on`
- L31: `async function listSubmissions(){`
- L33: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`
- L36: `async function listReadiness(){`
- L38: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`
- L41: `// The seeded implementation matrix is review-required and cannot be verified by`
- L44: `async function resolveMatrix(s,input){`
- L49: `const matrices=unwrap(await context.read('/municipal-requirement-versions?municipality='+encodeURIComponent(municipality)));current(s);`
- L55: `async function createSubmission(input){`
- L57: `if(context.capabilities?.readBackCollection!==true)throw Error('Submission readiness saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L59: `if(!OCCUPANCY.includes(occupation))throw Error('A valid occupancy type is required.');`
- L61: `if(!Number.isFinite(value)||value<=0)throw Error('A positive construction value is required.');`
- L67: `const saved=unwrap(await context.write(path(s,'submission-readiness'),body,{readBackPath:path(s,'submission-readiness')}));current(s);`
- L68: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`
- L75: `async function transitionSubmission(submissionId,{status=STATUS_APPROVED,reason,humanConfirmed=false}={}){`
- L77: `if(humanConfirmed!==true)throw Error('Professional submission review approval requires explicit human confirmation.');`
- L80: `if(!reviewReason)throw Error('A professional review reason is required.');`
- L81: `if(!/^[a-f0-9]{32}$/i.test(String(submissionId||'')))throw Error('A valid submission id is required.');`
- L82: `unwrap(await context.write('/submission-readiness/'+encodeURIComponent(submissionId)+'/approve',{humanConfirmed:true,reviewReason},{readBackPath:path(s,'submission-readiness')}));current(s);`
- L83: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="compliance-hub"></a>

## compliance_hub — Compliance Hub

**Placement:** workspace-tools / comply / family review. **Open:** `openTool('compliance_hub', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Governed calculators and evidence are live; external verification is source dependent.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Record and independently review compliance evidence. Drawing advisory checks and governed drawing workspaces do not certify compliance; source versions and professional responsibility remain mandatory.

**Production native next actions:** compliance-evidence → compliance_hub (projects.write, professional_review); drawing-precheck → drawing_compliance_navigator (tools.use, professional_review)

**Original scaffold boundary (not enhanced-module verdict):** Original has no compliance-item evidence or human review controls. This projection is trust compliance, not an automated cross-standard certification.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/trust-compliance-workspace`; returned complianceItems fields: id, title, status, category, expiresOn.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/compliance.js`:

- L13: `const WARNING = 'Internal compliance evidence only — not statutory certification, municipal approval or an externally verified status. Responsible-professional review remains required.';`
- L44: `async function load(def) {`
- L130: `async function createFlow(def) {`
- L136: `const documentType = root.prompt?.('Document type (e.g. health_safety_plan, cidb_registration, professional_indemnity)', 'health_safety_plan'); if (!documentType?.trim()) throw Error('A document type is required.');`
- L143: `let coverageAmountCents = null, requiredCoverageCents = null;`
- L146: `requiredCoverageCents = Number(root.prompt?.('Required cover (cents)'));`
- L147: `if (!Number.isFinite(coverageAmountCents) || !Number.isFinite(requiredCoverageCents) || requiredCoverageCents <= 0) throw Error('Insurance items require recorded and required cover amounts.');`
- L150: `await adapter.createComplianceItem({ subjectType: String(subjectType).trim(), subjectName: subjectName.trim(), category: String(category).trim(), documentType: documentType.trim(), authority: authority.trim(), referenceNumber: referenceNumber.trim(), grade: 'self_declared', details: {}, expiresOn, coverageAmountCents, requiredCoverageCents, purpose: purpose.trim(), retentionUntil: retentionUntil.trim(), consentGiven: true, humanConfirmed: true });`
- L161: `await adapter.createRegisterEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: ownerRole.trim(), dueOn, details: def.registerKey === 'insurance_register' ? { policyDescription: detailKey.trim() } : { complianceNote: detailKey.trim() }, evidenceFileIds: [], humanConfirmed: true });`
- L166: `async function reviewFlow(itemId) {`
- L177: `await adapter.reviewItem(itemId, { decision: String(decision).trim(), note: note.trim(), provider: provider?.trim() || null, providerReference: providerReference?.trim() || null, humanConfirmed: true });`
- L182: `async function transitionFlow(btn) {`
- L186: `const expectedVersion = Number(btn.getAttribute('data-compliance-version'));`
- L189: `await adapter.transitionEntry(entryId, { status, reason: reason.trim(), expectedVersion, humanConfirmed: true });`
- L194: `async function checklistFlow() {`
- L198: `notice('Drawing checklist: ' + result.overallStatus + ' (not persisted; professional review required).');`
- L201: `async function refresh(def) {`
- L212: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/compliance.js`:

- L30: `async function trustWorkspace(s){const data=unwrap(await context.read(path(s,'trust-compliance-workspace')));current(s);return data;}`
- L31: `async function registerWorkspace(s,key){`
- L32: `const data=unwrap(await context.read(path(s,'controlled-registers/'+encodeURIComponent(key))));current(s);`
- L38: `async function listComplianceItems(){`
- L44: `async function createComplianceItem(input={}){`
- L46: `if(input.humanConfirmed!==true)throw Error('A trust compliance item requires explicit human confirmation.');`
- L47: `if(!text(input.subjectName)||!text(input.authority)||!text(input.referenceNumber))throw Error('Subject name, authority and reference number are required.');`
- L48: `if(!text(input.purpose)||!input.retentionUntil)throw Error('A processing purpose and retention date are required for the record.');`
- L49: `if(input.consentGiven!==true)throw Error('Recorded consent is required before storing contact/company compliance detail.');`
- L55: `requiredCoverageCents:input.requiredCoverageCents??null,purpose:text(input.purpose),`
- L56: `retentionUntil:input.retentionUntil,consentGiven:true,humanConfirmed:true,`
- L58: `const saved=unwrap(await context.write(path(s,'trust/compliance-items'),body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);`
- L65: `async function reviewItem(itemId,input={}){`
- L67: `if(!valid(itemId))throw Error('A trust compliance item id is required.');`
- L68: `if(input.humanConfirmed!==true)throw Error('A compliance review requires explicit human confirmation.');`
- L70: `if(!text(input.note))throw Error('A review note is required.');`
- L72: `const body={decision:input.decision,note:text(input.note),provider:input.provider??null,providerReference:input.providerReference??null,humanConfirmed:true};`
- L73: `unwrap(await context.write('/trust/compliance-items/'+encodeURIComponent(itemId)+'/review',body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);`
- L82: `async function checklist(input={}){`
- L85: `const evaluation=unwrap(await context.write('/drawing-compliance/checklist',body,{readBackPath:'/drawing-compliance/sources'}));current(s);`
- L86: `const sources=unwrap(await context.read('/drawing-compliance/sources'));current(s);`
- L93: `async function drawingReview(input={}){`
- L95: `if(!text(input.title))throw Error('A drawing review title is required.');`
- L96: `if(!text(input.responsibleRole))throw Error('A responsible role is required for a project drawing review.');`
- L98: `const saved=unwrap(await context.write('/drawing-compliance/reviews',body,{readBackPath:r=>'/drawing-compliance/reviews/'+encodeURIComponent(r.id)}));current(s);`
- L100: `const record=unwrap(await context.read('/drawing-compliance/reviews/'+encodeURIComponent(saved.id)));current(s);`
- L108: `async function listRegister(registerKey){`
- L113: `async function createRegisterEntry(registerKey,input={}){`
- L115: `if(input.humanConfirmed!==true)throw Error('A controlled-register entry requires explicit human confirmation.');`
- L116: `if(!text(input.title)||!text(input.reference)||!text(input.ownerRole))throw Error('Title, reference and owner role are required.');`
- L117: `if(!input.details||typeof input.details!=='object'||Array.isArray(input.details))throw Error('A details object is required for a controlled-register entry.');`
- L118: `const body={title:text(input.title),reference:text(input.reference),ownerRole:text(input.ownerRole),dueOn:input.dueOn??null,details:input.details,evidenceFileIds:Array.isArray(input.evidenceFileIds)?input.evidenceFileIds:[],humanConfirmed:true};`
- L119: `const saved=unwrap(await context.write(path(s,'controlled-registers/'+encodeURIComponent(key)),body,{readBackPath:path(s,'controlled-registers/'+encodeURIComponent(key)),readBackCollection:'entries'}));current(s);`
- L127: `async function transitionEntry(entryId,input={}){`
- L129: `if(!valid(entryId))throw Error('A controlled-register entry id is required.');`
- L130: `if(input.humanConfirmed!==true)throw Error('A register transition requires explicit human confirmation.');`
- L131: `if(!text(input.status))throw Error('A target status is required.');`
- L132: `if(!text(input.reason))throw Error('A transition reason is required.');`
- L133: `if(!Number.isInteger(input.expectedVersion)||input.expectedVersion<1)throw Error('A current entry version is required to transition safely.');`
- L134: `const body={status:input.status,reason:text(input.reason),expectedVersion:input.expectedVersion,humanConfirmed:true};`
- L135: `const saved=unwrap(await context.write('/controlled-registers/'+encodeURIComponent(entryId)+'/transition',body,{readBackPath:r=>{`
- L143: `if(record.version!==input.expectedVersion+1)throw Error('Register transition version did not advance; refresh before retrying.');`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="municipal-tracker"></a>

## municipal_tracker — Municipal Tracker

**Placement:** workspace-tools / comply / family review. **Open:** `openTool('municipal_tracker', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Municipal readiness records are live; authority status remains human-entered.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Prepare internal submission-readiness assessments and accountable professional review. This does not lodge an authority submission, record an authority decision automatically or issue a certificate.

**Production native next actions:** readiness → municipal_tracker (projects.write, professional_review)

**Original scaffold boundary (not enhanced-module verdict):** Original has no municipal profile, versioned requirement matrix, supporting evidence or professional sign-off fields. Readiness is not municipal approval.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/submission-readiness`; returned  fields: id, status, createdAt.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/municipal.js`:

- L126: `if(!reason||!reason.trim()){notice('A professional review reason is required. Nothing was submitted.');return;}`
- L129: `try{await adapter.transitionSubmission(id,{status:adapter.statuses.approved,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Professional review approval verified in project records.');}`
- L134: `async function refresh(){`
- L143: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/municipal.js`:

- L5: `// scope(), current(), context.write with readBackPath, a humanConfirmed guard on`
- L31: `async function listSubmissions(){`
- L33: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`
- L36: `async function listReadiness(){`
- L38: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`
- L41: `// The seeded implementation matrix is review-required and cannot be verified by`
- L44: `async function resolveMatrix(s,input){`
- L49: `const matrices=unwrap(await context.read('/municipal-requirement-versions?municipality='+encodeURIComponent(municipality)));current(s);`
- L55: `async function createSubmission(input){`
- L57: `if(context.capabilities?.readBackCollection!==true)throw Error('Submission readiness saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L59: `if(!OCCUPANCY.includes(occupation))throw Error('A valid occupancy type is required.');`
- L61: `if(!Number.isFinite(value)||value<=0)throw Error('A positive construction value is required.');`
- L67: `const saved=unwrap(await context.write(path(s,'submission-readiness'),body,{readBackPath:path(s,'submission-readiness')}));current(s);`
- L68: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`
- L75: `async function transitionSubmission(submissionId,{status=STATUS_APPROVED,reason,humanConfirmed=false}={}){`
- L77: `if(humanConfirmed!==true)throw Error('Professional submission review approval requires explicit human confirmation.');`
- L80: `if(!reviewReason)throw Error('A professional review reason is required.');`
- L81: `if(!/^[a-f0-9]{32}$/i.test(String(submissionId||'')))throw Error('A valid submission id is required.');`
- L82: `unwrap(await context.write('/submission-readiness/'+encodeURIComponent(submissionId)+'/approve',{humanConfirmed:true,reviewReason},{readBackPath:path(s,'submission-readiness')}));current(s);`
- L83: `const data=unwrap(await context.read(path(s,'submission-readiness')));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="environmental-heritage"></a>

## environmental_heritage — Environmental & Heritage

**Placement:** workspace-tools / brief / family review. **Open:** `openTool('environmental_heritage', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** RegulatoryCaseRoutes with versioned cases and evidence.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no case type, authority, property facts, evidence or professional determination fields.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/regulatory-cases`; returned  fields: id, title, caseNumber, caseType, status, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/regulatory.js`:

- L13: `const ROUTES = ['no_ea_indicated','basic_assessment','scoping_eia','nid','heritage_impact_assessment','permit_required','other'];`
- L30: `async function load(def) { return adapter.listCases(def.caseType); }`
- L106: `adapter.determine(id, { professionalRole: role.trim(), route: route.trim(), reason: reason.trim(), externalReference: reference.trim(), humanConfirmed: true })`
- L121: `async function refresh(def) {`
- L129: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/regulatory.js`:

- L5: `// municipal adapter contract exactly: scope(), current(), context.write with an exact`
- L17: `const ROUTES=['no_ea_indicated','basic_assessment','scoping_eia','nid','heritage_impact_assessment','permit_required','other'];`
- L28: `function url(v){const t=text(v,1000,'A valid http(s) source URL is required.');if(!/^https?:\/\//i.test(t))throw Error('A valid http(s) source URL is required.');return t;}`
- L29: `function date(v){const t=text(v,10,'A YYYY-MM-DD date is required.');if(!/^\d{4}-\d{2}-\d{2}$/.test(t))throw Error('A YYYY-MM-DD date is required.');return t;}`
- L30: `function sourceBasis(v){if(!Array.isArray(v)||v.length===0||v.length>20)throw Error('At least one source-basis entry (title, url, retrievedAt) is required.');return v.map(s=>{if(!s||typeof s!=='object')throw Error('Each source-basis entry needs title, url and retrievedAt.');return {title:text(s.title,220,'Each source-basis entry needs a title, url and retrievedAt.'),url:url(s.url),retrievedAt:date(s.retrievedAt)};});}`
- L32: `async function listCases(type){`
- L35: `const data=unwrap(await context.read(listPath(s,type)));current(s);`
- L38: `async function createCase(input){`
- L40: `if(context.capabilities?.readBackCollection!==true)throw Error('Regulatory case saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L42: `if(!CASE_TYPES.includes(caseType))throw Error('A valid case type (environmental or heritage) is required.');`
- L43: `const body={caseType,caseNumber:text(input?.caseNumber,80,'A case number is required.'),title:text(input?.title,220,'A case title is required.'),authorityName:text(input?.authorityName,220,'The responsible authority name is required.'),responsibleRole:text(input?.responsibleRole,80,'A responsible professional role is required.'),sourceBasis:sourceBasis(input?.sourceBasis)};`
- L45: `const saved=unwrap(await context.write(listPath(s,null),body,{readBackPath:readPath}));current(s);`
- L46: `const data=unwrap(await context.read(readPath));current(s);`
- L51: `async function addEvidence(caseId,input){`
- L53: `const id=hex(caseId,'A valid regulatory case id is required.');`
- L54: `const existing=unwrap(await context.read(casePath(id)));current(s);`
- L58: `if(!EVIDENCE_TYPES.includes(evidenceType))throw Error('A valid evidence type is required.');`
- L60: `if(!EVIDENCE_STATUSES.includes(status))throw Error('A valid evidence status is required.');`
- L61: `const expected=Number.isInteger(input?.expectedVersion)?input.expectedVersion:(Number(existing.version)||1);`
- L63: `const body={expectedVersion:expected,evidenceType,title:text(input?.title,220,'An evidence title is required.'),status,externalReference:text(input?.externalReference,180,'An external reference is required.'),sourceUrl:url(input?.sourceUrl),observedAt:date(input?.observedAt),payload};`
- L64: `const saved=unwrap(await context.write(casePath(id)+'/evidence',body,{readBackPath:casePath(id),readBackCollection:'evidence'}));current(s);`
- L65: `const after=unwrap(await context.read(casePath(id)));current(s);`
- L74: `async function determine(caseId,input){`
- L76: `if(input?.humanConfirmed!==true)throw Error('Regulatory determination requires explicit human confirmation.');`
- L77: `const id=hex(caseId,'A valid regulatory case id is required.');`
- L79: `if(!ROUTES.includes(route))throw Error('A valid determination route is required.');`
- L80: `const before=unwrap(await context.read(casePath(id)));current(s);`
- L81: `const body={expectedVersion:Number.isInteger(input?.expectedVersion)?input.expectedVersion:(Number(before?.version)||1),humanConfirmed:true,professionalRole:text(input?.professionalRole,80,'The determining professional role is required.'),route,reason:text(input?.reason,500,'A determination reason is required.'),externalReference:text(input?.externalReference,180,'An external determination reference is required.')};`
- L82: `const saved=unwrap(await context.write(casePath(id)+'/determine',body,{readBackPath:casePath(id)}));current(s);`
- L83: `const after=unwrap(await context.read(casePath(id)));current(s);`
- L87: `return {boundary:BOUNDARY,caseTypes:CASE_TYPES.slice(),evidenceTypes:EVIDENCE_TYPES.slice(),evidenceStatuses:EVIDENCE_STATUSES.slice(),routes:ROUTES.slice(),requiredEvidence:REQUIRED_EVIDENCE,listCases,createCase,addEvidence,determine};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="eia-workspace"></a>

## eia_workspace — EIA Workspace

**Placement:** workspace-tools / brief / family review. **Open:** `openTool('eia_workspace', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** RegulatoryCaseRoutes with assessment and participation states.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no screening facts, studies, participation, EMPr or route determination form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/regulatory-cases?type=environmental`; returned  fields: id, title, caseNumber, caseType, status, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


Source `app/public/assets/v1-clone/js/modules/regulatory.js`:

- L13: `const ROUTES = ['no_ea_indicated','basic_assessment','scoping_eia','nid','heritage_impact_assessment','permit_required','other'];`
- L30: `async function load(def) { return adapter.listCases(def.caseType); }`
- L106: `adapter.determine(id, { professionalRole: role.trim(), route: route.trim(), reason: reason.trim(), externalReference: reference.trim(), humanConfirmed: true })`
- L121: `async function refresh(def) {`
- L129: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/regulatory.js`:

- L5: `// municipal adapter contract exactly: scope(), current(), context.write with an exact`
- L17: `const ROUTES=['no_ea_indicated','basic_assessment','scoping_eia','nid','heritage_impact_assessment','permit_required','other'];`
- L28: `function url(v){const t=text(v,1000,'A valid http(s) source URL is required.');if(!/^https?:\/\//i.test(t))throw Error('A valid http(s) source URL is required.');return t;}`
- L29: `function date(v){const t=text(v,10,'A YYYY-MM-DD date is required.');if(!/^\d{4}-\d{2}-\d{2}$/.test(t))throw Error('A YYYY-MM-DD date is required.');return t;}`
- L30: `function sourceBasis(v){if(!Array.isArray(v)||v.length===0||v.length>20)throw Error('At least one source-basis entry (title, url, retrievedAt) is required.');return v.map(s=>{if(!s||typeof s!=='object')throw Error('Each source-basis entry needs title, url and retrievedAt.');return {title:text(s.title,220,'Each source-basis entry needs a title, url and retrievedAt.'),url:url(s.url),retrievedAt:date(s.retrievedAt)};});}`
- L32: `async function listCases(type){`
- L35: `const data=unwrap(await context.read(listPath(s,type)));current(s);`
- L38: `async function createCase(input){`
- L40: `if(context.capabilities?.readBackCollection!==true)throw Error('Regulatory case saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L42: `if(!CASE_TYPES.includes(caseType))throw Error('A valid case type (environmental or heritage) is required.');`
- L43: `const body={caseType,caseNumber:text(input?.caseNumber,80,'A case number is required.'),title:text(input?.title,220,'A case title is required.'),authorityName:text(input?.authorityName,220,'The responsible authority name is required.'),responsibleRole:text(input?.responsibleRole,80,'A responsible professional role is required.'),sourceBasis:sourceBasis(input?.sourceBasis)};`
- L45: `const saved=unwrap(await context.write(listPath(s,null),body,{readBackPath:readPath}));current(s);`
- L46: `const data=unwrap(await context.read(readPath));current(s);`
- L51: `async function addEvidence(caseId,input){`
- L53: `const id=hex(caseId,'A valid regulatory case id is required.');`
- L54: `const existing=unwrap(await context.read(casePath(id)));current(s);`
- L58: `if(!EVIDENCE_TYPES.includes(evidenceType))throw Error('A valid evidence type is required.');`
- L60: `if(!EVIDENCE_STATUSES.includes(status))throw Error('A valid evidence status is required.');`
- L61: `const expected=Number.isInteger(input?.expectedVersion)?input.expectedVersion:(Number(existing.version)||1);`
- L63: `const body={expectedVersion:expected,evidenceType,title:text(input?.title,220,'An evidence title is required.'),status,externalReference:text(input?.externalReference,180,'An external reference is required.'),sourceUrl:url(input?.sourceUrl),observedAt:date(input?.observedAt),payload};`
- L64: `const saved=unwrap(await context.write(casePath(id)+'/evidence',body,{readBackPath:casePath(id),readBackCollection:'evidence'}));current(s);`
- L65: `const after=unwrap(await context.read(casePath(id)));current(s);`
- L74: `async function determine(caseId,input){`
- L76: `if(input?.humanConfirmed!==true)throw Error('Regulatory determination requires explicit human confirmation.');`
- L77: `const id=hex(caseId,'A valid regulatory case id is required.');`
- L79: `if(!ROUTES.includes(route))throw Error('A valid determination route is required.');`
- L80: `const before=unwrap(await context.read(casePath(id)));current(s);`
- L81: `const body={expectedVersion:Number.isInteger(input?.expectedVersion)?input.expectedVersion:(Number(before?.version)||1),humanConfirmed:true,professionalRole:text(input?.professionalRole,80,'The determining professional role is required.'),route,reason:text(input?.reason,500,'A determination reason is required.'),externalReference:text(input?.externalReference,180,'An external determination reference is required.')};`
- L82: `const saved=unwrap(await context.write(casePath(id)+'/determine',body,{readBackPath:casePath(id)}));current(s);`
- L83: `const after=unwrap(await context.read(casePath(id)));current(s);`
- L87: `return {boundary:BOUNDARY,caseTypes:CASE_TYPES.slice(),evidenceTypes:EVIDENCE_TYPES.slice(),evidenceStatuses:EVIDENCE_STATUSES.slice(),routes:ROUTES.slice(),requiredEvidence:REQUIRED_EVIDENCE,listCases,createCase,addEvidence,determine};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="refuse-calculator"></a>

## refuse_calculator — Refuse Area Calculator

**Placement:** workspace-tools / design / family create. **Open:** `openTool('refuse_calculator', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Source-controlled rules, persisted runs and advisory exports.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no ruleVersionId, occupancyUse, quantity, collection interval, bin/storage/facility inputs or professional declaration. History is user-scoped and bounded.

**Read input/output contracts:**
- `/api/v1/toolbox/runs`; returned  fields: id, toolId, status, createdAt.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/calc-workspace.js`:

- L53: `<div class="rfi-hero"><div><h1>Engineering &amp; Discipline Calculators</h1><p>${Object.keys(CALCS).length} real backend engines — advisory results, professional verification required.</p></div></div>`
- L73: `async function run(id, c, panel) {`
- L88: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


Source `app/public/assets/v1-clone/js/modules/refuse-calculator.js`:

- L49: `async function refresh(forceCatalogue, token = generation) {`
- L75: `${cell('refuseStorageArea', 'Storage area required', results ? results.requiredStorageAreaM2 : null, 'm²')}`
- L76: `${cell('binAllocation', 'Bins required', results ? \`${results.binsRequired} × ${results.binCapacityLitres}\` : null, 'litre')}`
- L108: `<label class="field"><span>Municipality</span><input data-refuse-field="municipality" value="${esc(value('municipality'))}" placeholder="City of Example" required></label>`
- L109: `<label class="field"><span>Building type</span><input data-refuse-field="buildingType" value="${esc(value('buildingType'))}" placeholder="Residential apartment" required></label>`
- L110: `<label class="field"><span>Title</span><input data-refuse-field="title" value="${esc(value('title'))}" placeholder="Refuse store — block A" required></label>`
- L118: `<label class="field"><span>Occupancy (persons)</span><input data-refuse-field="occupancy" type="number" min="1" step="1" value="${esc(value('occupancy'))}" required></label>`
- L120: `<label class="field"><span>Refuse generation rate (L/person/day)</span><input data-refuse-field="generationRateLitresPerPersonDay" type="number" min="0.1" step="0.1" value="${esc(value('generationRateLitresPerPersonDay', defaults.generationRateLitresPerPersonDay))}" required></label>`
- L127: `<label class="field"><span>Collection interval (days)</span><input data-refuse-field="collectionIntervalDays" type="number" min="1" max="31" step="1" value="${esc(value('collectionIntervalDays', defaults.collectionIntervalDays))}" required></label>`
- L128: `<label class="field"><span>Bin capacity (litres)</span><input data-refuse-field="binCapacityLitres" type="number" min="30" step="10" value="${esc(value('binCapacityLitres', defaults.binCapacityLitres))}" required></label>`
- L189: `const area = record?.results?.requiredStorageAreaM2;`
- L203: `<p class="refuse-hint">${bins ? \`${bins} bin positions at the required area of ${esc(area)} m².\` : 'Save a calculation to size this layout.'} This sketch illustrates arrangement only — it asserts no dimension.</p>`
- L247: `<input class="refuse-note" data-refuse-declaration placeholder="Basis of the declaration (required)" maxlength="500"${judged.length === 0 ? ' disabled' : ''}>`
- L258: `<p class="refuse-hint">${icon('lockedUntilSignOff', 14)} PDF export is <b>unavailable</b>: no PDF provider is configured for this deployment. The figures remain in the record and can be read from the API.</p>`
- L259: `<button class="btn" type="button" disabled title="No PDF provider is configured. This control stays unavailable rather than producing an empty file.">Export PDF — provider not configured</button>`
- L272: `<td>${esc(record.results?.requiredStorageAreaM2 ?? '—')} m²</td>`
- L297: `: \`<input data-refuse-review-reason="${esc(pack.id)}" placeholder="Reason (required)" maxlength="500"><button class="btn" data-refuse-verify="${esc(pack.id)}">Verify</button>\`}`
- L305: `<label class="field"><span>Source (required)</span><input data-rule="sourceReference" placeholder="Solid Waste By-law, schedule 3"></label>`
- L313: `<label class="field checkbox"><input type="checkbox" data-rule="drainage_required"> <span>Wash-down drainage required</span></label>`
- L396: `async function guarded(button, work, success) {`
- L425: `if (!fields.municipality || !fields.title || !fields.buildingType) { notice('Municipality, building type and title are required.'); return; }`
- L469: `if (!reason) { notice('A verification reason is required — it is recorded on the pack.'); return; }`
- L488: `if (main.querySelector('[data-rule="drainage_required"]')?.checked) criteria.drainage_required = { value: true, source: read('sourceReference') };`
- L492: `}), 'Rule pack stored as review_required. Verify it before its criteria are applied.');`
- L497: `async function apply(toolId) {`
- L517: `return { apply, dispose, supports: id => id === TOOL };`

Source `app/public/assets/v1-clone/js/modules/scaffold-records.js`:

- L15: `refuse_calculator: 'Stored calculation inputs — this is not a certified result, and a professional declaration is required before use.',`
- L50: `async function specFor(toolId) {`
- L55: `function fieldInput(name, spec, required) {`
- L56: `const requiredAttr = required ? ' required' : '';`
- L58: `return \`<label class="field"><span>${esc(label(name))}${required ? ' *' : ''}</span>`
- L59: `<select data-field="${esc(name)}"${requiredAttr}><option value="">Not set</option>${['low', 'medium', 'high', 'critical'].map(v => \`<option value="${v}">${v}</option>\`).join('')}</select></label>\`;`
- L62: `return \`<label class="field"><span>${esc(label(name))}${required ? ' *' : ''}</span><input data-field="${esc(name)}" type="text"${requiredAttr} placeholder="${name === 'dueOn' ? 'YYYY-MM-DD' : ''}"></label>\`;`
- L64: `return \`<label class="field"><span>${esc(label(name))}${required ? ' *' : ''}</span><input data-field="${esc(name)}" type="text"${requiredAttr}></label>\`;`
- L68: `const required = spec.requiredFields || [];`
- L69: `const base = ['title', 'detail'].concat(BASE_INPUTS.filter(f => required.includes(f) || ['ownerRole', 'dueOn', 'severity'].includes(f)));`
- L72: `.filter(name => !['reference'].includes(name) || required.includes('reference'))`
- L73: `.map(name => fieldInput(name, spec, required.includes(name)));`
- L81: `<label class="field checkbox"><input type="checkbox" data-field="humanConfirmed"> <span>I confirm this record as a human decision</span></label>`
- L82: `<label class="field"><span>Reason / note (required for a decision status)</span><input data-field="confirmationNote" type="text" maxlength="500"></label>`
- L105: `<input data-record-reason="${esc(record.id)}" type="text" placeholder="Reason (required)" maxlength="500">`
- L115: `async function render(toolId) {`
- L193: `if (name in fields && !['title', 'detail', 'ownerRole', 'dueOn', 'severity', 'reference', 'status', 'humanConfirmed', 'confirmationNote', 'evidenceFileIds'].includes(name)) {`
- L200: `if (fields.humanConfirmed === true) body.humanConfirmed = true;`
- L213: `if (!body.title) throw Error('A title is required.');`
- L231: `if (!reason) { notice('A reason is required for a status change.'); return; }`
- L257: `notice(error.message + ' History unavailable.');`
- L281: `async function bindScaffoldActions(main, toolId) {`
- L340: `async function apply(toolId) {`
- L357: `return { apply, bindScaffoldActions, dispose, supports: toolId => RECORD_TOOLS.includes(toolId), recordTools: RECORD_TOOLS };`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="survey-geomatics"></a>

## survey_geomatics — Survey & Geomatics

**Placement:** workspace-tools / brief / family create. **Open:** `openTool('survey_geomatics', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** SurveyJobRoutes, appointments, outputs and ProjectRecord write-back.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no appointment, cadastral reference, observations, deliverable or issue-declaration fields.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/survey-jobs`; returned  fields: id, title, jobNumber, status, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/survey.js`:

- L144: `const raw=root.prompt?.(label);if(raw==null||String(raw).trim()===''){notice(label+' is required. Nothing was estimated.');return null;}`
- L215: `async function refresh(){`
- L228: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/survey.js`:

- L5: `// scope(), current(), context.write with an exact read-back path, an explicit`
- L26: `function text(v,name,max){const t=typeof v==='string'?v.trim():v==null?'':String(v).trim();if(!t)throw Error(name+' is required.');if(t.length>max)throw Error(name+' is too long.');return t;}`
- L27: `function choice(v,allowed,name){if(!allowed.includes(v))throw Error('A valid '+name+' is required.');return v;}`
- L29: `function id(v,name){if(!/^[a-f0-9]{32}$/i.test(String(v==null?'':v)))throw Error('A valid '+name+' is required.');return String(v).toLowerCase();}`
- L46: `function object(v,name){if(!v||typeof v!=='object'||Array.isArray(v))throw Error(name+' is required.');return v;}`
- L48: `if(!Array.isArray(v)||v.length===0)throw Error('At least one source-basis reference is required.');`
- L51: `async function readJob(s,jobId){const job=unwrap(await context.read(jobPath(jobId)));current(s);if(!job||job.id!==jobId)throw Error('Survey job read-back failed; refresh before retrying.');return job;}`
- L52: `async function versionOf(s,jobId,override){if(Number.isInteger(override)&&override>=1)return override;return (await readJob(s,jobId)).version;}`
- L53: `async function listJobs(){`
- L55: `const data=unwrap(await context.read(path(s,'survey-jobs')));current(s);`
- L59: `async function getJob(jobId){const s=scope();return readJob(s,id(jobId,'survey job id'));}`
- L60: `async function createJob(input){`
- L62: `if(context.capabilities?.readBackCollection!==true)throw Error('Survey job saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L64: `const saved=unwrap(await context.write(path(s,'survey-jobs'),body,{readBackPath:path(s,'survey-jobs')}));current(s);`
- L70: `async function addObservation(jobId,jobInput){`
- L72: `if(context.capabilities?.readBackCollection!==true)throw Error('Observation saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L74: `const expectedVersion=await versionOf(s,j,jobInput?.expectedVersion);`
- L75: `const body={expectedVersion,payload:object(jobInput?.payload,'Observation payload'),pointCode:text(jobInput?.pointCode,'Point code',80),observationType:choice(jobInput?.observationType,OBSERVATION_TYPES,'observation type'),easting:decimal(jobInput?.easting,4,'Easting'),northing:decimal(jobInput?.northing,4,'Northing'),elevation:decimal(jobInput?.elevation,4,'Elevation'),status:choice(jobInput?.status,OBSERVATION_STATUSES,'observation status'),sourceReference:text(jobInput?.sourceReference,'Source reference',180),sourceUrl:url(jobInput?.sourceUrl,'Source URL'),observedAt:date(jobInput?.observedAt,'Observation date')};`
- L76: `const saved=unwrap(await context.write(jobPath(j)+'/observations',body,{readBackPath:jobPath(j),readBackCollection:'observations'}));current(s);`
- L82: `async function addDeliverable(jobId,jobInput){`
- L84: `if(context.capabilities?.readBackCollection!==true)throw Error('Deliverable saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L89: `const expectedVersion=Number.isInteger(jobInput?.expectedVersion)&&jobInput.expectedVersion>=1?jobInput.expectedVersion:before.version;`
- L91: `const body={expectedVersion,payload:object(jobInput?.payload,'Deliverable payload'),deliverableType:choice(jobInput?.deliverableType,DELIVERABLE_TYPES,'deliverable type'),title:text(jobInput?.title,'Deliverable title',220),status:choice(jobInput?.status,DELIVERABLE_STATUSES,'deliverable status'),externalReference:text(jobInput?.externalReference,'External reference',180),sourceUrl:url(jobInput?.sourceUrl,'Source URL')};`
- L92: `unwrap(await context.write(jobPath(j)+'/deliverables',body,{readBackPath:jobPath(j)}));current(s);`
- L98: `async function addFee(jobId,feeInput){`
- L101: `const expectedVersion=await versionOf(s,j,feeInput?.expectedVersion);`
- L102: `const body={expectedVersion,fieldDays:hundredths(feeInput?.fieldDays,'Field days'),officeDays:hundredths(feeInput?.officeDays,'Office days'),travelDistanceKm:hundredths(feeInput?.travelDistanceKm,'Travel distance'),fieldDayRateCents:money(feeInput?.fieldDayRateCents,'Field day rate'),officeDayRateCents:money(feeInput?.officeDayRateCents,'Office day rate'),travelRateCentsPerKm:money(feeInput?.travelRateCentsPerKm,'Travel rate'),complexityAllowanceCents:money(feeInput?.complexityAllowanceCents,'Complexity allowance'),statutoryFeeCents:money(feeInput?.statutoryFeeCents,'Statutory fee'),statutorySourceTitle:text(feeInput?.statutorySourceTitle,'Statutory source title',220),statutorySourceUrl:url(feeInput?.statutorySourceUrl,'Statutory source URL'),statutorySourceRetrievedAt:date(feeInput?.statutorySourceRetrievedAt,'Statutory source date')};`
- L103: `unwrap(await context.write(jobPath(j)+'/fees',body,{readBackPath:jobPath(j)}));current(s);`
- L112: `async function issue(jobId,{confirmed=false,professionalRole,reason,reference,expectedVersion}={}){`
- L116: `const expected=await versionOf(s,j,expectedVersion);`
- L117: `const body={expectedVersion:expected,humanConfirmed:true,professionalRole:role(professionalRole),reason:text(reason,'Issue reason',500),externalReference:text(reference,'Issue reference',180)};`
- L118: `unwrap(await context.write(jobPath(j)+'/issue',body,{readBackPath:jobPath(j)}));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="nhbrc-enrolment"></a>

## nhbrc_enrolment — NHBRC Enrolment

**Placement:** workspace-tools / comply / family administer. **Open:** `openTool('nhbrc_enrolment', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Controlled register persists enrolment states; external submission is false.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form. This is internal evidence, not NHBRC submission.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/controlled-registers/nhbrc_enrolment`; returned entries fields: id, title, reference, status, ownerRole, dueOn, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/registers.js`:

- L46: `async function load(def) {`
- L86: `async function createForm(def, main) {`
- L97: `adapter.createEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: role.trim(), dueOn: due || null, details: { summary: summary.trim() }, evidenceFileIds: [], humanConfirmed: true })`
- L111: `async function transitionForm(def, entry, target) {`
- L116: `adapter.transitionEntry(entry.id, { expectedVersion: entry.version, status: target, reason: reason.trim(), humanConfirmed: true })`
- L121: `async function refresh(def) {`
- L128: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/registers.js`:

- L54: `async function workspace(registerKey) {`
- L56: `const ws = workspaceOf(await context.read(registerPath(s, k)));`
- L60: `async function listEntries(registerKey) {`
- L63: `async function createEntry(registerKey, input) {`
- L66: `if (input.humanConfirmed !== true) throw Error('Controlled register entry requires explicit human confirmation.');`
- L68: `title: text(input.title, 220, 'A register entry title is required.'),`
- L69: `reference: text(input.reference, 120, 'A register reference is required.'),`
- L70: `ownerRole: text(input.ownerRole, 80, 'An owner role is required.'),`
- L74: `humanConfirmed: true,`
- L76: `const saved = unwrap(await context.write(registerPath(s, k), body, { readBackPath: registerPath(s, k), readBackCollection: 'entries' }));`
- L78: `const ws = workspaceOf(await context.read(registerPath(s, k)));`
- L84: `async function transitionEntry(entryId, input) {`
- L87: `if (input.humanConfirmed !== true) throw Error('Controlled register transition requires explicit human confirmation.');`
- L88: `if (!/^[a-f0-9]{32}$/i.test(entryId || '')) throw Error('A valid controlled register entry id is required.');`
- L89: `const expectedVersion = input.expectedVersion;`
- L90: `if (!Number.isInteger(expectedVersion) || expectedVersion < 1) throw Error('A current entry version (expectedVersion) is required.');`
- L91: `const status = text(input.status, 80, 'A target status is required.');`
- L92: `const reason = text(input.reason, 1000, 'A transition reason is required.');`
- L93: `const saved = unwrap(await context.write(`
- L95: `{ expectedVersion, status, reason, humanConfirmed: true },`
- L100: `const ws = workspaceOf(await context.read(registerPath(s, k)));`
- L103: `if (!record || record.status !== status || record.version !== expectedVersion + 1) throw Error('Controlled register transition not verified; refresh before retrying.');`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="insurance-register"></a>

## insurance_register — Insurance Register

**Placement:** workspace-tools / appoint / family administer. **Open:** `openTool('insurance_register', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Controlled register persists policy evidence, expiry and versions.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no policy title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/controlled-registers/insurance_register`; returned entries fields: id, title, reference, status, ownerRole, dueOn, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/compliance.js`:

- L13: `const WARNING = 'Internal compliance evidence only — not statutory certification, municipal approval or an externally verified status. Responsible-professional review remains required.';`
- L44: `async function load(def) {`
- L130: `async function createFlow(def) {`
- L136: `const documentType = root.prompt?.('Document type (e.g. health_safety_plan, cidb_registration, professional_indemnity)', 'health_safety_plan'); if (!documentType?.trim()) throw Error('A document type is required.');`
- L143: `let coverageAmountCents = null, requiredCoverageCents = null;`
- L146: `requiredCoverageCents = Number(root.prompt?.('Required cover (cents)'));`
- L147: `if (!Number.isFinite(coverageAmountCents) || !Number.isFinite(requiredCoverageCents) || requiredCoverageCents <= 0) throw Error('Insurance items require recorded and required cover amounts.');`
- L150: `await adapter.createComplianceItem({ subjectType: String(subjectType).trim(), subjectName: subjectName.trim(), category: String(category).trim(), documentType: documentType.trim(), authority: authority.trim(), referenceNumber: referenceNumber.trim(), grade: 'self_declared', details: {}, expiresOn, coverageAmountCents, requiredCoverageCents, purpose: purpose.trim(), retentionUntil: retentionUntil.trim(), consentGiven: true, humanConfirmed: true });`
- L161: `await adapter.createRegisterEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: ownerRole.trim(), dueOn, details: def.registerKey === 'insurance_register' ? { policyDescription: detailKey.trim() } : { complianceNote: detailKey.trim() }, evidenceFileIds: [], humanConfirmed: true });`
- L166: `async function reviewFlow(itemId) {`
- L177: `await adapter.reviewItem(itemId, { decision: String(decision).trim(), note: note.trim(), provider: provider?.trim() || null, providerReference: providerReference?.trim() || null, humanConfirmed: true });`
- L182: `async function transitionFlow(btn) {`
- L186: `const expectedVersion = Number(btn.getAttribute('data-compliance-version'));`
- L189: `await adapter.transitionEntry(entryId, { status, reason: reason.trim(), expectedVersion, humanConfirmed: true });`
- L194: `async function checklistFlow() {`
- L198: `notice('Drawing checklist: ' + result.overallStatus + ' (not persisted; professional review required).');`
- L201: `async function refresh(def) {`
- L212: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/compliance.js`:

- L30: `async function trustWorkspace(s){const data=unwrap(await context.read(path(s,'trust-compliance-workspace')));current(s);return data;}`
- L31: `async function registerWorkspace(s,key){`
- L32: `const data=unwrap(await context.read(path(s,'controlled-registers/'+encodeURIComponent(key))));current(s);`
- L38: `async function listComplianceItems(){`
- L44: `async function createComplianceItem(input={}){`
- L46: `if(input.humanConfirmed!==true)throw Error('A trust compliance item requires explicit human confirmation.');`
- L47: `if(!text(input.subjectName)||!text(input.authority)||!text(input.referenceNumber))throw Error('Subject name, authority and reference number are required.');`
- L48: `if(!text(input.purpose)||!input.retentionUntil)throw Error('A processing purpose and retention date are required for the record.');`
- L49: `if(input.consentGiven!==true)throw Error('Recorded consent is required before storing contact/company compliance detail.');`
- L55: `requiredCoverageCents:input.requiredCoverageCents??null,purpose:text(input.purpose),`
- L56: `retentionUntil:input.retentionUntil,consentGiven:true,humanConfirmed:true,`
- L58: `const saved=unwrap(await context.write(path(s,'trust/compliance-items'),body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);`
- L65: `async function reviewItem(itemId,input={}){`
- L67: `if(!valid(itemId))throw Error('A trust compliance item id is required.');`
- L68: `if(input.humanConfirmed!==true)throw Error('A compliance review requires explicit human confirmation.');`
- L70: `if(!text(input.note))throw Error('A review note is required.');`
- L72: `const body={decision:input.decision,note:text(input.note),provider:input.provider??null,providerReference:input.providerReference??null,humanConfirmed:true};`
- L73: `unwrap(await context.write('/trust/compliance-items/'+encodeURIComponent(itemId)+'/review',body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);`
- L82: `async function checklist(input={}){`
- L85: `const evaluation=unwrap(await context.write('/drawing-compliance/checklist',body,{readBackPath:'/drawing-compliance/sources'}));current(s);`
- L86: `const sources=unwrap(await context.read('/drawing-compliance/sources'));current(s);`
- L93: `async function drawingReview(input={}){`
- L95: `if(!text(input.title))throw Error('A drawing review title is required.');`
- L96: `if(!text(input.responsibleRole))throw Error('A responsible role is required for a project drawing review.');`
- L98: `const saved=unwrap(await context.write('/drawing-compliance/reviews',body,{readBackPath:r=>'/drawing-compliance/reviews/'+encodeURIComponent(r.id)}));current(s);`
- L100: `const record=unwrap(await context.read('/drawing-compliance/reviews/'+encodeURIComponent(saved.id)));current(s);`
- L108: `async function listRegister(registerKey){`
- L113: `async function createRegisterEntry(registerKey,input={}){`
- L115: `if(input.humanConfirmed!==true)throw Error('A controlled-register entry requires explicit human confirmation.');`
- L116: `if(!text(input.title)||!text(input.reference)||!text(input.ownerRole))throw Error('Title, reference and owner role are required.');`
- L117: `if(!input.details||typeof input.details!=='object'||Array.isArray(input.details))throw Error('A details object is required for a controlled-register entry.');`
- L118: `const body={title:text(input.title),reference:text(input.reference),ownerRole:text(input.ownerRole),dueOn:input.dueOn??null,details:input.details,evidenceFileIds:Array.isArray(input.evidenceFileIds)?input.evidenceFileIds:[],humanConfirmed:true};`
- L119: `const saved=unwrap(await context.write(path(s,'controlled-registers/'+encodeURIComponent(key)),body,{readBackPath:path(s,'controlled-registers/'+encodeURIComponent(key)),readBackCollection:'entries'}));current(s);`
- L127: `async function transitionEntry(entryId,input={}){`
- L129: `if(!valid(entryId))throw Error('A controlled-register entry id is required.');`
- L130: `if(input.humanConfirmed!==true)throw Error('A register transition requires explicit human confirmation.');`
- L131: `if(!text(input.status))throw Error('A target status is required.');`
- L132: `if(!text(input.reason))throw Error('A transition reason is required.');`
- L133: `if(!Number.isInteger(input.expectedVersion)||input.expectedVersion<1)throw Error('A current entry version is required to transition safely.');`
- L134: `const body={status:input.status,reason:text(input.reason),expectedVersion:input.expectedVersion,humanConfirmed:true};`
- L135: `const saved=unwrap(await context.write('/controlled-registers/'+encodeURIComponent(entryId)+'/transition',body,{readBackPath:r=>{`
- L143: `if(record.version!==input.expectedVersion+1)throw Error('Register transition version did not advance; refresh before retrying.');`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="bim-ifc"></a>

## bim_ifc — BIM / IFC Extraction

**Placement:** workspace-tools / design / family create. **Open:** `openTool('bim_ifc', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** ModelMetadataRoutes, provenance, revisions, review and issue.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no IFC file input, discipline, model source/revision or mapping decisions. No model parsing is simulated.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/model-sources`; returned  fields: id, name, title, status, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


Source `app/public/assets/v1-clone/js/modules/models.js`:

- L37: `async function load(token=generation){const value=await adapter.listSources();if(disposed||!ownsRoute(TOOL)||token!==generation)return null;sources=value;return sources;}`
- L154: `<p class="muted">Freezes an immutable snapshot hash of the approved revision. Professional confirmation required.</p>`
- L241: `humanConfirmed:main.querySelector('[data-models-dec-human]')?.checked===true,`
- L243: `if(input.humanConfirmed!==true){notice('Confirm the mapping decision checkbox first.');return;}`
- L260: `humanConfirmed:main.querySelector('[data-models-iss-human]')?.checked===true,`
- L262: `if(input.humanConfirmed!==true){notice('Confirm the professional issue checkbox first.');return;}`
- L271: `async function refresh(){`
- L281: `async function apply(toolId){`
- L285: `return ok?{applied:true,tool:TOOL}:{applied:false,tool:TOOL,reason:'workspace unavailable'};`
- L287: `function supports(toolId){return toolId===TOOL;}`
- L291: `return {apply,dispose,supports,spec:{tool:TOOL,name:'BIM / IFC Metadata',functional:true}};`

Source `app/public/assets/v1-clone/js/adapters/models.js`:

- L25: `function required(v,max,label){const s=typeof v==='string'?v.trim():'';if(s===''||s.length>max)throw Error(label+' is required (max '+max+' characters).');return s;}`
- L27: `function role(v){const s=typeof v==='string'?v.trim():'';if(!/^[a-z][a-z0-9_]{1,79}$/.test(s))throw Error('A valid responsible professional role is required.');return s;}`
- L33: `async function loadSource(s,sourceId){`
- L35: `const data=unwrap(await context.read(sourcePath(sourceId)));current(s);`
- L48: `const label=required(f.label,220,'Fact label');`
- L57: `async function listSources(){`
- L59: `const data=unwrap(await context.read(listPath(s)));current(s);`
- L64: `async function createSource(input){`
- L74: `title:required(input?.title,220,'Model source title'),`
- L76: `schemaVersion:required(input?.schemaVersion,80,'Schema version'),`
- L77: `sourceRevision:required(input?.sourceRevision,80,'Source revision'),`
- L81: `assumptions:assumptionsIn.map(a=>required(a,500,'Assumption')),`
- L83: `const saved=unwrap(await context.write(listPath(s),body,{readBackPath:r=>sourcePath(r?.id)}));current(s);`
- L85: `const record=unwrap(await context.read(sourcePath(saved.id)));current(s);`
- L89: `// (c) Add a metadata revision (facts + notes). Concurrency guarded by expectedVersion.`
- L90: `async function addRevision(input){`
- L96: `expectedVersion:source.version,`
- L97: `revisionLabel:required(input?.revisionLabel,120,'Revision label'),`
- L101: `const saved=unwrap(await context.write(sourcePath(sourceId)+'/revisions',body,{readBackPath:sourcePath(sourceId),readBackCollection:'revisions'}));current(s);`
- L109: `async function decide(input){`
- L111: `if(input?.humanConfirmed!==true)throw Error('A mapping decision requires explicit human confirmation.');`
- L123: `humanConfirmed:true,`
- L124: `reason:required(input?.reason,500,'Decision reason'),`
- L126: `const saved=unwrap(await context.write('/model-metadata-revisions/'+encodeURIComponent(revisionId)+'/decisions',body,{readBackPath:sourcePath(sourceId),readBackCollection:'revisions'}));current(s);`
- L134: `async function issue(input){`
- L136: `if(input?.humanConfirmed!==true)throw Error('Issuing model metadata requires explicit professional confirmation.');`
- L144: `expectedVersion:source.version,`
- L145: `humanConfirmed:true,`
- L147: `issueReason:required(input?.issueReason,500,'Issue reason'),`
- L149: `const saved=unwrap(await context.write(sourcePath(sourceId)+'/issue',body,{readBackPath:sourcePath(sourceId)}));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="rfq-marketplace"></a>

## rfq_marketplace — RFQ Marketplace

**Placement:** workspace-tools / procure / family create. **Open:** `openTool('rfq_marketplace', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** ProcurementRoutes, bidder messaging, quotes and award governance.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no package scope, returnables, invited supplier, quotation, award reason or human-confirmation fields.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/procurement-packages`; returned  fields: id, title, status, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


Source `app/public/assets/v1-clone/js/modules/procurement.js`:

- L56: `'<label>Required trades (comma separated) <input type="text" data-proc-field="requiredTrades"></label>'+`
- L57: `'<label>Required specialists (comma separated) <input type="text" data-proc-field="requiredSpecialists"></label>'+`
- L58: `'<label>Required returnables (comma separated) <input type="text" data-proc-field="requiredReturnables"></label>'+`
- L165: `async function createPackage(main,button){`
- L169: `const input={humanConfirmed:false,packageNumber:field(main,'packageNumber'),title:field(main,'title'),`
- L174: `requiredTrades:csv(field(main,'requiredTrades')),requiredSpecialists:csv(field(main,'requiredSpecialists')),`
- L175: `requiredReturnables:csv(field(main,'requiredReturnables')),documentRevisionIds:csv(field(main,'documentRevisionIds')),`
- L180: `input.humanConfirmed=true;button.disabled=true;notice('Creating package…');`
- L187: `async function openWorkspace(main,packageId){`
- L192: `async function issuePackage(main,button){`
- L199: `try{await adapter.issuePackage(packageId,{expectedVersion:record.version,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Package issued and read back from the server.');}`
- L203: `async function inviteBidder(main,button){`
- L209: `try{await adapter.invite(selectedId,{listingId,humanConfirmed:true});workspace=await adapter.workspace(selectedId);renderWorkspace(main);notice('Invitation recorded in-app and verified.');}`
- L213: `async function issueAddendum(main,button){`
- L220: `await adapter.addenda(selectedId,{title:field(main,'addendumTitle'),content:field(main,'addendumContent'),reason:field(main,'addendumReason'),clarificationIds:[clarificationId],humanConfirmed:true});`
- L225: `async function runComparison(main,button){`
- L239: `async function runMatches(main,button){`
- L250: `async function refresh(token){`
- L262: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/procurement.js`:

- L5: `// writes, context.write read-back verification, explicit human confirmation and no`
- L23: `function identifier(id){if(!ID.test(id||''))throw Error('A real procurement package identifier is required.');return id;}`
- L31: `async function listPackages(){`
- L32: `const s=scope();const records=packages(await context.read(listPath(s)),s);current(s);return records;`
- L34: `async function workspace(packageId){`
- L36: `const data=unwrap(await context.read(workspacePath(packageId)));current(s);`
- L40: `async function createPackage(input){`
- L42: `if(input.humanConfirmed!==true)throw Error('Package creation requires explicit human confirmation.');`
- L43: `if(context.capabilities?.readBackCollection!==true)throw Error('Package save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L50: `if(documents.length===0)throw Error('At least one controlled document revision issued for tender is required.');`
- L51: `const returnables=tokens(input.requiredReturnables);`
- L52: `if(returnables.length===0)throw Error('At least one required returnable must be listed.');`
- L53: `const trades=tokens(input.requiredTrades),specialists=tokens(input.requiredSpecialists);`
- L54: `if(trades.length===0&&specialists.length===0)throw Error('List at least one required trade or specialist scope.');`
- L56: `if(!need)throw Error('A procurement need or scope summary is required.');`
- L58: `if(!deadline)throw Error('A future submission deadline is required.');`
- L63: `requiredTrades:trades,requiredSpecialists:specialists,requiredReturnables:returnables,`
- L66: `const saved=unwrap(await context.write(listPath(s),body,{readBackPath:listPath(s)}));current(s);`
- L67: `const data=packages(await context.read(listPath(s)),s);current(s);`
- L70: `async function issuePackage(packageId,{expectedVersion,reason,humanConfirmed=false}={}){`
- L72: `if(humanConfirmed!==true)throw Error('Package issue requires explicit human confirmation.');`
- L74: `if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('The current package version is required to issue.');`
- L75: `if(!reason||!String(reason).trim())throw Error('A reason is required to issue a package.');`
- L76: `const body={expectedVersion,humanConfirmed:true,reason:String(reason).trim()};`
- L77: `const saved=unwrap(await context.write(packagePath(packageId)+'/issue',body,{readBackPath:packagePath(packageId)}));current(s);`
- L78: `const record=unwrap(await context.read(packagePath(packageId)));current(s);`
- L83: `async function matches(packageId){`
- L85: `const listed=unwrap(await context.write(packagePath(packageId)+'/matches',{},{readBackPath:packagePath(packageId)}));current(s);`
- L89: `async function invite(packageId,{listingId,humanConfirmed=false}={}){`
- L91: `if(humanConfirmed!==true)throw Error('Bidder invitation requires explicit human confirmation.');`
- L93: `if(!ID.test(listingId||''))throw Error('A real marketplace listing identifier is required.');`
- L94: `const saved=unwrap(await context.write(packagePath(packageId)+'/invitations',{listingId,humanConfirmed:true},{readBackPath:workspacePath(packageId),readBackCollection:'invitations'}));current(s);`
- L101: `async function addenda(packageId,{title,content,reason,clarificationIds,humanConfirmed=false}={}){`
- L103: `if(humanConfirmed!==true)throw Error('Addendum issue requires explicit human confirmation.');`
- L107: `const body={title:String(title||'').trim(),content:String(content||'').trim(),reason:String(reason||'').trim(),clarificationIds:clarifications,humanConfirmed:true};`
- L108: `const saved=unwrap(await context.write(packagePath(packageId)+'/addenda',body,{readBackPath:workspacePath(packageId),readBackCollection:'addenda'}));current(s);`
- L115: `async function comparisons(packageId){`
- L117: `if(context.capabilities?.readBackCollection!==true)throw Error('Comparison save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L118: `const saved=unwrap(await context.write(packagePath(packageId)+'/comparisons',{},{readBackPath:workspacePath(packageId),readBackCollection:'comparisons'}));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="supplier-catalog"></a>

## supplier_catalog — Supplier Catalogue

**Placement:** workspace-tools / procure / family find. **Open:** `openTool('supplier_catalog', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** SupplierProcurementRoutes, catalogues, matching and orders.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no supplier, catalogue upload or product editor. API exposes revision metadata here, not individual product detail.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/supplier-procurement`; returned catalogues fields: id, supplierId, revision, sourceKind, itemCount.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/supplier.js`:

- L10: `const LEVEL_LABEL = { 0: 'manual', 1: 'catalogue upload', 2: 'integration required' };`
- L27: `async function load() { return adapter.list(); }`
- L121: `async function refresh() {`
- L141: `busy(main, '[data-sc-create]', () => adapter.createSupplier({ name: name.trim(), capabilityLevel: level, serviceRegions: regions, contactEmail: email || null, humanConfirmed: true })`
- L161: `sourceKind: 'manual', items: [{ sku: sku.trim(), name: name.trim(), category: category.trim(), unit: unit.trim(), priceZar, stockQuantity, leadTimeDays, standards, deliveryRegions, substitutionEligible: false }], humanConfirmed: true,`
- L174: `const requiredStandard = String(root.prompt?.('Required standard (optional)', '') || '').trim();`
- L175: `const requiredOnSiteDate = String(root.prompt?.('Required on site (YYYY-MM-DD)', '') || '').trim();`
- L176: `if (!/^\d{4}-\d{2}-\d{2}$/.test(requiredOnSiteDate)) { notice('Invalid required-on-site date. No baseline drafted.'); return; }`
- L180: `lines: [{ sourceItemRef: ref.trim(), description: description.trim(), category: category.trim(), specification: null, requiredStandard: requiredStandard || null, quantity, unit: unit.trim(), allowSubstitution: true, requiredOnSiteDate, sourceDocumentIds: [] }],`
- L181: `humanConfirmed: true,`
- L189: `busy(main, '[data-sc-act="approve"][data-sc-id="' + id + '"]', () => adapter.approveBaseline(id, { reason: reason.trim(), expectedVersion: Number(version), humanConfirmed: true })`
- L198: `busy(main, '[data-sc-act="package"][data-sc-id="' + id + '"]', () => adapter.packages(id, { title: title.trim(), projectRegion: region.trim(), humanConfirmed: true })`
- L205: `busy(main, '[data-sc-act="match"][data-sc-id="' + id + '"]', () => adapter.matches(id, { humanConfirmed: true })`
- L220: `busy(main, '[data-sc-act="rfq"][data-sc-id="' + id + '"]', () => adapter.rfqs(id, { supplierId: supplier.id, expiresInDays: 14, humanConfirmed: true })`
- L240: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/supplier.js`:

- L29: `if(!input||input.humanConfirmed!==true)throw Error(what+' requires explicit human confirmation.');`
- L32: `if(typeof value!=='string'||!HEX.test(value))throw Error('A verified 32-hex '+what+' is required.');`
- L37: `if(!out)throw Error(what+' is required.');`
- L46: `if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))throw Error('requiredOnSiteDate must be an ISO date (YYYY-MM-DD).');`
- L49: `async function freshWorkspace(s){const data=unwrap(await context.read(workspace(s)));current(s);return data;}`
- L51: `async function list(){`
- L53: `const data=unwrap(await context.read(workspace(s)));current(s);`
- L66: `boundaries:data?.boundaries||{aiMayRecommend:true,aiMayApprove:false,movesMoney:false,externalConnectorModes:['manual_ready','catalogue_upload_ready','integration_required']},`
- L69: `// capabilityLevel 0 = manual, 1 = catalogue upload, 2+ = connector integration required.`
- L70: `async function createSupplier(input={}){`
- L73: `const name=text(input.name,'Supplier name');`
- L75: `if(!Number.isInteger(level)||level<0||level>6)throw Error('capabilityLevel must be an integer 0-6 (0 manual, 1 catalogue upload, 2+ integration required).');`
- L79: `const saved=unwrap(await context.write(path(s,'supplier-procurement/suppliers'),body,{readBackPath:workspace(s),readBackCollection:'suppliers'}));current(s);`
- L87: `async function addCatalogue(supplierId,input={}){`
- L92: `if(raw.length===0)throw Error('At least one catalogue item is required; no revision was created.');`
- L115: `const saved=unwrap(await context.write('/supplier-procurement/suppliers/'+encodeURIComponent(supplier)+'/catalogues',body,{readBackPath:workspace(s),readBackCollection:'catalogues'}));current(s);`
- L122: `async function baselines(input={}){`
- L126: `if(raw.length===0)throw Error('At least one baseline line is required.');`
- L137: `requiredStandard:line.requiredStandard==null||line.requiredStandard===''?null:String(line.requiredStandard).trim(),`
- L141: `requiredOnSiteDate:date(line.requiredOnSiteDate),`
- L146: `const saved=unwrap(await context.write(path(s,'supplier-procurement/baselines'),body,{readBackPath:workspace(s),readBackCollection:'baselines'}));current(s);`
- L152: `async function approveBaseline(baselineId,input={}){`
- L157: `const expectedVersion=input.expectedVersion;`
- L158: `if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('expectedVersion (the loaded baseline version) is required so approval cannot overwrite a newer revision.');`
- L159: `const saved=unwrap(await context.write('/supplier-procurement/baselines/'+encodeURIComponent(baseline)+'/approve',{humanConfirmed:true,reason,expectedVersion},{readBackPath:workspace(s),readBackCollection:'baselines'}));current(s);`
- L162: `if(!record||record.status!=='approved'||Number(record.version)<=expectedVersion)throw Error('Baseline approval not verified; refresh before retrying.');`
- L165: `async function packages(baselineId,input={}){`
- L170: `const saved=unwrap(await context.write('/supplier-procurement/baselines/'+encodeURIComponent(baseline)+'/packages',body,{readBackPath:workspace(s),readBackCollection:'packages'}));current(s);`
- L178: `async function matches(packageId,input={}){`
- L182: `await context.write('/supplier-procurement/packages/'+encodeURIComponent(target)+'/matches',{},{readBackPath:workspace(s),readBackCollection:'matches'});current(s);`
- L188: `async function rfqs(packageId,input={}){`
- L195: `const saved=unwrap(await context.write('/supplier-procurement/packages/'+encodeURIComponent(target)+'/rfqs',{supplierId,expiresInDays:days,humanConfirmed:true},{readBackPath:workspace(s),readBackCollection:'rfqs'}));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="market-insights"></a>

## market_insights — Market Insights

**Placement:** workspace-tools / procure / family find. **Open:** `openTool('market_insights', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** AnalyticsReportingRoutes, KPIs, alerts and scoped exports.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no benchmark source or forecast inputs. Project analytics are not external market intelligence.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/analytics-workspace`; returned latestMetrics fields: id, formulaKey, value, unit, asOf.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/governance.js`:

- L23: `async function load(def) {`
- L26: `const p = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId)));`
- L35: `try { profile = unwrap(await context.read('/professional-profile')) || null; }`
- L37: `return { directory: unwrap(await context.read('/directory/search')) || [], profile };`
- L95: `<form data-review-form><label>Decision <select name="decision" required><option value="">Select a decision</option><option value="manually_reviewed">Manually reviewed</option><option value="externally_verified">Externally verified</option><option value="rejected">Rejected</option></select></label>`
- L96: `<label>Reason <textarea name="note" required maxlength="4000"></textarea></label>`
- L97: `<label>External provider <input name="provider" maxlength="160"></label><label>Provider evidence reference <input name="providerReference" maxlength="180"></label><p>External verification requires a real provider and evidence reference supplied by the reviewer.</p>`
- L98: `<label><input type="checkbox" name="humanConfirmed" required> I confirm this decision for the selected record.</label><button class="btn" type="submit">Record decision</button></form>\`;`
- L100: `fields.decision.onchange=()=>{fields.provider.required=fields.providerReference.required=fields.decision.value==='externally_verified';};`
- L103: `const decision=fields.decision.value,payload={note:fields.note.value,provider:fields.provider.value,providerReference:fields.providerReference.value,humanConfirmed:fields.humanConfirmed.checked};`
- L120: `async function refresh(def) { if (disposed || !ownsRoute(routeFor(def))) return false;`
- L126: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/governance.js`:

- L5: `// Follows the canonical site-tools.js pattern: scope()/current(), context.write with read-back`
- L6: `// verify, humanConfirmed guards, never fabricated success.`
- L20: `async function listTeam(){`
- L22: `const data=unwrap(await context.read(path(s,'team')));current(s);`
- L26: `async function invite({candidateId,discipline,roleLabel}){`
- L28: `if(!candidateId||!discipline)throw Error('A candidate and discipline are required.');`
- L29: `const saved=unwrap(await context.write(path(s,'team'),{candidateId,discipline,roleLabel:roleLabel||discipline},{readBackPath:path(s,'team')}));current(s);`
- L34: `async function removeMember(teamMemberId,reason){`
- L36: `if(!reason||!String(reason).trim())throw Error('A removal reason is required.');`
- L37: `await context.write('/projects/'+encodeURIComponent(s.selectedProjectId)+'/team/'+encodeURIComponent(teamMemberId),{reason:String(reason).trim(),humanConfirmed:true},{method:'DELETE',readBackPath:path(s,'team')});current(s);`
- L41: `async function searchDirectory(profession,serviceArea){`
- L44: `return unwrap(await context.read('/directory/search'+(q.length?'?'+q.join('&'):'')));`
- L46: `async function saveProfile({serviceAreas,directoryVisible}){`
- L48: `return unwrap(await context.write('/professional-profile',{serviceAreas:serviceAreas??[],directoryVisible:directoryVisible===true,humanConfirmed:true},{readBackPath:'/professional-profile'}));`
- L50: `async function submitVerification({kind='professional_registration',grade,registrationBody,registrationNumber,purpose,expiresOn,evidenceFileId,consentGiven,retentionUntil}={}){`
- L52: `if(consentGiven!==true)throw Error('Explicit consent is required before submitting a verification.');`
- L53: `if(!['self_declared','document_uploaded'].includes(grade))throw Error('A valid grade (self_declared or document_uploaded) is required.');`
- L54: `if(!['SACAP','ECSA','SACQSP','SACLAP','SACPCMP'].includes(String(registrationBody).toUpperCase()))throw Error('A recognised professional body is required (SACAP, ECSA, SACQSP, SACLAP, SACPCMP).');`
- L55: `if(!purpose||!String(purpose).trim())throw Error('A POPIA purpose is required.');`
- L56: `if(!retentionUntil)throw Error('A retention date is required.');`
- L57: `return unwrap(await context.write('/verifications',{kind,grade,registrationBody:String(registrationBody).toUpperCase(),registrationNumber:registrationNumber||null,purpose:String(purpose).trim(),expiresOn:expiresOn||null,evidenceFileId:evidenceFileId||null,consentGiven:true,retentionUntil},{readBackPath:'/verification-review-queue'}));`
- L59: `function reviewer(){const s=scope(false),member=s.session.memberships?.find(m=>m.organisationId===s.organisationId);if(!['owner','admin'].includes(member?.role)||(member.status&&member.status!=='active'))throw Error('An active owner or admin membership is required.');return s;}`
- L61: `async function reviewQueue(){const s=reviewer();const rows=verificationRows(await context.read('/verification-review-queue'));current(s);return rows;}`
- L62: `async function ownVerifications(){const s=scope(false);const rows=verificationRows(await context.read('/verifications'));current(s);return rows;}`
- L64: `async function reviewVerification(verificationId,decision,{provider,providerReference,note,humanConfirmed}={}){`
- L67: `if(humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L68: `if(!note?.trim())throw Error('A review reason is required.');`
- L69: `if(!['manually_reviewed','externally_verified','rejected'].includes(decision))throw Error('A valid decision is required.');`
- L74: `const saved=unwrap(await context.write('/verifications/'+verificationId+'/review',{decision,provider:provider?.trim()||null,providerReference:providerReference?.trim()||null,note:note.trim(),humanConfirmed:true},{projectScoped:false,readBackPath:'/verification-review-queue',readBackAbsentId:verificationId}));current(s);`
- L81: `async function analyticsWorkspace(){const s=scope(true);return unwrap(await context.read(path(s,'analytics-workspace')));}`
- L82: `async function refreshKpis({asOf}={}){`
- L85: `return unwrap(await context.write(path(s,'analytics/kpis/refresh'),body,{readBackPath:path(s,'analytics-workspace')}));`
- L87: `async function createAlertRule(rule){`
- L90: `return unwrap(await context.write(path(s,'analytics/alert-rules'),body,{readBackPath:path(s,'analytics-workspace')}));`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="contract-admin"></a>

## contract_admin — Contract Administration

**Placement:** workspace-tools / build / family administer. **Open:** `openTool('contract_admin', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** FinanceRoutes, baselines, variations, claims and certificates.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no baseline, variation, valuation lines, expectedVersion, certification reason or human-confirmation fields.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/finance-workspace`; returned variations fields: id, reference, title, amountCents, timeImpactDays, status, version.
- `/api/v1/projects/{projectId}/finance-workspace`; returned claims fields: claimId, claimNumber, claimedToDateCents, currency, status, revision.
- `/api/v1/projects/{projectId}/finance-workspace`; returned certificates fields: certificateId, certificateRevisionId, netPayableCents, currency, status, revision.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/commercial.js`:

- L202: `const input={contractSumCents:sum,contingencyCents:centsFromRands(field('contingency')||0),retentionBasisPoints:Math.round(Number(field('retentionPercent')||0)*100),currency:'ZAR',vatIncluded:token('vatIncluded'),reason:String(field('baselineReason')||'').trim(),humanConfirmed:true};`
- L220: `const input={title:String(field('scheduleTitle')||'').trim(),reason:String(field('scheduleReason')||'').trim(),milestones:milestones(),humanConfirmed:true};`
- L229: `const input={claimNumber:String(field('claimNumber')||'').trim(),claimedToDateCents:centsFromRands(field('claimAmount')),milestoneId:String(field('milestoneId')||''),evidenceReferences:[String(field('claimEvidence')||'').trim()].filter(Boolean),disputed:token('claimDisputed'),reason:String(field('claimReason')||'').trim(),humanConfirmed:true};`
- L240: `if(!reason?.trim()){notice('A recorded reason is required. Nothing was submitted.');return;}`
- L243: `try{await adapter.submitVariation(id,{expectedVersion:version,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Variation submitted for review and verified. Approvals remain outstanding.');}`
- L250: `if(!reason?.trim()){notice('A recorded reason is required. Nothing was submitted.');return;}`
- L251: `if(!root.confirm?.('Record the CLIENT approval decision for this variation? Three separate approvals are required before incorporation; this is one of them.')){notice('Variation approval cancelled.');return;}`
- L253: `try{await adapter.approveVariation(id,{approvalType:'client',decision:'approved',reason:reason.trim(),humanConfirmed:true});await refresh();notice('Client approval recorded and verified. The variation is incorporated only after all three approvals.');}`
- L260: `if(gross==null||gross===''){notice('A certified amount is required. Nothing was submitted.');return;}`
- L262: `if(!reason?.trim()){notice('A recorded certification reason is required. Nothing was submitted.');return;}`
- L265: `try{const record=await adapter.issueCertificate(id,{grossCertifiedToDateCents:centsFromRands(gross),otherDeductionsCents:0,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Certificate revision '+record.revision+' verified (net payable '+record.netPayableCents+' cents). Internal metadata — no payment or release occurred.');}`
- L281: `async function refresh(){`
- L293: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/commercial.js`:

- L9: `// and an explicit humanConfirmed guard on every governed change. A write is only`
- L19: `const RELEASE_REFUSED='Release and payout are unavailable: no registered third-party payment or escrow provider is live-configured for this organisation. Architex never holds funds and never executes a payment. Nothing was submitted.';`
- L51: `async function readWorkspace(s){return workspace(unwrap(await context.read(path(s,'finance-workspace'))),s);}`
- L52: `async function load(){const s=scope();current(s);const data=await readWorkspace(s);current(s);return data;}`
- L56: `async function resolveAppointmentId(s,input){`
- L59: `const rows=unwrap(await context.read('/appointments'));current(s);`
- L65: `// Shared governed write. The shared context.write helper verifies read-back`
- L73: `async function persist(route,body,options){`
- L79: `try{const returned=unwrap(await context.write(route,body,writeOptions));if(!receipt&&returned&&typeof returned==='object')receipt=returned;}`
- L90: `async function createBaseline(input){`
- L92: `if(input?.humanConfirmed!==true)throw Error('Creating a commercial baseline requires explicit human confirmation.');`
- L97: `if(!Number.isInteger(sum)||sum<1)throw Error('A positive integer contract sum in cents is required.');`
- L98: `if(!Number.isInteger(contingency)||contingency<0)throw Error('A non-negative integer contingency in cents is required.');`
- L100: `if(!/^[A-Z]{3}$/.test(currency))throw Error('A three-letter currency code is required.');`
- L102: `if(!reason||reason.length>1000)throw Error('A recorded reason is required for the baseline.');`
- L104: `return persist(path(s,'commercial-baselines'),{appointmentId,contractSumCents:sum,contingencyCents:contingency,retentionBasisPoints:retention,currency,vatIncluded:input.vatIncluded,reason,humanConfirmed:true},`
- L107: `async function baselineId(s,input){`
- L113: `async function addPaymentSchedule(input){`
- L115: `if(input?.humanConfirmed!==true)throw Error('Issuing a payment schedule requires explicit human confirmation.');`
- L119: `if(!title)throw Error('A schedule title is required.');`
- L120: `if(!reason)throw Error('A recorded reason is required for the payment schedule.');`
- L121: `if(!raw.length||raw.length>100)throw Error('Between 1 and 100 payment milestones are required.');`
- L140: `return persist(route,{title,reason,milestones,humanConfirmed:true},{collection:'schedules',`
- L143: `async function addVariation(input){`
- L150: `if(!reference||reference.length>80)throw Error('A variation reference is required.');`
- L151: `if(!title||title.length>220)throw Error('A variation title is required.');`
- L152: `if(!description)throw Error('A variation description is required.');`
- L153: `if(!Number.isInteger(amountCents)||amountCents===0||Math.abs(amountCents)>9000000000000000)throw Error('A non-zero integer variation amount in cents is required.');`
- L157: `return persist(route,{reference,title,description,amountCents,timeImpactDays,humanConfirmed:true},{collection:'variations',`
- L160: `async function submitVariation(variationId,input){`
- L162: `if(input?.humanConfirmed!==true)throw Error('Submitting a variation for review requires explicit human confirmation.');`
- L163: `if(!isId(variationId))throw Error('A valid variation id is required.');`
- L164: `const expectedVersion=int(input?.expectedVersion);`
- L166: `if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('The variation expectedVersion is required for a safe transition.');`
- L167: `if(!reason)throw Error('A recorded reason is required for the variation submission.');`
- L169: `return persist(route,{expectedVersion,reason,humanConfirmed:true},{collection:'variations',`
- L172: `async function approveVariation(variationId,input){`
- L174: `if(input?.humanConfirmed!==true)throw Error('Recording a variation approval requires explicit human confirmation.');`
- L175: `if(!isId(variationId))throw Error('A valid variation id is required.');`
- L181: `if(!reason)throw Error('A recorded reason is required for the variation decision.');`
- L183: `return persist(route,{approvalType,decision,reason,humanConfirmed:true},{collection:'variations',`
- L188: `async function addClaim(input){`
- L190: `if(input?.humanConfirmed!==true)throw Error('Submitting a payment claim requires explicit human confirmation.');`
- L194: `if(!claimNumber||claimNumber.length>80)throw Error('A payment claim number is required.');`
- L197: `if(!reason)throw Error('A recorded reason is required for the payment claim.');`
- L199: `if(!evidence.length)throw Error('At least one evidence reference is required for a payment claim.');`
- L205: `if(!milestoneId)throw Error('A payment milestone from an issued payment schedule is required for a claim. Nothing was submitted.');`
- L209: `return persist(route,{claimNumber,claimedToDateCents,milestoneId,evidenceReferences:evidence,disputed:input.disputed,reason,humanConfirmed:true},{collection:'claims',`
- L212: `async function issueCertificate(claimId,input){`
- L214: `if(input?.humanConfirmed!==true)throw Error('Certifying a payment claim requires explicit human confirmation.');`
- L215: `if(!isId(claimId))throw Error('A valid payment claim id is required.');`
- L221: `if(!reason)throw Error('A recorded certification reason is required.');`
- L223: `return persist(route,{grossCertifiedToDateCents,otherDeductionsCents,reason,humanConfirmed:true},{collection:'certificates',`
- L226: `async function listRetention(input){`
- L229: `const data=unwrap(await context.read('/commercial-baselines/'+encodeURIComponent(baseline)+'/retention'));current(s);`
- L237: `async function requestRelease(){`
- L248: `unconfiguredStatus:boundary.unconfiguredStatus||'provider_configuration_required',message:ESCROW_BOUNDARY};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="contractor-compliance"></a>

## contractor_compliance — Contractor Compliance

**Placement:** workspace-tools / build / family review. **Open:** `openTool('contractor_compliance', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Controlled register persists reviewed compliance evidence and states.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/controlled-registers/contractor_compliance`; returned entries fields: id, title, reference, status, ownerRole, dueOn, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/compliance.js`:

- L13: `const WARNING = 'Internal compliance evidence only — not statutory certification, municipal approval or an externally verified status. Responsible-professional review remains required.';`
- L44: `async function load(def) {`
- L130: `async function createFlow(def) {`
- L136: `const documentType = root.prompt?.('Document type (e.g. health_safety_plan, cidb_registration, professional_indemnity)', 'health_safety_plan'); if (!documentType?.trim()) throw Error('A document type is required.');`
- L143: `let coverageAmountCents = null, requiredCoverageCents = null;`
- L146: `requiredCoverageCents = Number(root.prompt?.('Required cover (cents)'));`
- L147: `if (!Number.isFinite(coverageAmountCents) || !Number.isFinite(requiredCoverageCents) || requiredCoverageCents <= 0) throw Error('Insurance items require recorded and required cover amounts.');`
- L150: `await adapter.createComplianceItem({ subjectType: String(subjectType).trim(), subjectName: subjectName.trim(), category: String(category).trim(), documentType: documentType.trim(), authority: authority.trim(), referenceNumber: referenceNumber.trim(), grade: 'self_declared', details: {}, expiresOn, coverageAmountCents, requiredCoverageCents, purpose: purpose.trim(), retentionUntil: retentionUntil.trim(), consentGiven: true, humanConfirmed: true });`
- L161: `await adapter.createRegisterEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: ownerRole.trim(), dueOn, details: def.registerKey === 'insurance_register' ? { policyDescription: detailKey.trim() } : { complianceNote: detailKey.trim() }, evidenceFileIds: [], humanConfirmed: true });`
- L166: `async function reviewFlow(itemId) {`
- L177: `await adapter.reviewItem(itemId, { decision: String(decision).trim(), note: note.trim(), provider: provider?.trim() || null, providerReference: providerReference?.trim() || null, humanConfirmed: true });`
- L182: `async function transitionFlow(btn) {`
- L186: `const expectedVersion = Number(btn.getAttribute('data-compliance-version'));`
- L189: `await adapter.transitionEntry(entryId, { status, reason: reason.trim(), expectedVersion, humanConfirmed: true });`
- L194: `async function checklistFlow() {`
- L198: `notice('Drawing checklist: ' + result.overallStatus + ' (not persisted; professional review required).');`
- L201: `async function refresh(def) {`
- L212: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/compliance.js`:

- L30: `async function trustWorkspace(s){const data=unwrap(await context.read(path(s,'trust-compliance-workspace')));current(s);return data;}`
- L31: `async function registerWorkspace(s,key){`
- L32: `const data=unwrap(await context.read(path(s,'controlled-registers/'+encodeURIComponent(key))));current(s);`
- L38: `async function listComplianceItems(){`
- L44: `async function createComplianceItem(input={}){`
- L46: `if(input.humanConfirmed!==true)throw Error('A trust compliance item requires explicit human confirmation.');`
- L47: `if(!text(input.subjectName)||!text(input.authority)||!text(input.referenceNumber))throw Error('Subject name, authority and reference number are required.');`
- L48: `if(!text(input.purpose)||!input.retentionUntil)throw Error('A processing purpose and retention date are required for the record.');`
- L49: `if(input.consentGiven!==true)throw Error('Recorded consent is required before storing contact/company compliance detail.');`
- L55: `requiredCoverageCents:input.requiredCoverageCents??null,purpose:text(input.purpose),`
- L56: `retentionUntil:input.retentionUntil,consentGiven:true,humanConfirmed:true,`
- L58: `const saved=unwrap(await context.write(path(s,'trust/compliance-items'),body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);`
- L65: `async function reviewItem(itemId,input={}){`
- L67: `if(!valid(itemId))throw Error('A trust compliance item id is required.');`
- L68: `if(input.humanConfirmed!==true)throw Error('A compliance review requires explicit human confirmation.');`
- L70: `if(!text(input.note))throw Error('A review note is required.');`
- L72: `const body={decision:input.decision,note:text(input.note),provider:input.provider??null,providerReference:input.providerReference??null,humanConfirmed:true};`
- L73: `unwrap(await context.write('/trust/compliance-items/'+encodeURIComponent(itemId)+'/review',body,{readBackPath:path(s,'trust-compliance-workspace'),readBackCollection:'complianceItems'}));current(s);`
- L82: `async function checklist(input={}){`
- L85: `const evaluation=unwrap(await context.write('/drawing-compliance/checklist',body,{readBackPath:'/drawing-compliance/sources'}));current(s);`
- L86: `const sources=unwrap(await context.read('/drawing-compliance/sources'));current(s);`
- L93: `async function drawingReview(input={}){`
- L95: `if(!text(input.title))throw Error('A drawing review title is required.');`
- L96: `if(!text(input.responsibleRole))throw Error('A responsible role is required for a project drawing review.');`
- L98: `const saved=unwrap(await context.write('/drawing-compliance/reviews',body,{readBackPath:r=>'/drawing-compliance/reviews/'+encodeURIComponent(r.id)}));current(s);`
- L100: `const record=unwrap(await context.read('/drawing-compliance/reviews/'+encodeURIComponent(saved.id)));current(s);`
- L108: `async function listRegister(registerKey){`
- L113: `async function createRegisterEntry(registerKey,input={}){`
- L115: `if(input.humanConfirmed!==true)throw Error('A controlled-register entry requires explicit human confirmation.');`
- L116: `if(!text(input.title)||!text(input.reference)||!text(input.ownerRole))throw Error('Title, reference and owner role are required.');`
- L117: `if(!input.details||typeof input.details!=='object'||Array.isArray(input.details))throw Error('A details object is required for a controlled-register entry.');`
- L118: `const body={title:text(input.title),reference:text(input.reference),ownerRole:text(input.ownerRole),dueOn:input.dueOn??null,details:input.details,evidenceFileIds:Array.isArray(input.evidenceFileIds)?input.evidenceFileIds:[],humanConfirmed:true};`
- L119: `const saved=unwrap(await context.write(path(s,'controlled-registers/'+encodeURIComponent(key)),body,{readBackPath:path(s,'controlled-registers/'+encodeURIComponent(key)),readBackCollection:'entries'}));current(s);`
- L127: `async function transitionEntry(entryId,input={}){`
- L129: `if(!valid(entryId))throw Error('A controlled-register entry id is required.');`
- L130: `if(input.humanConfirmed!==true)throw Error('A register transition requires explicit human confirmation.');`
- L131: `if(!text(input.status))throw Error('A target status is required.');`
- L132: `if(!text(input.reason))throw Error('A transition reason is required.');`
- L133: `if(!Number.isInteger(input.expectedVersion)||input.expectedVersion<1)throw Error('A current entry version is required to transition safely.');`
- L134: `const body={status:input.status,reason:text(input.reason),expectedVersion:input.expectedVersion,humanConfirmed:true};`
- L135: `const saved=unwrap(await context.write('/controlled-registers/'+encodeURIComponent(entryId)+'/transition',body,{readBackPath:r=>{`
- L143: `if(record.version!==input.expectedVersion+1)throw Error('Register transition version did not advance; refresh before retrying.');`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="approvals-queue"></a>

## approvals_queue — Approvals Queue

**Placement:** collaboration / platform / family review. **Open:** `openTool('approvals_queue', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Decision inbox aggregates human gates; sequences vary by source module.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Inspect mixed source decisions and missing-record tasks, then open the source workflow. Resolving an inbox item is not approving its source record or deploying a release.

**Production native next actions:** inspect-decisions → approvals_queue (projects.read, source_record_review)

**Original scaffold boundary (not enhanced-module verdict):** Original has no selected record, review reason, version or human-confirmation controls. Decisions include missing-record actions; not all are approvals.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/operating-snapshot`; returned decisions fields: id, title, priority, humanGate, actionTarget.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/approvals.js`:

- L29: `async function refresh() {`
- L88: `if (sensitive && !reason) { notice('A reason is required. Nothing was changed.'); return; }`
- L90: `adapter.transitionInbox(id, { status, reason, humanConfirmed: sensitive })`
- L104: `if (!route || !Number.isInteger(status)) { notice('A route and an integer status are required. Nothing was recorded.'); return; }`
- L111: `routeSmoke: [{ route, status }], dependencyVulnerabilityCount: 0, humanConfirmed: true,`
- L124: `async function apply(toolId) {`
- L132: `return { apply, dispose, tool: TOOL, supports: id => id === TOOL };`

Source `app/public/assets/v1-clone/js/adapters/approvals.js`:

- L26: `function identity(value,label){if(!/^[a-f0-9]{32}$/i.test(String(value||'')))throw Error('A valid '+label+' id is required.');return String(value).toLowerCase();}`
- L31: `function isApproval(decision){return decisionKind(decision)==='approval'||decision?.inboxType==='approval_required';}`
- L33: `async function readInbox(s){const data=unwrap(await context.read('/inbox'));current(s);return projectItems(data,s);}`
- L34: `async function readGates(s){const data=unwrap(await context.read('/release-gates'));current(s);if(!Array.isArray(data))throw Error('Invalid release-gate response.');return data;}`
- L37: `async function listInbox(){`
- L44: `async function listDecisions(){`
- L46: `const snapshot=unwrap(await context.read(path(s,'operating-snapshot')));current(s);`
- L58: `async function transitionInbox(inboxItemId,{status,reason,humanConfirmed=false}={}){`
- L64: `if(sensitive&&humanConfirmed!==true)throw Error('Resolving or dismissing a decision requires explicit human confirmation.');`
- L65: `if(sensitive&&!text)throw Error('A reason is required to resolve or dismiss a decision.');`
- L67: `if(sensitive)body.humanConfirmed=true;`
- L68: `unwrap(await context.write('/inbox/'+encodeURIComponent(id)+'/transition',body,{readBackPath:'/inbox'}));current(s);`
- L72: `return {id:record.id,status:record.status,eventType:record.eventType||null,humanGate:record.payload?.humanGate||null,humanConfirmed:sensitive,reason:text||null};`
- L74: `async function listReleaseGates(){`
- L81: `async function createReleaseGate(input){`
- L83: `if(input?.humanConfirmed!==true)throw Error('Recording a release gate requires explicit human confirmation.');`
- L84: `if(!ENVIRONMENTS.includes(input?.environment))throw Error('A valid environment is required ('+ENVIRONMENTS.join(', ')+').');`
- L86: `if(!repoBranch||!deployedBranch)throw Error('Both the reviewed and deployed branch names are required.');`
- L89: `const apiMode=String(input?.apiMode||'').trim();if(!apiMode)throw Error('An API mode is required.');`
- L90: `if(!input?.buildTimestamp)throw Error('A build timestamp is required.');`
- L93: `if(routes.length===0)throw Error('At least one route smoke probe is required.');`
- L96: `if(!Number.isInteger(vulnerabilities)||vulnerabilities<0)throw Error('A dependency vulnerability count (0 or more) is required.');`
- L97: `const body={environment:input.environment,repoBranch,repoCommit,deployedBranch,deployedCommit,buildTimestamp:input.buildTimestamp,apiMode,apiHealthOk:input.apiHealthOk,databaseReady:input.databaseReady,migrationsReady:input.migrationsReady,mailReady:input.mailReady,routeSmoke,dependencyVulnerabilityCount:vulnerabilities,humanConfirmed:true};`
- L98: `const saved=unwrap(await context.write('/release-gates',body,{readBackPath:'/release-gates'}));current(s);`
- L102: `return {id:record.id,environment:record.environment,status:record.status,summary:record.summary,checks:Array.isArray(record.checks)?record.checks:[],releaseActionsSupported:RELEASE_GATE_ACTIONS_SUPPORTED,humanConfirmed:true};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="payments-escrow"></a>

## payments_escrow — Payments & Escrow

**Placement:** workspace-tools / pay / family administer. **Open:** `openTool('payments_escrow', mode)`. **Registry availability claim:** `integration_required`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Release governance is live; money movement needs a configured provider.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Payment-provider integration is explicitly skipped. Internal certificate/release metadata remains visible; no transfer, escrow custody or settlement is claimed.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/finance-workspace`; returned certificates fields: certificateId, certificateRevisionId, netPayableCents, currency, status, revision.
- `/api/v1/projects/{projectId}/finance-workspace`; returned releaseRequests fields: id, certificateRevisionId, requestedAmountCents, currency, providerPaidCents, status, version.
- `/api/v1/tools`; returned  fields: id, name, status, evidence.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/commercial.js`:

- L202: `const input={contractSumCents:sum,contingencyCents:centsFromRands(field('contingency')||0),retentionBasisPoints:Math.round(Number(field('retentionPercent')||0)*100),currency:'ZAR',vatIncluded:token('vatIncluded'),reason:String(field('baselineReason')||'').trim(),humanConfirmed:true};`
- L220: `const input={title:String(field('scheduleTitle')||'').trim(),reason:String(field('scheduleReason')||'').trim(),milestones:milestones(),humanConfirmed:true};`
- L229: `const input={claimNumber:String(field('claimNumber')||'').trim(),claimedToDateCents:centsFromRands(field('claimAmount')),milestoneId:String(field('milestoneId')||''),evidenceReferences:[String(field('claimEvidence')||'').trim()].filter(Boolean),disputed:token('claimDisputed'),reason:String(field('claimReason')||'').trim(),humanConfirmed:true};`
- L240: `if(!reason?.trim()){notice('A recorded reason is required. Nothing was submitted.');return;}`
- L243: `try{await adapter.submitVariation(id,{expectedVersion:version,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Variation submitted for review and verified. Approvals remain outstanding.');}`
- L250: `if(!reason?.trim()){notice('A recorded reason is required. Nothing was submitted.');return;}`
- L251: `if(!root.confirm?.('Record the CLIENT approval decision for this variation? Three separate approvals are required before incorporation; this is one of them.')){notice('Variation approval cancelled.');return;}`
- L253: `try{await adapter.approveVariation(id,{approvalType:'client',decision:'approved',reason:reason.trim(),humanConfirmed:true});await refresh();notice('Client approval recorded and verified. The variation is incorporated only after all three approvals.');}`
- L260: `if(gross==null||gross===''){notice('A certified amount is required. Nothing was submitted.');return;}`
- L262: `if(!reason?.trim()){notice('A recorded certification reason is required. Nothing was submitted.');return;}`
- L265: `try{const record=await adapter.issueCertificate(id,{grossCertifiedToDateCents:centsFromRands(gross),otherDeductionsCents:0,reason:reason.trim(),humanConfirmed:true});await refresh();notice('Certificate revision '+record.revision+' verified (net payable '+record.netPayableCents+' cents). Internal metadata — no payment or release occurred.');}`
- L281: `async function refresh(){`
- L293: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/commercial.js`:

- L9: `// and an explicit humanConfirmed guard on every governed change. A write is only`
- L19: `const RELEASE_REFUSED='Release and payout are unavailable: no registered third-party payment or escrow provider is live-configured for this organisation. Architex never holds funds and never executes a payment. Nothing was submitted.';`
- L51: `async function readWorkspace(s){return workspace(unwrap(await context.read(path(s,'finance-workspace'))),s);}`
- L52: `async function load(){const s=scope();current(s);const data=await readWorkspace(s);current(s);return data;}`
- L56: `async function resolveAppointmentId(s,input){`
- L59: `const rows=unwrap(await context.read('/appointments'));current(s);`
- L65: `// Shared governed write. The shared context.write helper verifies read-back`
- L73: `async function persist(route,body,options){`
- L79: `try{const returned=unwrap(await context.write(route,body,writeOptions));if(!receipt&&returned&&typeof returned==='object')receipt=returned;}`
- L90: `async function createBaseline(input){`
- L92: `if(input?.humanConfirmed!==true)throw Error('Creating a commercial baseline requires explicit human confirmation.');`
- L97: `if(!Number.isInteger(sum)||sum<1)throw Error('A positive integer contract sum in cents is required.');`
- L98: `if(!Number.isInteger(contingency)||contingency<0)throw Error('A non-negative integer contingency in cents is required.');`
- L100: `if(!/^[A-Z]{3}$/.test(currency))throw Error('A three-letter currency code is required.');`
- L102: `if(!reason||reason.length>1000)throw Error('A recorded reason is required for the baseline.');`
- L104: `return persist(path(s,'commercial-baselines'),{appointmentId,contractSumCents:sum,contingencyCents:contingency,retentionBasisPoints:retention,currency,vatIncluded:input.vatIncluded,reason,humanConfirmed:true},`
- L107: `async function baselineId(s,input){`
- L113: `async function addPaymentSchedule(input){`
- L115: `if(input?.humanConfirmed!==true)throw Error('Issuing a payment schedule requires explicit human confirmation.');`
- L119: `if(!title)throw Error('A schedule title is required.');`
- L120: `if(!reason)throw Error('A recorded reason is required for the payment schedule.');`
- L121: `if(!raw.length||raw.length>100)throw Error('Between 1 and 100 payment milestones are required.');`
- L140: `return persist(route,{title,reason,milestones,humanConfirmed:true},{collection:'schedules',`
- L143: `async function addVariation(input){`
- L150: `if(!reference||reference.length>80)throw Error('A variation reference is required.');`
- L151: `if(!title||title.length>220)throw Error('A variation title is required.');`
- L152: `if(!description)throw Error('A variation description is required.');`
- L153: `if(!Number.isInteger(amountCents)||amountCents===0||Math.abs(amountCents)>9000000000000000)throw Error('A non-zero integer variation amount in cents is required.');`
- L157: `return persist(route,{reference,title,description,amountCents,timeImpactDays,humanConfirmed:true},{collection:'variations',`
- L160: `async function submitVariation(variationId,input){`
- L162: `if(input?.humanConfirmed!==true)throw Error('Submitting a variation for review requires explicit human confirmation.');`
- L163: `if(!isId(variationId))throw Error('A valid variation id is required.');`
- L164: `const expectedVersion=int(input?.expectedVersion);`
- L166: `if(!Number.isInteger(expectedVersion)||expectedVersion<1)throw Error('The variation expectedVersion is required for a safe transition.');`
- L167: `if(!reason)throw Error('A recorded reason is required for the variation submission.');`
- L169: `return persist(route,{expectedVersion,reason,humanConfirmed:true},{collection:'variations',`
- L172: `async function approveVariation(variationId,input){`
- L174: `if(input?.humanConfirmed!==true)throw Error('Recording a variation approval requires explicit human confirmation.');`
- L175: `if(!isId(variationId))throw Error('A valid variation id is required.');`
- L181: `if(!reason)throw Error('A recorded reason is required for the variation decision.');`
- L183: `return persist(route,{approvalType,decision,reason,humanConfirmed:true},{collection:'variations',`
- L188: `async function addClaim(input){`
- L190: `if(input?.humanConfirmed!==true)throw Error('Submitting a payment claim requires explicit human confirmation.');`
- L194: `if(!claimNumber||claimNumber.length>80)throw Error('A payment claim number is required.');`
- L197: `if(!reason)throw Error('A recorded reason is required for the payment claim.');`
- L199: `if(!evidence.length)throw Error('At least one evidence reference is required for a payment claim.');`
- L205: `if(!milestoneId)throw Error('A payment milestone from an issued payment schedule is required for a claim. Nothing was submitted.');`
- L209: `return persist(route,{claimNumber,claimedToDateCents,milestoneId,evidenceReferences:evidence,disputed:input.disputed,reason,humanConfirmed:true},{collection:'claims',`
- L212: `async function issueCertificate(claimId,input){`
- L214: `if(input?.humanConfirmed!==true)throw Error('Certifying a payment claim requires explicit human confirmation.');`
- L215: `if(!isId(claimId))throw Error('A valid payment claim id is required.');`
- L221: `if(!reason)throw Error('A recorded certification reason is required.');`
- L223: `return persist(route,{grossCertifiedToDateCents,otherDeductionsCents,reason,humanConfirmed:true},{collection:'certificates',`
- L226: `async function listRetention(input){`
- L229: `const data=unwrap(await context.read('/commercial-baselines/'+encodeURIComponent(baseline)+'/retention'));current(s);`
- L237: `async function requestRelease(){`
- L248: `unconfiguredStatus:boundary.unconfiguredStatus||'provider_configuration_required',message:ESCROW_BOUNDARY};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="dispute-resolution"></a>

## dispute_resolution — Dispute Resolution

**Placement:** workspace-tools / pay / family review. **Open:** `openTool('dispute_resolution', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Controlled register persists notice through close with human gates.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no title, reference, ownerRole, dueOn, details, evidenceFileIds or humanConfirmed form. Legal resolution is not inferred.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/controlled-registers/dispute_resolution`; returned entries fields: id, title, reference, status, ownerRole, dueOn, version.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/registers.js`:

- L46: `async function load(def) {`
- L86: `async function createForm(def, main) {`
- L97: `adapter.createEntry(def.registerKey, { title: title.trim(), reference: reference.trim(), ownerRole: role.trim(), dueOn: due || null, details: { summary: summary.trim() }, evidenceFileIds: [], humanConfirmed: true })`
- L111: `async function transitionForm(def, entry, target) {`
- L116: `adapter.transitionEntry(entry.id, { expectedVersion: entry.version, status: target, reason: reason.trim(), humanConfirmed: true })`
- L121: `async function refresh(def) {`
- L128: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/registers.js`:

- L54: `async function workspace(registerKey) {`
- L56: `const ws = workspaceOf(await context.read(registerPath(s, k)));`
- L60: `async function listEntries(registerKey) {`
- L63: `async function createEntry(registerKey, input) {`
- L66: `if (input.humanConfirmed !== true) throw Error('Controlled register entry requires explicit human confirmation.');`
- L68: `title: text(input.title, 220, 'A register entry title is required.'),`
- L69: `reference: text(input.reference, 120, 'A register reference is required.'),`
- L70: `ownerRole: text(input.ownerRole, 80, 'An owner role is required.'),`
- L74: `humanConfirmed: true,`
- L76: `const saved = unwrap(await context.write(registerPath(s, k), body, { readBackPath: registerPath(s, k), readBackCollection: 'entries' }));`
- L78: `const ws = workspaceOf(await context.read(registerPath(s, k)));`
- L84: `async function transitionEntry(entryId, input) {`
- L87: `if (input.humanConfirmed !== true) throw Error('Controlled register transition requires explicit human confirmation.');`
- L88: `if (!/^[a-f0-9]{32}$/i.test(entryId || '')) throw Error('A valid controlled register entry id is required.');`
- L89: `const expectedVersion = input.expectedVersion;`
- L90: `if (!Number.isInteger(expectedVersion) || expectedVersion < 1) throw Error('A current entry version (expectedVersion) is required.');`
- L91: `const status = text(input.status, 80, 'A target status is required.');`
- L92: `const reason = text(input.reason, 1000, 'A transition reason is required.');`
- L93: `const saved = unwrap(await context.write(`
- L95: `{ expectedVersion, status, reason, humanConfirmed: true },`
- L100: `const ws = workspaceOf(await context.read(registerPath(s, k)));`
- L103: `if (!record || record.status !== status || record.version !== expectedVersion + 1) throw Error('Controlled register transition not verified; refresh before retrying.');`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="site-instructions"></a>

## site_instructions — Site Instructions

**Placement:** workspace-tools / build / family create. **Open:** `openTool('site_instructions', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Professional issue, acknowledgement and superseding revisions.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no instruction title, detail, recipient, evidence, issue reason or acknowledgement form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/site-execution-workspace`; returned siteInstructions fields: id, title, status, dueOn, severity, priority.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/site-field.js`:

- L29: `async function load(def) {`
- L31: `const site = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId) + '/site-execution-workspace'));`
- L102: `adapter.createNcr({ title: title.trim(), description: desc.trim(), severity: sev.trim(), responsibleParty: party.trim(), evidenceIds: [], blocksPayment: false, humanConfirmed: true })`
- L112: `adapter.createSnag({ location: loc.trim(), description: desc.trim(), priority: pri.trim(), responsibleParty: party.trim(), dueAt: null, evidenceIds: [], blocksPayment: false, humanConfirmed: true })`
- L122: `adapter.createInstruction({ title: title.trim(), instruction: body.trim(), costImpact: cost.trim(), timeImpact: time.trim(), linkedRfiId: null, humanConfirmed: true })`
- L137: `async function runAction(def, main, id, act) {`
- L146: `adapter.submitNcrCorrectiveAction(id, { correctiveAction: ca.trim(), evidenceIds: [], humanConfirmed: true })`
- L152: `adapter.decideNcr(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true })`
- L158: `adapter.allocateSnag(id, { humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag allocated and verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L161: `adapter.markSnagReady(id, { evidenceIds: [], humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag marked ready for reinspection.')).catch(e => notice(e.message + ' No success claimed.'));`
- L166: `adapter.decideSnag(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag decision verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L171: `adapter.issueInstruction(id, { reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Site instruction issued and verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L175: `async function refresh(def) {`
- L183: `async function apply(toolId) {`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="ncr-manager"></a>

## ncr_manager — NCR Manager

**Placement:** workspace-tools / build / family review. **Open:** `openTool('ncr_manager', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** NCR actions, decisions, evidence and payment blockers.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no NCR evidence, severity, corrective-action or review decision form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/site-execution-workspace`; returned ncrs fields: id, title, status, dueOn, severity, priority.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/site-field.js`:

- L29: `async function load(def) {`
- L31: `const site = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId) + '/site-execution-workspace'));`
- L102: `adapter.createNcr({ title: title.trim(), description: desc.trim(), severity: sev.trim(), responsibleParty: party.trim(), evidenceIds: [], blocksPayment: false, humanConfirmed: true })`
- L112: `adapter.createSnag({ location: loc.trim(), description: desc.trim(), priority: pri.trim(), responsibleParty: party.trim(), dueAt: null, evidenceIds: [], blocksPayment: false, humanConfirmed: true })`
- L122: `adapter.createInstruction({ title: title.trim(), instruction: body.trim(), costImpact: cost.trim(), timeImpact: time.trim(), linkedRfiId: null, humanConfirmed: true })`
- L137: `async function runAction(def, main, id, act) {`
- L146: `adapter.submitNcrCorrectiveAction(id, { correctiveAction: ca.trim(), evidenceIds: [], humanConfirmed: true })`
- L152: `adapter.decideNcr(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true })`
- L158: `adapter.allocateSnag(id, { humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag allocated and verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L161: `adapter.markSnagReady(id, { evidenceIds: [], humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag marked ready for reinspection.')).catch(e => notice(e.message + ' No success claimed.'));`
- L166: `adapter.decideSnag(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag decision verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L171: `adapter.issueInstruction(id, { reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Site instruction issued and verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L175: `async function refresh(def) {`
- L183: `async function apply(toolId) {`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="snag-manager"></a>

## snag_manager — Snag Manager

**Placement:** workspace-tools / close-out / family review. **Open:** `openTool('snag_manager', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** Allocation, evidence, reinspection and human closure.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no location, evidence, allocation, corrective-action or reinspection decision form.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/site-execution-workspace`; returned snags fields: id, title, status, dueOn, severity, priority.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/site-field.js`:

- L29: `async function load(def) {`
- L31: `const site = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId) + '/site-execution-workspace'));`
- L102: `adapter.createNcr({ title: title.trim(), description: desc.trim(), severity: sev.trim(), responsibleParty: party.trim(), evidenceIds: [], blocksPayment: false, humanConfirmed: true })`
- L112: `adapter.createSnag({ location: loc.trim(), description: desc.trim(), priority: pri.trim(), responsibleParty: party.trim(), dueAt: null, evidenceIds: [], blocksPayment: false, humanConfirmed: true })`
- L122: `adapter.createInstruction({ title: title.trim(), instruction: body.trim(), costImpact: cost.trim(), timeImpact: time.trim(), linkedRfiId: null, humanConfirmed: true })`
- L137: `async function runAction(def, main, id, act) {`
- L146: `adapter.submitNcrCorrectiveAction(id, { correctiveAction: ca.trim(), evidenceIds: [], humanConfirmed: true })`
- L152: `adapter.decideNcr(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true })`
- L158: `adapter.allocateSnag(id, { humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag allocated and verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L161: `adapter.markSnagReady(id, { evidenceIds: [], humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag marked ready for reinspection.')).catch(e => notice(e.message + ' No success claimed.'));`
- L166: `adapter.decideSnag(id, { decision: decision.trim(), reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Snag decision verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L171: `adapter.issueInstruction(id, { reason: reason.trim(), humanConfirmed: true }).then(() => refresh(def)).then(() => notice('Site instruction issued and verified.')).catch(e => notice(e.message + ' No success claimed.'));`
- L175: `async function refresh(def) {`
- L183: `async function apply(toolId) {`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="fm-bridge"></a>

## fm_bridge — FM Bridge

**Placement:** workspace-tools / close-out / family administer. **Open:** `openTool('fm_bridge', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `True` (can differ from placement).

**Registry evidence claim:** CloseoutRoutes, handover, occupation and defects-liability records.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no handover checklist, document association or acceptance form. External FM synchronisation is not provided by the closeout API.

**Read input/output contracts:**
- `/api/v1/projects/{projectId}/closeout-workspace`; returned handoverPacks fields: id, title, status, createdAt.
- `/api/v1/projects/{projectId}/closeout-workspace`; returned handoverDocuments fields: id, title, category, status.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/closeout.js`:

- L7: `// by the adapter. External FM synchronisation is not provided by the closeout API and`
- L34: `async function refresh() {`
- L127: `if (!/^[a-f0-9]{32}$/i.test(revisionId)) { notice('A controlled-document revision id owned by this project is required. Nothing was saved.'); return; }`
- L136: `adapter.issuePack(packId, { reason, humanConfirmed: true })`
- L163: `adapter.issueCompletion(id, { reason, humanConfirmed: true })`
- L169: `adapter.acceptCompletion(id, { reason, humanConfirmed: true })`
- L178: `async function apply(toolId) {`
- L186: `return { apply, dispose, tool: TOOL, supports: id => id === TOOL };`

Source `app/public/assets/v1-clone/js/adapters/closeout.js`:

- L7: `// scope(), current(), context.write with an exact readBackPath, an explicit read-back`
- L28: `function identity(value,label){if(!/^[a-f0-9]{32}$/i.test(String(value||'')))throw Error('A valid '+label+' id is required.');return String(value).toLowerCase();}`
- L29: `function reasonText(value,label,required){const text=typeof value==='string'?value.trim():'';if(required&&!text)throw Error('A '+label+' reason is required.');return text||null;}`
- L30: `function boolMap(value,label){if(!value||typeof value!=='object'||Array.isArray(value)||Object.keys(value).length===0)throw Error('A '+label+' checklist is required.');const out={};for(const key of Object.keys(value)){if(typeof value[key]!=='boolean'||!key)throw Error('Every '+label+' checklist value must be true or false.');out[key]=value[key];}return out;}`
- L32: `async function readWorkspace(s){const data=unwrap(await context.read(path(s,'closeout-workspace')));current(s);return data;}`
- L33: `async function verify(s,collection,id){const data=await readWorkspace(s);const record=rows(data,collection).find(r=>r.id===id);if(!record)throw Error('Close-out persistence not verified; refresh before retrying.');return record;}`
- L37: `async function listCloseout(){`
- L56: `async function createPracticalCompletion(input){`
- L59: `const saved=unwrap(await context.write(path(s,'practical-completions'),{checklist,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'practicalCompletions'}));current(s);`
- L62: `async function issueCompletion(completionId,{reason,humanConfirmed=false}={}){`
- L64: `if(humanConfirmed!==true)throw Error('Practical-completion issue requires explicit human confirmation.');`
- L68: `unwrap(await context.write('/practical-completions/'+encodeURIComponent(id)+'/issue',{reason:text,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'practicalCompletions'}));current(s);`
- L73: `async function acceptCompletion(completionId,{reason,humanConfirmed=false}={}){`
- L75: `if(humanConfirmed!==true)throw Error('Client acceptance of practical completion requires explicit human confirmation.');`
- L79: `unwrap(await context.write('/practical-completions/'+encodeURIComponent(id)+'/accept',{reason:text,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'practicalCompletions'}));current(s);`
- L84: `async function createHandoverPack(){`
- L86: `const saved=unwrap(await context.write(path(s,'handover-packs'),{}, {readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);`
- L89: `return {...record,requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED};`
- L93: `async function addHandoverDocument(packId,{category,title,versionLabel,fileId=null,documentRevisionId=null}={}){`
- L96: `if(!CATEGORIES.includes(category))throw Error('A valid handover category is required ('+CATEGORIES.join(', ')+').');`
- L97: `const name=typeof title==='string'?title.trim():'';if(!name)throw Error('A handover document title is required.');`
- L98: `const version=typeof versionLabel==='string'?versionLabel.trim():'';if(!version)throw Error('A handover document version label is required.');`
- L102: `const saved=unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/documents',{category,title:name,versionLabel:version,fileId:file,documentRevisionId:revision},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverDocuments'}));current(s);`
- L108: `async function refreshPack(packId){`
- L111: `unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/refresh',{}, {readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);`
- L113: `return {...record,requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED};`
- L115: `async function issuePack(packId,{reason,humanConfirmed=false}={}){`
- L117: `if(humanConfirmed!==true)throw Error('Handover pack issue requires explicit human confirmation.');`
- L119: `unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/refresh',{}, {readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);`
- L122: `unwrap(await context.write('/handover-packs/'+encodeURIComponent(id)+'/issue',{reason:text,humanConfirmed:true},{readBackPath:path(s,'closeout-workspace'),readBackCollection:'handoverPacks'}));current(s);`
- L130: `async function occupationReadiness(input){`
- L134: `const body={municipalCertificateFileId:file,municipalCertificateVerified:input?.municipalCertificateVerified===true,insuranceTransitionConfirmed:input?.insuranceTransitionConfirmed===true,utilitiesChecklist:checklist,humanConfirmed:true};`
- L135: `const saved=unwrap(await context.write(path(s,'occupation-readiness'),body,{readBackPath:path(s,'closeout-workspace'),readBackCollection:'occupationReadiness'}));current(s);`
- L139: `return {boundary:BOUNDARY,categories:CATEGORIES.slice(),requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED,listCloseout,createPracticalCompletion,issueCompletion,acceptCompletion,createHandoverPack,addHandoverDocument,refreshPack,issuePack,occupationReadiness};`
- L141: `root.V1CloneCloseoutAdapter={create,boundary:BOUNDARY,categories:CATEGORIES.slice(),requiredCategories:REQUIRED_CATEGORIES.slice(),externalFmSyncSupported:EXTERNAL_FM_SYNC_SUPPORTED};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="remote-desktop"></a>

## remote_desktop — Remote Desktop

**Placement:** workspace-tools / platform / family administer. **Open:** `openTool('remote_desktop', mode)`. **Registry availability claim:** `integration_required`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Governance exists; host agent, broker, viewer and allowlist are unconfigured.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Remote Desktop integration is explicitly skipped. Host agent, broker, viewer and allowlist remain unconfigured; no remote session is started.

**Read input/output contracts:**
- `/api/v1/tools`; returned  fields: id, name, status, evidence.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/icon-map.js`:


**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="cpd-learning"></a>

## cpd_learning — CPD & Learning

**Placement:** knowledge-cpd / platform / family find. **Open:** `openTool('cpd_learning', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** CPDRoutes, review, assessments, entitlements, certificates and records.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no learner profile, enrolment, assessment answer, accreditation or certificate action controls.

**Read input/output contracts:**
- `/api/v1/cpd/workspace`; returned courses fields: id, title, status.
- `/api/v1/cpd/workspace`; returned records fields: id, activityTitle, completedOn, creditsAwarded, professionalBody, approvedCategory.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/cpd.js`:

- L7: `if(!root.V1CloneCpdAdapter||typeof root.V1CloneCpdAdapter.create!=='function')throw Error('The CPD adapter is required before the CPD controller.');`
- L49: `return head+options.map(option=>'<label class="cpd-option"><input type="'+input+'" name="'+escape(question.id)+'" value="'+escape(option.id)+'"> '+escape(option.optionText??option.text??'Option')+'</label>').join('')+'</fieldset>';`
- L61: `async function renderCourses(rootEl){`
- L74: `async function startAttempt(rootEl,button){`
- L85: `const ws=await context.read('/cpd/workspace');`
- L103: `async function submitAttempt(rootEl,button){`
- L116: `const record=await adapter.scoreAttempt({attemptId:currentAttempt.id,answers,humanConfirmed:true});`
- L125: `async function verifyCertificate(rootEl,button){`
- L141: `async function apply(toolId){`

Source `app/public/assets/v1-clone/js/adapters/cpd.js`:

- L21: `async function workspace(){`
- L22: `const s=scope();const data=unwrap(await context.read('/cpd/workspace'));current(s);`
- L26: `async function listCourses(){`
- L27: `const s=scope();const data=unwrap(await context.read('/cpd/workspace'));current(s);`
- L30: `async function createAssessment(input){`
- L32: `if(input.humanConfirmed!==true)throw Error('CPD assessment creation requires explicit human confirmation.');`
- L33: `if(!HEX32.test(input.contentId||''))throw Error('A valid content id is required to create an assessment.');`
- L34: `if(context.capabilities?.readBackCollection!==true)throw Error('Assessment save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L35: `const body={humanConfirmed:true,contentId:input.contentId,title:input.title,passPercent:input.passPercent,`
- L37: `const saved=unwrap(await context.write('/cpd/assessments',body,{readBackPath:'/cpd/workspace',readBackCollection:'assessments',projectScoped:false}));current(s);`
- L38: `const data=unwrap(await context.read('/cpd/workspace'));current(s);`
- L43: `async function attemptAssessment({courseId}={}){`
- L45: `if(!HEX32.test(courseId||''))throw Error('A valid course id is required to start an assessment.');`
- L46: `if(context.capabilities?.readBackCollection!==true)throw Error('Attempt start unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L47: `const saved=unwrap(await context.write('/cpd/courses/'+encodeURIComponent(courseId)+'/attempts',{},{`
- L49: `const data=unwrap(await context.read('/cpd/workspace'));current(s);`
- L54: `async function scoreAttempt({attemptId,answers,humanConfirmed=false}={}){`
- L56: `if(humanConfirmed!==true)throw Error('Attempt submission requires explicit human confirmation.');`
- L57: `if(!HEX32.test(attemptId||''))throw Error('A valid attempt id is required.');`
- L58: `if(!Array.isArray(answers)||answers.length===0)throw Error('Answers are required before submitting an assessment.');`
- L59: `if(context.capabilities?.readBackCollection!==true)throw Error('Attempt grading unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L60: `const saved=unwrap(await context.write('/cpd/attempts/'+encodeURIComponent(attemptId)+'/submit',{humanConfirmed:true,answers},{`
- L62: `const data=unwrap(await context.read('/cpd/workspace'));current(s);`
- L64: `if(!record||!['passed','failed','manual_review_required'].includes(record.status))throw Error('Attempt grading not verified; refresh before retrying.');`
- L67: `async function fetchCertificate(verificationCode){`
- L69: `if(typeof verificationCode!=='string'||!CODE32.test(verificationCode.trim()))throw Error('A valid 32-character certificate verification code is required.');`
- L70: `const data=unwrap(await context.read('/cpd/certificates/verify/'+encodeURIComponent(verificationCode.trim())));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="admin-review"></a>

## admin_review — Admin Review

**Placement:** admin-settings / platform / family review. **Open:** `openTool('admin_review', mode)`. **Registry availability claim:** `limited`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Completeness, QC, capability and release-gate workspaces are live.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "verification.review"}; admin: {"read": true, "write": true, "review": true, "permission": "verification.review"}; member: {"read": false, "write": false, "review": false, "permission": "verification.review"}; viewer: {"read": false, "write": false, "review": false, "permission": "verification.review"}. Endpoint-specific professional gates remain additional.

**Native boundary:** Review independent registration evidence with a reason and explicit confirmation, and inspect your own verification status separately. Membership scope is tenant-bound; self-review and unsupported external verification remain protected.

**Production native next actions:** review-verification → admin_review (verification.review, independent_human_review)

**Original scaffold boundary (not enhanced-module verdict):** Original has no selected verification, decision, evidence review reason or human-confirmation fields. Permission errors remain visible.

**Read input/output contracts:**
- `/api/v1/verification-review-queue`; returned  fields: id, kind, grade, effectiveGrade, registration_body, expires_on.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/governance.js`:

- L23: `async function load(def) {`
- L26: `const p = unwrap(await context.read('/projects/' + encodeURIComponent(s.selectedProjectId)));`
- L35: `try { profile = unwrap(await context.read('/professional-profile')) || null; }`
- L37: `return { directory: unwrap(await context.read('/directory/search')) || [], profile };`
- L95: `<form data-review-form><label>Decision <select name="decision" required><option value="">Select a decision</option><option value="manually_reviewed">Manually reviewed</option><option value="externally_verified">Externally verified</option><option value="rejected">Rejected</option></select></label>`
- L96: `<label>Reason <textarea name="note" required maxlength="4000"></textarea></label>`
- L97: `<label>External provider <input name="provider" maxlength="160"></label><label>Provider evidence reference <input name="providerReference" maxlength="180"></label><p>External verification requires a real provider and evidence reference supplied by the reviewer.</p>`
- L98: `<label><input type="checkbox" name="humanConfirmed" required> I confirm this decision for the selected record.</label><button class="btn" type="submit">Record decision</button></form>\`;`
- L100: `fields.decision.onchange=()=>{fields.provider.required=fields.providerReference.required=fields.decision.value==='externally_verified';};`
- L103: `const decision=fields.decision.value,payload={note:fields.note.value,provider:fields.provider.value,providerReference:fields.providerReference.value,humanConfirmed:fields.humanConfirmed.checked};`
- L120: `async function refresh(def) { if (disposed || !ownsRoute(routeFor(def))) return false;`
- L126: `async function apply(toolId) {`

Source `app/public/assets/v1-clone/js/adapters/governance.js`:

- L5: `// Follows the canonical site-tools.js pattern: scope()/current(), context.write with read-back`
- L6: `// verify, humanConfirmed guards, never fabricated success.`
- L20: `async function listTeam(){`
- L22: `const data=unwrap(await context.read(path(s,'team')));current(s);`
- L26: `async function invite({candidateId,discipline,roleLabel}){`
- L28: `if(!candidateId||!discipline)throw Error('A candidate and discipline are required.');`
- L29: `const saved=unwrap(await context.write(path(s,'team'),{candidateId,discipline,roleLabel:roleLabel||discipline},{readBackPath:path(s,'team')}));current(s);`
- L34: `async function removeMember(teamMemberId,reason){`
- L36: `if(!reason||!String(reason).trim())throw Error('A removal reason is required.');`
- L37: `await context.write('/projects/'+encodeURIComponent(s.selectedProjectId)+'/team/'+encodeURIComponent(teamMemberId),{reason:String(reason).trim(),humanConfirmed:true},{method:'DELETE',readBackPath:path(s,'team')});current(s);`
- L41: `async function searchDirectory(profession,serviceArea){`
- L44: `return unwrap(await context.read('/directory/search'+(q.length?'?'+q.join('&'):'')));`
- L46: `async function saveProfile({serviceAreas,directoryVisible}){`
- L48: `return unwrap(await context.write('/professional-profile',{serviceAreas:serviceAreas??[],directoryVisible:directoryVisible===true,humanConfirmed:true},{readBackPath:'/professional-profile'}));`
- L50: `async function submitVerification({kind='professional_registration',grade,registrationBody,registrationNumber,purpose,expiresOn,evidenceFileId,consentGiven,retentionUntil}={}){`
- L52: `if(consentGiven!==true)throw Error('Explicit consent is required before submitting a verification.');`
- L53: `if(!['self_declared','document_uploaded'].includes(grade))throw Error('A valid grade (self_declared or document_uploaded) is required.');`
- L54: `if(!['SACAP','ECSA','SACQSP','SACLAP','SACPCMP'].includes(String(registrationBody).toUpperCase()))throw Error('A recognised professional body is required (SACAP, ECSA, SACQSP, SACLAP, SACPCMP).');`
- L55: `if(!purpose||!String(purpose).trim())throw Error('A POPIA purpose is required.');`
- L56: `if(!retentionUntil)throw Error('A retention date is required.');`
- L57: `return unwrap(await context.write('/verifications',{kind,grade,registrationBody:String(registrationBody).toUpperCase(),registrationNumber:registrationNumber||null,purpose:String(purpose).trim(),expiresOn:expiresOn||null,evidenceFileId:evidenceFileId||null,consentGiven:true,retentionUntil},{readBackPath:'/verification-review-queue'}));`
- L59: `function reviewer(){const s=scope(false),member=s.session.memberships?.find(m=>m.organisationId===s.organisationId);if(!['owner','admin'].includes(member?.role)||(member.status&&member.status!=='active'))throw Error('An active owner or admin membership is required.');return s;}`
- L61: `async function reviewQueue(){const s=reviewer();const rows=verificationRows(await context.read('/verification-review-queue'));current(s);return rows;}`
- L62: `async function ownVerifications(){const s=scope(false);const rows=verificationRows(await context.read('/verifications'));current(s);return rows;}`
- L64: `async function reviewVerification(verificationId,decision,{provider,providerReference,note,humanConfirmed}={}){`
- L67: `if(humanConfirmed!==true)throw Error('Explicit human confirmation is required.');`
- L68: `if(!note?.trim())throw Error('A review reason is required.');`
- L69: `if(!['manually_reviewed','externally_verified','rejected'].includes(decision))throw Error('A valid decision is required.');`
- L74: `const saved=unwrap(await context.write('/verifications/'+verificationId+'/review',{decision,provider:provider?.trim()||null,providerReference:providerReference?.trim()||null,note:note.trim(),humanConfirmed:true},{projectScoped:false,readBackPath:'/verification-review-queue',readBackAbsentId:verificationId}));current(s);`
- L81: `async function analyticsWorkspace(){const s=scope(true);return unwrap(await context.read(path(s,'analytics-workspace')));}`
- L82: `async function refreshKpis({asOf}={}){`
- L85: `return unwrap(await context.write(path(s,'analytics/kpis/refresh'),body,{readBackPath:path(s,'analytics-workspace')}));`
- L87: `async function createAlertRule(rule){`
- L90: `return unwrap(await context.write(path(s,'analytics/alert-rules'),body,{readBackPath:path(s,'analytics-workspace')}));`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="iconography-registry"></a>

## iconography_registry — Iconography Registry

**Placement:** admin-settings / platform / family administer. **Open:** `openTool('iconography_registry', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Central semantic V1 icon map is searchable and reusable.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.

**Original scaffold boundary (not enhanced-module verdict):** Original has no icon editor or search control. Backend tool metadata is read-only; original icon shapes and tones are preserved.

**Read input/output contracts:**
- `/api/v1/tools`; returned  fields: id, name, group, stage, status.

**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/iconography.js`:

- L343: `async function apply(toolId) {`
- L348: `root.showToast?.('Icon registry unavailable: js/architex-icon-pack.js did not load.');`
- L349: `return { applied: false, reason: 'registry-unavailable' };`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="meetings"></a>

## meetings — Architex Meetings

**Placement:** collaboration / platform / family review. **Open:** `openTool('meetings', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Consent, transcript review, outcome approval and immutable minutes.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/meetings.js`:

- L10: `data.lifecycle=['Draft','Scheduled','Started','Review required','Published'];`
- L16: `async function attach(){try{if(await context.setMode('project')===false)return;if(projectRequired())return notice('No authorised project is available.');root.openTool?.('meetings','project');}catch(e){notice(e.message);}}`
- L23: `async function refresh(){const token=++generation,origin=key();if(projectRequired()){rows=[];selectedId=null;loaded=true;error='';render();return false;}try{const value=await adapter.list();if(token!==generation||origin!==key())return false;rows=value;if(selectedId&&!selected())selectedId=null;loaded=true;error='';render();return true;}catch(e){if(token===generation&&origin===key()){rows=[];loaded=false;error=e.message;render();}return false;}}`
- L24: `async function save(operation,after){if(pending)return notice('A meeting save is already pending.');pending=true;apply('meetings');const origin=key();try{const saved=await operation();if(origin!==key())throw Error('Project changed; refresh the original project.');selectedId=saved.id;const i=rows.findIndex(r=>r.id===saved.id);if(i<0)rows.push(saved);else rows[i]=saved;after?.(saved);error='Saved record verified by read-back.';render();return saved;}catch(e){notice(e.message+' No verified success. Refresh before retrying an uncertain write.');return null;}finally{pending=false;render();}}`
- L25: `function capture(){if(ui().meetingScreen!=='schedule'||!draft)return;all('#main [data-mtg-field]').forEach(e=>{const name=e.dataset.mtgField;if(name.startsWith('policy.'))draft.policy[name.slice(7)]=e.value;else draft[name]=name==='durationMinutes'?Number(e.value):e.value;});all('#main .mtg-agenda-item').forEach((row,i)=>{const inputs=Array.from(row.querySelectorAll('input'));if(inputs.length===3)draft.agenda[i]={title:inputs[0].value,owner:inputs[1].value,minutes:Number(inputs[2].value)};});drafts.set(current,{draft,reviewDraft,selectedId});}`
- L29: `const unavailable=()=>notice('Media, live calls and external calendar delivery are unavailable. No connection or recording was started.');`
- L34: `root.toggleMeetingControl=unavailable;`
- L35: `function select(id){capture();if(pending)return;if(reviewDraft&&selectedId)reviewDrafts.set(current+':'+selectedId,reviewDraft);selectedId=id;reviewDraft=reviewDrafts.get(current+':'+id)||null;const r=selected();if(!r)return;ui().meetingScreen=({scheduled:'prejoin',live:'room',review_required:'review',published:'issued'})[r.status]||'home';render();}`
- L36: `async function schedule(){capture();if(!draft)return;if(!root.confirm?.('Save this meeting, agenda, attendees and policy? No external invitations or calendar delivery will be sent.'))return;`
- L39: `async function start(){const r=selected();if(!r)return;const consent=one('#mtgConsent');if(!consent?.checked)return notice('Review and acknowledge the saved meeting policy first.');if(!root.confirm?.('Start this meeting record? Consent is recorded only for your matching attendee identity; live media remains unavailable.'))return;`
- L46: `root.setMeetingOutcome=async(index,newState)=>{const r=selected();if(!r||r.status!=='review_required')return notice('Save draft evidence before reviewing outcomes.');const reason=promptValue('Reason for '+newState+' outcome (verify its transcript source)');if(!reason||!root.confirm?.('Record this outcome review?'))return;return save(()=>adapter.reviewOutcome(r.id,index,newState,reason));};`
- L47: `root.publishMeetingMinutes=async()=>{const r=selected();if(!r)return;if(r.status==='live'){if(!reviewDraft||!root.confirm?.('Conclude this record with the entered transcript, sourced minutes and proposed outcomes?'))return;return save(()=>adapter.conclude(r.id,reviewDraft),()=>{reviewDrafts.delete(current+':'+r.id);reviewDraft=null;ui().meetingScreen='review';});}if(r.status!=='review_required')return notice('Only draft minutes awaiting review can be published.');if(r.minutes?.outcomes?.some(x=>!['accepted','rejected'].includes(x.state)))return notice('Review every proposed outcome first.');const reason=promptValue('Publication reason');if(!reason||!root.confirm?.('Confirm human review and publish these minutes and accepted outputs?'))return;return save(()=>adapter.publish(r.id,{reviewerConfirmed:true,publishReason:reason}),()=>{ui().meetingScreen='issued';});};`
- L53: `if(step===4){field('Confidentiality','policy.confidentiality',draft.policy.confidentiality);for(const label of ['Recording','Transcription']){const el=field(label,'unavailable','off',[['off','Off — media unavailable']]);if(el)el.disabled=true;}const retention=field('Retention','retention',90,[[90,'90 days']]);if(retention)retention.disabled=true;}`
- L55: `all('#main .mtg-summary li').forEach((e,i)=>{e.classList.remove('ok');e.textContent=['Project: selected; title: '+(draft.title?'entered':'required'),'Agenda: '+draft.agenda.filter(a=>a.title&&a.owner).length+' complete items','Attendees: '+draft.attendees.length,'Time: '+(draft.date&&draft.time?'entered':'required'),'Media off; policy saved with meeting'][i];});`
- L58: `function review(){const r=selected();text('#main .mtg-actions h2',r?.title||'Select a meeting record');text('#main .mtg-actions p',r?.status==='live'?'Human-entered evidence · no automated transcript':'Saved transcript sources · human review required');text('#main .mtg-actions > .mtg-badge',r?.minutes?.status||'Draft evidence');`
- L62: `all('#main .mtg-outcome').forEach((e,i)=>{const edit=Array.from(e.querySelectorAll('button')).find(x=>x.textContent==='Edit');if(edit){edit.removeAttribute('onclick');edit.disabled=!reviewDraft||pending;edit.title=reviewDraft?'Edit unsaved outcome':'The API supports accept/reject with a reason; editing saved outcomes is unavailable.';edit.onclick=()=>addOutcome(i);}});`
- L66: `let status=shell.querySelector('[data-meeting-status]');if(!status){status=doc.createElement('div');status.className='mtg-policy';status.dataset.meetingStatus='';status.setAttribute('role','status');shell.prepend(status);}status.textContent=projectRequired()?'Meetings requires a selected project. Attach a project to schedule and review saved records.':error||(!loaded?'Loading meetings…':'Saved meeting records. Live media, recording and external invitations are unavailable.');if(projectRequired()){const b=doc.createElement('button');b.className='btn';b.textContent='Attach project';b.disabled=!context.getState().projects?.length;b.onclick=attach;status.append(b);}if(!projectRequired()&&!loaded&&!pending){const b=doc.createElement('button');b.className='btn ghost';b.textContent='Retry load';b.onclick=refresh;status.append(b);}`
- L68: `text('#main .mtg-context-ribbon > b','No project attached');text('#main .mtg-context-ribbon > b + span','Select a project to use meeting records');text('#navigator .nav-sub','Project attachment required');`
- L75: `else if(screen==='prejoin'){text('#main .mtg-network','Live media unavailable');text('#main .mtg-preview-person b',context.getState().session.email);text('#main .mtg-preview-person .mtg-avatar','—');text('#main .mtg-preview-person > span','Camera unavailable');text('#main .mtg-prejoin aside > p',r?.title||'Select a saved meeting');text('#main .mtg-prejoin .mtg-policy','Saved policy: '+(r?.policy?.confidentiality||'—')+' · '+(r?.policy?.retentionDays||'—')+' days. Recording '+(r?.policy?.recordingRequested?'requested':'off')+'; transcription '+(r?.policy?.transcriptionRequested?'requested':'off')+'. No media is connected.');all('#main .mtg-device select').forEach(e=>{e.disabled=true;e.options[0].textContent='Unavailable';});button('Join meeting',start,'Record consent & start record');button('Join audio-only',unavailable);}`
- L76: `else if(screen==='room'){text('#main .mtg-room-top b',r?.title||'Meeting record');text('#main .mtg-room-top > .mtg-badge','No live call');text('#main .mtg-record-state','Recording unavailable');text('#main .mtg-ai-state','Transcription unavailable');text('#main .mtg-drawing','No screen share connected');text('#main .mtg-annotation-copy','');const ann=one('#main .mtg-annotation');if(ann)ann.style.display='none';all('#main .mtg-person').forEach(e=>{e.classList.remove('speaking');e.querySelector('b').textContent='—';e.querySelector('small').textContent='Not connected';});if(['people','chat','context'].includes(ui().meetingRoomPanel)){const body=one('#main .mtg-room-panel-body');if(body){body.replaceChildren();const p=doc.createElement('p');p.className='mtg-note';p.textContent=ui().meetingRoomPanel==='people'?(r?.attendees||[]).map(a=>a.identity+' · '+a.role+' (listed, not connected)').join('\n'):'No connected '+ui().meetingRoomPanel+' data';body.append(p);}}button('End for all',()=>root.openMeetingScreen('review'),'Prepare minutes');}`
- L77: `else {const reviewRecord=rows.find(x=>x.status==='review_required');text('#main .mtg-card.review p',reviewRecord?reviewRecord.title:'No minutes awaiting review');text('#main .mtg-card.review .mtg-badge',reviewRecord?'Review required':'No pending review');button('Review minutes',()=>reviewRecord?select(reviewRecord.id):notice('No minutes awaiting review.'));all('#main .mtg-card').forEach(card=>{const h=card.querySelector('h3')?.textContent;if(h==='Upcoming this week'){card.querySelector('p').textContent=rows.filter(x=>x.status==='scheduled').length+' scheduled records in this project.';card.querySelector('b').textContent='No external calendar connected';}if(h==='Open meeting actions')card.querySelector('p').textContent=rows.reduce((n,x)=>n+(x.minutes?.outcomes?.filter(o=>o.type==='action'&&o.state==='accepted').length||0),0)+' accepted action outputs (completion not tracked here).';if(h==='Processing'){card.querySelector('p').textContent='No media processing connected';card.querySelector('span').textContent='Recordings unavailable';}});}`
- L80: `all('#main button[onclick]').forEach(b=>{const action=b.getAttribute('onclick')||'';if(action.includes('showToast(')){b.removeAttribute('onclick');b.onclick=unavailable;b.title='Not connected';}if(/publishMeetingMinutes|setMeetingOutcome/.test(action))b.disabled=!adapter.canWrite()||pending;});`
- L88: `function inspect(){if(ui().tool!=='meetings')return;const r=selected();const s=context.getState();const name=projectRequired()?'Project attachment required':s.projects.find(p=>p.id===s.selectedProjectId)?.name||'Selected project';all('#inspectBody .inspect-note').forEach(e=>e.textContent='Saved meeting governance only. Media, recording and automated transcription are unavailable.');all('#inspectBody .inspect-block').forEach(block=>{const heading=block.querySelector('h4')?.textContent,p=block.querySelector('p');if(!p)return;if(['Current scope','Meeting context'].includes(heading))p.textContent=name+(r?' · '+r.title:'');if(heading==='Connection')p.textContent='No live connection or device session.';if(heading==='Lifecycle')p.textContent='Scheduled → Started record → Human evidence → Review required → Published';if(heading==='Write-back preview')p.textContent='Only accepted action/decision outputs are published by the service.';});}`

Source `app/public/assets/v1-clone/js/adapters/meetings.js`:

- L6: `const required=(v,label)=>{if(typeof v!=='string'||!v.trim())throw Error(label+' is required.');return v.trim();};`
- L10: `function scope(write=false){const s=context.getState();if(!s.ready||!s.session||!s.organisationId)throw Error('An authenticated project is required.');if(projectRequired())throw Error('Select an authorised project before using meetings.');identity(s.selectedProjectId);if(write&&!['owner','admin','member'].includes(s.session.memberships?.find(m=>m.organisationId===s.organisationId)?.role))throw Error('Meetings are read-only for this membership.');return s;}`
- L11: `async function list(){const s=scope(),rows=await context.read('/meetings?projectId='+s.selectedProjectId);if(!Array.isArray(rows))throw Error('Invalid meetings response.');return rows.filter(m=>m.projectId===s.selectedProjectId);}`
- L12: `async function write(path,payload){const s=scope(true);return context.write(path,{...payload,projectId:s.selectedProjectId},{projectScoped:true,readBackPath:saved=>'/meetings/'+identity(saved.id)});}`
- L13: `async function schedule(p){scope(true);required(p.title,'Meeting title');required(p.meetingType,'Meeting type');if(!phases.includes(p.phase))throw Error('Choose a valid work stage.');required(p.startsAt,'Start time');if(!p.agenda?.length||!p.attendees?.length)throw Error('Agenda and attendees are required.');if(!p.policy||!Number.isInteger(p.durationMinutes))throw Error('Duration and policy are required.');return write('/meetings',p);}`
- L14: `async function consent(id,attendeeIdentity,consented){scope(true);identity(id);required(attendeeIdentity,'Attendee');if(typeof consented!=='boolean')throw Error('Consent choice is required.');return write('/meetings/'+id+'/consents',{attendeeIdentity,consented});}`
- L15: `async function start(id){scope(true);return write('/meetings/'+identity(id)+'/start',{});}`
- L16: `async function conclude(id,p){scope(true);identity(id);if(!p.transcript?.length||!p.minutes?.length||!p.outcomes?.length)throw Error('Human-entered transcript, sourced minutes and outcomes are required.');return write('/meetings/'+id+'/conclude',p);}`
- L17: `async function reviewOutcome(id,index,state,reason){scope(true);identity(id);required(reason,'Review reason');if(!Number.isInteger(index)||index<0||!['accepted','rejected'].includes(state))throw Error('Invalid outcome review.');return write('/meetings/'+id+'/outcomes/'+index+'/review',{state,reason});}`
- L18: `async function publish(id,p){scope(true);identity(id);if(p.reviewerConfirmed!==true)throw Error('Human review confirmation is required.');required(p.publishReason,'Publication reason');return write('/meetings/'+id+'/minutes/publish',p);}`
- L19: `function viewModel(rows,selectedId){const s=context.getState(),selected=rows.find(m=>m.id===selectedId),labels={scheduled:'Scheduled',live:'Started · media unavailable',review_required:'Review required',published:'Published'};`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="engineering-calc"></a>

## engineering_calc — Engineer's Calculation Hub

**Placement:** workspace-tools / design / family create. **Open:** `openTool('engineering_calc', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Versioned inputs, professional review and governed outputs.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "tools.use"}; admin: {"read": true, "write": true, "review": true, "permission": "tools.use"}; member: {"read": true, "write": true, "review": false, "permission": "tools.use"}; viewer: {"read": false, "write": false, "review": false, "permission": "tools.use"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**

Source `app/public/assets/v1-clone/js/modules/site-tools.js`:

- L5: `const supports=id=>Object.hasOwn(root.V1CloneSiteToolsAdapter.aliases,id);`
- L10: `button.title=review?'Unavailable: a governed engineering review workflow is not connected.':button.disabled?'Read-only membership: calculation saving requires write permission.':'Only edited Rational Method inputs can be saved as an advisory run.';`
- L18: `if(review||!active)return notice('Calculation not saved or sent to review: only Rational Method saving is mapped to Toolbox. Other calculators and professional review remain unavailable in this hub.');`
- L27: `const boundary='Internal records only — not statutory approval, safety certification or permission to work.';`
- L28: `const unavailable='Not available from the connected service.';`
- L30: `if(!supports(id))return html;`
- L42: `else if(!el.classList.contains('tab')&&!el.classList.contains('nav-item')&&!/activateTab/.test(el.getAttribute('onclick')||'')){el.dataset.siteUnavailable='';el.removeAttribute('onclick');el.title=unavailable;}`
- L45: `doc.querySelectorAll('tbody').forEach(el=>{const sample=el.querySelector('tr');if(sample){while(el.children.length>1)el.lastElementChild.remove();sample.querySelectorAll('td').forEach(cell=>{cell.textContent='—';});}el.dataset.siteRecords='unavailable';});`
- L55: `card.innerHTML='<h2 class="card-title">Internal safety inspections</h2><p>Draft findings only. Not safety certification or permission to work.</p><button class="action-btn" type="button" data-site-create>Draft safety inspection</button><table class="table"><thead><tr><th>Inspection</th><th>Findings</th><th>Revision</th><th>Status</th><th>Checklist</th><th>Follow-ups</th><th>Boundary</th></tr></thead><tbody data-site-records="inspections"><tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table>';`
- L60: `const status=doc.createElement('p');status.className=id==='itp'?'card-title':'card-title';status.dataset.siteToolsStatus='';status.setAttribute('role','status');status.textContent=id==='engineering_calc'?'Local advisory calculator — inputs and results are not saved; professional verification required.':('Loading project records. '+boundary);`
- L65: `const headers=kind==='inspections'?['Internal inspection','Findings','Revision unavailable','Workflow status','Checklist items','Follow-ups','Boundary']:kind==='plans'?['Subject','Reference','Recorded by','Recorded at','Evidence grade','Record status','Boundary']:['ID','Title','Severity','Status','Description'];`
- L69: `const values=r=>kind==='inspections'?[r.inspectionType+' inspection',r.findings?.join('; ')||'No findings recorded','—',r.status,r.checklist?.length??0,r.followUps?.join('; ')||'No follow-ups recorded','Not statutory approval']:kind==='plans'?[r.subjectName,r.referenceNumber||'—',r.createdBy||'—',r.createdAt||'—',r.grade,r.status,'Not permission to work']:[r.id,r.title,r.severity,r.status,r.description];`
- L73: `async function mountFrame(id,frame){`
- L74: `if(!supports(id))return false;`
- L75: `const doc=frame.contentDocument,win=frame.contentWindow;if(!doc||!win)throw Error('A loaded same-origin frame is required.');`
- L80: `doc.querySelectorAll('[data-site-unavailable]').forEach(button=>{button.disabled=true;button.title=unavailable;button.onclick=null;});`
- L85: `const target=[...doc.querySelectorAll('h2')].find(h=>h.textContent.includes(headings[index]||'Inspection Test Plans'));target?.scrollIntoView({block:'start'});setStatus(button.textContent+': '+(index===1?'Internal inspection records. ':unavailable+' ')+boundary);`
- L89: `doc.querySelectorAll('[data-site-create]').forEach(button=>{button.disabled=context.getState().readOnly===true;button.title=button.disabled?'Read-only membership: inspection creation requires write permission.':'Create an internal draft inspection.';});`
- L95: `doc.querySelectorAll('[data-site-records]').forEach(table=>{const kind=table.dataset.siteRecords;if(Array.isArray(model[kind]))bindTable(table,model[kind],kind);else{const cell=table.querySelector('td');if(cell)cell.textContent=unavailable;}});`
- L96: `setStatus('Project: '+model.projectName+'. '+boundary+' Unsupported registers are unavailable, not empty or compliant.');return true;`
- L115: `return {supports,prepareSource,mountFrame};`

Source `app/public/assets/v1-clone/js/adapters/site-tools.js`:

- L9: `async function load(id){`
- L11: `const s=scope(),site=unwrap(await context.read(path(s,'site-execution-workspace')));`
- L13: `if(id==='safety'){const trust=unwrap(await context.read(path(s,'trust-compliance-workspace')));model.plans=rows(trust,'complianceItems',s).filter(r=>r.documentType==='health_safety_plan');model.inspections=model.inspections.filter(r=>r.inspectionType==='safety');}`
- L17: `async function createInspection(input){`
- L19: `if(context.capabilities?.readBackCollection!==true)throw Error('Draft inspection saving unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L21: `const saved=unwrap(await context.write(path(s,'inspections'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'inspections'}));current(s);`
- L22: `const data=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L25: `async function saveRational(values){`
- L30: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L31: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L36: `async function saveVoltageDrop(values){`
- L41: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L42: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L51: `async function persistRun(toolId,input,outputKey){`
- L54: `const saved=unwrap(await context.write('/toolbox/runs',body,{readBackPath:'/toolbox/runs?limit=100'}));current(s);`
- L55: `const history=unwrap(await context.read('/toolbox/runs?limit=100'));current(s);`
- L61: `if(!v||typeof v!=='object')throw Error('A compliance project description is required.');`
- L68: `function ruleVersion(v){if(!/^[a-f0-9]{32}$/i.test(v))throw Error('A registered 32-hex ruleVersionId is required for this calculator.');return v;}`
- L69: `async function saveBeamCheck(values){`
- L74: `async function saveConcreteQuantity(values){`
- L80: `async function saveMasonryQuantity(values){`
- L85: `async function saveFinishesQuantity(values){`
- L93: `async function saveTenderRateBuildUp(values){`
- L98: `async function saveLabourProductivity(values){`
- L103: `async function saveRefuseArea(values){`
- L108: `async function saveFenestrationXa(values){`
- L110: `const raw=[].concat(values.elements||[]);if(raw.length===0)throw Error('At least one fenestration element is required.');`
- L117: `async function saveRValueThermal(values){`
- L119: `const raw=[].concat(values.assemblies||[]);if(raw.length===0)throw Error('At least one thermal assembly is required.');`
- L129: `// read-back-verified, never fabricated success on an unavailable path).`
- L130: `async function createRfi(input){`
- L132: `if(input.humanConfirmed!==true)throw Error('RFI requires explicit human confirmation.');if(!input.dueAt)throw Error('A response due date is required — the record cannot be created without one.');`
- L133: `if(context.capabilities?.readBackCollection!==true)throw Error('RFI save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L135: `const body={subject:input.subject,question:input.question,discipline,dueAt:input.dueAt||null,linkedDocumentIds:input.linkedDocumentIds??[],humanConfirmed:true};`
- L136: `const saved=unwrap(await context.write(path(s,'rfis'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L137: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L140: `async function respondRfi(rfiId,{answer,requiresInstruction=false,humanConfirmed=false}={}){`
- L142: `if(humanConfirmed!==true)throw Error('RFI response requires explicit human confirmation.');`
- L143: `if(context.capabilities?.readBackCollection!==true)throw Error('RFI response save unavailable: shared collection read-back support is required. Nothing was submitted.');`
- L144: `const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/responses',{answer,requiresInstruction,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L145: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L148: `async function closeRfi(rfiId,{reason,humanConfirmed=false}={}){`
- L150: `if(humanConfirmed!==true)throw Error('RFI close requires explicit human confirmation.');`
- L151: `const saved=unwrap(await context.write('/rfis/'+encodeURIComponent(rfiId)+'/close',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'rfis'}));current(s);`
- L152: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L157: `async function createNcr(input){`
- L159: `if(input.humanConfirmed!==true)throw Error('NCR requires explicit human confirmation.');`
- L160: `const body={title:input.title,description:input.description,severity:input.severity,responsibleParty:input.responsibleParty,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};`
- L161: `const saved=unwrap(await context.write(path(s,'ncrs'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L162: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L165: `async function submitNcrCorrectiveAction(ncrId,{correctiveAction,evidenceIds=[],humanConfirmed=false}={}){`
- L167: `if(humanConfirmed!==true)throw Error('NCR corrective action requires explicit human confirmation.');`
- L168: `const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/corrective-actions',{correctiveAction,evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L169: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L172: `async function decideNcr(ncrId,{decision,reason,humanConfirmed=false}={}){`
- L174: `if(humanConfirmed!==true||!['verified_closed','rejected'].includes(decision))throw Error('NCR decision requires confirmation and a valid decision.');`
- L175: `const saved=unwrap(await context.write('/ncrs/'+encodeURIComponent(ncrId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'ncrs'}));current(s);`
- L176: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L179: `async function createSnag(input){`
- L181: `if(input.humanConfirmed!==true)throw Error('Snag requires explicit human confirmation.');if(!input.dueAt)throw Error('A due date is required — the record cannot be created without one.');`
- L182: `const body={location:input.location,description:input.description,priority:input.priority,responsibleParty:input.responsibleParty,dueAt:input.dueAt||null,evidenceIds:input.evidenceIds??[],blocksPayment:input.blocksPayment===true,humanConfirmed:true};`
- L183: `const saved=unwrap(await context.write(path(s,'snags'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L184: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L187: `async function allocateSnag(snagId,{humanConfirmed=false}={}){`
- L189: `if(humanConfirmed!==true)throw Error('Snag allocation requires explicit human confirmation.');`
- L190: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/allocate',{humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L191: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L194: `async function markSnagReady(snagId,{evidenceIds=[],humanConfirmed=false}={}){`
- L196: `if(humanConfirmed!==true)throw Error('Snag ready-for-reinspection requires explicit human confirmation.');`
- L197: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/ready-for-reinspection',{evidenceIds,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L198: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L201: `async function decideSnag(snagId,{decision,reason,humanConfirmed=false}={}){`
- L203: `if(humanConfirmed!==true||!['closed','rejected'].includes(decision))throw Error('Snag decision requires confirmation and a valid decision.');`
- L204: `const saved=unwrap(await context.write('/snags/'+encodeURIComponent(snagId)+'/decisions',{decision,reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'snags'}));current(s);`
- L205: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L208: `async function createInstruction(input){`
- L210: `if(input.humanConfirmed!==true)throw Error('Site instruction requires explicit human confirmation.');`
- L211: `const body={title:input.title,instruction:input.instruction,costImpact:input.costImpact||'none',timeImpact:input.timeImpact||'none',linkedRfiId:input.linkedRfiId||null,humanConfirmed:true};`
- L212: `const saved=unwrap(await context.write(path(s,'site-instructions'),body,{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);`
- L213: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`
- L216: `async function issueInstruction(instructionId,{reason,humanConfirmed=false}={}){`
- L218: `if(humanConfirmed!==true)throw Error('Site instruction issue requires explicit human confirmation.');`
- L219: `const saved=unwrap(await context.write('/site-instructions/'+encodeURIComponent(instructionId)+'/issue',{reason,humanConfirmed:true},{readBackPath:path(s,'site-execution-workspace'),readBackCollection:'siteInstructions'}));current(s);`
- L220: `const site=unwrap(await context.read(path(s,'site-execution-workspace')));current(s);`

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="drawing-compliance-navigator"></a>

## drawing_compliance_navigator — SA Council Drawing Compliance Navigator

**Mount status correction: unsupported-in-v1-clone.** Read the [entry/controller/input/output association](#drawing-navigator-association) above. Legacy app.php support and service operations do not establish this registry key's v1-clone mounted action. The generic journey below is a future acceptance requirement, not a claim of available controls.

**Placement:** workspace-tools / comply / family review. **Open:** `openTool('drawing_compliance_navigator', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Source-bound checks, professional gate and immutable snapshots.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "tools.use"}; admin: {"read": true, "write": true, "review": true, "permission": "tools.use"}; member: {"read": true, "write": true, "review": false, "permission": "tools.use"}; viewer: {"read": false, "write": false, "review": false, "permission": "tools.use"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**
No tool-key-specific enhanced module association located by literal-key scan. Inspect runtime shared controllers and iframe inventory before declaring actions available.

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

<a id="valuations"></a>

## valuations — Valuations

**Mount status correction: unsupported-in-v1-clone.** Read the [entry/controller/input/output association](#valuations-association) above. Legacy app.php support and service operations do not establish this registry key's v1-clone mounted action. The generic journey below is a future acceptance requirement, not a claim of available controls.

**Placement:** workspace-tools / pay / family review. **Open:** `openTool('valuations', mode)`. **Registry availability claim:** `live`. **Runtime project guard:** `False` (can differ from placement).

**Registry evidence claim:** Claims, certificates, retention and human review controls.

**Membership discovery:** owner: {"read": true, "write": true, "review": true, "permission": "projects.read"}; admin: {"read": true, "write": true, "review": true, "permission": "projects.read"}; member: {"read": true, "write": true, "review": false, "permission": "projects.read"}; viewer: {"read": true, "write": false, "review": false, "permission": "projects.read"}. Endpoint-specific professional gates remain additional.

**Production native next actions:** No additional next action declared in ProductionToolRegistry; inspect enhanced module controls, do not invent one.


**Enhanced implementation evidence / inputs / controls:**
No tool-key-specific enhanced module association located by literal-key scan. Inspect runtime shared controllers and iframe inventory before declaring actions available.

**Acceptance journey:** authenticate → check read permission → select required scope → inspect returned source fields → use an enabled source-backed action only → confirm → reload exact target and compare fields/status → independently review any issue/approval gate. Test viewer write denial, unknown membership, wrong tenant/project, stale version, missing professional evidence, empty response, timeout and unavailable provider. No success on failed save; no synthetic records rendered as tenant facts. **Evidence:** source inspection only; execution/visual/negative tests pending.

## Public, standalone and iframe entry boundaries

The machine inventory retains exact route registration and iframe-source lines, including line numbers. A public entry is not anonymous permission to protected APIs. Preserve CSRF/authentication and server role restrictions; iframe UI cannot confer authority. No remote host/provider/authority integration is inferred from a URL or prototype label. Test direct entry, blocked cross-origin content, back/forward navigation, scope changes while requests are in flight and disposal/reinstallation.

## Embedded prototype controls — explicitly not implementation receipts

The reference shell contains base64 embedded HTML samples. These input/button inventories are source-inspected local prototype controls; no backend persistence or real data follows from their presence. Runtime native controllers may replace them. Exact decoded sample SHA-256 hashes are retained in coverage.json.

### practice embedded sample

Inputs by source name/id: .

Prototype button labels: Accept; Dismiss; SpecForge Active; Sync'd with Project Passport; ‣ New Project.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### wingman embedded sample

Inputs by source name/id: .

Prototype button labels: + New; Cancel; Import with Provenance; Send; Start New Conversation; ✎ Edit; ✓ Finalise → RFI Register; ✕ Discard.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### planning embedded sample

Inputs by source name/id: .

Prototype button labels: + New Application; Active (2); All (4); Appeal; Applications; Approved (0); Audit Trail; Conditions; Dashboard; Deadlines; Documents; Draft (2); Hearings; Overview; Participation; Public Participation; Reports; Submit Response; ← Back to Dashboard; ✅ Compliance Report; 🏛️ Standalone; 👤 Client Report; 👤 Client Status Report; 📈 Processing Time Analysis; 📊 Portfolio Report; 📋 Project; 📤 Share with Client; 🔔 Notify Team.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### municipal embedded sample

Inputs by source name/id: .

Prototype button labels: + Record Submission; 12 Riverside Ave; 45 Kloof St; All Projects; Certificate; Circulation; Export PDF Bundle; Land Use; Outcomes; Overview; Re-Run; Run Validation; Standalone; Submission Pack; Sunset Views Ph2; View / Download Certificate.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### xa embedded sample

Inputs by source name/id: .

Prototype button labels: + Add Layer; + Add Opening; + Add Storey; + Add window/door; + Add zone; Air Con; Basics; Clear; Export Report; Fenestration; Floors; Hot Water; Import from Drawing; Lighting; Overview; Preset: Heavy; Request Sign-off (resolve FAILs); Results; Roof; Shading; Walls; ✕.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### forms embedded sample

Inputs by source name/id: `export`.

Prototype button labels: All Projects; All Sections; All Templates; Client; Company Res.; Construction; Contracts; Filter; Municipal; POA; Professional; Project; Recommended; Signatures; ⬇️ Export PDF; ⬇️ Generate PDF; 🏛️ View in Municipal Readiness; 👁️ Preview; 👁️ Preview First; 💾 Save Draft.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### specforge embedded sample

Inputs by source name/id: `aiApprover`, `aiBudget`, `aiClauses`, `aiClientDec`, `aiCode`, `aiCost`, `aiDims`, `aiDrawings`, `aiFinish`, `aiLead`, `aiModel`, `aiNotes`, `aiOwner`, `aiPkg`, `aiReviewer`, `aiRoom`, `aiSection`, `aiSupplier`, `aiSustain`, `aiTitle`, `aiWarranty`, `newProjMunicipality`, `newProjName`, `newProjProfile`, `newProjStage`, `packageFilter`, `projectSelector`, `roleSelector`, `roomFilter`, `secApprover`, `secCode`, `secDisc`, `secOwner`, `secReviewer`, `secTitle`.

Prototype button labels: &times;; + Add Section; AI Scan; Accept; Add all ('+totalSuggestions+'); Add all suggested items; Add to spec →; Add →; Approve; Cancel; Confirm & Sign; Confirm Issue; Create Item; Create Project; Create Section; Edit; Flag; Installed; Manual →; Open →; Order; Received; Reject; Send RFQ; Shipped; Snapshot; Substitute; Use this →; View; View Items; ← Back; ✏️; ✓; ✗; 💬; 📄; 📚; 🚀 Issue Now.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### bom embedded sample

Inputs by source name/id: .

Prototype button labels: + Add Bidder from Catalogue; + Add Item; + Start New Independent BoM; Approve; Batch Approve; Camps Bay Residence; Create Missing Spec Items; Download; Edit; Edit &amp; Approve; Export; Generate; Generate All Ready Packages; Issue to Bidders via Action Centre; Locked; Preview Tender Document; QS Certify &amp; Sign-Off (blockers remain); Reject; Request Clarification; Request Info; Review; Submit for QS Review.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### itp embedded sample

Inputs by source name/id: .

Prototype button labels: + Add Item; + Create ITP; All Projects; CBD Retail Complex; Cancel; Compliance Report; Conditional Pass; Confirm Sign-Off; Create Revision; Greenfield Phase 2; Hold Points; Inspection Items; Lab Results; Material Testing; NCR Linkage; Overview; Sign Off — Fail; Sign Off — Pass; Waterfall Towers; ⚡ Conditional; ✓ Pass; ✗ Fail.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### safety embedded sample

Inputs by source name/id: .

Prototype button labels: + Add Hazard; + Induction; + New Plan; + Report Incident; + Request New Permit; + Toolbox Talk; All Projects; Fall Protection; H&S Plans; HIRA Register; Incidents; Inductions; Kensington Mixed-Use; Melrose Arch Phase 3; Overview; Permits; Safety File; Sandton Office Park; ⚙️Settings; ⚠️ Add Hazard; ⛑️Health & Safety; ✅Compliance Hub; 🏗️ Fall Protection Plan; 🏗️Site Execution; 💳Finance; 📄Documents; 📊Command Centre; 📋 New Permit; 📝 Record Induction; 📢 Toolbox Talk; 📥Inbox; 🚨 Log Incident; 🚪Logout; 🤖 Ask AI.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

### feedback embedded sample

Inputs by source name/id: .

Prototype button labels: 1; 2; 3; 4; 5; Clusters; Friction Signals; My Feedback; Overview; Submit; Submit Feedback; Trend Chart; ›; → Advance to Planned; ↻ Regenerate Brief; ✕; ✕ Decline; 💬.

Boundary: Embedded sample controls are local prototype UI, not persistence, endpoint or production permission receipts.

