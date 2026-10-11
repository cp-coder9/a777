'use strict';
// Real production shell and original SOURCES; transport is test-only, not backend proof.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
const root=path.resolve(__dirname,'../../..'),publicRoot=path.join(root,'app/public');
const project='a'.repeat(32),organisation='b'.repeat(32);
let browser;
test.before(async()=>{browser=await chromium.launch({headless:true,channel:'msedge'});});
test.after(async()=>{await browser?.close();});
// Reuse the full-shell fixture's actual registry and routing contract without importing its tests.
function metadata(role){return JSON.parse(cp.execFileSync('php',['-r','$root=$argv[1];spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,"Architex\\\\"))require $root."/app/src/".str_replace("\\\\","/",substr($class,strlen("Architex\\\\"))).".php";});print(json_encode(Architex\\Platform\\Toolbox\\ProductionToolRegistry::workspaceCatalogue($argv[2],"architect")));',root,role],{encoding:'utf8'}));}
async function fixture(t,role='member',legacy=false,slowFrame=false){
 const page=await browser.newPage({viewport:{width:1280,height:900}});t.after(()=>page.close());page.setDefaultTimeout(15000);
 const errors=[],mutations=[];page.on('request',r=>{if(new URL(r.url()).pathname.startsWith('/api/v1/')&&r.method()!=='GET')mutations.push(r.method()+' '+r.url());});t.after(()=>assert.deepEqual(mutations,[],'no synthetic transport mutations'));page.on('pageerror',e=>errors.push(e.message));const tools=metadata(role);
 await page.addInitScript(()=>{window.__missingSpecTargets=[];const get=Document.prototype.getElementById;Document.prototype.getElementById=function(id){const el=get.call(this,id);if(!el&&id==='summaryName')window.top.__missingSpecTargets.push({id,stack:new Error().stack});return el;};});
 await page.route('http://role-fixture.test/**',async route=>{
  const p=new URL(route.request().url()).pathname;
  // Force the production 100ms body poll to run before parser-blocked tail scripts.
  if(slowFrame&&p.endsWith('/material3-embedded-workspaces.css'))await new Promise(resolve=>setTimeout(resolve,800));
  if(p.startsWith('/assets/')){const file=path.resolve(publicRoot,'.'+p);if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file))return route.fulfill({status:404,body:''});return route.fulfill({body:fs.readFileSync(file),contentType:p.endsWith('.js')?'application/javascript':p.endsWith('.css')?'text/css':'application/octet-stream'});}
  if(p.startsWith('/api/v1/')){assert.equal(route.request().method(),'GET','read-only regression must never mutate server records');let data=[];
   if(p==='/api/v1/session')data={userId:'d'.repeat(32),email:'synthetic-navigation@example.test',csrfToken:'test-only',memberships:[{organisationId:organisation,role,profileRole:'architect'}]};
   if(p==='/api/v1/projects')data=[{id:project,name:'Synthetic navigation fixture',organisationId:organisation,phase:'inception'}];
   if(p==='/api/v1/tools')data=tools;
   if(p.endsWith('/specforge-workspaces'))data=[{id:'c'.repeat(32),projectId:project,title:'Synthetic native-shaped specification',revisionCode:'P07',status:'draft',version:1,sections:[{id:'e'.repeat(32),code:'09',title:'Verified fixture finishes',discipline:'architectural',ownerRole:'architect',reviewerRole:'quantity_surveyor',approverRole:'architect'}],items:[{id:'f'.repeat(32),sectionId:'e'.repeat(32),code:'09.01',title:'Native-shaped fixture item',status:'review_required',budgetAllowanceCents:10000,estimatedCostCents:9000,ownerRole:'architect',reviewerRole:'quantity_surveyor',approverRole:'architect',drawingReferences:[],clauseReferences:[]}],riskSummary:{readyToIssue:false}}];
   if(p.endsWith('/work-permits'))data={permits:[],canDraft:false};
   if(p.includes('site-execution'))data={inspections:[],ncrs:[],evidence:[]};
   if(p==='/api/v1/wingman/tutorial')data={label:'Test-only tour',progress:{status:'completed'},steps:[]};
   if(p.includes('trust-compliance'))data={complianceItems:[]};
   return route.fulfill({contentType:'application/json',body:JSON.stringify({data,meta:{},error:null})});
  }
  // Only the entry document is a shell. Serving app.php for /preview3.html
  // recursively embeds another shell (and repeats its session/bootstrap scripts).
  // Resolve non-API resources as production static files instead of a shell fallback.
  if(p===(legacy?'/app.php':'/v1-candidate.php'))return route.fulfill({headers:{'Content-Security-Policy':"script-src 'self' 'unsafe-inline'; object-src 'none'"},contentType:'text/html',body:fs.readFileSync(path.join(root,legacy?'app/public/app.php':'app/views/v1-clone.php'),'utf8')});
  const file=path.resolve(publicRoot,'.'+p);
  if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile())return route.fulfill({status:404,body:''});
  const contentType=p.endsWith('.html')?'text/html':p.endsWith('.png')?'image/png':p.endsWith('.webmanifest')?'application/manifest+json':'application/octet-stream';
  return route.fulfill({contentType,body:fs.readFileSync(file)});
 });
 await page.goto('http://role-fixture.test/'+(legacy?'app.php':'v1-candidate.php?workspace=bom&orientation=project&project='+project));
 await page.waitForSelector(legacy?'html[data-access-state="workspace"][data-shell-state="ready"]':'body[data-v1-clone-state="ready"]');return {page,errors};
}
for(const slow of [false,true])test('SpecForge original prepared source mounts with delayed stylesheet='+slow,async t=>{
 const {page,errors}=await fixture(t,'owner',false,slow);
 await page.evaluate(()=>openTool('specforge','project'));
 await page.waitForFunction(()=>['ready','error'].includes(document.querySelector('#toolFrame')?.dataset.v1CloneAdapter));
 const result=await page.locator('#toolFrame').evaluate(f=>({adapter:f.dataset.v1CloneAdapter,targets:['summaryName','summaryStage','summaryMunicipality','summaryRevision'].map(id=>[id,!!f.contentDocument.getElementById(id)])}));
 const missing=await page.evaluate(()=>window.__missingSpecTargets);console.log(JSON.stringify({result,missing,errors}));
 assert.equal(result.adapter,'ready');assert.ok(result.targets.every(x=>x[1]));assert.deepEqual(missing,[]);assert.deepEqual(errors,[]);
 const frame=page.frames().find(f=>f.parentFrame());await frame.evaluate(()=>{renderAll();renderCurrentView('overview');});assert.deepEqual(await page.evaluate(()=>window.__missingSpecTargets),[]);
 assert.equal(await frame.locator('#summaryName').textContent(),'Synthetic navigation fixture');
 assert.equal(await frame.locator('#summaryRevision').textContent(),'P07');
 assert.equal(await frame.locator('#summaryStatus').textContent(),'draft');
 assert.equal(await frame.locator('#issueNowBtn').isDisabled(),true);
 assert.match(await frame.locator('#quickStats').textContent(),/Verified fixture finishes/);
 assert.equal(await frame.locator('[onclick="openAddSectionForm()"]').isEnabled(),true);
 const appearance=()=>{const s=getComputedStyle(document.body);return [s.fontFamily,s.color,s.backgroundColor];};
 await frame.waitForFunction(()=>!!document.querySelector('link[data-architex-m3-theme]')?.sheet);
 const before=await frame.evaluate(appearance);const theme=frame.locator('link[data-architex-m3-theme]');await theme.evaluate(el=>el.sheet.disabled=true);assert.deepEqual(await frame.evaluate(appearance),before);await theme.evaluate(el=>el.sheet.disabled=false);
 await page.screenshot({path:'E:/Hermes/home/cache/scratch/specforge-overview-'+slow+'.png'});
});
test('viewer SpecForge mounts and write controls remain disabled even when risk readiness is true',async t=>{
 const {page}=await fixture(t,'viewer');await page.evaluate(()=>openTool('specforge','project'));await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');
 const frame=page.frames().find(f=>f.parentFrame());
 await frame.evaluate(()=>__v1DocumentFrame.apply({role:'viewer',readOnly:true,workspace:{id:'c'.repeat(32),status:'draft',sections:[],items:[],riskSummary:{readyToIssue:true}}}));
 for(const selector of ['[onclick="openAddItemForm()"]','[onclick="openAddSectionForm()"]','#issueNowBtn'])assert.equal(await frame.locator(selector).evaluateAll(nodes=>nodes.length>0&&nodes.every(e=>e.disabled===true||e.getAttribute('aria-disabled')==='true')),true);
});

