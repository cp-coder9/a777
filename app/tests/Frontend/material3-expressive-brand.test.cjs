'use strict';
// Real shell/assets; API responses below are explicitly synthetic transport fixtures.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),crypto=require('node:crypto');
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
const root=path.resolve(__dirname,'../../..'),publicRoot=path.join(root,'app/public');
const baseline=cp.execFileSync('git',['show','HEAD:app/views/v1-clone.php'],{cwd:root,encoding:'utf8'}).match(/data:image\/png;base64,[^"']+/)[0];
async function fixture(t,options={}){
 const browser=await chromium.launch({headless:true,channel:'msedge'});t.after(()=>browser.close());
 const page=await browser.newPage({viewport:options.viewport||{width:1600,height:1000},reducedMotion:options.reducedMotion||'no-preference'});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const tools=JSON.parse(cp.execFileSync('php',['-r','$root=$argv[1];spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,"Architex\\\\"))require $root."/app/src/".str_replace("\\\\","/",substr($class,strlen("Architex\\\\"))).".php";});print(json_encode(Architex\\Platform\\Toolbox\\ProductionToolRegistry::workspaceCatalogue("owner","architect")));',root],{encoding:'utf8'}));
 await page.route('http://expressive-fixture.test/**',async route=>{
  const p=new URL(route.request().url()).pathname;
  if(p.startsWith('/assets/')){const file=path.resolve(publicRoot,'.'+p);return route.fulfill({status:fs.existsSync(file)?200:404,body:fs.existsSync(file)?fs.readFileSync(file):'',contentType:p.endsWith('.css')?'text/css':p.endsWith('.js')?'application/javascript':'application/octet-stream'});}
  if(p.startsWith('/api/v1/')){let data=[];if(p==='/api/v1/session')data={userId:'d'.repeat(32),email:'synthetic-foundation@example.test',csrfToken:'test-only',memberships:[{organisationId:'b'.repeat(32),role:'owner',profileRole:'architect'}]};if(p==='/api/v1/projects')data=[{id:'a'.repeat(32),name:'Synthetic foundation fixture',organisationId:'b'.repeat(32),phase:'inception'}];if(p==='/api/v1/tools')data=tools;return route.fulfill({contentType:'application/json',body:JSON.stringify({data,meta:{},error:null})});}
  return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(root,'app/views/v1-clone.php'),'utf8')});
 });
 await page.goto('http://expressive-fixture.test/v1-candidate.php');await page.waitForSelector('body[data-v1-clone-state="ready"]',{timeout:120000});return {page,errors};
}
module.exports={fixture};
if(require.main===module)test('computed semantic aliases preserve the exact brand, font, PNG and final cascade',async t=>{
 const {page,errors}=await fixture(t);
 const palette={primary:'#19B7B0',deep:'#167E79',jade:'#58C8BC',mint:'#BFE9E2',aqua:'#DFF5F2',ink:'#102033',muted:'#657287',cobalt:'#2563EB',lavender:'#8B5CF6',coral:'#FF6B6B',amber:'#FFB020',success:'#28a86b',error:'#d95747'};
 for(const [key,value]of Object.entries(palette))assert.equal(await page.evaluate(k=>getComputedStyle(document.documentElement).getPropertyValue('--md-'+k).trim(),key),value);
 assert.match(await page.locator('body').evaluate(e=>getComputedStyle(e).fontFamily),/^Inter, ui-sans-serif, system-ui/);
 assert.equal(crypto.createHash('sha256').update(await page.locator('.logo-btn img').getAttribute('src')).digest('hex'),crypto.createHash('sha256').update(baseline).digest('hex'));
 assert.deepEqual(await page.locator('link[rel="stylesheet"]').evaluateAll(links=>links.slice(-5).map(e=>e.getAttribute('href'))),['material3-expressive','material3-project-workspaces','material3-technical-workspaces','material3-commercial-workspaces','material3-role-dashboards'].map(name=>`/assets/v1-clone/css/${name}.css`));assert.deepEqual(errors,[]);
});
