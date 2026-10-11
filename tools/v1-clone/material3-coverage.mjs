import fs from 'node:fs';import vm from 'node:vm';import crypto from 'node:crypto';import {fileURLToPath} from 'node:url';
const read=p=>fs.readFileSync(p,'utf8');const base='app/public/assets/v1-clone/js/';
// Execute only trusted repository scripts. The context exposes no host callbacks,
// filesystem, network, timers, require or process; dynamic code generation is disabled.
export function renderCoverage(d){
 const context=vm.createContext({}, {codeGeneration:{strings:false,wasm:false}});
 const run=code=>vm.runInContext(code,context,{timeout:2000});
 run(`const nodes={};function node(){return {innerHTML:'',textContent:'',value:'',style:{},dataset:{},classList:{contains(){return false},add(){},remove(){},toggle(){}},addEventListener(){},setAttribute(){},insertAdjacentHTML(){},insertBefore(){},appendChild(){},querySelector(){return node()},querySelectorAll(){return []}}}const document={querySelector:s=>nodes[s]??=node(),querySelectorAll:()=>[],getElementById:s=>nodes['#'+s]??=node(),createElement:()=>node(),body:node(),addEventListener(){}};const window=globalThis;const setTimeout=()=>0,clearTimeout=()=>{},setInterval=()=>0,clearInterval=()=>{};const localStorage={getItem:()=>null,setItem(){}};const navigator={};`);
 const collected=new Map();d.rendererContexts=[];
 for(const file of ['reference-001.js','reference-002.js','reference-003.js','reference-004.js']){
  run(read(base+file));
  for(const role of d.allowlists.profileKeys)for(const stage of ['Brief','Appoint','Design','Comply','Procure','Build','Pay','Close-out'])for(const godMode of file==='reference-004.js'?[false,true]:[false]){
   run(`state.role=${JSON.stringify(role)};project.stage=${JSON.stringify(stage)};state.godMode=${godMode};state.tool=null;state.mode='project';state.global='projects';`);
   const outputs=run(`({datum:renderProjectSpace(),projectTools:toolGroups('project'),standaloneTools:toolGroups('standalone'),globalMenu:(renderGlobalMenu(),nodes['#globalMenu'].innerHTML)})`);
   const cardKeys=[...outputs.datum.matchAll(/<article\b[^>]*class="datum-card[^>]*onclick="openTool\('([^']+)'/g)].map(m=>m[1]);
   d.rendererContexts.push({source:base+file,role,stage,godMode,helper:({'reference-002.js':'datumV6','reference-003.js':'mtgDatumCard','reference-004.js':'v8DatumCard'})[file]||'datumCard',cardKeys,evidenceStatus:'isolated-renderer-executed; live-pending'});
   for(const [renderer,html] of Object.entries(outputs))for(const m of html.matchAll(/onclick="([^"]+)"/g)){
    const handler=m[1];const call=handler.match(/^(openTool|openGlobal|selectProjectStage|setStage|godOpenStage)\('([^']+)'(?:,'([^']+)')?\)/);if(!call)continue;
    const action=['selectProjectStage','setStage','godOpenStage'].includes(call[1])?'stage':call[1];const key=action==='stage'?call[2].toLowerCase():call[2];const mode=call[3]||(action==='openGlobal'?'global':'project');
    const identity=JSON.stringify([file,renderer,action,key,mode,godMode]);let row=collected.get(identity);if(!row){row={source:base+file,renderer,action,key,mode,godMode,handler,reachableContexts:[],evidenceStatus:'isolated-renderer-executed; live-pending'};collected.set(identity,row);}row.reachableContexts.push(role+':'+stage.toLowerCase());
   }
  }
 }
 return [...collected.values()];
}
export function build(){
 const d=JSON.parse(read('docs/material3-expressive/coverage.json'));const ref=read(base+'reference-001.js');
 const globals=vm.runInNewContext(ref.match(/const globalItems=([\s\S]*?\n\]);/)[1],{TOOLS:d.referenceToolMetadata});
 const metadata={...d.referenceToolMetadata};for(const file of ['reference-001.js','reference-002.js','reference-003.js','reference-004.js']){const text=read(base+file);for(const m of text.matchAll(/TOOLS\.([a-z_]+)\s*=\s*(\{[\s\S]+?\});/g)){try{metadata[m[1]]=vm.runInNewContext('('+m[2]+')');}catch{}}}
 const rows=renderCoverage(d);
 d.generatedMenuTemplates=[];for(const file of ['reference-001.js','reference-002.js','reference-003.js','reference-004.js'])read(base+file).split('\n').forEach((text,i)=>{if(text.includes('.map(')&&text.includes('onclick='))d.generatedMenuTemplates.push({source:base+file,line:i+1,template:text.trim(),evidenceStatus:'pending'});});
 d.generatedMenuAcceptance=rows;d.menuInventoryBoundary='menuOccurrences contains literal calls only. generatedMenuAcceptance records click handlers emitted by the trusted sequential reference assembly at each version: project datum, project/standalone toolGroups and global menu. rendererContexts records actual datum-card keys per role/stage/God Mode with version-specific prioritisation and limits. Superseded declarations, library pages and non-navigation handlers are not executed coverage. Isolated renderer candidates are not live workflow passes.';
 const html=read('app/public/preview3.html');const controls=[];html.split('\n').forEach((line,i)=>{for(const m of line.matchAll(/<(button|input|select|textarea|a)\b([^>]*?)>(?:([^<]*))?/g)){if(i>1169)continue;controls.push({line:i+1,tag:m[1],attributes:m[2].trim(),label:(m[3]||'').trim(),evidenceStatus:'pending'});}});
 d.publicLanding={source:'app/public/preview3.html',embeddedBy:{source:'app/public/app.php',line:24,selector:'#publicLandingFrame'},controls,operatingGuide:'module-prd.md#actual-public-landing-preview3',boundary:'Concept handlers are local demonstrations. Parent public-access bridge may replace selected interactions; verify separately. No server submission, authenticated project creation or AI provider receipt from this page alone.',evidenceStatus:'source-inspected-live-pending'};
 d.publicLanding.generatedDatumActions=[...html.slice(html.indexOf('const nodeActions = ['),html.indexOf('function resizeCanvas')).matchAll(/label: "([^"]+)"/g)].map((m,index)=>({index,label:m[1],evidenceStatus:'pending'}));
 for(const r of d.profileRoles)r.operatingGuides=Object.fromEntries(r.recommendedToolIds.map(id=>[id,'module-prd.md#'+id.replaceAll('_','-')]));
 const association={drawing_compliance_navigator:{status:'unsupported-in-v1-clone',paths:['app/public/assets/js/shell.js',base+'runtime.js',base+'modules/compliance.js',base+'adapters/compliance.js'],reason:'Legacy app.php shell mounts drawingComplianceWorkspace via shell.js. v1-clone runtime installs complianceHub, whose supports list does not include drawing_compliance_navigator; no mounted v1 controller association is established. API/checklist availability is not this dashboard mount.'},valuations:{status:'unsupported-in-v1-clone',paths:['app/public/assets/js/shell.js',base+'runtime.js',base+'modules/commercial.js',base+'adapters/commercial.js'],reason:'Legacy shell.js aliases TOOLS.valuations and renders financeCommercialWorkspace. v1-clone commercial controller explicitly supports contract_admin and payments_escrow only, not valuations. Do not infer mounted valuation actions from the live registry label.'}};
 for(const t of d.tools)if(association[t.id]){const a=association[t.id];t.mountedDashboardStatus=a.status;t.mountBoundary=a.reason;t.implementationSources=a.paths.map(source=>({source,evidenceStatus:'source-inspected',association:a.reason}));}
 const paths=new Set(d.sources.map(x=>x.path));for(const file of ['reference-001.js','reference-002.js','reference-003.js','reference-004.js'])paths.add(base+file);paths.add('app/public/preview3.html');paths.add('app/public/app.php');for(const a of Object.values(association))for(const p of a.paths)paths.add(p);
 d.sources=[...paths].filter(p=>fs.existsSync(p)).map(path=>({path,sha256:crypto.createHash('sha256').update(fs.readFileSync(path)).digest('hex')}));d.hashPolicy='Hashes are observations of source bytes at regeneration, not assertions that concurrent app edits are owned by this documentation task. Re-run for drift; do not silently interpret drift as a role/key identity change.';
 d.counts.generatedMenuAcceptance=rows.length;d.counts.publicLandingControls=controls.length;d.counts.roleRecommendationGuides=d.profileRoles.reduce((n,r)=>n+r.recommendedToolIds.length,0);return d;
}
if(process.argv[1]&&fileURLToPath(import.meta.url)===fs.realpathSync(process.argv[1])){const d=build();fs.writeFileSync('docs/material3-expressive/coverage.json',JSON.stringify(d,null,2)+'\n');console.log(JSON.stringify(d.counts));}
