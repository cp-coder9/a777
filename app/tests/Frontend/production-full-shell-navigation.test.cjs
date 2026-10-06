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
  await page.goto('http://role-fixture.test/v1-candidate.php'+query);await page.waitForSelector('body[data-v1-clone-state="ready"]');return {page,errors};
}
test('original sidebar section tabs retain the native administrative queue',async t=>{
  const {page,errors}=await fixture(t,'owner','?workspace=admin_review&orientation=standalone');
  await page.waitForSelector('[data-gov-tool="Admin Review"] [data-review-select]');
  await page.locator('#navigator button[onclick="setToolTab(1)"]').click();
  await page.waitForSelector('[data-gov-tool="Admin Review"] [data-review-select]');
  assert.equal(await page.locator('#main .scaffold-shell').count(),0);assert.equal(await page.locator('#inspectBody [data-workspace-context]').count(),1);assert.deepEqual(errors,[]);
});
test('viewer cannot open administrative review or advisory run writing; explorer keeps project attachment',async t=>{
  const {page,errors}=await fixture(t,'viewer');
  assert.equal(await page.evaluate(()=>openTool('admin_review','project')),false);
  assert.equal(await page.evaluate(()=>openTool('bom','project')),false);
  await page.evaluate(()=>openTool('project_explorer','standalone'));
  await page.waitForSelector('[data-v1-project-required-tool="project_explorer"]');
  assert.equal(await page.locator('#main').getByRole('button',{name:'Attach project'}).isEnabled(),true);assert.deepEqual(errors,[]);
});
test('an authorised incoming BoQ link mounts native inputs within the original frame',async t=>{
  const {page,errors}=await fixture(t,'member','?workspace=bom&orientation=project&project='+project);
  await page.waitForSelector('#toolFrame');await page.frameLocator('#toolFrame').locator('[data-boq-form]').waitFor();
  assert.equal(await page.frameLocator('#toolFrame').locator('[data-boq-form] input[name="title"]').inputValue(),'');assert.deepEqual(errors,[]);
});
