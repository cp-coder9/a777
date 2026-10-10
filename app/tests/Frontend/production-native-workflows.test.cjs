'use strict';
// Local test-only transport fixtures; no live provider or external success is asserted.
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const base=path.resolve(__dirname,'../../public/assets/v1-clone/js');
const source=f=>fs.readFileSync(path.join(base,f),'utf8');
const id='a'.repeat(32),project='b'.repeat(32),org='c'.repeat(32);
function fixture(role='admin'){
  const state={ready:true,session:{userId:'reviewer',memberships:[{organisationId:org,role,profileRole:'admin'}]},organisationId:org,selectedProjectId:project,projects:[]};
  const calls=[];let queue=[{id,subject_user_id:'applicant',grade:'document_uploaded'}];
  const context={getState:()=>state,read:async p=>p==='/verification-review-queue'?queue:[],write:async(p,b,o)=>{calls.push({p,b,o});queue=[];return {id,grade:b.decision};}};
  const window={};vm.runInNewContext(source('adapters/governance.js'),{window});
  return {adapter:window.V1CloneGovernanceAdapter.create(context),context,state,calls,setQueue:q=>queue=q};
}
test('review requires actual membership, confirmation and reason',async()=>{
  const f=fixture('member');await assert.rejects(f.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:'Evidence invalid'}),/membership|owner|admin/i);assert.equal(f.calls.length,0);
  const a=fixture();await assert.rejects(a.adapter.reviewVerification(id,'rejected',{note:'Evidence invalid'}),/confirm/i);
  await assert.rejects(a.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:' '}),/reason/i);
  await assert.rejects(a.adapter.reviewVerification(id,'externally_verified',{humanConfirmed:true,note:'Checked',provider:' ',providerReference:' '}),/provider/i);
});
test('review uses exact queue disappearance contract without a project',async()=>{
  const f=fixture();f.state.selectedProjectId=null;
  const saved=await f.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:'Evidence invalid'});
  assert.equal(saved.grade,'rejected');assert.equal(f.calls[0].o.projectScoped,false);assert.equal(f.calls[0].o.readBackAbsentId,id);assert.equal(f.calls[0].o.readBackPath,'/verification-review-queue');
});
test('review refuses retained queue records and wrong decision receipts',async()=>{
  const f=fixture();f.context.write=async()=>({id,grade:'rejected'});
  await assert.rejects(f.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:'Checked'}),/readback|verified|still/i);
  f.context.write=async()=>{f.setQueue([]);return {id,grade:'manually_reviewed'};};
  await assert.rejects(f.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:'Checked'}),/readback|decision|verified/i);
});
test('BoQ rejects blank invented defaults before writing',async()=>{
  const f=fixture();const window={};vm.runInNewContext(source('adapters/document-tools.js'),{window});
  const a=window.V1CloneDocumentToolsAdapter.create(f.context);
  await assert.rejects(a.saveBoq({items:[{description:'',quantity:'',rate:'',unit:''}]}),/required|invalid|enter/i);assert.equal(f.calls.length,0);
});
test('review fails closed for malformed queue, failed own readback and read-only membership',async()=>{
  const f=fixture();f.setQueue({items:[]});await assert.rejects(f.adapter.reviewQueue(),/Invalid/);
  f.setQueue([{id}]);const read=f.context.read;f.context.read=async p=>{if(p==='/verifications')throw Error('Own status unavailable');return read(p);};
  await assert.rejects(f.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:'Checked'}),/Own status unavailable/);
  const ro=fixture();ro.state.readOnly=true;await assert.rejects(ro.adapter.reviewVerification(id,'rejected',{humanConfirmed:true,note:'Checked'}),/Read-only/);assert.equal(ro.calls.length,0);
});
test('BoQ readback requires exact completed project run and rejects read-only writes',async()=>{
  const f=fixture(),window={};vm.runInNewContext(source('adapters/document-tools.js'),{window});const a=window.V1CloneDocumentToolsAdapter.create(f.context);
  const input={title:'Measured',currency:'ZAR',contingencyPercent:'0',items:[{description:'Wall',quantity:'2',rate:'0',unit:'m2'}]};
  f.context.read=async()=>[{id,toolId:'boq_takeoff',assignment:{projectId:'wrong'},status:'completed'}];
  await assert.rejects(a.saveBoq(input),/not verified/);f.state.readOnly=true;
  await assert.rejects(a.saveBoq(input),/Read-only/);assert.equal(f.calls.length,1);
});
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
let browser;
test.before(async()=>{browser=await chromium.launch({headless:true,channel:'msedge'});});
test.after(async()=>{await browser?.close();});
function original(id){const match=source('reference-001.js').match(/const SOURCES\s*=\s*(\{[^\n]+\});/);return Buffer.from(JSON.parse(match[1])[id],'base64').toString();}
async function pageFor(t,kind){
  const page=await browser.newPage();page.setDefaultTimeout(5000);t.after(()=>page.close());await page.route('**/*',r=>r.abort());
  await page.setContent('<main id="main"></main>');
  await page.evaluate(({project,org,id})=>{
    window.calls=[];window.records=[];window.queue=[{id,subject_user_id:'independent-applicant',subject_email:'test@example.invalid',kind:'professional_registration',grade:'document_uploaded',registration_body:'ECSA',registration_number:'TEST-123',evidence_file_id:'e'.repeat(32)}];
    const state={ready:true,session:{userId:'reviewer',memberships:[{organisationId:org,role:'admin'}]},organisationId:org,selectedProjectId:project,projects:[{id:project,name:'Test project'}]};
    window.context={getState:()=>state,capabilities:{readBackCollection:true},subscribe:()=>()=>{},read:async p=>{
      if(p==='/projects/'+project+'/work-permits')return {permits:[],canDraft:false};if(p.includes('toolbox'))return window.records;if(p.includes('site-execution'))return {inspections:window.records,ncrs:[],evidence:[]};if(p.includes('trust-compliance'))return {complianceItems:[]};if(p==='/verification-review-queue')return window.queue;if(p==='/verifications')return [];throw Error('Unexpected read '+p);
    },write:async(p,b,o)=>{window.calls.push({p,b,o});const r=p.includes('/review')?{id,grade:b.decision}:p.includes('toolbox')?{id,...b,status:'completed'}:{id,...b,status:'draft',projectId:project};window.records.push(r);window.queue=[];return r;}};
    window.confirm=()=>true;
  },{project,org,id});
  const group=kind==='bom'?'document-tools':kind==='safety'?'site-tools':'governance';
  for(const f of ['adapters/'+group+'.js','modules/'+group+'.js'])await page.addScriptTag({content:source(f)});
  // about:blank has no asset origin and all network is intentionally blocked.
  // Load the real native dependency, not a stand-in or a bypass of mountFrame.
  if(kind==='safety'){await page.addScriptTag({content:source('modules/work-permits.js')});await page.addScriptTag({content:source('modules/m3-dialog.js')});await page.addStyleTag({content:fs.readFileSync(path.join(base,'../css/material3-expressive.css'),'utf8')});}
  await page.evaluate(async({kind,html})=>{
    const api=kind==='bom'?V1CloneDocumentTools:kind==='safety'?V1CloneSiteTools:V1CloneGovernance;window.module=api.install({context});
    if(kind==='admin_review'){await window.module.apply(kind);return;}
    const frame=document.createElement('iframe');document.body.append(frame);const loaded=new Promise(r=>frame.onload=r);frame.srcdoc=window.module.prepareSource(kind,html);await loaded;frame.contentWindow.confirm=()=>true;frame.contentWindow.prompt=()=> 'Observed unguarded opening';await window.module.mountFrame(kind,frame);
  },{kind,html:kind==='admin_review'?'':original(kind)});return page;
}
test('original BoQ frame edits explicit lines, saves and renders real history',async t=>{
  const page=await pageFor(t,'bom'),f=page.frameLocator('iframe');
  assert.equal(await f.locator('[data-boq-form]').count(),1);
  for(const [name,value] of Object.entries({title:'Measured takeoff',currency:'ZAR',contingencyPercent:'0',description:'Measured wall',quantity:'2',unit:'m2',rate:'30'}))await f.locator(`[name="${name}"]`).fill(value);
  await f.locator('[data-boq-form] button[type=submit]').click();await page.waitForFunction(()=>window.calls.length===1);
  await f.locator('[data-boq-history]').getByText('Measured wall',{exact:false}).waitFor();
  const call=(await page.evaluate(()=>calls))[0];assert.equal(call.b.issueImmediately,false);assert.equal(call.b.input.items[0].rate,30);
});
test('original safety frame loads the native internal permit register without claiming external approval',async t=>{
  const page=await pageFor(t,'safety'),permits=page.frameLocator('iframe').locator('#tab-permits');
  await permits.getByRole('status',{includeHidden:true}).filter({hasText:'Persisted internal work-permit register'}).waitFor({state:'attached'});
  assert.match(await permits.innerText(),/No internal work permits returned for this project/);
  assert.match(await permits.innerText(),/No statutory permit.*OHS compliance is certified/);
  assert.equal(await permits.locator('form.work-permit-form').evaluate(form=>form.hidden),true);
  assert.equal(await page.evaluate(()=>calls.length),0);
});
test('original safety frame drafts safety inspection and keeps external permits separate',async t=>{
  const page=await pageFor(t,'safety'),f=page.frameLocator('iframe');assert.equal(await f.locator('[data-site-create]').count(),1);
  await f.locator('[data-site-create]').click();const draft=page.getByRole('dialog',{name:'Draft internal inspection'});await draft.getByLabel('Inspection finding',{exact:true}).fill('Observed unguarded opening');await draft.getByRole('button',{name:'Continue',exact:true}).click();await page.getByRole('dialog',{name:'Save draft inspection'}).getByRole('button',{name:'Save draft',exact:true}).click();await f.locator('[data-site-records="inspections"]').getByText('Observed unguarded opening').waitFor();
  const call=(await page.evaluate(()=>calls))[0];assert.equal(call.b.inspectionType,'safety');assert.deepEqual(call.b.checklist,[]);assert.equal(call.b.signOffRequired,true);
  assert.match(await f.locator('#tab-permits').innerText(),/Required external permits must be uploaded as project documents/);
});
test('same-scope inspection failure restores action and retains finding',async t=>{
 const page=await pageFor(t,'safety'),action=page.frameLocator('iframe').locator('[data-site-create]');await page.evaluate(()=>{context.write=async()=>{throw Error('Write unavailable');};});
 await action.click();await page.getByRole('dialog').getByLabel('Inspection finding',{exact:true}).fill('Retained finding');await page.getByRole('dialog').getByRole('button',{name:'Continue',exact:true}).click();await page.getByRole('dialog').getByRole('button',{name:'Save draft',exact:true}).click();
 await page.frameLocator('iframe').locator('[data-site-tools-status]').filter({hasText:'Write unavailable No success claimed. Finding retained: Retained finding'}).waitFor({state:'attached'});assert.equal(await action.isEnabled(),true);
});
for(const outcome of ['resolve','reject'])for(const lifecycle of ['scope','removed','replaced'])test('pending inspection '+outcome+' ignores stale '+lifecycle+' UI',async t=>{
 const page=await pageFor(t,'safety'),f=page.frameLocator('iframe');
 
 await page.evaluate(outcome=>{context.write=()=>new Promise((resolve,reject)=>{window.settleWrite=()=>outcome==='resolve'?resolve({id:'a'.repeat(32)}):reject(Error('Deferred failure'));});},outcome);
 await f.locator('[data-site-create]').click();await page.getByRole('dialog',{name:'Draft internal inspection'}).getByLabel('Inspection finding',{exact:true}).fill('Retained finding');await page.getByRole('dialog').getByRole('button',{name:'Continue',exact:true}).click();await page.getByRole('dialog',{name:'Save draft inspection'}).getByRole('button',{name:'Save draft',exact:true}).click();
 await page.waitForFunction(()=>!!window.settleWrite);
 const mutations=await page.evaluate(async lifecycle=>{
  const frame=document.querySelector('iframe'),doc=frame.contentDocument;let changes=0;const observer=new MutationObserver(records=>changes+=records.length);observer.observe(doc,{subtree:true,attributes:true,childList:true,characterData:true});
  if(lifecycle==='removed')frame.remove();else if(lifecycle==='replaced'){frame.srcdoc='<p>Replacement</p>';await new Promise(r=>frame.onload=r);}else context.getState().selectedProjectId='f'.repeat(32);
  settleWrite();await new Promise(r=>setTimeout(r,150));observer.disconnect();return changes;
 },lifecycle);assert.equal(mutations,0);
});
test('admin displays selected independent evidence and explicitly confirmed decision',async t=>{
  const page=await pageFor(t,'admin_review');assert.match(await page.locator('#main').innerText(),/independent-applicant/);
  await page.locator('[data-review-select]').click();assert.match(await page.locator('[data-review-evidence]').innerText(),/TEST-123/);
  await page.locator('[name=decision]').selectOption('rejected');await page.locator('[name=note]').fill('Evidence does not support registration');await page.locator('[name=humanConfirmed]').check();
  await page.locator('[data-review-form] button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('[data-gov-status]').textContent.includes('read back'));
  assert.equal((await page.evaluate(()=>calls))[0].o.readBackAbsentId,id);assert.equal(await page.locator('[data-review-select]').count(),0);
});
test('saved BoQ downloads the exact server advisory artifact without inventing a PDF',async t=>{
  const page=await pageFor(t,'bom'),f=page.frameLocator('iframe');
  await page.evaluate(()=>{const read=context.read;context.read=async p=>p.endsWith('/exports')?[{id:'f'.repeat(32),format:'json',mime_type:'application/json',filename:'boq_takeoff-'+'a'.repeat(32)+'.json',sha256:'d'.repeat(64)}]:read(p);context.download=async(p,options)=>{calls.push({p,options});return {blob:new Blob(['Synthetic advisory artifact only'],{type:options.mimeType}),...options};};});
  for(const [name,value] of Object.entries({title:'Measured export',currency:'ZAR',contingencyPercent:'0',description:'Measured line',quantity:'1',unit:'m2',rate:'0'}))await f.locator(`[name="${name}"]`).fill(value);
  await f.locator('[data-boq-form] button[type=submit]').click();await f.locator('[data-boq-history]').getByText('Measured line',{exact:false}).waitFor();
  const pending=page.waitForEvent('download',{timeout:20000});await f.getByRole('button',{name:'Download JSON',exact:true}).click();const download=await pending;
  assert.equal(download.suggestedFilename(),'boq_takeoff-'+id+'.json');const output=path.join(process.env.TMPDIR||'E:/Hermes/home/cache/scratch','a777-roles-advisory-download-test.json');await download.saveAs(output);assert.equal(fs.readFileSync(output,'utf8'),'Synthetic advisory artifact only');fs.unlinkSync(output);
  // PDF is a supported server artifact; absence from this response must refuse
  // retrieval rather than manufacture a browser-side PDF.
  const before=await page.evaluate(()=>calls.length);let pdfDownloads=0;
  page.on('download',()=>pdfDownloads++);
  await f.getByRole('button',{name:'Download PDF',exact:true}).click();
  await page.waitForFunction(()=>document.querySelector('iframe').contentDocument.documentElement.dataset.documentStatus==='Invalid advisory artifact metadata.');
  assert.equal(await page.evaluate(()=>calls.length),before);
  assert.equal(pdfDownloads,0);
});
test('native save cancellation sends nothing and BoQ starts with no synthetic input values',async t=>{
  const page=await pageFor(t,'bom'),f=page.frameLocator('iframe');
  assert.deepEqual(await f.locator('[data-boq-form] input').evaluateAll(inputs=>inputs.map(i=>i.value)),['','','','','','','']);
  await f.locator('[data-boq-add]').click();assert.equal(await f.locator('[data-boq-line]').count(),2);
  await f.locator('[data-boq-line]').last().getByRole('button',{name:'Remove line'}).click();
  for(const [name,value] of Object.entries({title:'Measured',currency:'ZAR',contingencyPercent:'0',description:'Wall',quantity:'2',unit:'m2',rate:'0'}))await f.locator(`[name="${name}"]`).fill(value);
  await page.evaluate(()=>document.querySelector('iframe').contentWindow.confirm=()=>false);
  await f.locator('[data-boq-form] button[type=submit]').click();assert.equal(await page.evaluate(()=>calls.length),0);
  const safety=await pageFor(t,'safety');await safety.evaluate(()=>document.querySelector('iframe').contentWindow.confirm=()=>false);
  await safety.frameLocator('iframe').locator('[data-site-create]').click();await safety.getByRole('dialog',{name:'Draft internal inspection'}).getByRole('button',{name:'Cancel',exact:true}).click();assert.equal(await safety.evaluate(()=>calls.length),0);
});