for(const phase of ['parser','shell'])test('dispose while '+phase+' readiness pending prevents late connected mount',async()=>{
 const vm=require('node:vm');let resolve,subscriptions=0,applies=0,loads=0;
 const ready=new Promise(r=>resolve=r),doc={readyState:phase==='parser'?'loading':'complete',addEventListener:(name,fn)=>ready.then(fn)};
 const win={__v1SpecShellReady:phase==='shell'?ready:Promise.resolve(),__v1DocumentFrame:{apply(){applies++;}}};
 const frame={isConnected:true,contentDocument:doc,contentWindow:win};
 const root={V1CloneDocumentToolsAdapter:{create:()=>({load:async()=>{loads++;return {workspaces:[]};}}),safe:x=>x}};
 vm.runInNewContext(fs.readFileSync(path.join(publicRoot,'assets/v1-clone/js/modules/document-tools.js'),'utf8'),{window:root});
 const tools=root.V1CloneDocumentTools.install({context:{getState:()=>({}),subscribe:()=>{subscriptions++;return ()=>{};}}});
 const pending=tools.mountFrame('specforge',frame);tools.dispose();resolve();assert.equal(await pending,false);
 assert.equal(subscriptions,0);assert.equal(applies,0);assert.equal(loads,0);assert.equal(win.__v1DocumentCommand,undefined);
});

