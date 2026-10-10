'use strict';
// Production entry and original sources; intercepted transport is MOCK evidence, not native backend proof.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),cp=require('node:child_process');
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
const root=path.resolve(__dirname,'../../..'),publicRoot=path.join(root,'app/public');
const runtime=fs.readFileSync(path.join(publicRoot,'assets/v1-clone/js/runtime.js'),'utf8');
const wingman=fs.readFileSync(path.join(publicRoot,'assets/v1-clone/js/modules/wingman.js'),'utf8');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function wingmanController(){
 const adapter={scopeKey:()=> 'scope',bootstrap:async()=>({conversations:[],availability:{configured:false}}),dispose(){}};
 const window={V1CloneWingmanAdapter:{create:()=>adapter}};
 vm.runInNewContext(wingman,{window});
 return window.V1CloneWingman.install({context:{getState:()=>({}),subscribe:()=>()=>{}}});
}
function view(){return {scope(){},restore(){},threads(){},availability(){},notice(){},draft:()=>({})};}
test('runtime routes synchronous native mount exceptions to terminal error, not stranded loading',async()=>{
 const notices=[],window={__V1_CLONE_NO_AUTOSTART__:true,adaptFrame:()=> 'original',decode64:x=>x,renderAll(){},showToast:x=>notices.push(x)};
 const document={querySelector:()=>null,querySelectorAll:()=>[],activeElement:null};
 for(const name of new Set(runtime.match(/V1Clone[A-Za-z]+(?=\.install\()/g)))window[name]={install:()=>({reconcile(){},supports:()=>false})};
 window.V1CloneWingman={install:()=>({supports:id=>id==='wingman',mountFrame(){throw Error('native synchronous mount failure');}})};
 vm.runInNewContext(runtime,{window,document,URLSearchParams,state:{mode:'project'}});
 await window.V1CloneRuntime.installProduction({getState:()=>({projects:[],mode:'project'}),read:async()=>[],subscribe(){}});
 const frame={isConnected:true,dataset:{}};
 // The original adapter must still return normally; its promise chain owns mount errors.
 assert.equal(window.adaptFrame('wingman',frame),'original');
 await flush();assert.equal(frame.dataset.v1CloneAdapter,'error');assert.deepEqual(notices,['native synchronous mount failure']);
});
test('Wingman waits for this document parser without attempting eval',async()=>{
 const controller=wingmanController();let ready;
 const document={readyState:'loading',addEventListener:(name,callback)=>{assert.equal(name,'DOMContentLoaded');ready=callback;}};
 const frame={contentDocument:document,isConnected:true,contentWindow:{eval(){throw Error('unsafe-eval forbidden');}}};
 const pending=controller.mountFrame('wingman',frame);
 assert.equal(typeof ready,'function');
 frame.contentWindow.__v1Wingman=view();document.readyState='interactive';ready();
 const mount=await pending;assert.equal(typeof mount.dispose,'function');mount.dispose();controller.dispose();
});
test('Wingman rejects missing bootstrap after parser readiness',async()=>{
 const controller=wingmanController();
 await assert.rejects(async()=>controller.mountFrame('wingman',{isConnected:true,contentDocument:{readyState:'complete'},contentWindow:{eval(){throw Error('unsafe-eval forbidden');}}}),/Wingman frame was not prepared before srcdoc/);
 controller.dispose();
});
for(const reason of ['detached','replaced','disposed'])test('Wingman does not mount '+reason+' document after parser wait',async()=>{
 const controller=wingmanController();let ready;
 const document={readyState:'loading',addEventListener:(name,callback)=>{ready=callback;}};
 const frame={isConnected:true,contentDocument:document,contentWindow:{__v1Wingman:view()}};
 const pending=controller.mountFrame('wingman',frame);
 if(reason==='detached')frame.isConnected=false;if(reason==='replaced')frame.contentDocument={};if(reason==='disposed')controller.dispose();
 assert.equal(typeof ready,'function','parser wait must be registered');ready();assert.equal(await pending,false);controller.dispose();
});
let browser;
test.before(async()=>{browser=await chromium.launch({headless:true,channel:'msedge'});});
test.after(async()=>{await browser?.close();});
const project='a'.repeat(32),organisation='b'.repeat(32);
function metadata(){return JSON.parse(cp.execFileSync('php',['-r','$root=$argv[1];spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,"Architex\\\\"))require $root."/app/src/".str_replace("\\\\","/",substr($class,strlen("Architex\\\\"))).".php";});print(json_encode(Architex\\Platform\\Toolbox\\ProductionToolRegistry::workspaceCatalogue("owner","architect")));',root],{encoding:'utf8'}));}
for(const entry of ['direct','tool'])test('MOCK transport: real Wingman '+entry+' entry waits for delayed parser under strict CSP',async t=>{
 const page=await browser.newPage();t.after(()=>page.close());page.setDefaultTimeout(15000);
 const errors=[],violations=[],reads=[],tools=metadata();
 page.on('pageerror',e=>errors.push(e.message));
 await page.exposeFunction('reportCsp',v=>violations.push(v));
 await page.addInitScript(()=>{
  document.addEventListener('securitypolicyviolation',e=>window.reportCsp({directive:e.violatedDirective,blocked:e.blockedURI}));
 });
 await page.route('http://wingman-fixture.test/**',async route=>{
  const request=route.request(),p=new URL(request.url()).pathname;
  if(p.endsWith('/material3-embedded-workspaces.css'))await new Promise(resolve=>setTimeout(resolve,900));
  if(p.startsWith('/api/v1/')){
   assert.equal(request.method(),'GET','read-only fixture must not write');reads.push(p);let data=[];
   if(p==='/api/v1/session')data={userId:'d'.repeat(32),csrfToken:'mock-only',memberships:[{organisationId:organisation,role:'owner',profileRole:'architect'}]};
   if(p==='/api/v1/projects')data=[{id:project,name:'MOCK project',organisationId:organisation,phase:'inception'}];
   if(p==='/api/v1/tools')data=tools;
   if(p==='/api/v1/wingman/tutorial')data={progress:{status:'completed'},steps:[]};
   if(p==='/api/v1/wingman/bootstrap')data={context:{projectId:new URL(request.url()).searchParams.get('projectId')},conversations:[],availability:{configured:false}};
   return route.fulfill({contentType:'application/json',body:JSON.stringify({data,meta:{},error:null})});
  }
  if(p==='/v1-candidate.php')return route.fulfill({contentType:'text/html',headers:{'Content-Security-Policy':"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'"},body:fs.readFileSync(path.join(root,'app/views/v1-clone.php'),'utf8')});
  if(p.endsWith('/modules/wingman.js'))return route.fulfill({contentType:'application/javascript',body:wingman+`;(() => { const install=V1CloneWingman.install; V1CloneWingman.install=function(options){const controller=install(options),mount=controller.mountFrame;controller.mountFrame=function(id,frame){window.__wingmanMountEntry={state:frame.contentDocument?.readyState,bootstrap:!!frame.contentWindow?.__v1Wingman};return mount.call(this,id,frame);};return controller;};})();`});
  const file=path.resolve(publicRoot,'.'+p);if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file))return route.fulfill({status:404,body:''});
  return route.fulfill({body:fs.readFileSync(file),contentType:p.endsWith('.js')?'application/javascript':p.endsWith('.css')?'text/css':'application/octet-stream'});
 });
 await page.goto('http://wingman-fixture.test/v1-candidate.php'+(entry==='direct'?'?workspace=wingman&orientation=project&project='+project:''));
 await page.waitForSelector('body[data-v1-clone-state="ready"]');
 if(entry==='tool')await page.evaluate(()=>openTool('wingman','project'));
 await page.waitForFunction(()=>['ready','error'].includes(document.querySelector('#toolFrame')?.dataset.v1CloneAdapter));
 assert.equal(await page.locator('#toolFrame').getAttribute('data-v1-clone-adapter'),'ready');
 await page.frameLocator('#toolFrame').locator('#v1-wingman-status').filter({hasText:'provider configuration required'}).waitFor();
 assert.equal(await page.locator('#toolFrame').evaluate(f=>Boolean(f.contentWindow.__v1Wingman&&f.contentWindow.__v1WingmanCommand)),true);
 assert.equal(await page.frameLocator('#toolFrame').locator('.input-area button').isDisabled(),true);
 assert.deepEqual(await page.evaluate(()=>window.__wingmanMountEntry),{state:'loading',bootstrap:false},'body poll reaches native mount before prepared tail bootstrap');
 assert.ok(reads.includes('/api/v1/wingman/bootstrap'));assert.deepEqual(errors,[]);assert.deepEqual(violations,[]);
});
