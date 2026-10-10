'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),cp=require('node:child_process');
const root=path.resolve(__dirname,'../../..');
function load(){const window={};vm.runInNewContext(fs.readFileSync(path.join(root,'app/public/assets/v1-clone/js/modules/shell-data.js'),'utf8'),{window});return window.V1CloneShellData;}
test('all 21 registered profiles keep guidance separate from four membership permissions',()=>{
 const api=load();assert.equal(typeof api.dashboardModel,'function');
 const source=fs.readFileSync(path.join(root,'app/src/Platform/Identity/RegisterOrganisation.php'),'utf8');
 const roles=[...source.match(/\$roles = \[([^\]]+)\]/)[1].matchAll(/'([^']+)'/g)].map(m=>m[1]);assert.equal(roles.length,21);
 assert.deepEqual(Object.keys(api.priorities).sort(),roles.sort());
 const catalogues=JSON.parse(cp.execFileSync('php',['-r',String.raw`$root=$argv[1];spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'Architex\\'))require $root.'/app/src/'.str_replace('\\','/',substr($class,strlen('Architex\\'))).'.php';});$out=[];foreach(json_decode($argv[2],true) as $profile)foreach(['owner','admin','member','viewer'] as $role)$out[$profile][$role]=Architex\Platform\Toolbox\ProductionToolRegistry::workspaceCatalogue($role,$profile);echo json_encode($out);`,root,JSON.stringify(roles)],{encoding:'utf8',maxBuffer:16*1024*1024}));
 for(const profileRole of roles)for(const role of ['owner','admin','member','viewer']){
 const current={ready:true,organisationId:'o',session:{memberships:[{organisationId:'o',role,profileRole}]}};
 const discovery={getCatalogue:()=>[{id:'project_passport',name:'Passport',recommended:true,stage:'Brief'},{id:'admin_review',recommended:true}],allowed:id=>id==='project_passport'};
 const model=api.dashboardModel(current,discovery);assert.equal(model.tools.length,1);assert.equal(model.tools[0].id,'project_passport');assert.ok(model.priority);
 if(role==='viewer'||(role==='member'&&/admin/.test(profileRole)))assert.doesNotMatch(model.label,/Administrator|Admin/);
 const catalogue=catalogues[profileRole][role];
 const real=api.dashboardModel(current,{getCatalogue:()=>catalogue,allowed:id=>catalogue.find(t=>t.id===id)?.access?.read===true});
 assert.deepEqual(Array.from(real.tools,t=>t.id),catalogue.filter(t=>t.recommended===true&&t.access.read===true).map(t=>t.id));
 assert.ok(real.tools.every(t=>t.access.read===true));
 }
 const unknown=api.dashboardModel({ready:true,organisationId:'o',session:{memberships:[{organisationId:'o',role:'root',profileRole:'platform_admin'}]}},{getCatalogue:()=>[{id:'admin_review',recommended:true}],allowed:()=>true});assert.equal(unknown.tools.length,0);
});
test('unknown memberships and inherited profile names fail closed',()=>{
 const api=load(),discovery={getCatalogue:()=>[{id:'project_passport',recommended:true}],allowed:()=>true};
 for(const role of ['root',undefined]){
  const model=api.dashboardModel({ready:true,organisationId:'o',session:{memberships:[{organisationId:'o',role,profileRole:'platform_admin'}]}},discovery);
  assert.equal(model.label,'Membership unavailable');assert.equal(model.tools.length,0);
 }
 for(const profileRole of ['unknown','constructor','__proto__']){
  assert.equal(api.dashboardModel({ready:true,organisationId:'o',session:{memberships:[{organisationId:'o',role:'owner',profileRole}]}},discovery).label,'Project workspace');
 }
});
test('dashboard stylesheet follows the expressive base and three family styles',()=>{
 const view=fs.readFileSync(path.join(root,'app/views/v1-clone.php'),'utf8');
 const styles=[...view.matchAll(/<link[^>]+href="([^"]+\.css)"/g)].map(m=>m[1]);
 assert.deepEqual(styles.slice(-5),['material3-expressive','material3-project-workspaces','material3-technical-workspaces','material3-commercial-workspaces','material3-role-dashboards'].map(n=>`/assets/v1-clone/css/${n}.css`));
});
test('pending project selection cannot report into changed membership scope',async()=>{
 let current={ready:true,organisationId:'o',selectedProjectId:'p',mode:'project',session:{userId:'u',memberships:[{organisationId:'o',role:'owner'}]},projects:[{id:'p',name:'Project'}]},rejectSelection,toasts=0;
 const select={options:[],isConnected:true,replaceChildren(...options){this.options=options;}};
 const document={querySelector:()=>null,querySelectorAll:s=>s==='.project-select'?[select]:[],createElement:()=>({})};
 const window={showToast:()=>toasts++};
 vm.runInNewContext(fs.readFileSync(path.join(root,'app/public/assets/v1-clone/js/modules/shell-data.js'),'utf8'),{window,document,state:{tool:'native'}});
 const shell=window.V1CloneShellData.install({context:{getState:()=>current,subscribe:()=>()=>{},selectProject:()=>new Promise((_,reject)=>{rejectSelection=reject;})}});
 const pending=select.onchange();current={...current,session:{...current.session,memberships:[{organisationId:'o',role:'viewer'}]}};
 rejectSelection(Error('stale selection'));await pending;shell.dispose();assert.equal(toasts,0);
});
test('desktop and mobile command use real shell with explicitly synthetic transport fixtures',async t=>{
 const {fixture}=require('./material3-expressive-brand.test.cjs');
 for(const viewport of [{width:1600,height:1000},{width:390,height:844}]){
 const {page,errors}=await fixture(t,{viewport});
 await page.evaluate(()=>openGlobal('command'));
 await page.waitForSelector('[data-role-dashboard]');
 assert.equal(await page.locator('[data-role-dashboard]').count(),1);
 assert.match(await page.locator('[data-role-dashboard]').innerText(),/Attention records are not loaded/);
 assert.match(await page.locator('[data-role-dashboard]').innerText(),/Unavailable/);
 await page.evaluate(()=>renderAll());assert.equal(await page.locator('[data-role-dashboard]').count(),1);
 const guidance=await page.locator('[data-role-dashboard]').innerText();
 await page.locator('#productionRoleLens').selectOption('engineer');
 assert.equal(await page.locator('[data-role-dashboard]').innerText(),guidance,'presentation lens cannot change saved-profile authority or tools');
 const output='E:/Hermes/home/cache/scratch/material3expressive-role-dashboards';fs.mkdirSync(output,{recursive:true});
 await page.screenshot({path:path.join(output,`command-${viewport.width}.png`),fullPage:true});
 assert.equal(await page.locator('[data-role-dashboard]').evaluate(e=>getComputedStyle(e).display),'grid');
 assert.equal(await page.locator('[data-role-dashboard]').evaluate(e=>e.scrollWidth<=e.clientWidth),true);
 assert.deepEqual(errors,[]);
 const safety=await page.evaluate(()=>{
  let calls=0,unsubscribed=0;
  let current={ready:true,mode:'project',organisationId:'fixture-org',selectedProjectId:'fixture-project',session:{userId:'fixture-user',memberships:[{organisationId:'fixture-org',role:'viewer',profileRole:'platform_admin'}]},projects:[{id:'fixture-project',organisationId:'fixture-org',name:'<img src=x onerror="window.fixtureXss=true">',phase:'<script>fixtureXss=true</script>'}]};
  const original=window.openTool;window.openTool=()=>{calls++;};
  const discovery=V1CloneRuntime.controllers.discovery;const oldCatalogue=discovery.getCatalogue,oldAllowed=discovery.allowed;
  discovery.getCatalogue=()=>[{id:'project_passport',name:'<svg onload=fixtureXss=true>',stage:'Brief',recommended:true}];discovery.allowed=()=>true;
  const shell=V1CloneShellData.install({context:{getState:()=>current,subscribe:()=>()=>{unsubscribed++;}}});
  const main=document.querySelector('#main');const oldButton=main.querySelector('button');
  const escaped=main.textContent.includes('<img')&&!main.querySelector('img,svg,script');
  current={...current,organisationId:'other-org'};oldButton.click();
  shell.reconcile();const empty=main.textContent.includes('No authorised project selected');
  shell.dispose();shell.dispose();oldButton.click();
  discovery.getCatalogue=oldCatalogue;discovery.allowed=oldAllowed;window.openTool=original;
  return {calls,unsubscribed,escaped,empty,xss:!!window.fixtureXss};
 });assert.deepEqual(safety,{calls:0,unsubscribed:1,escaped:true,empty:true,xss:false});
 }
});
test('membership changes with identical dashboard guidance replace stale buttons with working buttons',async t=>{
 const {fixture}=require('./material3-expressive-brand.test.cjs');const {page,errors}=await fixture(t);
 await page.evaluate(()=>openGlobal('command'));await page.waitForSelector('[data-role-dashboard]');
 const result=await page.evaluate(()=>{
  V1CloneRuntime.controllers.shell.dispose();
  let current={ready:true,mode:'project',organisationId:'o',selectedProjectId:'p',session:{userId:'u',memberships:[{organisationId:'o',role:'member',profileRole:'admin'}]},projects:[{id:'p',name:'Authorised',organisationId:'o'}]},opened=0;
  const discovery=V1CloneRuntime.controllers.discovery;
  discovery.getCatalogue=()=>[{id:'project_passport',name:'Passport',stage:'Brief',recommended:true}];discovery.allowed=id=>id==='project_passport';
  window.openTool=()=>{opened++;};
  const shell=V1CloneShellData.install({context:{getState:()=>current,subscribe:()=>()=>{}}});
  const main=document.querySelector('#main'),oldDashboard=main.querySelector('[data-role-dashboard]'),oldButton=main.querySelector('button');
  const before=JSON.stringify(V1CloneShellData.dashboardModel(current,discovery));oldButton.click();
  current={...current,session:{...current.session,memberships:[{organisationId:'o',role:'member',profileRole:'platform_admin'}]}};
  const identicalModel=before===JSON.stringify(V1CloneShellData.dashboardModel(current,discovery));
  oldButton.click();const staleBefore=opened===1;
  shell.reconcile();const newDashboard=main.querySelector('[data-role-dashboard]'),newButton=main.querySelector('button');
  oldButton.click();const staleAfter=opened===1;
  newButton.click();const freshWorks=opened===2;
  shell.reconcile();const stable=main.querySelector('button')===newButton;
  shell.dispose();newButton.click();
  return {identicalModel,staleBefore,staleAfter,replaced:newDashboard!==oldDashboard&&newButton!==oldButton,freshWorks,stable,opened};
 });
 assert.deepEqual(result,{identicalModel:true,staleBefore:true,staleAfter:true,replaced:true,freshWorks:true,stable:true,opened:2});assert.deepEqual(errors,[]);
});
test('dashboard lifecycle preserves native bodies, focus and single selection handlers; stale work is inert',async t=>{
 const {fixture}=require('./material3-expressive-brand.test.cjs');const {page,errors}=await fixture(t);
 await page.evaluate(()=>openGlobal('command'));await page.waitForSelector('[data-role-dashboard]');
 const result=await page.evaluate(async()=>{
  V1CloneRuntime.controllers.shell.dispose();
  let current={ready:true,mode:'project',organisationId:'o',selectedProjectId:'p',session:{userId:'u',memberships:[{organisationId:'o',role:'viewer',profileRole:'architect'}]},projects:[{id:'p',name:'Authorised',organisationId:'o'},{id:'q',name:'Second',organisationId:'o'},{id:'foreign',name:'Foreign',organisationId:'elsewhere'}]};
  let selected=0,opened=0,writes=0,unsubscribed=0,toasts=0,rejectSelection;
  window.showToast=()=>toasts++;
  const discovery=V1CloneRuntime.controllers.discovery;
  discovery.getCatalogue=()=>[{id:'project_passport',name:'Passport',stage:'Brief',recommended:true},{id:'admin_review',name:'Denied',recommended:true},{id:'team_workspace',name:'Not recommended',recommended:false}];
  let allowed=true;discovery.allowed=id=>allowed&&id==='project_passport';
  window.openTool=()=>{opened++;};
  const shell=V1CloneShellData.install({context:{getState:()=>current,subscribe:()=>()=>unsubscribed++,write:()=>writes++,selectProject:()=>{selected++;return new Promise((_,reject)=>{rejectSelection=reject;});}}});
  const main=document.querySelector('#main'),button=main.querySelector('button');button.focus();
  shell.reconcile();shell.reconcile();const focus=document.activeElement===button;
  const filtered=!main.textContent.includes('Denied')&&!main.textContent.includes('Not recommended')&&!main.textContent.includes('Foreign');
  button.click();const once=opened===1;
  allowed=false;button.click();const revoked=opened===1;allowed=true;
  current={...current,ready:false};button.click();const unready=opened===1;current={...current,ready:true};
  const select=document.querySelector('.project-select'),handler=select.onchange;
  shell.reconcile();const single=select.onchange===handler;select.value='q';const pending=handler();
  current={...current,session:{...current.session,memberships:[{organisationId:'o',role:'member',profileRole:'engineer'}]}};shell.reconcile();const dashboard=main.querySelector('[data-role-dashboard]'),scopeText=dashboard.textContent;
  rejectSelection(Error('old scope'));await pending;const stale=main.querySelector('[data-role-dashboard]')===dashboard&&dashboard.textContent===scopeText;
  state.global='projects';state.tool=null;main.replaceChildren(document.createElement('article'));const projectBody=main.firstChild;shell.reconcile();const nativeProject=main.firstChild===projectBody;
  state.global='command';state.tool='project_passport';main.replaceChildren(document.createElement('article'));const toolBody=main.firstChild;shell.reconcile();const nativeTool=main.firstChild===toolBody;
  const before=main.innerHTML;shell.dispose();shell.dispose();shell.reconcile();button.click();const disposed=main.innerHTML===before&&opened===1;
  return {focus,filtered,once,revoked,unready,single,selected,stale,nativeProject,nativeTool,disposed,unsubscribed,writes,toasts};
 });
 assert.deepEqual(result,{focus:true,filtered:true,once:true,revoked:true,unready:true,single:true,selected:1,stale:true,nativeProject:true,nativeTool:true,disposed:true,unsubscribed:1,writes:0,toasts:0});assert.deepEqual(errors,[]);
});
