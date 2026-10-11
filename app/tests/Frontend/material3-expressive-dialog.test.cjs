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
 const errors=[];page.posts=[];page.on('dialog',d=>d.dismiss());page.on('pageerror',e=>errors.push(e.message));const tools=metadata(role);
 await page.route('http://role-fixture.test/**',async route=>{
  const p=new URL(route.request().url()).pathname;
  // Force the production 100ms body poll to run before parser-blocked tail scripts.
  if(slowFrame&&p.endsWith('/material3-embedded-workspaces.css'))await new Promise(resolve=>setTimeout(resolve,800));
  if(p.startsWith('/assets/')){const file=path.resolve(publicRoot,'.'+p);if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file))return route.fulfill({status:404,body:''});return route.fulfill({body:fs.readFileSync(file),contentType:p.endsWith('.js')?'application/javascript':p.endsWith('.css')?'text/css':'application/octet-stream'});}
  if(p.startsWith('/api/v1/')){let data=[];if(route.request().method()==='POST'){page.posts.push(route.request().postDataJSON());}
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
 await page.goto('http://role-fixture.test/'+(legacy?'app.php':'v1-candidate.php?workspace=itp&orientation=project&project='+project));
 await page.waitForSelector(legacy?'html[data-access-state="workspace"][data-shell-state="ready"]':'body[data-v1-clone-state="ready"]');return {page,errors};
}
test('settled dialogs release frame load listeners without changing focus restoration',async t=>{
 const {page}=await fixture(t,'owner');await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');
 const result=await page.evaluate(async()=>{
  const frame=document.createElement('iframe');const loaded=new Promise(resolve=>frame.onload=resolve);frame.srcdoc='<button>Invoker</button>';document.body.append(frame);await loaded;frame.onload=null;
  const doc=frame.contentDocument;doc.body.innerHTML='<button>Invoker</button>';const button=doc.querySelector('button');
  const add=frame.addEventListener.bind(frame),remove=frame.removeEventListener.bind(frame),listeners=new Set();let adds=0,removes=0;
  frame.addEventListener=(type,callback,options)=>{if(type==='load'){adds++;listeners.add(callback);}return add(type,callback,options);};
  frame.removeEventListener=(type,callback,options)=>{if(type==='load'){removes++;listeners.delete(callback);}return remove(type,callback,options);};
  const values=[],focused=[];
  for(const kind of ['prompt','confirm','prompt']){
   button.focus();const pending=V1CloneM3Dialog[kind]({title:'Cleanup probe',label:'Finding',invoker:button});
   const dialog=document.querySelector('dialog.m3-dialog');if(kind==='prompt'){dialog.querySelector('textarea').value='Finding';dialog.querySelector('button[type="submit"]').click();}else dialog.querySelector('button[type="button"]').click();
   values.push(await pending);focused.push(doc.activeElement===button);
  }
  const counts={adds,removes,outstanding:listeners.size,dialogs:document.querySelectorAll('dialog.m3-dialog').length,values,focused};frame.remove();return counts;
 });
 assert.deepEqual(result,{adds:3,removes:3,outstanding:0,dialogs:0,values:['Finding',false,'Finding'],focused:[true,true,true]});
});
test('dialog lifecycle abort never focuses an orphaned or cancelled invoker',async t=>{
 const {page}=await fixture(t,'owner');await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');
 for(const reason of ['signal','removed','replaced','cancel']){
  const result=await page.evaluate(async reason=>{
   const frame=document.createElement('iframe');document.body.append(frame);const doc=frame.contentDocument;doc.body.innerHTML='<button>Invoker</button>';const button=doc.querySelector('button');let focusCalls=0;button.focus=()=>focusCalls++;
   const controller=new AbortController();const pending=V1CloneM3Dialog.prompt({title:'Lifecycle probe',label:'Finding',invoker:button,signal:controller.signal});
   if(reason==='signal')controller.abort();else if(reason==='removed')frame.remove();else if(reason==='replaced'){frame.srcdoc='<p>Replacement</p>';await new Promise(resolve=>frame.onload=resolve);}else document.querySelector('dialog button').click();
   const value=await pending;frame.remove();return {value,focusCalls};
  },reason);
  assert.equal(result.value,null);assert.equal(result.focusCalls,reason==='cancel'?1:0,reason);
 }
});
for(const scenario of ['readonly','scope','disposed','generation'])test('inspection '+scenario+' prevents dialogs or disposes pending input without writes',async t=>{
 const {page}=await fixture(t,'owner');await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');const action=page.frameLocator('#toolFrame').locator('[data-site-create]');
 if(scenario==='readonly'){await page.evaluate(()=>{const c=V1CloneRuntime.context,old=c.getState;c.getState=()=>({...old(),readOnly:true});document.querySelector('#toolFrame').contentDocument.querySelector('[data-site-create]').onclick();});assert.equal(await page.getByRole('dialog').count(),0);}
 else {await action.click();await page.getByRole('dialog').waitFor();await page.evaluate(s=>{if(s==='disposed')document.querySelector('#toolFrame').remove();else if(s==='generation')V1CloneRuntime.context.setMode('standalone');else openTool('safety','project');},scenario);await page.getByRole('dialog').waitFor({state:'detached'});}
 assert.equal(page.posts.length,0);
});
test('inspection draft uses accessible dialog, validates, traps focus, cancels without writes and preserves payload',async t=>{
 const {page}=await fixture(t,'owner');await page.waitForSelector('#toolFrame[data-v1-clone-adapter="ready"]');
 const action=page.frameLocator('#toolFrame').locator('[data-site-create]');
 await action.click();const dialog=page.getByRole('dialog',{name:'Draft internal inspection'});await dialog.waitFor();
 const input=dialog.getByLabel('Inspection finding',{exact:true});assert.equal(await input.evaluate(e=>e===document.activeElement),true);
 await dialog.getByRole('button',{name:'Continue',exact:true}).click();await dialog.getByRole('alert').filter({hasText:'Enter an inspection finding.'}).waitFor();
 await dialog.getByRole('button',{name:'Continue',exact:true}).focus();await page.keyboard.press('Tab');assert.equal(await input.evaluate(e=>e===document.activeElement),true);
 await page.keyboard.press('Escape');await dialog.waitFor({state:'detached'});assert.equal(page.posts.length,0);assert.equal(await action.evaluate(e=>e===document.activeElement),true);
 await action.click();await input.fill('  Check anchor bolts  ');await dialog.getByRole('button',{name:'Continue',exact:true}).click();
 const confirm=page.getByRole('dialog',{name:'Save draft inspection'});await confirm.waitFor();await confirm.getByRole('button',{name:'Cancel',exact:true}).click();assert.equal(page.posts.length,0);
 await action.click();await input.fill('  Check anchor bolts  ');await dialog.getByRole('button',{name:'Continue',exact:true}).click();await confirm.getByRole('button',{name:'Save draft',exact:true}).click();
 await page.waitForFunction(()=>!document.querySelector('dialog[open]'));await page.waitForTimeout(250);
 assert.equal(page.posts.length,1);assert.deepEqual(page.posts[0],{inspectionType:'quality',findings:['Check anchor bolts'],checklist:[],followUps:[],signOffRequired:true});
});
