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
 const errors=[];page.on('pageerror',e=>errors.push(e.message));const tools=metadata(role);
 await page.route('http://role-fixture.test/**',async route=>{
  const p=new URL(route.request().url()).pathname;
  // Force the production 100ms body poll to run before parser-blocked tail scripts.
  if(slowFrame&&p.endsWith('/material3-embedded-workspaces.css'))await new Promise(resolve=>setTimeout(resolve,800));
  if(p.startsWith('/assets/')){const file=path.resolve(publicRoot,'.'+p);if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file))return route.fulfill({status:404,body:''});return route.fulfill({body:fs.readFileSync(file),contentType:p.endsWith('.js')?'application/javascript':p.endsWith('.css')?'text/css':'application/octet-stream'});}
  if(p.startsWith('/api/v1/')){let data=[];
   if(p==='/api/v1/session')data={userId:'d'.repeat(32),email:'synthetic-navigation@example.test',csrfToken:'test-only',memberships:[{organisationId:organisation,role,profileRole:'architect'}]};
   if(p==='/api/v1/projects')data=[{id:project,name:'Synthetic navigation fixture',organisationId:organisation,phase:'inception'}];
   if(p==='/api/v1/tools')data=tools;
   if(p.endsWith('/work-permits'))data={permits:[],canDraft:false};
   if(p.includes('site-execution'))data={inspections:[],ncrs:[],evidence:[]};
   if(p==='/api/v1/wingman/tutorial')data={label:'Test-only tour',progress:{status:'completed'},steps:[]};
   if(p.includes('trust-compliance'))data={complianceItems:[]};
   return route.fulfill({contentType:'application/json',body:JSON.stringify({data,meta:{},error:null})});
  }
  // Only the entry document is a shell. Serving app.php for /preview3.html
  // recursively embeds another shell (and repeats its session/bootstrap scripts).
  // Resolve non-API resources as production static files instead of a shell fallback.
  if(p===(legacy?'/app.php':'/v1-candidate.php'))return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(root,legacy?'app/public/app.php':'app/views/v1-clone.php'),'utf8')});
  const file=path.resolve(publicRoot,'.'+p);
  if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile())return route.fulfill({status:404,body:''});
  const contentType=p.endsWith('.html')?'text/html':p.endsWith('.png')?'image/png':p.endsWith('.webmanifest')?'application/manifest+json':'application/octet-stream';
  return route.fulfill({contentType,body:fs.readFileSync(file)});
 });
 await page.goto('http://role-fixture.test/'+(legacy?'app.php':'v1-candidate.php?workspace=bom&orientation=project&project='+project));
 await page.waitForSelector(legacy?'html[data-access-state="workspace"][data-shell-state="ready"]':'body[data-v1-clone-state="ready"]');return {page,errors};
}
for(const entry of ['direct','openTool'])test('BoQ production '+entry+' waits for parser completion before native mount',async t=>{
 const {page,errors}=await fixture(t,'owner',false,true);
 if(entry==='openTool')await page.evaluate(()=>openTool('bom','project'));
 await page.waitForFunction(()=>['ready','error'].includes(document.querySelector('#toolFrame')?.dataset.v1CloneAdapter));
 assert.equal(await page.locator('#toolFrame').getAttribute('data-v1-clone-adapter'),'ready');
 const frame=page.frameLocator('#toolFrame');
 assert.equal(await frame.locator('[data-boq-form]').count(),1);
 assert.equal(await frame.locator('[name="quantity"]').isEnabled(),true);
 assert.equal(await page.locator('#toolFrame').evaluate(f=>Boolean(f.contentWindow.__v1DocumentFrame&&f.contentWindow.__v1DocumentCommand)),true);
 assert.deepEqual(errors,[]);
});
async function baseline(frame){return frame.evaluate(()=>{
 const s=getComputedStyle(document.body);return {font:s.fontFamily,color:s.color,background:s.backgroundColor,logos:[...document.querySelectorAll('img')].map(x=>x.getAttribute('src')),scripts:[...document.scripts].map(x=>x.textContent)};
});}
for(const tool of ['bom','safety','xa'])test('actual '+tool+' frame mounts scoped components and preserves original typography, palette and scripts',async t=>{
 const {page,errors}=await fixture(t);await page.evaluate(tool=>openTool(tool,'project'),tool);
 await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');const frame=page.frames().find(f=>f.parentFrame());
 assert.equal(await frame.locator('body[data-architex-m3-embedded]').count(),1);
 const link=frame.locator('link[data-architex-m3-theme]');assert.equal(await link.getAttribute('href'),'http://role-fixture.test/assets/v1-clone/css/material3-embedded-workspaces.css');
 await frame.waitForFunction(()=>[...document.styleSheets].some(s=>s.href?.endsWith('material3-embedded-workspaces.css')));
 const after=await baseline(frame);
 const expectedScripts=await page.evaluate(tool=>{
  const html=new TextDecoder().decode(Uint8Array.from(atob(SOURCES[tool]),c=>c.charCodeAt(0))),controller=Object.values(V1CloneRuntime.controllers).find(c=>c.supports?.(tool));
  const doc=new DOMParser().parseFromString(controller.prepareSource(tool,html),'text/html');return [...doc.scripts].map(s=>s.textContent);
 },tool);assert.deepEqual(after.scripts,expectedScripts);
 assert.equal(await frame.evaluate(()=>document.head.querySelector('link[data-architex-m3-theme]')===document.head.lastElementChild),true);
 await link.evaluate(el=>el.sheet.disabled=true);const before=await baseline(frame);assert.deepEqual(after,before);
 const components=()=>{document.getAnimations().forEach(a=>{try{a.finish();}catch{}});return [...document.querySelectorAll('button,input,select,textarea,.card,.badge')].map(el=>{const s=getComputedStyle(el);return [s.fontFamily,s.color,s.backgroundColor,el.hidden,el.disabled];});};
 const componentBefore=await frame.evaluate(components);
 await link.evaluate(el=>el.sheet.disabled=false);assert.deepEqual(await frame.evaluate(components),componentBefore);
 const button=frame.locator('button').filter({visible:true}).first();assert.equal(await button.evaluate(el=>getComputedStyle(el).borderRadius),'24px');
 const native=tool==='bom'?'[data-boq-form]':tool==='safety'?'[data-site-create]':'main';assert.ok(await frame.locator(native).count());
 if(tool==='safety')assert.equal(await frame.locator('form.work-permit-form').evaluate(el=>el.hidden),true);
 const tabs=page.locator('#navigator button[onclick="setToolTab(1)"]');if(await tabs.count()){await tabs.click();await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');assert.equal(await page.frameLocator('#toolFrame').locator('body[data-architex-m3-embedded]').count(),1);}
 console.log('BASELINE '+tool+' '+JSON.stringify({...after,scripts:after.scripts.length}));
 const dir='E:/Hermes/home/cache/scratch';await page.screenshot({path:path.join(dir,'material3-embedded-'+tool+'.png')});assert.deepEqual(errors,[]);
});
test('source preparation preserves a literal head-close in scripts and doctype',async t=>{
 const {page}=await fixture(t);const result=await page.evaluate(()=>{
 const original=SOURCES.bom,source=decode64(original),doc=new DOMParser().parseFromString(source,'text/html');
 const script=doc.createElement('script');script.textContent='window.literalHeadClose="</head>";';doc.head.append(script);
 const encoded=btoa(unescape(encodeURIComponent('<!DOCTYPE html>'+doc.documentElement.outerHTML)));SOURCES.bom=encoded;state.tool='bom';const prepared=decode64(encoded);SOURCES.bom=original;
 const parsed=new DOMParser().parseFromString(prepared,'text/html');return {doctype:!!parsed.doctype,text:[...parsed.scripts].find(s=>s.textContent.includes('literalHeadClose'))?.textContent,links:parsed.head.querySelectorAll('link[data-architex-m3-theme]').length};
 });assert.deepEqual(result,{doctype:true,text:'window.literalHeadClose="</head>";',links:1});
});
test('viewer denial stays denied',async t=>{
 const {page}=await fixture(t,'viewer');assert.equal(await page.evaluate(()=>openTool('bom','project')),false);assert.equal(await page.evaluate(()=>openTool('admin_review','project')),false);
});
test('readonly native safety and narrow long tables preserve disabled controls and all columns',async t=>{
 const {page}=await fixture(t);
 // Explicit test-only readonly context; actual entry and native controller still mount.
 await page.evaluate(()=>{const context=V1CloneRuntime.context,read=context.getState;context.getState=()=>({...read(),readOnly:true});openTool('safety','project');});
 await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');const frame=page.frames().find(f=>f.parentFrame());
 assert.equal(await frame.locator('[data-site-create]').isDisabled(),true);
 await page.setViewportSize({width:390,height:844});await page.emulateMedia({reducedMotion:'reduce'});
 const table=frame.locator('table').first();const result=await table.evaluate(table=>{
  const row=table.querySelector('tbody tr');const columns=row.cells.length;
  for(const cell of row.cells)cell.textContent='RecordedColumnValue'.repeat(25);
  for(let i=0;i<25;i++)row.parentElement.append(row.cloneNode(true));
  const wrap=table.parentElement;wrap.style.width='280px';
  return {columns,retained:[...table.querySelectorAll('tbody tr')].every(r=>r.cells.length===columns),scroll:getComputedStyle(wrap).overflowX,width:wrap.clientWidth,content:wrap.scrollWidth};
 });assert.ok(result.columns>1);assert.equal(result.retained,true);assert.equal(result.scroll,'auto');assert.ok(result.content>result.width);
 assert.equal(await frame.locator('[data-site-create]').evaluate(el=>getComputedStyle(el).transitionDuration),'0s');
 assert.equal(await frame.locator('form.work-permit-form').evaluate(el=>el.hidden),true);
});
test('active legacy workspace only is marked and component styles do not recolour or expose public access',async t=>{
 const {page,errors}=await fixture(t,'owner',true);assert.deepEqual(errors,[]);
 // The public landing must be the original static document, never another app
 // shell. This catches the recursive fallback independently of readiness timing.
 const landing=page.frames().find(frame=>frame.url()==='http://role-fixture.test/preview3.html');
 assert.ok(landing);assert.equal(page.frames().length,2);
 assert.equal(await landing.locator('#publicLandingFrame').count(),0);
 assert.equal(await landing.locator('script[src="/assets/js/shell.js"]').count(),0);
 assert.equal(await landing.locator('a.brand[aria-label="Architex home"] img').count(),1);
 const logoBefore=await page.locator('.brand img').getAttribute('src');
 assert.equal(await page.locator('#main[data-architex-m3-embedded]').count(),1);assert.equal(await page.locator('#publicAccess[data-architex-m3-embedded]').count(),0);
 for(const tool of ['drawing_compliance_navigator','valuations']){
  await page.evaluate(tool=>openTool(tool,'project'),tool);
  const button=page.locator('#main button').first();await button.waitFor();
  const before=await button.evaluate(el=>{const s=getComputedStyle(el);return {font:s.fontFamily,color:s.color,background:s.backgroundColor};});
  assert.equal(await button.evaluate(el=>getComputedStyle(el).borderRadius),'24px');
  await page.locator('link[href$="material3-embedded-workspaces.css"]').evaluate(el=>el.sheet.disabled=true);
  assert.deepEqual(await button.evaluate(el=>{const s=getComputedStyle(el);return {font:s.fontFamily,color:s.color,background:s.backgroundColor};}),before);
  await page.locator('link[href$="material3-embedded-workspaces.css"]').evaluate(el=>el.sheet.disabled=false);
  assert.equal(await page.locator('.brand img').getAttribute('src'),logoBefore);
  await page.screenshot({path:path.join('E:/Hermes/home/cache/scratch','material3-legacy-'+tool+'.png')});
  console.log('BASELINE legacy '+tool+' '+JSON.stringify(before));
 }
});