test('viewer transition disables existing and newly opened native modal submits and direct decisions',async t=>{
 const {page}=await fixture(t,'owner');await page.evaluate(()=>openTool('specforge','project'));await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');const frame=page.frames().find(f=>f.parentFrame());
 await frame.evaluate(()=>renderCurrentView('sections'));await frame.locator('[onclick="openAddSectionForm()"]').evaluate(el=>el.click());assert.equal(await frame.locator('#modalContent button[type="submit"]').isEnabled(),true);
 await frame.evaluate(()=>{const ws={id:'c'.repeat(32),projectId:'a'.repeat(32),version:1,status:'draft',sections:[],items:[{id:'f'.repeat(32),sectionId:'e'.repeat(32),code:'09.01',title:'Native-shaped fixture item',status:'review_required',budgetAllowanceCents:10000,estimatedCostCents:9000,ownerRole:'architect',reviewerRole:'quantity_surveyor',approverRole:'architect'}],riskSummary:{readyToIssue:true}};__v1DocumentFrame.apply({role:'viewer',readOnly:true,workspace:ws});});
 assert.equal(await frame.locator('#modalContent button[type="submit"]').isDisabled(),true);
 await frame.evaluate(()=>{closeModal();openAddSectionForm();});
 assert.equal(await frame.locator('#modalContent button[type="submit"]').isDisabled(),true);
 await frame.evaluate(()=>{submitAddSection({preventDefault(){}});approveItem('f'.repeat(32));rejectItem('f'.repeat(32));issueNow();});
 assert.match(await frame.locator('html').getAttribute('data-document-status'),/Read-only/);
});

