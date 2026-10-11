'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
const assets=path.resolve(__dirname,'../../public/assets/v1-clone');
const source=n=>fs.readFileSync(path.join(assets,'js/modules',n+'.js'),'utf8');
let browser;
test.before(async()=>{browser=await chromium.launch({headless:true,channel:'msedge'});});
test.after(async()=>{await browser?.close();});
async function pageFor(t,width=390){const p=await browser.newPage({viewport:{width,height:900}});p.setDefaultTimeout(6000);t.after(()=>p.close());await p.setContent('<main id="main"></main>');const css=path.join(assets,'css/material3-technical-workspaces.css');if(fs.existsSync(css))await p.addStyleTag({path:css});return p;}
async function calc(t,width){const p=await pageFor(t,width);await p.evaluate(()=>{window.calls=[];window.historyRows=[];const session={};window.context={getState:()=>({ready:true,session,organisationId:'b'.repeat(32),selectedProjectId:'a'.repeat(32)}),subscribe:()=>()=>{},write:async(url,body)=>{calls.push(body);if(window.fail)throw Error('Service unavailable');const record={id:'1',toolId:body.toolId,assignment:body.assignment,status:'completed',output:{advisoryOnly:true,flowM3s:0.1}};historyRows.push(record);return record;},read:async()=>historyRows};});await p.addScriptTag({path:path.join(assets,'js/adapters/site-tools.js')});await p.addScriptTag({content:source('calc-workspace')});await p.evaluate(async()=>{window.workspace=V1CloneCalcWorkspace.install({context});await workspace.apply('refuse_calculator');});return p;}
for(const width of [390,1440])test(`calculator units, keyboard action, advisory output and responsive fields at ${width}px`,async t=>{
 const p=await calc(t,width);await p.getByRole('button',{name:'Rational Method stormwater',exact:true}).click();const panel=p.locator('[data-calc-detail]');
 assert.equal(await panel.locator('[data-calc-run]').getAttribute('data-technical-primary'),'');
 await p.getByLabel('Rainfall intensity (mm/h)',{exact:true}).fill('95');await p.getByLabel('Catchment area (ha)',{exact:true}).fill('0.7');
 await panel.getByRole('button',{name:'Run calculation'}).focus();await p.keyboard.press('Enter');await p.waitForFunction(()=>calls.length===1);
 assert.equal((await p.evaluate(()=>calls))[0].input.rainfallIntensityMmPerHour,95);assert.match(await panel.locator('[data-calc-result]').innerText(),/advisoryOnly/);
 assert.equal(await panel.locator('[data-calc-result]').getAttribute('role'),'status');
 const metrics=await p.getByLabel('Rainfall intensity (mm/h)',{exact:true}).evaluate(e=>({width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height,right:e.getBoundingClientRect().right}));assert.ok(metrics.height>=44);assert.ok(metrics.right<=width);
 await p.evaluate(()=>window.fail=true);await panel.getByRole('button',{name:'Run calculation'}).click();await p.getByRole('status').filter({hasText:'No success claimed'}).waitFor();assert.equal(await panel.getByRole('button',{name:'Run calculation'}).isEnabled(),true);
});
async function permits(t,data){const p=await pageFor(t);await p.setContent('<main id="main"><section id="tab-permits"></section></main>');const css=path.join(assets,'css/material3-technical-workspaces.css');if(fs.existsSync(css))await p.addStyleTag({path:css});await p.evaluate(data=>{const session={};window.context={getState:()=>({ready:true,session,organisationId:'b'.repeat(32),selectedProjectId:'a'.repeat(32)}),read:async()=>data,subscribe:()=>()=>{}};},data);await p.addScriptTag({content:source('work-permits')});await p.evaluate(()=>V1CloneWorkPermits.mount({context,doc:document}));return p;}
test('internal permit decision fields have durable labels, evidence disclosure and keyboard order',async t=>{
 const p=await permits(t,{canDraft:false,permits:[{id:'c'.repeat(32),reference:'PTW-1',version:2,status:'requested',payload:{title:'Isolation',hazards:'Stored energy',controls:'Lock out'},allowedTransitions:['reviewed']}]});await p.getByText('PTW-1 — Isolation').waitFor();
 await p.getByLabel('Decision reason',{exact:true}).fill('Evidence checked');await p.getByLabel('Review / authorization / closure responsibility statement',{exact:true}).fill('Independent reviewer');await p.getByLabel('Decision evidence project file IDs, comma-separated',{exact:true}).fill('d'.repeat(32));await p.getByLabel('Internal decision',{exact:true}).selectOption('reviewed');
 await p.getByText('Evidence, decisions and version history',{exact:true}).focus();await p.keyboard.press('Enter');assert.equal(await p.locator('details').getAttribute('open'),'');assert.match(await p.locator('pre').innerText(),/Stored energy/);
 const order=['Internal decision','Decision reason','Review / authorization / closure responsibility statement','Decision evidence project file IDs, comma-separated','I personally confirm this internal decision'];
 for(const name of order){await p.keyboard.press('Tab');assert.equal(await p.getByLabel(name,{exact:true}).evaluate(e=>e===document.activeElement),true,`Tab focuses ${name}`);}
 await p.keyboard.press('Tab');assert.equal(await p.getByRole('button',{name:'Record authorised action',exact:true}).evaluate(e=>e===document.activeElement),true);
});
test('viewer sees internal empty register, unavailable draft and honest external approval boundary',async t=>{const p=await permits(t,{canDraft:false,permits:[]});await p.getByText('No internal work permits returned for this project.').waitFor();assert.equal(await p.locator('form.work-permit-form').isVisible(),false);assert.match(await p.locator('#tab-permits').innerText(),/No statutory permit.*OHS compliance is certified/);});
