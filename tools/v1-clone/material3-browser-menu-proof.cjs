'use strict';
// Isolated native HTTP acceptance. Never persist storageState, headers, or response bodies.
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto');
const {execFileSync} = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const WORK = 'E:/Hermes/home/cache/scratch/material3-runtime';
const ORIGIN = 'http://127.0.0.1:59947';
const args = new Set(process.argv.slice(2));
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
const read = name => fs.readFileSync(path.join(ROOT, name), 'utf8');
const json = name => JSON.parse(fs.readFileSync(name, 'utf8'));
const assert = require('node:assert/strict');
const vm = require('node:vm');
const privateSecrets=[];
function sanitize(value) {
  if(typeof value==='string') {
    let s=value;for(const secret of privateSecrets)if(secret)s=s.split(secret).join('[REDACTED]');
    return s.replace(/\b(token|password|cookie|set-cookie|csrf|authorization)\b(["'\s:=]+)[^\r\n]*/gi,'$1$2[REDACTED]');
  }
  if(Array.isArray(value))return value.map(sanitize);
  if(value&&typeof value==='object')return Object.fromEntries(Object.entries(value).map(([k,v])=>[k,/^(token|password|cookie|set-cookie|csrf|authorization|csrfToken|accessToken|refreshToken)$/i.test(k)?'[REDACTED]':sanitize(v)]));
  return value;
}
const safeJSON=(value,space)=>JSON.stringify(sanitize(value),null,space);
function redactionRegression(){
  privateSecrets.push('privateSecret');
  const original={key:'owner/itp/iframe',status:'FAIL',elapsedMs:20000,fatal:'apiRequestContext.get: Timeout 20000ms\n\u001b[31m cookie: privateSecret "quotes" \\path [brackets]\n authorization: Bearer privateSecret',nested:[{token:'unknown-secret',message:'token: privateSecret\nend',cookie:'another-secret'}]};
  const encoded=safeJSON(original);const parsed=JSON.parse(encoded);
  assert.equal(parsed.key,original.key);assert.equal(parsed.elapsedMs,20000);assert.equal(parsed.nested[0].token,'[REDACTED]');
  assert.ok(!encoded.includes('privateSecret')&&!encoded.includes('unknown-secret')&&!encoded.includes('another-secret'));
  assert.deepEqual(JSON.parse(safeJSON(parsed)),parsed);
  console.log(safeJSON({regression:'redaction-json-string-leaves-roundtrip-console',status:'PASS',sample:parsed}));
}
function inventory() {
  const source = read('app/public/assets/v1-clone/js/reference-001.js');
  const literal = source.match(/const TOOLS\s*=\s*(\{[^\n]+\});/);
  assert.ok(literal, 'Source tool registry not found');
  const tools = vm.runInNewContext('('+literal[1]+')', {}, {timeout:2000});
  for(const n of [2,3,4]) for(const m of read(`app/public/assets/v1-clone/js/reference-00${n}.js`).matchAll(/TOOLS\.([a-z_]+)\s*=\s*(\{[\s\S]+?\});/g)) {
    tools[m[1]]={id:m[1],declaredIn:`reference-00${n}.js`};
  }
  const profiles=[...read('app/src/Platform/Identity/RegisterOrganisation.php').match(/\$roles = \[([^\]]+)\]/)[1].matchAll(/'([^']+)'/g)].map(m=>m[1]);
  const lenses=JSON.parse(execFileSync('php',[WORK+'/http-bootstrap.php','--dry-run'],{encoding:'utf8',timeout:30000})).lenses;
  const native=json(path.join(ROOT,'app/public/assets/tool-registry.json')).tools;
  assert.equal(new Set(native.map(t=>t.id)).size,49);assert.equal(profiles.length,21);assert.equal(Object.keys(lenses).length,22);
  return {tools:Object.fromEntries(native.map(t=>[t.id,t])),shellTools:tools,profiles,lenses};
}
function revision() {
  const files=[];
  function walk(dir) { for(const e of fs.readdirSync(path.join(ROOT,dir),{withFileTypes:true})) {
    const p=dir+'/'+e.name;if(e.isDirectory())walk(p);else if(/\.(js|css|php)$/.test(e.name))files.push({path:p,sha256:hash(fs.readFileSync(path.join(ROOT,p)))});
  }}
  for(const dir of ['app/public/assets/v1-clone','app/src'])walk(dir);
  for(const p of ['app/views/v1-clone.php','app/public/assets/tool-registry.json','app/public/assets/route-placement.json','app/public/assets/js/api-client.js'])files.push({path:p,sha256:hash(read(p))});
  files.sort((a,b)=>a.path.localeCompare(b.path));
  let head=null,gitFailure=null;try{head=execFileSync('git',['rev-parse','HEAD'],{cwd:ROOT,encoding:'utf8',timeout:20000,stdio:['ignore','pipe','pipe']}).trim();}catch(e){gitFailure=sanitize(e.message);}
  return {head,gitFailure,sha256:hash(JSON.stringify(files)),files};
}
async function setPageAccount(page,account,label){
  const ctx=page.context();await ctx.clearCookies();
  await ctx.addCookies([{name:'architex_session',value:account.token,url:ORIGIN,httpOnly:true,sameSite:'Lax'}]);
  page.__material3Account={...account,membership:account.membership||label};
}
async function accountPreflight(context,account,org) {
  const response=await context.request.get(ORIGIN+'/api/v1/session',{headers:{'X-Organisation-Id':org},timeout:20000});
  if(response.status()!==200)throw new Error('FAIL bootstrap-auth-status: HTTP '+response.status()+' before shell boot');
  const body=await response.json(),membership=body.data?.memberships?.find(m=>m.organisationId===org);
  assert.equal(body.data?.userId,account.userId,'FAIL bootstrap-auth-identity');
  assert.ok(membership,'FAIL bootstrap-auth-organisation');
  if(account.membership)assert.equal(membership.role,account.membership,'FAIL bootstrap-auth-membership');
  if(account.profile)assert.equal(membership.profileRole,account.profile,'FAIL bootstrap-auth-profile');
  return {status:200,identityVerified:true,membershipVerified:true,synthetic:true,normalProductionLogin:false};
}
function selectAcceptance(fullKeys,ids,argv) {
  const value=flag=>{const i=argv.indexOf(flag);if(i<0)return null;assert.ok(argv[i+1]&&!argv[i+1].startsWith('--'),'Missing '+flag);return argv[i+1];};
  const batch=value('--batch')||(argv.includes('--profiles-only')?'profiles':'full');
  assert.ok(['full','profiles','core'].includes(batch),'Invalid acceptance batch');
  const raw=value('--tools'),tools=raw?raw.split(','):ids;
  assert.equal(new Set(tools).size,tools.length,'Duplicate tool selection');
  assert.ok(tools.every(id=>ids.includes(id)),'Unknown tool selection');
  assert.ok(!(batch==='profiles'&&raw),'Profiles batch cannot select tools');
  const expectedKeys=fullKeys.filter(key=>{
    if(batch==='profiles')return key.startsWith('profile/');
    if(batch==='core'&&key.startsWith('profile/'))return false;
    const parts=key.split('/');const toolKey=['owner','viewer'].includes(parts[0])&&ids.includes(parts[1]);
    return toolKey?tools.includes(parts[1]):!raw||batch==='core';
  });
  return {batch,tools:raw?tools:null,expectedKeys,parentFullExpected:fullKeys.length,parentFullExpectedKeys:fullKeys,manifestVersion:1};
}
function buildExpectedKeys(inv) {
  const expected=new Set(),roles=['owner','admin','member','viewer'],ids=Object.keys(inv.tools);
  function expect(key){assert.ok(!expected.has(key),'Duplicate expected key: '+key);expected.add(key);}
  for(const role of roles){expect('membership/'+role);expect('dashboard/'+role);}
  for(const role of roles)for(const profile of inv.profiles)expect('profile/'+role+'/'+profile);
  for(const id of ids){for(const step of ['menu','section-tabs','back','project-switch','reload','iframe'])expect('owner/'+id+'/'+step);expect('viewer/'+id+'/direct');expect('viewer/'+id+'/iframe');}
  for(const lens of Object.keys(inv.lenses))expect('lens/'+lens+'/native-project-allowlist');
  expect('boundaries/professional-authority');expect('boundaries/source-version');
  const globalIds=[...read('app/public/assets/v1-clone/js/reference-001.js').match(/const globalItems=\[([\s\S]+?)\];/)[1].matchAll(/id:"([^"]+)"/g)].map(m=>m[1]);
  for(const id of globalIds)expect("global/openGlobal('"+id+"')");
  return [...expected];
}
function sourceExpectedInventory() {
  // Source-only inventory: no fixture, bootstrap, credentials, DB or HTTP.
  const native=json(path.join(ROOT,'app/public/assets/tool-registry.json')).tools;
  const profiles=[...read('app/src/Platform/Identity/RegisterOrganisation.php').match(/\$roles = \[([^\]]+)\]/)[1].matchAll(/'([^']+)'/g)].map(m=>m[1]);
  const lensSource=read('app/src/Platform/Demo/DemoRoleLensRegistry.php');
  const lensKeys=[...lensSource.match(/private const ROLES = \[([\s\S]+?)\n    \];/)[1].matchAll(/'([^']+)'\s*=>\s*\['key'/g)].map(m=>m[1]);
  assert.equal(new Set(native.map(t=>t.id)).size,49);assert.equal(profiles.length,21);assert.equal(lensKeys.length,22);
  const inv={tools:Object.fromEntries(native.map(t=>[t.id,t])),profiles,lenses:Object.fromEntries(lensKeys.map(key=>[key,{}]))};
  return {ids:native.map(t=>t.id),fullKeys:buildExpectedKeys(inv)};
}
module.exports={selectAcceptance,buildExpectedKeys,sourceExpectedInventory};
async function exhaustive({browser,fixture,secrets,sanitize,out}) {
  const inv=inventory(),start=revision(),expected=new Set(),results=new Map(),contexts=[],served=new Map();
  const roles=['owner','admin','member','viewer'],ids=Object.keys(inv.tools);
  const privateFixtures=json(WORK+'/browser-fixtures.private.json');
  for(const account of privateFixtures.accounts)secrets.push(account.token,account.csrf);
  const manifest={origin:ORIGIN,harnessPid:process.pid,harnessSha256:hash(fs.readFileSync(__filename)),synthetic:true,normalProductionLogin:false,apiMocks:false,startedAt:new Date().toISOString(),revision:start,inventory:inv,allPass:false,finalCandidateRerunRequired:true};
  const save=(file,data)=>fs.writeFileSync(out+'/'+file,safeJSON(data,2));
  function expect(key){assert.ok(!expected.has(key),'Duplicate expected key: '+key);expected.add(key);}
  for(const key of buildExpectedKeys(inv))expect(key);
  const selection=selectAcceptance([...expected],ids,process.argv.slice(2));
  Object.assign(manifest,selection,{fixedIdentity:{origin:ORIGIN,schema:'architex_taskm37aa281_0bf7a858_test',organisationId:fixture.organisationId},candidateHash:start.sha256});
  expected.clear();for(const key of selection.expectedKeys)expect(key);
  const selectedIds=selection.tools||ids;
  const smoke=args.has('--iframe-smoke');
  if(smoke){manifest.scope='Bounded owner/viewer iframe smoke: itp, safety; UI opening/cancellation only, not exhaustive acceptance';expected.clear();for(const role of ['owner','viewer'])for(const id of ['itp','safety'])expect(role+'/'+id+'/iframe');}
  manifest.expectedKeys=[...expected];
  save('manifest.json',manifest);save('expected.json',[...expected]);
  const deadline=Date.now()+120*60*1000;
  function append(row){
    assert.ok(expected.has(row.key),'Unexpected result key');assert.ok(!results.has(row.key),'Duplicate result');
    assert.ok(['PASS','FAIL','UNSUPPORTED'].includes(row.status));
    results.set(row.key,row);const fd=fs.openSync(out+'/progress.jsonl','a');try{fs.writeSync(fd,safeJSON(row)+'\n');fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
    save('progress.json',{expected:expected.size,completed:results.size,counts:counts(),lastKey:row.key,updatedAt:new Date().toISOString()});
    if(results.size%10===0)console.log(JSON.stringify({batchCompleted:results.size,expected:expected.size,counts:counts()}));
  }
  function counts(){return [...results.values()].reduce((a,r)=>(a[r.status]++,a),{PASS:0,FAIL:0,UNSUPPORTED:0});}
  async function snapshot(page){return page.evaluate(()=>{
    const c=window.V1CloneRuntime?.context?.getState(),v=typeof state==='object'?state:{};
    const main=document.querySelector('#main');
    return {url:location.pathname+location.search,title:document.title,ready:document.body.dataset.v1CloneState,tool:v.tool,global:v.global,role:v.role,mode:v.mode,toolTab:v.toolTab,
      selectedProjectId:c?.selectedProjectId,membership:c?.session?.memberships?.find(m=>m.organisationId===c.organisationId)?.role,
      mainText:main?.innerText.slice(0,1700),mainDigestInput:main?.innerHTML,
      controllerCalls:window.__material3Calls||[],frames:[...document.querySelectorAll('#main iframe')].map(f=>({id:f.id,adapter:f.dataset.v1CloneAdapter,src:f.getAttribute('src')?.split('?')[0],srcdoc:!!f.srcdoc})),
      markers:[...(main?.querySelectorAll('[data-v1-project-required-tool],[data-role-dashboard],[class*="workspace"]')||[])].slice(0,12).map(n=>({tag:n.tagName,class:n.className,data:{...n.dataset}}))};
  });}
  async function settle(page){await page.waitForLoadState('networkidle',{timeout:20000}).catch(()=>{});await page.waitForTimeout(150);}
  async function instrument(page){await page.evaluate(()=>{
    window.__material3Calls=[];
    for(const [name,c]of Object.entries(window.V1CloneRuntime?.controllers||{}))for(const method of ['apply','mountFrame','prepareSource']){
      if(typeof c?.[method]!=='function'||c[method].__material3Wrapped)continue;
      const original=c[method];const wrapped=function(...args){const entry={name,method,tool:typeof args[0]==='string'?args[0]:null,supports:typeof c.supports==='function'?c.supports(args[0]):null};window.__material3Calls.push(entry);const result=original.apply(this,args);const done=v=>{entry.completed=true;entry.returnedFalse=v===false||v?.applied===false;entry.applied=v?.applied;};if(result?.then)result.then(done,()=>{entry.completed=false;});else done(result);return result;};wrapped.__material3Wrapped=true;c[method]=wrapped;
    }
    document.title='Material3Acceptance — Native HTTP acceptance';
  });}
  async function boot(page,url=ORIGIN+'/v1'){
    assert.ok(page.__material3Account,'Bootstrap account preflight missing');
    await accountPreflight(page.context(),page.__material3Account,fixture.organisationId);
    await page.goto(url,{waitUntil:'domcontentloaded',timeout:180000});
    await page.waitForFunction(()=>['ready','error'].includes(document.body.dataset.v1CloneState),null,{timeout:90000});
    assert.equal(await page.getAttribute('body','data-v1-clone-state'),'ready','Candidate bootstrap failed');
    await settle(page);await instrument(page);
  }
  async function openAccount(account,label){
    assert.ok(Date.now()<deadline,'Bounded run deadline exceeded');
    const ctx=await browser.newContext({viewport:{width:1440,height:1000},acceptDownloads:false});contexts.push(ctx);
    const page=await ctx.newPage();page.setDefaultTimeout(5000);page.setDefaultNavigationTimeout(180000);
    const events=[],pending=new Set();
    page.on('console',m=>{if(['error','warning'].includes(m.type()))events.push({type:m.type(),message:sanitize(m.text()).slice(0,700)});});
    page.on('pageerror',e=>events.push({type:'pageerror',message:sanitize(e.message).slice(0,700)}));
    page.on('requestfailed',r=>{const u=new URL(r.url());events.push({type:'requestfailed',path:u.pathname,error:r.failure()?.errorText});});
    page.on('response',r=>{const u=new URL(r.url());if(u.origin!==ORIGIN)return;
      if(u.pathname.startsWith('/api/'))events.push({type:'api',path:u.pathname,status:r.status(),method:r.request().method()});
      if(u.pathname.startsWith('/assets/v1-clone/')&&/\.(js|css)$/.test(u.pathname)){
        const job=r.body().then(body=>{served.set(u.pathname,{sha256:hash(body),diskSha256:hash(fs.readFileSync(path.join(ROOT,'app/public',u.pathname))),status:r.status()});}).catch(()=>{}).finally(()=>pending.delete(job));pending.add(job);
      }
    });
    page.on('dialog',d=>{events.push({type:'dialog',dialogType:d.type(),cancelled:true});void d.dismiss();});
    page.on('request',r=>{const u=new URL(r.url());events.push({type:'request',path:u.pathname,method:r.method(),api:u.origin===ORIGIN&&u.pathname.startsWith('/api/')});});
    await setPageAccount(page,account,label);
    const run={page,ctx,events,pending,label};await boot(page);return run;
  }
  async function step(run,key,action){
    if(!expected.has(key)||results.has(key))return;
    const offset=run.events.length,startAt=Date.now();let data={},status='PASS',reason='';
    try {assert.ok(Date.now()<deadline,'Bounded run deadline exceeded');data=await action()||{};status=data.status||'PASS';reason=data.reason||'';}
    catch(e){status='FAIL';reason=sanitize(e.message).slice(0,1200);}
    let state={};try{state=await snapshot(run.page);if(state.mainDigestInput){state.mainSha256=hash(state.mainDigestInput);delete state.mainDigestInput;}}catch{}
    const file=key.replace(/[^a-z0-9_-]/gi,'_')+'.png';let screenshot=null;
    try{await run.page.screenshot({path:out+'/'+file,fullPage:false,timeout:10000});screenshot=file;}catch{}
    const events=run.events.slice(offset);
    if(status==='PASS'&&events.some(e=>e.type==='pageerror'||(e.type==='api'&&e.status>=500))){status='FAIL';reason='Uncaught browser exception or API server error during step';}
    append({key,status,reason,...data,status,screenshot,elapsedMs:Date.now()-startAt,state,events});
  }
  async function api(run,p){return run.page.evaluate(async({p,org})=>{
    const r=await fetch(p,{headers:{'X-Organisation-Id':org},signal:AbortSignal.timeout(20000)});const body=await r.json();
    const data=body.data;
    return {status:r.status,data:p.endsWith('/session')?{userId:data?.userId,memberships:data?.memberships}:data};
  },{p,org:fixture.organisationId});}
  async function clickHandler(page,scope,handler){
    const nodes=page.locator(scope+' [onclick]');const attrs=await nodes.evaluateAll(ns=>ns.map(n=>n.getAttribute('onclick')));
    const index=attrs.indexOf(handler);assert.ok(index>=0,'Live menu control not found: '+handler);
    const target=nodes.nth(index);assert.ok(await target.isVisible(),'Menu control not visible: '+handler);await target.click();await settle(page);
  }
  async function globalInventory(run){return run.page.locator('#globalMenu [onclick]').evaluateAll(ns=>ns.map(n=>({handler:n.getAttribute('onclick'),label:n.getAttribute('aria-label')||n.innerText})));}
  async function openToolMenu(run,id){
    const globals=await globalInventory(run);const menu=globals.find(r=>/openGlobal\(['"]tools['"]/.test(r.handler));assert.ok(menu,'Tool library global entry missing');
    await clickHandler(run.page,'#globalMenu',menu.handler);
    const handler=await run.page.locator('#main [onclick],#navigator [onclick]').evaluateAll((ns,id)=>ns.map(n=>n.getAttribute('onclick')).find(h=>new RegExp('openTool\\([\\\'\"]'+id+'[\\\'\"]').test(h)),id);
    assert.ok(handler,'Tool not reachable through rendered library: '+id);
    const all=run.page.locator('#main [onclick],#navigator [onclick]');const index=(await all.evaluateAll(ns=>ns.map(n=>n.getAttribute('onclick')))).indexOf(handler);
    await run.page.evaluate(()=>window.__material3Calls=[]);
    await all.nth(index).click();await settle(run.page);
    assert.equal(await run.page.evaluate(()=>state.tool),id,'Click did not open requested tool');
    // Attach actual native project through the accessible selector when library orientation is standalone.
    const select=run.page.locator('.project-select:visible').first();
    if(await select.count()) {const option=await select.locator('option').first().getAttribute('value');if(option&&await run.page.evaluate(()=>V1CloneRuntime.context.getState().mode)==='standalone'){await select.selectOption(option);await settle(run.page);}}
    return {handler};
  }
  async function iframeCheck(run,id,role){
    const mount=await waitForWorkspaceMount(run.page);
    if(!mount.adapters.length)return {...mount,status:'UNSUPPORTED',reason:'Workspace renders natively without an iframe; iframe form check is not applicable.'};
    if(mount.mountTimedOut)return {...mount,status:'FAIL',reason:'Workspace iframe mount did not reach a terminal adapter state within the bounded readiness wait; form controls were not judged.'};
    if(mount.adapters.some(f=>f.adapter!=='ready'))return {...mount,status:'FAIL',reason:'Workspace iframe adapter reached unavailable/error rather than ready; form controls were not judged.'};
    const frames=[];for(const element of await run.page.locator('#main iframe').elementHandles()){const frame=await element.contentFrame();if(frame)frames.push(frame);}
    const forms=[];
    for(const frame of frames)forms.push(await frame.evaluate(nativeControlInventory));
    const controls=forms.flatMap(f=>f.controls),writes=controls.filter(c=>c.write),enabled=writes.filter(c=>c.enabled);
    const evidence={forms,...mount,visibleControls:controls.length,enabledControls:controls.filter(c=>c.enabled).length,writeControls:writes.length,enabledWriteControls:enabled.length,
      scope:'Native UI affordances only; no submission or domain persistence inferred'};
    if(role==='viewer'){
      if(enabled.length)return {...evidence,status:'FAIL',reason:'Viewer iframe exposes enabled native write controls'};
      assert.equal(await run.page.getByRole('dialog').count(),0,'Viewer unexpectedly has an inspection dialog');
      assert.equal(await run.page.evaluate(()=>V1CloneRuntime.context.getState().readOnly),true,'Viewer context must deny writes');
      return {...evidence,viewerWriteDisabledOrAbsent:true,viewerAccessDenialVerified:true,noDialog:true,reason:'Native write triggers and bound form controls are disabled or absent; viewer read-only context denies writes and no dialog is present.'};
    }
    if(!writes.length)return {...evidence,status:'UNSUPPORTED',reason:'No source-connected native iframe write trigger or bound form controls found; prototype buttons are not capabilities.'};
    if(!enabled.length)return {...evidence,status:'FAIL',reason:'Owner iframe has no enabled native write controls'};
    if(!['itp','safety'].includes(id))return evidence;
    const frame=frames.find((_,i)=>forms[i].controls.some(c=>c.marker==='site-create'&&c.enabled));
    if(!frame)return {...evidence,status:'FAIL',reason:'Expected connected inspection draft trigger is absent or disabled'};
    const offset=run.events.length,cancellations=[];
    assert.equal(await run.page.getByRole('dialog').count(),0,'Unexpected dialog before native trigger');
    for(const method of ['Escape','Cancel']){
      await frame.locator('[data-site-create]:enabled').first().click();
      const dialog=run.page.getByRole('dialog',{name:'Draft internal inspection',exact:true});
      await dialog.waitFor({state:'visible'});
      const input=dialog.getByLabel('Inspection finding',{exact:true});
      assert.ok(await input.isVisible(),'Required inspection finding field not visible');
      assert.equal(await input.getAttribute('required'),'','Inspection finding must be required');
      assert.equal(await input.evaluate(n=>n.tagName),'TEXTAREA');
      assert.equal(await dialog.evaluate(n=>n.ownerDocument===document&&n.open&&n.matches('dialog.m3-dialog')) ,true,'Expected parent-document M3 modal');
      const screenshot=id+'-draft-'+method.toLowerCase()+'.png';
      await run.page.screenshot({path:out+'/'+screenshot,fullPage:false});
      if(method==='Escape')await input.press('Escape');else await dialog.getByRole('button',{name:'Cancel',exact:true}).click();
      await dialog.waitFor({state:'detached'});await run.page.waitForTimeout(150);
      assert.equal(await run.page.getByRole('dialog').count(),0,'Cancellation opened another dialog');
      cancellations.push({method,formOpened:true,requiredFinding:true,closed:true,screenshot});
    }
    const actionEvents=run.events.slice(offset),requests=actionEvents.filter(e=>e.type==='request');
    const dialogs=actionEvents.filter(e=>e.type==='dialog');
    const postCount=requests.filter(e=>e.method==='POST').length,apiCount=requests.filter(e=>e.api).length;
    const mutationCount=requests.filter(e=>!['GET','HEAD','OPTIONS'].includes(e.method)).length;
    const action={formOpened:true,visibleFormFields:1,cancelClosed:true,cancellations,dialogDocument:'parent',dialogName:'Draft internal inspection',dialogs,postCount,apiCount,mutationCount,zeroBusinessWrites:mutationCount===0};
    if(mutationCount||apiCount||dialogs.length)return {...evidence,...action,status:'FAIL',reason:'Draft opening/cancellation unexpectedly sent an API request, mutation, or browser-native dialog'};
    return {...evidence,...action,reason:'Connected native trigger opened the parent M3 required-finding dialog; Escape and Cancel closed it with zero POST/API requests.'};
  }
  try {
    if(smoke){
      for(const role of ['owner','viewer']){
        // Use the existing role/profile fixture sessions; original bootstrap sessions may have expired.
        assert.equal(privateFixtures.organisationId,fixture.organisationId,'Smoke fixtures must share the native organisation');
        const account=privateFixtures.accounts.find(a=>a.membership===role&&a.profile==='architect');
        assert.ok(account,'Existing smoke role session fixture missing: '+role);
        const run=await openAccount(account,role);
        const session=await api(run,'/api/v1/session');assert.equal(session.status,200);
        assert.equal(session.data.memberships.find(m=>m.organisationId===fixture.organisationId)?.role,role);
        for(const id of ['itp','safety'])await step(run,role+'/'+id+'/iframe',async()=>{
          const project=await run.page.evaluate(()=>V1CloneRuntime.context.getState().projects[0]?.id);
          await boot(run.page,ORIGIN+'/v1?workspace='+id+(project?'&project='+project:''));
          assert.equal(await run.page.evaluate(()=>state.tool),id);
          return {...await iframeCheck(run,id,role),verifiedMembership:role};
        });
      }
      return;
    }
    const runs={};
    if(selection.batch!=='profiles') {
    for(const role of roles){
      const account=role==='owner'||role==='viewer'?fixture[role]:privateFixtures.accounts.find(a=>a.membership===role&&a.profile==='architect');
      if(selection.tools&&!expected.has('membership/'+role)&&!['owner','viewer'].includes(role))continue;
      const run=await openAccount(account,role);runs[role]=run;
      if(selection.tools){const discovery=await api(run,'/api/v1/tools');assert.equal(discovery.status,200);run.catalogue=discovery.data;}
      await step(run,'membership/'+role,async()=>{const s=await api(run,'/api/v1/session');assert.equal(s.status,200);assert.equal(s.data.memberships.find(m=>m.organisationId===fixture.organisationId)?.role,role);const t=await api(run,'/api/v1/tools');assert.equal(t.status,200);run.catalogue=t.data;return {sessionStatus:s.status,discoveryStatus:t.status,discovery:t.data};});
      await step(run,'dashboard/'+role,async()=>{await clickHandler(run.page,'#globalMenu',"openGlobal('command')");assert.ok(await run.page.locator('[data-role-dashboard]').isVisible());assert.ok((await run.page.locator('[data-role-dashboard]').innerText()).includes('Membership: '+role));return {globalMenu:await globalInventory(run),bootstrapEvents:run.events};});
    }
    const owner=runs.owner;const observed=await owner.page.evaluate(()=>Object.keys(TOOLS));assert.deepEqual(observed.sort(),Object.keys(inv.shellTools).sort(),'DOM/source shell tool inventory mismatch');
    const nativeProjects=await owner.page.evaluate(()=>V1CloneRuntime.context.getState().projects.slice(0,2).map(p=>p.id));
    save('native-project-readback.json',nativeProjects.map(id=>JSON.parse(execFileSync('php',[WORK+'/http-bootstrap.php','--read-project',id],{encoding:'utf8',timeout:60000}))));
    save('inventory-comparison.json',{nativeCount:ids.length,shellCount:observed.length,missingShellIds:ids.filter(id=>!observed.includes(id)),nativeDiscoveryIds:owner.catalogue.map(t=>t.id)});
    const globals=await globalInventory(owner);save('global-menu.json',globals);
    for(const g of globals){const key='global/'+g.handler;await step(owner,key,async()=>{await clickHandler(owner.page,'#globalMenu',g.handler);assert.ok((await owner.page.locator('#main').innerText()).trim());assert.equal(await owner.page.evaluate(()=>state.global),g.handler.match(/['"]([^'"]+)/)[1]);return {control:g,scope:'Global navigation and visible destination only; no domain completion inferred'};});}
    save('expected.json',[...expected]);
    for(const id of selectedIds){
      await step(owner,'owner/'+id+'/menu',async()=>{const clicked=await openToolMenu(owner,id);const s=await snapshot(owner.page);const calls=s.controllerCalls.filter(c=>c.tool===id&&!c.returnedFalse);const content=s.mainText||'';
        const closureController=await owner.page.evaluate(id=>id==='practice'&&document.querySelector('#main .pm-shell')&&window.V1CloneRuntime.controllers.practice?'practice':id==='meetings'&&window.V1CloneRuntime.controllers.meetings&&document.querySelector('#main [class*="mtg-"]')?'meetings':null,id);
        const controllers=[...new Set([...calls.map(c=>c.name),...(closureController?[closureController]:[])])];
        if(/not implemented|not configured|integration scaffold|preview only/i.test(content)||!controllers.length)return {...clicked,status:'UNSUPPORTED',reason:'Menu clicked, but native controller completion is not established; see loaded controller calls and visible content.',controllerCalls:calls};
        assert.ok(content.trim(),'Empty workspace');return {...clicked,controllerCalls:calls,loadedControllers:controllers,association:closureController?'Loaded controller plus source-defined DOM mount; internal closure invocation is not instrumented':'Observed native controller invocation',scope:'Native workspace entry, not business action completion'};});
      await step(owner,'owner/'+id+'/section-tabs',async()=>{if(await owner.page.evaluate(()=>state.tool)!==id)return {status:'UNSUPPORTED',reason:'Requested workspace did not open'};
        const handlers=await owner.page.locator('#navigator [onclick]').evaluateAll(ns=>ns.map(n=>n.getAttribute('onclick')).filter(h=>/^setToolTab\(\d+\)/.test(h)));
        if(!handlers.length)return {status:'UNSUPPORTED',reason:'No section-tab controls in this workspace'};
        const tabs=[];for(const handler of [...new Set(handlers)]){await clickHandler(owner.page,'#navigator',handler);assert.equal(await owner.page.evaluate(()=>state.tool),id);const index=Number(handler.match(/\d+/)[0]);assert.equal(await owner.page.evaluate(()=>state.toolTab),index);const screenshot='tab-'+id+'-'+index+'.png';await owner.page.screenshot({path:out+'/'+screenshot,fullPage:false});tabs.push({handler,status:'PASS',screenshot,mainSha256:hash(await owner.page.locator('#main').innerHTML())});}return {tabs};});
      await step(owner,'owner/'+id+'/iframe',()=>iframeCheck(owner,id,'owner'));
      await step(owner,'owner/'+id+'/project-switch',async()=>{await clickHandler(owner.page,'#globalMenu',"openGlobal('projects')");const select=owner.page.locator('.project-select:visible').first();if(!await select.count())return {status:'UNSUPPORTED',reason:'No visible native project selector in Project Space'};
        const options=await select.locator('option').evaluateAll(ns=>ns.map(n=>n.value).filter(v=>/^[a-f0-9]{32}$/.test(v)));if(options.length<2)return {status:'UNSUPPORTED',reason:'Fewer than two independently native synthetic projects available'};
        const original=await select.inputValue(),next=options.find(o=>o!==original);await select.selectOption(next);await settle(owner.page);assert.equal(await owner.page.evaluate(()=>V1CloneRuntime.context.getState().selectedProjectId),next);await openToolMenu(owner,id);assert.equal(await owner.page.evaluate(()=>V1CloneRuntime.context.getState().selectedProjectId),next);await clickHandler(owner.page,'#globalMenu',"openGlobal('projects')");await owner.page.locator('.project-select:visible').first().selectOption(original);await settle(owner.page);await openToolMenu(owner,id);return {from:original,to:next,restored:true};});
      await step(owner,'owner/'+id+'/back',async()=>{const controls=owner.page.locator('#main button,#navigator button,#navigator .back-row');const index=await controls.evaluateAll(ns=>ns.findIndex(n=>/^(←\s*)?back\b/i.test(n.innerText)||/back/i.test(n.getAttribute('aria-label')||'')||n.classList.contains('back-row')));
        if(index<0)return {status:'UNSUPPORTED',reason:'No accessible in-app Back control; browser-history navigation is tested by real direct-link reload separately'};
        await controls.nth(index).click();await settle(owner.page);assert.notEqual(await owner.page.evaluate(()=>state.tool),id,'Back did not leave the workspace');return {};});
      await step(owner,'owner/'+id+'/reload',async()=>{const project=await owner.page.evaluate(()=>V1CloneRuntime.context.getState().projects[0]?.id);const url=ORIGIN+'/v1?workspace='+id+(project?'&project='+project:'');await boot(owner.page,url);assert.equal(await owner.page.evaluate(()=>state.tool),id);const before=await snapshot(owner.page);await owner.page.reload({waitUntil:'domcontentloaded',timeout:180000});await owner.page.waitForFunction(()=>document.body.dataset.v1CloneState==='ready',null,{timeout:90000});await settle(owner.page);assert.equal(await owner.page.evaluate(()=>state.tool),id);assert.equal(await owner.page.evaluate(()=>V1CloneRuntime.context.getState().selectedProjectId),before.selectedProjectId);await instrument(owner.page);return {url,reloadVerified:true};});
      const viewer=runs.viewer;
      await step(viewer,'viewer/'+id+'/direct',async()=>{const project=await viewer.page.evaluate(()=>V1CloneRuntime.context.getState().projects[0]?.id);await boot(viewer.page,ORIGIN+'/v1?workspace='+id+(project?'&project='+project:''));const read=viewer.catalogue.find(t=>t.id===id)?.access?.read===true;const tool=await viewer.page.evaluate(()=>state.tool);if(read)assert.equal(tool,id,'Permitted viewer direct link failed');else assert.notEqual(tool,id,'Denied viewer direct link opened workspace');return {permitted:read,denialRequired:!read,observedTool:tool};});
      await step(viewer,'viewer/'+id+'/iframe',()=>iframeCheck(viewer,id,'viewer'));
    }
    }
    let profileRun;
    for(const account of privateFixtures.accounts){const key='profile/'+account.membership+'/'+account.profile;
      if(!expected.has(key))continue;
      if(Date.now()>=deadline)break;
      let run;try{if(!profileRun)profileRun=await openAccount(account,key);else{await setPageAccount(profileRun.page,account,key);await boot(profileRun.page);}run=profileRun;await step(run,key,async()=>{
        const s=await api(run,'/api/v1/session');assert.equal(s.status,200);assert.equal(s.data.userId,account.userId);const m=s.data.memberships.find(m=>m.organisationId===fixture.organisationId);assert.equal(m.role,account.membership);assert.equal(m.profileRole,account.profile);
        const expectedRole=account.membership==='viewer'?'viewer':account.membership==='member'&&['firm_admin','organisation_admin','admin','platform_admin'].includes(account.profile)?'member':account.profile==='organisation_admin'?'admin':account.profile;
        const actual=await run.page.evaluate(()=>state.role);assert.equal(actual,expectedRole,'Saved profile escalated membership presentation');await clickHandler(run.page,'#globalMenu',"openGlobal('command')");assert.ok(await run.page.locator('[data-role-dashboard]').isVisible());
        const priorities=JSON.parse(read('app/public/assets/v1-clone/js/modules/shell-data.js').match(/const priorities = Object.freeze\((\{[^\n]+\})\);/)[1]);
        const privileged=['owner','admin'].includes(account.membership),safeProfile=privileged||!/admin/.test(account.profile);
        assert.equal(await run.page.locator('[data-role-dashboard] h2').first().innerText(),safeProfile?priorities[account.profile].label:'Project workspace','Incorrect profile dashboard presentation');
        const t=await api(run,'/api/v1/tools');assert.equal(t.status,200);
        if(account.membership==='viewer')assert.equal(t.data.filter(t=>t.access?.write===true||t.access?.review===true).length,0,'Viewer profile grants write/review authority');
        if(!privileged)assert.notEqual(t.data.find(t=>t.id==='admin_review')?.access?.read,true,'Profile grants administrator workspace');
        return {membership:account.membership,profile:account.profile,presentationRole:actual,sessionStatus:s.status,dashboard:await run.page.locator('[data-role-dashboard]').innerText()};});}catch(e){if(!results.has(key))append({key,status:'FAIL',reason:sanitize(e.message),screenshot:null,events:[]});}
    }
    for(const [lens,policy]of Object.entries(inv.lenses))if(expected.has('lens/'+lens+'/native-project-allowlist'))append({key:'lens/'+lens+'/native-project-allowlist',status:'UNSUPPORTED',verification:'UNVERIFIED',reason:'Demo project keys have no independently verified native assigned-project fixture mapping on this production-mode synthetic host.',policy,screenshot:null,events:[]});
    for(const boundary of ['professional-authority','source-version'])if(expected.has('boundaries/'+boundary))append({key:'boundaries/'+boundary,status:'UNSUPPORTED',reason:'No real domain registration, professional authority or source-version issuance is invented by synthetic acceptance.',screenshot:null,events:[]});
  } catch(e){manifest.fatal=sanitize(e.message).slice(0,1600);}
  finally{
    for(const ctx of contexts)await ctx.close().catch(()=>{});
    for(const key of expected)if(!results.has(key))append({key,status:'FAIL',verification:'UNVERIFIED',reason:'Not executed after bounded run interruption: '+(manifest.fatal||'deadline'),screenshot:null,events:[]});
    const end=revision();const missing=[...expected].filter(k=>!results.has(k));
    const summary={...manifest,collectorFailure:!!(start.gitFailure||end.gitFailure),endGitFailure:end.gitFailure,finishedAt:new Date().toISOString(),endRevision:end.sha256,candidateChanged:start.sha256!==end.sha256,expected:expected.size,completed:results.size,missingKeys:missing,counts:counts(),allPass:!start.gitFailure&&!end.gitFailure&&!manifest.fatal&&missing.length===0&&[...results.values()].every(r=>r.status==='PASS'),servedAssets:[...served].map(([path,data])=>({path,...data})),results:[...results.values()]};
    save('report.json',summary);save('manifest.json',manifest);save('expected.json',[...expected]);
    console.log(safeJSON({out,expected:summary.expected,completed:summary.completed,counts:summary.counts,candidateChanged:summary.candidateChanged,fatal:manifest.fatal||null,collectorFailure:!!(start.gitFailure||end.gitFailure)}));
    if(start.gitFailure||end.gitFailure||manifest.fatal||summary.counts.FAIL||summary.results.some(r=>r.verification==='UNVERIFIED')||summary.candidateChanged||summary.servedAssets.some(a=>a.sha256!==a.diskSha256)||(smoke&&!summary.allPass))process.exitCode=1;
  }
}
// Serialized into the real frame. Classify only selectors consumed by native modules.
// A form tag or an English action label alone is not a connected write contract.
function nativeControlInventory(){
  const boundForm=n=>{const f=n.closest('form');return f&&typeof f.onsubmit==='function'&&f.matches('.work-permit-form,[data-boq-form]')?f:null;};
  return {url:location.protocol==='about:'?location.href:location.pathname,
    controls:[...document.querySelectorAll('input,textarea,select,button')].filter(n=>n.getClientRects().length).map(n=>{
      const unavailable=n.matches('[data-site-unavailable],[aria-disabled="true"]')||!!n.closest('[inert]');
      const marker=n.matches('[data-site-create]')?'site-create':boundForm(n)?(n.closest('[data-boq-form]')?'boq-form':'work-permit-form'):null;
      const connected=marker==='site-create'?typeof n.onclick==='function':!!boundForm(n);
      const write=!!marker&&connected&&!unavailable;
      const disabled=n.matches(':disabled')||n.getAttribute('aria-disabled')==='true';
      return {tag:n.tagName,type:n.type,id:n.id,name:n.name,label:(n.getAttribute('aria-label')||n.textContent||'').slice(0,100),marker,connected,unavailable,disabled,readOnly:!!n.readOnly,write,enabled:!disabled&&!n.readOnly&&!unavailable};
    })};
}
async function classificationRegression(){
  const {chromium}=require(WORK+'/browser-deps/node_modules/playwright-core');
  const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try{
    const page=await browser.newPage();
    await page.setContent('<button data-site-create>Entwurf prüfen</button><button data-site-unavailable disabled>Create Revision</button><button>Create inspection</button><form><input name="fake"><button>Save</button></form><form class="work-permit-form"><input name="title"><button type="submit">Speichern</button></form>');
    await page.evaluate(()=>{document.querySelector('[data-site-create]').onclick=()=>{};document.querySelector('.work-permit-form').onsubmit=e=>e.preventDefault();});
    const owner=await page.evaluate(nativeControlInventory);
    assert.equal(owner.controls.filter(c=>c.write).length,3);
    assert.equal(owner.controls.find(c=>c.marker==='site-create').write,true);
    assert.equal(owner.controls.find(c=>c.label==='Create Revision').unavailable,true);
    assert.equal(owner.controls.find(c=>c.label==='Create Revision').write,false);
    assert.equal(owner.controls.find(c=>c.label==='Create inspection').write,false);
    assert.equal(owner.controls.find(c=>c.name==='fake').write,false);
    await page.evaluate(()=>{document.querySelector('[data-site-create]').disabled=true;document.querySelectorAll('.work-permit-form input,.work-permit-form button').forEach(n=>n.disabled=true);});
    const viewer=await page.evaluate(nativeControlInventory);assert.equal(viewer.controls.filter(c=>c.write&&c.enabled).length,0);
    await page.evaluate(()=>document.querySelector('[data-site-create]').onclick=null);
    assert.equal((await page.evaluate(nativeControlInventory)).controls.find(c=>c.marker==='site-create').write,false);
    console.log(JSON.stringify({regression:'native-write-markers-localized-prototype-unavailable-viewer-unbound',status:'PASS',ownerWrites:3,viewerEnabledWrites:0}));
  }finally{await browser.close();}
}
async function readinessRegression() {
  const {chromium}=require(WORK+'/browser-deps/node_modules/playwright-core');
  const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try {
    const page=await browser.newPage();
    await page.setContent('<main id="main"><iframe data-v1-clone-adapter="loading" srcdoc="<form><button disabled>Create</button></form>"></iframe></main>');
    await page.waitForFunction(()=>document.querySelector('iframe').contentDocument?.querySelector('button'));
    assert.equal(await page.locator('iframe').getAttribute('data-v1-clone-adapter'),'loading');
    await page.evaluate(()=>setTimeout(()=>{const f=document.querySelector('iframe');f.contentDocument.querySelector('button').disabled=false;f.dataset.v1CloneAdapter='ready';},700));
    const mount=await waitForWorkspaceMount(page,{timeout:2000,settleMs:50});
    assert.equal(mount.adapterState,'ready','Controls must not be judged while adapter is loading');
    assert.ok(mount.mountElapsedMs>=600);
    assert.equal(await page.frames()[1].locator('button:enabled').count(),1);
    console.log(JSON.stringify({regression:'delayed-iframe-readiness',status:'PASS',...mount}));
    await page.locator('iframe').evaluate(f=>f.dataset.v1CloneAdapter='loading');
    const stuck=await waitForWorkspaceMount(page,{timeout:150,settleMs:0});
    assert.equal(stuck.mountTimedOut,true);assert.equal(stuck.adapterState,'loading');
    await page.locator('iframe').evaluate(f=>f.dataset.v1CloneAdapter='error');
    const failed=await waitForWorkspaceMount(page,{timeout:1000,settleMs:0});
    assert.equal(failed.mountTimedOut,false);assert.equal(failed.adapterState,'error');
    console.log(JSON.stringify({regression:'loading-timeout-and-terminal-error',status:'PASS'}));
    await page.setContent('<main id="main"><iframe data-v1-clone-adapter="ready" srcdoc="<button data-site-create>Draft</button>"></iframe></main>');
    await page.waitForFunction(()=>document.querySelector('iframe').contentDocument?.querySelector('[data-site-create]'));
    await page.addScriptTag({content:read('app/public/assets/v1-clone/js/modules/m3-dialog.js')});
    await page.evaluate(()=>{const button=document.querySelector('iframe').contentDocument.querySelector('[data-site-create]');button.onclick=()=>window.V1CloneM3Dialog.prompt({title:'Draft internal inspection',label:'Inspection finding',required:true,invoker:button});});
    const frame=page.frames()[1];
    assert.equal((await frame.evaluate(nativeControlInventory)).controls.filter(c=>c.write&&c.enabled).length,1);
    for(const cancellation of ['Escape','Cancel']){
      await frame.locator('[data-site-create]').click();
      const dialog=page.getByRole('dialog',{name:'Draft internal inspection',exact:true});
      await dialog.waitFor({state:'visible'});
      assert.equal(await frame.locator('form:visible').count(),0,'M3 helper form belongs to parent document, not iframe');
      const input=dialog.getByLabel('Inspection finding',{exact:true});assert.ok(await input.isVisible());assert.equal(await input.getAttribute('required'),'');
      if(cancellation==='Escape')await input.press('Escape');else await dialog.getByRole('button',{name:'Cancel',exact:true}).click();
      await dialog.waitFor({state:'detached'});
    }
    await frame.locator('[data-site-create]').evaluate(n=>n.disabled=true);
    assert.equal((await frame.evaluate(nativeControlInventory)).controls.filter(c=>c.write&&c.enabled).length,0);
    assert.equal(await page.getByRole('dialog').count(),0);
    console.log(JSON.stringify({regression:'real-helper-parent-dialog-native-trigger-required-field-escape-cancel-viewer',status:'PASS'}));
  } finally {await browser.close();}
}
async function waitForWorkspaceMount(page,{timeout=25000,settleMs=200}={}) {
  const started=Date.now();let mountTimedOut=false;
  try {
    await page.waitForFunction(()=>{
      const frames=[...document.querySelectorAll('#main iframe')];
      return frames.length>0&&frames.every(f=>['ready','unavailable','error'].includes(f.dataset.v1CloneAdapter));
    },null,{timeout});
  } catch(error) {if(error.name!=='TimeoutError')throw error;mountTimedOut=true;}
  const mountElapsedMs=Date.now()-started;
  await page.waitForTimeout(settleMs);
  const adapters=await page.locator('#main iframe').evaluateAll(ns=>ns.map(f=>({id:f.id,adapter:f.dataset.v1CloneAdapter||null})));
  return {adapterState:adapters[0]?.adapter||null,adapters,mountElapsedMs,mountTimedOut,mountTimeoutMs:timeout};
}
async function preflightRegression(){
  // Synthetic transport only here: exercise the actual preflight and transition code.
  const org='regression-org',first={userId:'first',membership:'owner',profile:'architect',token:'test-first'},second={userId:'second',membership:'viewer',profile:'engineer',token:'test-second'};
  let active,responseBody,bootAttempts=0;
  const context={clearCookies:async()=>{active=null;},addCookies:async cookies=>{active=cookies[0].value;},request:{get:async(url,options)=>{
    assert.equal(url,ORIGIN+'/api/v1/session');assert.equal(options.headers['X-Organisation-Id'],org);
    assert.equal(options.timeout,20000);
    const account=active===first.token?first:active===second.token?second:null;
    return {status:()=>account?200:401,json:async()=>responseBody||{data:{userId:account.userId,memberships:[{organisationId:org,role:account.membership,profileRole:account.profile}]}}};
  }}};
  const page={context:()=>context};
  const boot=async()=>{await accountPreflight(page.context(),page.__material3Account,org);bootAttempts++;};
  await setPageAccount(page,first,'owner');await boot();
  await setPageAccount(page,second,'viewer');await boot();assert.equal(bootAttempts,2);
  for(const [field,value,error]of [['userId',first.userId,'identity'],['organisationId','wrong-org','organisation'],['role',first.membership,'membership'],['profileRole',first.profile,'profile']]){
    responseBody={data:{userId:second.userId,memberships:[{organisationId:org,role:second.membership,profileRole:second.profile}]}};
    if(field==='userId')responseBody.data.userId=value;else responseBody.data.memberships[0][field]=value;
    await assert.rejects(boot(),new RegExp('FAIL bootstrap-auth-'+error));assert.equal(bootAttempts,2);
  }
  responseBody=null;await context.clearCookies();await assert.rejects(boot(),/HTTP 401 before shell boot/);assert.equal(bootAttempts,2);
  console.log(JSON.stringify({regression:'reused-profile-context-preflight',status:'PASS',acceptedAccounts:2,rejectedMismatches:4,missingSessionRejectedBeforeBoot:true}));
}
async function main() {
  if(args.has('--redaction-regression'))return redactionRegression();
  if(args.has('--preflight-regression'))return preflightRegression();
  if(args.has('--auth-preflight-regression')){
    const {chromium}=require(WORK+'/browser-deps/node_modules/playwright-core');
    const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try{const ctx=await browser.newContext();const started=Date.now();await assert.rejects(accountPreflight(ctx,{userId:'absent'},'bc3c1f58d8dd1a3d5625413b0b44ed3e'),/FAIL bootstrap-auth-status: HTTP 401 before shell boot/);console.log(JSON.stringify({regression:'absent-session-native-preflight',status:'PASS',httpStatus:401,bootAttempted:false,elapsedMs:Date.now()-started}));}finally{await browser.close();}return;
  }
  if(args.has('--classification-regression'))return classificationRegression();
  if(args.has('--readiness-regression'))return readinessRegression();
  require('./material3-menu-proof.cjs').validateReceipt(json(WORK + '/receipt.json'));
  const fixture = json(WORK + '/fixture.json');
  const secrets = privateSecrets;
  secrets.push(fixture.owner.token, fixture.owner.csrf, fixture.viewer.token, fixture.viewer.csrf);
  const out = WORK + '/browser-proof-' + new Date().toISOString().replace(/[:.]/g, '-');
  fs.mkdirSync(out, {recursive:true});
  const {chromium} = require(WORK + '/browser-deps/node_modules/playwright-core');
  if(args.has('--handoff')){
    // Explicit handoff owns a fresh temporary browser; it never attaches to a personal profile.
    const net=require('node:net');const reservation=net.createServer();
    await new Promise((resolve,reject)=>{reservation.once('error',reject);reservation.listen(0,'127.0.0.1',resolve);});
    const cdpPort=reservation.address().port;await new Promise(resolve=>reservation.close(resolve));
    const server=await chromium.launchServer({headless:false,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',downloadsPath:out+'/downloads',args:['--remote-debugging-address=127.0.0.1','--remote-debugging-port='+cdpPort],timeout:30000});
    const browser=await chromium.connect(server.wsEndpoint());
    const context=await browser.newContext({viewport:{width:1440,height:1000},acceptDownloads:false});
    await context.addCookies([{name:'architex_session',value:fixture.owner.token,url:ORIGIN,httpOnly:true,sameSite:'Lax'}]);
    const page=await context.newPage();
    try{
      await accountPreflight(context,{...fixture.owner,membership:'owner'},fixture.organisationId);
      await page.goto(ORIGIN+'/v1',{waitUntil:'domcontentloaded',timeout:180000});
      await page.waitForFunction(()=>document.body.dataset.v1CloneState==='ready',null,{timeout:90000});
      await page.getByRole('button',{name:'OS Command Centre',exact:true}).click({timeout:10000});
      await page.evaluate(()=>document.title='Material3Acceptance — Synthetic owner Command Centre');
      await page.screenshot({path:out+'/handoff.png',fullPage:false});
      const handle={origin:ORIGIN,url:page.url(),title:await page.title(),browserPid:server.process().pid,cdpPort,synthetic:true,normalProductionLogin:false,expiresAt:new Date(Date.now()+30*60000).toISOString(),screenshot:out+'/handoff.png'};
      fs.writeFileSync(out+'/handoff.json',JSON.stringify(handle,null,2));console.log(JSON.stringify(handle));
      setTimeout(()=>server.close().finally(()=>process.exit(0)),30*60000);
      await new Promise(resolve=>server.on('close',resolve));
    }catch(error){await server.close();throw error;}return;
  }
  const browser = await chromium.launch({headless:args.has('--headless'), executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', downloadsPath:out+'/downloads', timeout:30000});
  if(!args.has('--inspect')){try{await exhaustive({browser,fixture,secrets,sanitize,out});}finally{await browser.close();}return;}
  const context = await browser.newContext({viewport:{width:1440,height:1000},acceptDownloads:false});
  await context.addCookies([{name:'architex_session',value:fixture.owner.token,url:ORIGIN,httpOnly:true,sameSite:'Lax'}]);
  const page = await context.newPage(); page.setDefaultTimeout(6000); page.setDefaultNavigationTimeout(180000);
  const events=[];
  page.on('console',m=>{if(['warning','error'].includes(m.type()))events.push({type:m.type(),message:sanitize(m.text()).slice(0,700)});});
  page.on('pageerror',e=>events.push({type:'pageerror',message:sanitize(e.message).slice(0,700)}));
  page.on('response',r=>{const u=new URL(r.url());if(u.origin===ORIGIN&&u.pathname.startsWith('/api/'))events.push({type:'api',path:u.pathname,status:r.status(),method:r.request().method()});});
  try {
    await accountPreflight(context,{...fixture.owner,membership:'owner'},fixture.organisationId);
    await page.goto(ORIGIN+'/v1',{waitUntil:'domcontentloaded'});
    await page.waitForFunction(()=>window.V1CloneRuntime?.context?.getState().ready && window.V1CloneRuntime?.controllers?.discovery, null,{timeout:45000});
    await page.waitForLoadState('networkidle',{timeout:30000}).catch(()=>{});
    await page.evaluate(()=>document.title='Material3Acceptance — Owner native menu inspection');
    const dom=await page.evaluate(()=>({tools:Object.keys(TOOLS),state:{tool:state.tool,global:state.global,role:state.role,mode:state.mode},catalogue:V1CloneRuntime.controllers.discovery.getCatalogue(),projects:V1CloneRuntime.context.getState().projects.map(p=>({id:p.id,name:p.name})),controls:[...document.querySelectorAll('#globalMenu button,#navigator button,#navigator select,#main button,#main select,[onclick]')].map(e=>({tag:e.tagName,id:e.id,label:(e.innerText||e.getAttribute('aria-label')||'').slice(0,110),handler:e.getAttribute('onclick'),options:e.options?[...e.options].map(o=>({value:o.value,label:o.text})):undefined})),controllers:Object.keys(V1CloneRuntime.controllers)}));
    fs.writeFileSync(out+'/inspection.json',safeJSON({origin:ORIGIN,dom,events},2));
    await page.screenshot({path:out+'/owner-dashboard.png',fullPage:true});
    console.log(JSON.stringify({out,toolCount:dom.tools.length,catalogueCount:dom.catalogue.length,projects:dom.projects.length,events:events.length}));
  } catch(error) {
    const diagnosis=await page.evaluate(()=>({url:location.href,bodyState:document.body?.dataset,bodyText:document.body?.innerText?.slice(0,1500),bodyLength:document.body?.innerHTML?.length,scripts:[...document.scripts].map(s=>({src:s.src,type:s.type})),runtime:typeof window.V1CloneRuntime,resources:performance.getEntriesByType('resource').map(r=>({name:r.name.split('?')[0],duration:r.duration,bytes:r.transferSize}))})).catch(()=>({}));
    fs.writeFileSync(out+'/failure.json',safeJSON({message:error.message,events,diagnosis},2));
    await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});
    console.log(JSON.stringify({out,error:sanitize(error.message).slice(0,300)}));
    process.exitCode=1;
  } finally {await browser.close();}
}
if(require.main===module)main().catch(error=>{console.error(safeJSON({collectorFailure:true,fatal:String(error.message).slice(0,1000)}));process.exitCode=1;});
