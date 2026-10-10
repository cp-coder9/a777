'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs');
const {fixture}=require('./material3-expressive-brand.test.cjs');
const output='E:/Hermes/home/cache/scratch/material3expressive-foundation';
async function hit(locator){assert.ok(await locator.evaluate(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0&&r.left>=0&&r.right<=innerWidth&&e.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'actual control must be visible and unobstructed');}
test('390px header keeps role lens and right controls keyboard and pointer reachable',async t=>{
 const {page,errors}=await fixture(t,{viewport:{width:390,height:1000}});
 const role=page.locator('#productionRoleLens');await role.focus();await hit(role);
 assert.ok(await page.locator('.topbar').evaluate(e=>e.scrollWidth<=e.clientWidth),'header fits without hidden horizontal controls');
 await role.selectOption('engineer');assert.equal(await role.inputValue(),'engineer');
 await page.waitForTimeout(300);assert.equal(await page.evaluate(()=>document.activeElement.id),'productionRoleLens');await hit(role);
 const following=await role.evaluate(e=>{const controls=[...document.querySelector('.topbar').querySelectorAll('button,select')].filter(x=>!x.disabled&&x.getClientRects().length&&getComputedStyle(x).visibility!=='hidden');return controls.slice(controls.indexOf(e)+1).map(x=>x.id);});
 assert.ok(following.includes('wingmanTop'));assert.ok(following.includes('inspectToggle'));
 for(const id of following){await page.keyboard.press('Tab');assert.equal(await page.evaluate(()=>document.activeElement.id),id);await hit(page.locator('#'+id));}
 await page.locator('#inspectToggle').press('Enter');await page.locator('.inspect-close').click();
 assert.deepEqual(errors,[]);
});
for(const width of [750,760,768,800,801,825,849,850])test(`role lens visible, centre-hit and keyboard reachable ${width}px`,async t=>{
 const {page,errors}=await fixture(t,{viewport:{width,height:1000},reducedMotion:'reduce'});
 const role=page.locator('#productionRoleLens');
 const measured=await role.evaluate(e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e),parent=getComputedStyle(e.closest('#roleSwitch'));return {display:s.display,parentDisplay:parent.display,visibility:s.visibility,width:r.width,height:r.height,x:r.x,y:r.y};});
 t.diagnostic(JSON.stringify({width,lens:measured}));
 assert.ok(await role.isVisible(),`lens visible at ${width}: ${JSON.stringify(measured)}`);await hit(role);
 await page.locator('#navToggle').focus();let reached=false;
 for(let i=0;i<12;i++){await page.keyboard.press('Tab');if(await page.evaluate(()=>document.activeElement.id==='productionRoleLens')){reached=true;break;}}
 assert.ok(reached,'lens reachable through actual Tab navigation');await hit(role);
 await role.selectOption('engineer');await page.waitForTimeout(300);assert.equal(await role.inputValue(),'engineer');assert.equal(await page.evaluate(()=>document.activeElement.id),'productionRoleLens');await hit(role);
 assert.ok(await page.locator('.topbar').evaluate(e=>e.scrollWidth<=e.clientWidth),'header controls fit');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no document overflow');
 const following=await role.evaluate(e=>{const controls=[...document.querySelector('.topbar').querySelectorAll('button,select')].filter(x=>!x.disabled&&x.getClientRects().length&&getComputedStyle(x).visibility!=='hidden');return controls.slice(controls.indexOf(e)+1).map(x=>x.id);});
 assert.ok(following.includes('wingmanTop'));assert.ok(following.includes('inspectToggle'));
 for(const id of following){await page.keyboard.press('Tab');assert.equal(await page.evaluate(()=>document.activeElement.id),id);await hit(page.locator('#'+id));}
 if(width<=850)assert.ok(await role.evaluate(e=>e.getBoundingClientRect().top>document.querySelector('#navToggle').getBoundingClientRect().bottom),'lens occupies second header row');
 fs.mkdirSync(output,{recursive:true});await page.screenshot({path:`${output}/shell-${width}-role-lens.png`});assert.deepEqual(errors,[]);
});
test('1600px side panes constrain actual datum into bounded hit-testable grid cards',async t=>{
 const {page,errors}=await fixture(t,{viewport:{width:1600,height:1000},reducedMotion:'reduce'});
 const contentWidth=await page.locator('#main').evaluate(e=>{const s=getComputedStyle(e);return e.clientWidth-parseFloat(s.paddingLeft)-parseFloat(s.paddingRight);});
 assert.ok(contentWidth<1199,`side panes constrain content: ${contentWidth}`);assert.equal(await page.locator('.datum-world').evaluate(e=>getComputedStyle(e).display),'grid');
 const cards=page.locator('.datum-card'),count=await cards.count();assert.ok(count>0);const bounds=[];
 for(let i=0;i<count;i++){const card=cards.nth(i);await card.scrollIntoViewIfNeeded();await hit(card);const measured=await card.evaluate(e=>{const r=e.getBoundingClientRect(),m=document.querySelector('#main').getBoundingClientRect(),w=e.closest('.datum-world').getBoundingClientRect();return {x:r.x,right:r.right,y:r.y,bottom:r.bottom,mainLeft:m.left,mainRight:m.right,worldLeft:w.left,worldRight:w.right,worldTop:w.top,worldBottom:w.bottom,position:getComputedStyle(e).position,fits:e.scrollWidth<=e.clientWidth};});assert.equal(measured.position,'relative');assert.ok(measured.x>=Math.max(measured.mainLeft,measured.worldLeft)&&measured.right<=Math.min(measured.mainRight,measured.worldRight)&&measured.y>=measured.worldTop&&measured.bottom<=measured.worldBottom&&measured.fits,JSON.stringify(measured));bounds.push(measured);}
 t.diagnostic(JSON.stringify({width:1600,contentWidth,count,cards:bounds}));fs.mkdirSync(output,{recursive:true});await page.screenshot({path:`${output}/shell-1600-constrained-datum.png`});assert.deepEqual(errors,[]);
});
test('390px final stage label can be scrolled and activated without feedback interception',async t=>{
 const {page,errors}=await fixture(t,{viewport:{width:390,height:1000}});
 const stage=page.locator('.stages .stage').last();await stage.scrollIntoViewIfNeeded();await hit(stage);
 assert.ok(await stage.evaluate(e=>e.scrollWidth<=e.clientWidth),'stage label is not clipped');
 await stage.click();assert.match(await page.locator('.stage-help').innerText(),/Close-out/i);
 await page.locator('#feedbackFab').click();assert.ok(await page.locator('#feedbackPanel').isVisible());await page.locator('.feedback-x').click();
 fs.mkdirSync(output,{recursive:true});await page.screenshot({path:`${output}/shell-390-stage-closeout.png`});assert.deepEqual(errors,[]);
});
test('expressive targets, keyboard focus, reduced motion and honest control states',async t=>{
 const {page,errors}=await fixture(t,{reducedMotion:'reduce'});
 const btn=page.locator('#navToggle');await btn.focus();
 const styles=await btn.evaluate(e=>{const s=getComputedStyle(e);return {height:parseFloat(s.height),outline:s.outlineWidth,radius:s.borderRadius,transition:s.transitionDuration};});
 assert.ok(styles.height>=44);assert.equal(styles.outline,'3px');assert.equal(styles.transition,'0s');
 assert.equal(await page.locator('.os-item.active').first().evaluate(e=>getComputedStyle(e).borderRadius),'28px');
 // Test-only component specimens; never production sample records or success receipts.
 await page.locator('#main').evaluate(e=>e.insertAdjacentHTML('beforeend','<section id="foundation-specimens"><button class="btn" disabled>Unavailable</button><input readonly value="Read-only fixture"><input aria-invalid="true"><div role="alert">Synthetic server rejection</div><div aria-busy="true">Loading fixture</div><div class="md-denied">Access denied fixture</div></section>'));
 assert.equal(await page.locator('#foundation-specimens button').evaluate(e=>getComputedStyle(e).cursor),'not-allowed');
 assert.equal(await page.locator('#foundation-specimens input[readonly]').evaluate(e=>getComputedStyle(e).borderStyle),'dashed');
 assert.equal(await page.locator('#foundation-specimens input[aria-invalid]').evaluate(e=>getComputedStyle(e).borderTopColor),'rgb(217, 87, 71)');
 await page.locator('#main').evaluate(e=>e.insertAdjacentHTML('beforeend','<section class="gov-workspace" id="native-specimens"><form><label>Fixture<input></label><button class="btn">Fixture action</button></form><div class="panel"><table><tr><th>Fixture heading</th><td>Fixture value</td></tr></table></div></section>'));
 assert.ok(await page.locator('#native-specimens input').evaluate(e=>parseFloat(getComputedStyle(e).height))>=44,'native input target');
 assert.equal(await page.locator('#native-specimens .panel').evaluate(e=>getComputedStyle(e).paddingTop),'24px','native panel spacing');
 assert.equal(await page.locator('#native-specimens td').evaluate(e=>getComputedStyle(e).paddingTop),'12px','native table spacing');assert.deepEqual(errors,[]);
});
test('2400px wide datum preserves positioned coordinates and actual hit targets above 1199px',async t=>{
 const {page,errors}=await fixture(t,{viewport:{width:2400,height:1000},reducedMotion:'reduce'});
 if(await page.locator('#app').evaluate(e=>e.classList.contains('rail-expanded')))await page.locator('.rail-close').click();
 if(!(await page.locator('#app').evaluate(e=>e.classList.contains('nav-closed')||e.classList.contains('nav-compact'))))await page.locator('#navToggle').click();
 if(!(await page.locator('#app').evaluate(e=>e.classList.contains('inspector-closed'))))await page.locator('.inspect-close').click();
 await page.waitForTimeout(300);
 const main=await page.locator('#main').boundingBox();
 const contentWidth=await page.locator('#main').evaluate(e=>{const s=getComputedStyle(e);return e.clientWidth-parseFloat(s.paddingLeft)-parseFloat(s.paddingRight);});
 assert.ok(contentWidth>1199,`actual main content width ${contentWidth} exceeds container breakpoint`);
 assert.notEqual(await page.locator('.datum-world').evaluate(e=>getComputedStyle(e).display),'grid');
 const cards=page.locator('.datum-card'),count=await cards.count();assert.ok(count>0);
 const coordinates=[];
 for(let i=0;i<count;i++){
  const card=cards.nth(i);await card.scrollIntoViewIfNeeded();await hit(card);
  const measured=await card.evaluate(e=>{const s=getComputedStyle(e),r=e.getBoundingClientRect(),w=e.closest('.datum-world'),b=w.getBoundingClientRect();return {position:s.position,left:e.style.left,top:e.style.top,x:r.x,y:r.y,width:r.width,height:r.height,worldX:b.x,worldY:b.y,worldWidth:w.clientWidth,worldHeight:w.clientHeight,transform:s.transform};});
  const above=i<Math.ceil(count/2),rowCount=above?Math.ceil(count/2):Math.floor(count/2),index=above?i:i-Math.ceil(count/2),left=rowCount<2?45:7+index*86/(rowCount-1);
  assert.equal(measured.position,'absolute');assert.ok(Math.abs(parseFloat(measured.left)-left)<0.001);assert.equal(measured.top,above?'11%':'67%');
  // Original wide cards use left-edge percentage anchors, not grid placement.
  assert.equal(measured.transform,'none');assert.equal(measured.width,215);
  assert.ok(Math.abs(measured.x-(measured.worldX+measured.worldWidth*left/100))<1,JSON.stringify(measured));
  assert.ok(Math.abs(measured.y-(measured.worldY+measured.worldHeight*(above?.11:.67)))<1,JSON.stringify(measured));
  coordinates.push(measured);
 }
 assert.deepEqual(errors,[]);fs.mkdirSync(output,{recursive:true});await page.screenshot({path:`${output}/shell-2400-wide-datum.png`});
 t.diagnostic(JSON.stringify({viewport:{width:2400,height:1000},main,contentWidth,cards:coordinates}));
});
for(const render of ['renderAll','renderMain'])test(`${render} preserves unrelated active control and input without role-lens focus theft`,async t=>{
 const {page,errors}=await fixture(t);
 // A real persistent shell control and an explicitly test-only input outside replaced render regions.
 await page.locator('body').evaluate(e=>e.insertAdjacentHTML('beforeend','<input id="foundation-unrelated-focus" aria-label="Test-only unrelated input" style="position:fixed;left:100px;bottom:10px;z-index:9999" value="preserve selection">'));
 for(const selector of ['#feedbackFab','#foundation-unrelated-focus']){
  const control=page.locator(selector);await control.focus();await hit(control);
  const result=await page.evaluate(({selector,render})=>{const active=document.querySelector(selector),lens=document.querySelector('#productionRoleLens');if(active.tagName==='INPUT')active.setSelectionRange(2,7);window[render]();return {same:document.activeElement===active,connected:active.isConnected,lensConnected:!!document.querySelector('#productionRoleLens')?.isConnected,lensReplaced:document.querySelector('#productionRoleLens')!==lens,selection:active.tagName==='INPUT'?[active.selectionStart,active.selectionEnd]:null};},{selector,render});
  assert.ok(result.same,`${render} must not steal ${selector} focus`);assert.ok(result.connected);assert.ok(result.lensConnected);
  if(render==='renderAll')assert.ok(result.lensReplaced,'renderAll actually replaces the role lens');
  if(selector.includes('unrelated'))assert.deepEqual(result.selection,[2,7]);
  await page.waitForTimeout(300);assert.equal(await page.evaluate(()=>document.activeElement.id),selector.slice(1));
 }
 assert.deepEqual(errors,[]);
});
for(const width of [1024,768,390])test(`actual datum targets reflow and remain hit-testable ${width}px`,async t=>{
 const {page,errors}=await fixture(t,{viewport:{width,height:1000}});
 const cards=page.locator('.datum-card');assert.ok(await cards.count()>0,'real datum cards rendered');
 const count=await cards.count();for(let i=0;i<count;i++){const card=cards.nth(i);await card.scrollIntoViewIfNeeded();await hit(card);assert.ok(await card.evaluate(e=>{const r=e.getBoundingClientRect(),m=document.querySelector('#main').getBoundingClientRect(),canvas=e.closest('.datum-canvas,.datum-world').getBoundingClientRect();return r.left>=Math.max(m.left,canvas.left)&&r.right<=Math.min(m.right,canvas.right)&&r.top>=canvas.top&&r.bottom<=canvas.bottom&&e.scrollWidth<=e.clientWidth;}),`card ${i} fits main including rightmost target`);}
 assert.deepEqual(errors,[]);
});
for(const width of [1600,1024,768,390])test(`responsive real shell ${width}px preserves usable main and toggle panels`,async t=>{
 const {page,errors}=await fixture(t,{viewport:{width,height:1000}});
 if(width<=1200)assert.ok(await page.evaluate(()=>document.elementFromPoint(innerWidth-20,200)?.closest('#main')),'mobile main is not covered by default drawers');
 for(const state of ['default','rail','navigator','inspector']){
  if(state!=='default'){await page.reload();await page.waitForSelector('body[data-v1-clone-state="ready"]');}
  // Each drawer starts from a freshly initialized shell, not a prior drawer's state.
  if(await page.locator('#app').evaluate(e=>e.classList.contains('rail-expanded')))await page.locator('.rail-close').click();
  if(state==='rail')await page.locator('.logo-btn').click();
  if(state==='navigator'){
   if(!(await page.locator('#app').evaluate(e=>e.classList.contains('nav-closed')||e.classList.contains('nav-compact'))))await page.locator('#navToggle').click();
   await page.locator('#navToggle').click();
  }
  if(state==='inspector'){
   if(!(await page.locator('#app').evaluate(e=>e.classList.contains('inspector-closed'))))await page.locator('.inspect-close').click();
   await page.locator('#inspectToggle').click();
  }
  await page.waitForTimeout(300);
  const bounds=await page.locator('#main').boundingBox();assert.ok(bounds.width>=Math.min(280,width-96),`${state}: main width ${bounds.width}`);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${state}: document overflow`);
  if(width<=1024&&state==='inspector')assert.ok(await page.locator('.inspector').isVisible(),'inspector toggle remains reachable');
  fs.mkdirSync(output,{recursive:true});await page.screenshot({path:`${output}/shell-${width}-${state}.png`});
  if(state==='rail'){await hit(page.locator('.rail-close'));await page.locator('.rail-close').click();assert.equal(await page.locator('#app').evaluate(e=>e.classList.contains('rail-expanded')),false);}
  if(state==='navigator'){
   const row=page.locator('#navigator .nav-row').first();await row.scrollIntoViewIfNeeded();await hit(row);await row.click();
   if(await page.locator('#navigator').isVisible())await page.locator('#navToggle').click();
   assert.ok(await page.locator('#app').evaluate(e=>e.classList.contains('nav-compact')||e.classList.contains('nav-closed')),'navigator closes through header control');
  }
  if(state==='inspector'){const tab=page.locator('.inspect-tab').filter({hasText:'Activity'});await hit(tab);await tab.click();assert.ok(await tab.evaluate(e=>e.classList.contains('active')));await page.locator('.inspect-close').click();assert.ok(await page.locator('#app').evaluate(e=>e.classList.contains('inspector-closed')));}
 }
 fs.mkdirSync(output,{recursive:true});await page.screenshot({path:`${output}/shell-${width}.png`});assert.deepEqual(errors,[]);
});
