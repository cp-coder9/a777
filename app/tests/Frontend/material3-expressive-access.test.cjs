'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const {chromium}=require('E:/a777/tools/v1-clone/node_modules/playwright');
const root=path.resolve(__dirname,'../../..'),pub=path.join(root,'app/public');
async function fixture(t,width=1280){
 const browser=await chromium.launch({headless:true,channel:'msedge'});t.after(()=>browser.close());
 const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});
 await page.route('http://access-fixture.test/**',route=>{const p=new URL(route.request().url()).pathname;const file=path.join(pub,p);return route.fulfill({body:fs.existsSync(file)&&fs.statSync(file).isFile()?fs.readFileSync(file):'',contentType:p.endsWith('.css')?'text/css':p.endsWith('.png')?'image/png':'text/html'});});
 await page.goto('http://access-fixture.test/');
 await page.setContent('<html data-access-state="public"><head></head><body><section id="publicAccess" class="public-access"><div id="publicAuthMount" class="public-auth-mount" hidden></div></section><div id="app" inert></div></body></html>');
 await page.addStyleTag({content:fs.readFileSync(path.join(pub,'assets/css/public-access.css'),'utf8')});
 // No auth transport is simulated or submitted. Notice discovery is unavailable in this isolated renderer.
 await page.evaluate(()=>{window.ArchitexApi={ApiClient:class{get(){return Promise.reject(new Error('Fixture: legal notice service not connected'));}}};});
 await page.addScriptTag({content:fs.readFileSync(path.join(pub,'assets/js/public-access.js'),'utf8')});
 await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('signin'));
 await page.waitForFunction(()=>document.querySelector('.is-auth-ready'));
 return page;
}
// Deferred transport is fixture-only: exercise the real renderer's async lifecycle, not a live API.
const registrationIdentity={email:'fixture@example.test',password:'Fixture-only-password-123',organisationName:'Fixture Practice',organisationSlug:'fixture-practice',fullName:'Fixture Architect',roleKey:'architect',profile:{sacap_number:'PrArch FIXTURE'}};
const noticeVersions=prefix=>Object.fromEntries(['terms','privacy','disclaimer','consent'].map(type=>[type,`${prefix}-${type}`]));
async function registrationTransport(page){
 await deferredNotices(page);
 await page.evaluate(()=>{window.registrationPosts=[];window.ArchitexApi.ApiClient.prototype.post=async function(url,payload){window.registrationPosts.push({url,payload});return {status:'pending_email_verification'};};});
}
async function fillRegistration(page,consent=true){
 for(const name of ['fullName','organisationName','organisationSlug','email','password'])await page.locator(`input[name="${name}"]`).fill(registrationIdentity[name]);
 await page.locator('select[name=roleKey]').selectOption('architect');
 await page.locator('input[name=profile_sacap_number]').fill('  PrArch FIXTURE  ');
 if(consent)await page.locator('input[name=acceptNotices]').check();
 assert.equal(await page.locator('#v8AuthenticationForm').evaluate(form=>form.checkValidity()),consent);
}
async function resolveVersions(page,index,versions){
 await page.evaluate(async ({index,versions})=>{window.noticeRequests[index].resolve(Object.entries(versions).map(([noticeType,version])=>({noticeType,version})));await new Promise(resolve=>setTimeout(resolve,100));},{index,versions});
}
test('valid pending registration submits cannot post or mutate any stale navigation destination',async t=>{
 const page=await fixture(t);page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await registrationTransport(page);
 for(const destination of ['landing','signin','register','workspace'])for(const outcome of ['resolve','reject']){
  await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));
  await page.waitForFunction(()=>document.querySelector('#publicAuthMount').inert===false);
  const start=await page.evaluate(()=>window.noticeRequests.length-1);
  await fillRegistration(page);
  await page.locator('button[type=submit]').click();
  await page.waitForFunction(()=>document.querySelector('button[type=submit]').disabled);
  assert.equal(await page.evaluate(()=>window.noticeRequests.length),start+2,'submit really awaits its own notice discovery');
  if(destination==='workspace')await page.evaluate(()=>window.ArchitexPublicAccess.showWorkspace());
  else if(destination==='signin')await page.getByRole('tab',{name:'Sign in',exact:true}).click();
  else {await page.getByRole('button',{name:'Back to landing'}).click();if(destination==='register'){await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));await page.waitForFunction(()=>document.querySelector('#publicAuthMount').inert===false);}}
  const before=await page.evaluate(()=>({html:document.querySelector('#publicAuthMount').innerHTML,state:document.documentElement.dataset.accessState,hidden:document.querySelector('#publicAuthMount').hidden,workspace:document.querySelector('#app').outerHTML}));
  await settleNotice(page,start,outcome);await settleNotice(page,start+1,outcome);
  assert.deepEqual(await page.evaluate(()=>({html:document.querySelector('#publicAuthMount').innerHTML,state:document.documentElement.dataset.accessState,hidden:document.querySelector('#publicAuthMount').hidden,workspace:document.querySelector('#app').outerHTML})),before,`${destination}/${outcome} unchanged`);
  assert.deepEqual(await page.evaluate(()=>window.registrationPosts),[]);
  assert.deepEqual(errors,[]);
 }
});
test('already-sent registration POST responses cannot reset stale forms or mutate navigation destinations',async t=>{
 const page=await fixture(t);page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await registrationTransport(page);
 await page.evaluate(()=>{window.registrationResponses=[];window.ArchitexApi.ApiClient.prototype.post=function(url,payload){window.registrationPosts.push({url,payload});return new Promise((resolve,reject)=>window.registrationResponses.push({resolve,reject}));};});
 for(const destination of ['landing','signin','register','workspace'])for(const outcome of ['resolve','reject']){
  await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));
  await page.waitForFunction(()=>document.querySelector('#publicAuthMount').inert===false);
  const noticeIndex=await page.evaluate(()=>window.noticeRequests.length-1);await resolveVersions(page,noticeIndex,noticeVersions('post-await'));
  await fillRegistration(page);
  await page.evaluate(()=>{window.staleRegistrationForm=document.querySelector('#v8AuthenticationForm');window.staleResetCount=0;window.staleRegistrationForm.addEventListener('reset',()=>window.staleResetCount++);window.registrationPosts=[];});
  await page.locator('button[type=submit]').click();await page.waitForFunction(()=>window.registrationPosts.length===1);
  assert.deepEqual(await page.evaluate(()=>window.registrationPosts),[{url:'/registrations',payload:{...registrationIdentity,acceptedNoticeVersions:noticeVersions('post-await')}}],'POST has already been sent; navigation does not undo it');
  if(destination==='workspace')await page.evaluate(()=>window.ArchitexPublicAccess.showWorkspace());
  else if(destination==='signin')await page.getByRole('tab',{name:'Sign in',exact:true}).click();
  else {await page.getByRole('button',{name:'Back to landing'}).click();if(destination==='register'){await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));await page.waitForFunction(()=>document.querySelector('#publicAuthMount').inert===false);}}
  if(destination==='signin'||destination==='register'){await page.locator('input[name=email]').fill('destination@example.test');await page.locator('input[name=email]').focus();}
  if(destination==='workspace')await page.evaluate(()=>{const app=document.querySelector('#app');app.innerHTML='<input aria-label="Workspace draft" value="Preserve this draft">';app.querySelector('input').focus();});
  const snapshot=()=>page.evaluate(()=>({html:document.querySelector('#publicAuthMount').innerHTML,state:document.documentElement.dataset.accessState,hidden:document.querySelector('#publicAuthMount').hidden,workspace:document.querySelector('#app').outerHTML,fields:[...document.querySelectorAll('#publicAuthMount input,#publicAuthMount select')].map(el=>[el.name,el.value,el.checked]),focus:document.activeElement.outerHTML,staleFields:[...window.staleRegistrationForm.elements].map(el=>[el.name,el.value,el.checked]),staleButton:[window.staleRegistrationForm.querySelector('button[type=submit]').disabled,window.staleRegistrationForm.querySelector('button[type=submit]').textContent]}));
  const before=await snapshot();
  await page.evaluate(async outcome=>{const response=window.registrationResponses.at(-1);if(outcome==='resolve')response.resolve({status:'pending_email_verification'});else response.reject(new Error('Deferred registration failure'));await new Promise(resolve=>setTimeout(resolve,100));},outcome);
  assert.deepEqual(await snapshot(),before,`${destination}/${outcome} fields, status, focus and stale submit unchanged`);
  assert.equal(await page.evaluate(()=>window.staleResetCount),0);assert.equal(await page.evaluate(()=>window.registrationPosts.length),1);assert.deepEqual(errors,[]);
 }
 // The same delayed success still resets and restores the current form normally.
 await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));await page.waitForFunction(()=>document.querySelector('#publicAuthMount').inert===false);
 await resolveVersions(page,await page.evaluate(()=>window.noticeRequests.length-1),noticeVersions('current'));await fillRegistration(page);
 const count=await page.evaluate(()=>window.registrationResponses.length);await page.locator('button[type=submit]').click();await page.waitForFunction(count=>window.registrationResponses.length===count+1,count);
 await page.evaluate(()=>window.registrationResponses.at(-1).resolve({status:'pending_email_verification'}));
 await page.waitForFunction(()=>document.querySelector('#v8AuthenticationStatus').textContent==='Registration received. Check your email to verify the account.');
 for(const name of ['fullName','organisationName','organisationSlug','email','password','profile_sacap_number'])assert.equal(await page.locator(`input[name="${name}"]`).inputValue(),'');
 assert.equal(await page.locator('input[name=acceptNotices]').isChecked(),false);assert.equal(await page.locator('button[type=submit]').isEnabled(),true);assert.equal(await page.locator('button[type=submit]').textContent(),'Create account →');assert.deepEqual(errors,[]);
});
test('registration re-entry posts exactly the fresh notices and identity then resets with verification disclosure',async t=>{
 const page=await fixture(t);page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',error=>errors.push(error.message));await registrationTransport(page);
 await page.getByRole('tab',{name:'Create account'}).click();await resolveVersions(page,0,noticeVersions('old'));
 await page.getByRole('tab',{name:'Sign in',exact:true}).click();await page.getByRole('tab',{name:'Create account'}).click();
 await resolveVersions(page,1,noticeVersions('fresh'));await fillRegistration(page);await page.locator('button[type=submit]').click();
 await page.waitForFunction(()=>document.querySelector('#v8AuthenticationStatus').textContent==='Registration received. Check your email to verify the account.');
 assert.deepEqual(await page.evaluate(()=>window.registrationPosts),[{url:'/registrations',payload:{...registrationIdentity,acceptedNoticeVersions:noticeVersions('fresh')}}]);
 assert.equal(await page.evaluate(()=>window.noticeRequests.length),2);
 for(const name of ['fullName','organisationName','organisationSlug','email','password','profile_sacap_number'])assert.equal(await page.locator(`input[name="${name}"]`).inputValue(),'');
 assert.equal(await page.locator('input[name=acceptNotices]').isChecked(),false);
 assert.equal(await page.locator('button[type=submit]').isEnabled(),true);assert.equal(await page.locator('button[type=submit]').textContent(),'Create account →');assert.deepEqual(errors,[]);
});
test('current registration notice failure restores submit; unchecked consent blocks submission before successful retry',async t=>{
 const page=await fixture(t);page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',error=>errors.push(error.message));await registrationTransport(page);
 await page.getByRole('tab',{name:'Create account'}).click();await fillRegistration(page);await page.locator('button[type=submit]').click();
 await page.waitForFunction(()=>window.noticeRequests.length===2);await settleNotice(page,0,'reject');await settleNotice(page,1,'reject');
 assert.equal(await page.locator('#v8AuthenticationStatus').isVisible(),true);assert.equal(await page.locator('#v8AuthenticationStatus').textContent(),'Deferred notice failure');assert.equal(await page.locator('button[type=submit]').isEnabled(),true);assert.deepEqual(await page.evaluate(()=>window.registrationPosts),[]);
 await page.locator('input[name=acceptNotices]').uncheck();await page.locator('button[type=submit]').click();
 assert.equal(await page.locator('input[name=acceptNotices]').evaluate(el=>el.validity.valueMissing),true);assert.equal(await page.locator('#v8AuthenticationForm').evaluate(form=>form.checkValidity()),false);
 assert.equal(await page.evaluate(()=>window.noticeRequests.length),2);assert.deepEqual(await page.evaluate(()=>window.registrationPosts),[]);
 await page.locator('input[name=acceptNotices]').check();await page.locator('button[type=submit]').click();await page.waitForFunction(()=>window.noticeRequests.length===3);await resolveVersions(page,2,noticeVersions('retry'));
 await page.waitForFunction(()=>document.querySelector('#v8AuthenticationStatus').textContent==='Registration received. Check your email to verify the account.');
 assert.deepEqual(await page.evaluate(()=>window.registrationPosts),[{url:'/registrations',payload:{...registrationIdentity,acceptedNoticeVersions:noticeVersions('retry')}}]);assert.equal(await page.locator('button[type=submit]').isEnabled(),true);assert.deepEqual(errors,[]);
});
async function deferredNotices(page){
 await page.evaluate(()=>{
  window.noticeRequests=[];
  window.ArchitexApi.ApiClient.prototype.get=function(url){
   return new Promise((resolve,reject)=>window.noticeRequests.push({url,resolve,reject}));
  };
 });
}
async function settleNotice(page,index,outcome){
 await page.evaluate(async ({index,outcome})=>{
  const request=window.noticeRequests[index];
  if(outcome==='reject')request.reject(new Error('Deferred notice failure'));
  else request.resolve(['terms','privacy','disclaimer','consent'].map(noticeType=>({noticeType,version:'fixture-old'})));
  // Drain promise reactions and the browser's unhandled-rejection notification task.
  await new Promise(resolve=>setTimeout(resolve,100));
 },{index,outcome});
}
for(const outcome of ['reject','resolve'])test(`delayed notice ${outcome} after Back leaves landing unchanged`,async t=>{
 const page=await fixture(t);const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await deferredNotices(page);
 await page.getByRole('tab',{name:'Create account'}).click();
 await page.getByRole('button',{name:'Back to landing'}).click();
 const before=await page.locator('#publicAuthMount').innerHTML();
 await settleNotice(page,0,outcome);
 assert.deepEqual(errors,[]);
 assert.equal(await page.locator('html').getAttribute('data-access-state'),'public');
 assert.equal(await page.locator('#publicAuthMount').innerHTML(),before);
 assert.equal(await page.locator('#publicAuthMount').isHidden(),true);
});
for(const destination of ['signin','register','workspace'])for(const outcome of ['reject','resolve'])test(`stale notice ${outcome} is isolated from ${destination} and registration re-entry`,async t=>{
 const page=await fixture(t);const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await deferredNotices(page);
 await page.getByRole('tab',{name:'Create account'}).click();
 if(destination==='workspace')await page.evaluate(()=>window.ArchitexPublicAccess.showWorkspace());
 else if(destination==='signin')await page.getByRole('tab',{name:'Sign in',exact:true}).click();
 else {await page.getByRole('button',{name:'Back to landing'}).click();await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));}
 await settleNotice(page,0,outcome);
 assert.deepEqual(errors,[]);
 assert.equal(await page.locator('#v8AuthenticationStatus').textContent(),'');
 assert.equal(await page.locator('html').getAttribute('data-access-state'),destination==='workspace'?'workspace':'auth');
 if(destination!=='register')await page.evaluate(()=>window.ArchitexPublicAccess.showAuth('register'));
 assert.equal(await page.evaluate(()=>window.noticeRequests.length),2,'new registration must discover its own notices');
 assert.equal(await page.evaluate(()=>window.noticeRequests.every(request=>request.url==='/legal-notices/signup')),true);
 await settleNotice(page,1,'reject');
 assert.equal(await page.locator('#v8AuthenticationStatus').textContent(),'Deferred notice failure','current failures must still be disclosed');
 assert.deepEqual(errors,[]);
});
test('recovery is unavailable with visible accessible disclosure in both auth modes',async t=>{
 const page=await fixture(t,390);
 for(const mode of ['signin','register']){
  await page.evaluate(mode=>window.ArchitexPublicAccess.showAuth(mode),mode);
  await page.waitForFunction(()=>document.querySelector('.is-auth-ready'));
  const recovery=page.getByRole('button',{name:'Recover access',exact:true});
  assert.equal(await recovery.isDisabled(),true);
  const disclosure=page.locator('#v8RecoveryUnavailable');
  assert.equal(await disclosure.isVisible(),true);
  assert.equal(await disclosure.textContent(),'Account recovery is not available here yet.');
  assert.equal(await recovery.getAttribute('aria-describedby'),'v8RecoveryUnavailable');
  await recovery.evaluate(el=>el.click());
  assert.equal(await page.locator('#v8AuthenticationStatus').textContent(),mode==='register'?'Fixture: legal notice service not connected':'');
 }
});
test('both auth modes show neutral access copy without unsupported security guarantees',async t=>{
 const page=await fixture(t);
 for(const mode of ['signin','register']){
  await page.evaluate(mode=>window.ArchitexPublicAccess.showAuth(mode),mode);
  await page.waitForFunction(()=>document.querySelector('.is-auth-ready'));
  const copy=await page.locator('.v8-signin-panel').innerText();
  assert.doesNotMatch(copy,/encrypted|immutable|secure access|secure enrolment|identity boundary active|audit trail ready|governed|permissions intact|keep this device trusted/i);
  assert.deepEqual(await page.locator('.v8-status-stack span').allTextContents(),[' Sign in or create an account',' Choose a role profile',' Access your workspace']);
  assert.equal(await page.locator('.v8-auth-note').last().textContent(),'◇ Sign in with your account email and password.');
  assert.match(copy,/Trusted device preference \(not applied\)/);
 }
});
test('legal centre provides keyboard skip access and expressive reading sections',async t=>{
 const page=await fixture(t,390);
 const html=cp.execFileSync('php',[path.join(pub,'legal.php')],{encoding:'utf8'});
 await page.setContent(html);
 assert.equal(await page.getByRole('link',{name:'Skip to legal content'}).count(),1);
 assert.equal(await page.locator('main section').first().evaluate(el=>getComputedStyle(el).borderRadius),'20px');
 await page.getByRole('link',{name:'Skip to legal content'}).focus();await page.keyboard.press('Enter');assert.equal(await page.locator('main').evaluate(el=>el===document.activeElement),true);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 const out=process.env.ARCHITEX_ACCESS_SCREENSHOTS||'E:/Hermes/home/cache/scratch/material3-access';fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,'legal-mobile.png'),fullPage:true});
});
test('mobile registration keeps original role fields and consent links without overflow',async t=>{
 const page=await fixture(t,390);await page.getByRole('tab',{name:'Create account'}).click();
 await page.getByRole('combobox',{name:'Role profile'}).selectOption('architect');
 assert.equal(await page.getByRole('textbox',{name:'SACAP registration number'}).count(),1);
 assert.equal(await page.locator('input[name=acceptNotices]').getAttribute('required'),'');
 assert.equal(await page.getByRole('link',{name:'Privacy Policy'}).getAttribute('href'),'/privacy');
 assert.equal(await page.locator('.v8-signin-identity').isVisible(),true);
 assert.equal(await page.evaluate(()=>document.querySelector('#publicAuthMount').scrollWidth<=innerWidth),true);
 const out=process.env.ARCHITEX_ACCESS_SCREENSHOTS||'E:/Hermes/home/cache/scratch/material3-access';fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,'signup-mobile.png'),fullPage:true});
});
test('access composition retains brand and supplies expressive touch and reduced-motion treatment',async t=>{
 const page=await fixture(t);
 assert.equal(await page.locator('.v8-enter-button').evaluate(el=>getComputedStyle(el).borderRadius),'28px');
 assert.equal(await page.locator('.v8-access-badge').evaluate(el=>getComputedStyle(el).animationName),'none');
 assert.match(await page.locator('.v8-signin').evaluate(el=>getComputedStyle(el).fontFamily),/^Inter/);
 assert.equal(await page.locator('.v8-signin-identity').evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(10, 69, 64)');
 await page.getByRole('textbox',{name:'Email address'}).focus();await page.keyboard.press('Tab');assert.equal(await page.locator('input[name=password]').evaluate(el=>el===document.activeElement),true);
 const out=process.env.ARCHITEX_ACCESS_SCREENSHOTS||'E:/Hermes/home/cache/scratch/material3-access';fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,'signin-desktop.png'),fullPage:true});
});
