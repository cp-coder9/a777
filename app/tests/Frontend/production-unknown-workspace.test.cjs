'use strict';
// Full-shell browser regression with explicitly test-only API transport fixtures.
// This proves renderer/navigation integration, not database or external provider success.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
const root=path.resolve(__dirname,'../../..'),publicRoot=path.join(root,'app/public');
const project='a'.repeat(32),organisation='b'.repeat(32),verification='c'.repeat(32);
let browser;
test.before(async()=>{browser=await chromium.launch({headless:true,channel:'msedge'});});
test.after(async()=>{await browser?.close();});
function metadata(role){return JSON.parse(cp.execFileSync('php',['-r','$root=$argv[1];spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,"Architex\\\\"))require $root."/app/src/".str_replace("\\\\","/",substr($class,strlen("Architex\\\\"))).".php";});print(json_encode(Architex\\Platform\\Toolbox\\ProductionToolRegistry::workspaceCatalogue($argv[2],"architect")));',root,role],{encoding:'utf8'}));}
async function fixture(t,role='owner',query=''){
  const page=await browser.newPage();t.after(()=>page.close());page.setDefaultTimeout(15000);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));const tools=metadata(role);
  await page.route('http://role-fixture.test/**',async route=>{
    const url=new URL(route.request().url()),p=url.pathname;
    if(p.startsWith('/assets/')){
      const file=path.resolve(publicRoot,'.'+p);if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file))return route.fulfill({status:404,body:''});
      return route.fulfill({status:200,body:fs.readFileSync(file),contentType:p.endsWith('.js')?'application/javascript':p.endsWith('.css')?'text/css':'application/octet-stream'});
    }
    if(p.startsWith('/api/v1/')){
      let data=[];
      if(p==='/api/v1/session')data={userId:'d'.repeat(32),email:'synthetic-navigation@example.test',csrfToken:'test-only',memberships:[{organisationId:organisation,role,profileRole:'architect'}]};
      if(p==='/api/v1/projects')data=[{id:project,name:'Synthetic navigation fixture',organisationId:organisation,phase:'inception'}];
      if(p==='/api/v1/tools')data=tools;
      if(p==='/api/v1/verification-review-queue')data=[{id:verification,subject_user_id:'e'.repeat(32),subject_email:'synthetic-applicant@example.test',kind:'professional_registration',grade:'self_declared'}];
      return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({data,meta:{},error:null})});
    }
    const html=fs.readFileSync(path.join(root,'app/views/v1-clone.php'),'utf8');return route.fulfill({status:200,contentType:'text/html',body:html});
  });
  await page.goto('http://role-fixture.test/v1-candidate.php'+query);await page.waitForFunction(()=>['ready','error'].includes(document.body.dataset.v1CloneState));assert.equal(await page.locator('body').getAttribute('data-v1-clone-state'),'ready',await page.locator('body').innerText());return {page,errors};
}
test('production-only workspace does not crash assembled production renderer',async t=>{
 const {page,errors}=await fixture(t,'owner','?workspace=drawing_compliance_navigator&project='+project);
 assert.match(await page.locator('#main').innerText(),/not supported/i);
 assert.equal(await page.locator('#main a').getAttribute('href'),'/toolbox/standalone/sa-council-drawing-compliance-navigator');
 await page.evaluate(()=>{renderTopbar();reloadTool();renderAll();});
 await page.evaluate(()=>openTool('valuations','project'));
 assert.match(await page.locator('#main').innerText(),/not supported/i);
 assert.equal(await page.locator('#main a').getAttribute('href'),'/app');
 await page.evaluate(()=>openGlobal('tools'));
 assert.match(await page.locator('#main').innerText(),/registry/i);
 assert.deepEqual(errors,[]);
});
test('unknown hostile URL is text only and navigation survives',async t=>{
 const id='<img src=x onerror="window.injected=true">';
 const {page,errors}=await fixture(t,'owner','?workspace='+encodeURIComponent(id));
 assert.match(await page.locator('#main').innerText(),/not supported/i);
 assert.equal(await page.locator('#main img,#main a,#toolFrame').count(),0);
 assert.equal(await page.evaluate(()=>window.injected),undefined);
 await page.evaluate(()=>{renderTopbar();setToolTab(1);reloadTool();renderAll();});
 await page.evaluate(()=>openGlobal('tools'));assert.deepEqual(errors,[]);
});
test('viewer unknown legacy ID retains denial without routes',async t=>{
 const {page,errors}=await fixture(t,'viewer','?workspace=drawing_compliance_navigator&project='+project);
 assert.match(await page.locator('#main').innerText(),/unavailable with your current membership/i);
 assert.equal(await page.locator('#main a,#toolFrame').count(),0);
 await page.evaluate(()=>openTool('valuations','project'));
 assert.match(await page.locator('#main').innerText(),/unavailable with your current membership/i);
 assert.equal(await page.locator('#main a,#toolFrame').count(),0);
 await page.evaluate(()=>openGlobal('tools'));assert.deepEqual(errors,[]);
});
test('known engineering entry still mounts original BoQ frame',async t=>{
 const {page,errors}=await fixture(t,'owner','?workspace=bom&project='+project);
 await page.frameLocator('#toolFrame').locator('[data-boq-form]').waitFor();
 assert.equal(await page.locator('[data-v1-unsupported-workspace]').count(),0);
 assert.deepEqual(errors,[]);
});