test('native-shaped viewer decisions and disabled nonbutton clicks cannot mutate',async t=>{
 const {page}=await fixture(t,'owner');await page.evaluate(()=>openTool('specforge','project'));await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');const frame=page.frames().find(f=>f.parentFrame());
 await frame.evaluate(()=>{currentRole='architect';openItemDetail('f'.repeat(32));});
 const decisions=frame.locator('[onclick^="approveItem("],[onclick^="rejectItem("]');assert.equal(await decisions.count(),2);
 await frame.evaluate(()=>__v1DocumentFrame.apply({role:'viewer',readOnly:true,workspace:{id:'c'.repeat(32),status:'draft',items:[],sections:[]}}));
 assert.equal(await decisions.evaluateAll(nodes=>nodes.every(e=>e.disabled&&e.getAttribute('aria-disabled')==='true')),true);
 await decisions.evaluateAll(nodes=>nodes.forEach(e=>e.click()));
 const blocked=await frame.evaluate(()=>{const el=document.querySelector('[onclick="openAddItemForm()"]');const before=document.querySelector('#modalContent').innerHTML;const event=new MouseEvent('click',{bubbles:true,cancelable:true});el.dispatchEvent(event);return {cancelled:event.defaultPrevented,unchanged:before===document.querySelector('#modalContent').innerHTML};});assert.deepEqual(blocked,{cancelled:true,unchanged:true});
 await frame.evaluate(()=>{closeModal();openAddItemForm();});assert.equal(await frame.locator('#modalContent button[type="submit"]').isDisabled(),true);
 await frame.evaluate(()=>{submitAddItem({preventDefault(){}});approveItem('f'.repeat(32));rejectItem('f'.repeat(32));});assert.match(await frame.locator('html').getAttribute('data-document-status'),/Read-only/);
 await frame.evaluate(()=>{__v1DocumentFrame.apply({error:'Unavailable: service not configured',role:'owner'});openAddSectionForm();});assert.equal(await frame.locator('#modalContent button[type="submit"]').isDisabled(),true);
 assert.match(await frame.locator('html').getAttribute('data-document-status'),/Read-only/);
});

test('strict no-eval production frame disposal while shell pending leaves controls and native adapter untouched',async t=>{
 const {page}=await fixture(t,'owner');await page.evaluate(()=>openTool('specforge','project'));await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');
 const result=await page.evaluate(async()=>{
  const frame=document.querySelector('#toolFrame'),win=frame.contentWindow;let release,reads=0,subscribes=0,mutations=0;
  const state={session:{userId:'d'.repeat(32)},organisationId:'b'.repeat(32),selectedProjectId:'a'.repeat(32),role:'owner',readOnly:false};
  const tools=V1CloneDocumentTools.install({context:{getState:()=>state,read:async()=>{reads++;return [];},write:async()=>{mutations++;throw Error('Unexpected synthetic write');},subscribe:()=>{subscribes++;return ()=>{};}}});
  const before=win.document.body.innerHTML,command=win.__v1DocumentCommand,ready=win.__v1SpecShellReady;win.__v1SpecShellReady=new Promise(r=>release=r);
  const pending=tools.mountFrame('specforge',frame);tools.dispose();release();const mounted=await pending;win.__v1SpecShellReady=ready;
  let evalDenied=false;try{win.eval('1');}catch(e){evalDenied=e.name==='EvalError';}
  return {mounted,reads,subscribes,mutations,unchanged:before===win.document.body.innerHTML,commandRetained:command===win.__v1DocumentCommand,connected:frame.isConnected,evalDenied};
 });assert.deepEqual(result,{mounted:false,reads:0,subscribes:0,mutations:0,unchanged:true,commandRetained:true,connected:true,evalDenied:true});
 const boundary=await page.evaluate(async()=>{
  const frame=document.querySelector('#toolFrame'),win=frame.contentWindow;let writes=0,prompts=0;
  const state={session:{userId:'d'.repeat(32)},organisationId:'b'.repeat(32),selectedProjectId:'a'.repeat(32),role:'owner',readOnly:false};
  const workspace={id:'c'.repeat(32),projectId:state.selectedProjectId,status:'draft',version:1,sections:[],items:[{id:'f'.repeat(32),title:'Boundary fixture',status:'approved',budgetAllowanceCents:100,estimatedCostCents:100}]};
  const tools=V1CloneDocumentTools.install({context:{getState:()=>state,read:async()=>[workspace],write:async()=>{writes++;throw Error('Unexpected synthetic transport write');},subscribe:()=>()=>{}}});
  await tools.mountFrame('specforge',frame);state.role='viewer';state.readOnly=true;win.prompt=()=>{prompts++;return 'Forbidden prompt';};
  const errors=[];for(const action of ['section','item','decision','issue'])try{await win.__v1DocumentCommand(action,{id:'f'.repeat(32),action:'approve'});}catch(e){errors.push(e.message);}
  tools.dispose();return {writes,prompts,errors};
 });assert.equal(boundary.writes,0);assert.equal(boundary.prompts,0);assert.equal(boundary.errors.length,4);assert.ok(boundary.errors.every(e=>/Read-only/.test(e)));
});
