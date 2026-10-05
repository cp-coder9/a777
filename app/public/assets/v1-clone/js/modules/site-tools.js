(function(root){
  'use strict';
  function install({context}){
    const adapter=root.V1CloneSiteToolsAdapter.create(context);
    const supports=id=>Object.hasOwn(root.V1CloneSiteToolsAdapter.aliases,id);
    let engineeringFrame=null,engineeringDirty=false,saving=false;
    function engineeringControls(){
      root.document?.querySelectorAll('button[onclick*="saveEngineeringCalculation"]').forEach(button=>{
        const review=button.getAttribute('onclick').includes('true');button.disabled=review||context.getState().readOnly===true;
        button.title=review?'Unavailable: a governed engineering review workflow is not connected.':button.disabled?'Read-only membership: calculation saving requires write permission.':'Only edited Rational Method inputs can be saved as an advisory run.';
      });
    }
    root.saveEngineeringCalculation=function(review){
      const doc=engineeringFrame?.isConnected&&engineeringFrame.contentDocument;
      const active=doc?.querySelector('#calc-stormwater-rational.active #sw-sub-rational.active');
      const notice=message=>{root.showToast?.(message);const status=doc?.querySelector('[data-site-tools-status]');if(status)status.textContent=message;return {saved:false,reviewRequested:false,message};};
      if(context.getState().readOnly===true)return notice('Calculation not saved: read-only membership. Local advisory calculations remain available.');
      if(review||!active)return notice('Calculation not saved or sent to review: only Rational Method saving is mapped to Toolbox. Other calculators and professional review remain unavailable in this hub.');
      if(!engineeringDirty)return notice('Calculation not saved: enter or change the Rational Method inputs before saving.');
      if(saving)return notice('Calculation save already in progress.');
      if(!root.confirm?.('Save these Rational Method inputs as a project-assigned advisory Toolbox run? This does not issue or send it for review.'))return notice('Calculation not saved.');
      const values={coefficient:doc.getElementById('sw-c').value,intensity:doc.getElementById('sw-i').value,areaHa:doc.getElementById('sw-a').value};saving=true;
      return adapter.saveRational(values).then(record=>{engineeringDirty=false;notice('Rational Method run verified in Toolbox history. Not issued, reviewed or certified.');return {saved:true,reviewRequested:false,record};}).catch(error=>notice(error.message+' No success claimed.')).finally(()=>{saving=false;});
    };
    const mounted=new Map();let generation=0;
    context.subscribe?.(()=>{generation++;engineeringDirty=false;engineeringControls();for(const [frame,entry] of mounted){if(!frame.isConnected||frame.contentDocument!==entry.doc){mounted.delete(frame);continue;}entry.refresh();}});
    const boundary='Internal records only — not statutory approval, safety certification or permission to work.';
    const unavailable='Not available from the connected service.';
    function prepareSource(id,html){
      if(!supports(id))return html;
      const doc=new root.DOMParser().parseFromString(html,'text/html');
      if(id!=='engineering_calc'){
        // Preserve the original DOM/CSS; clear only demonstration text/data.
        const walker=doc.createTreeWalker(doc.body,4);const text=[];while(walker.nextNode())text.push(walker.currentNode);
        for(const node of text){const el=node.parentElement;if(!node.textContent.trim()||el.closest('script,style,svg'))continue;
          if(el.closest('button,.nav-label,.breadcrumb,h2,h3,thead,.stat-label,.file-name,.sf-name,.rl'))continue;
          if(el.closest('h1,.card-title')){node.textContent=node.textContent.split('—')[0].trim();continue;}
          node.textContent='—';
        }
        doc.querySelectorAll('button').forEach(el=>{
          if(el.classList.contains('project-toggle')||/Greenfield|Waterfall|CBD Retail|All Projects/.test(el.textContent)){el.textContent='Selected project';el.dataset.siteProject='';el.removeAttribute('onclick');}
          else if(!el.classList.contains('tab')&&!el.classList.contains('nav-item')&&!/activateTab/.test(el.getAttribute('onclick')||'')){el.dataset.siteUnavailable='';el.removeAttribute('onclick');el.title=unavailable;}
        });
        doc.querySelectorAll('.progress-fill,[style*="width:87%"],[style*="width:90%"],.rc').forEach(el=>{if(el.classList.contains('rc'))el.textContent='—';else el.style.width='0%';});
        doc.querySelectorAll('tbody').forEach(el=>{const sample=el.querySelector('tr');if(sample){while(el.children.length>1)el.lastElementChild.remove();sample.querySelectorAll('td').forEach(cell=>{cell.textContent='—';});}el.dataset.siteRecords='unavailable';});
        if(id==='itp'){
          const first=doc.querySelector('tbody');if(first)first.dataset.siteRecords='inspections';
          doc.querySelectorAll('.card').forEach(card=>{const title=card.querySelector('h2')?.textContent||'';if(title.includes('Linked Non-Conformance')){const table=card.querySelector('tbody');if(table)table.dataset.siteRecords='ncrs';}});
          const heading=doc.querySelector('h1');if(heading){heading.textContent='Selected project';heading.dataset.siteProject='';}
          const create=[...doc.querySelectorAll('button')].find(b=>b.textContent.includes('Create ITP'));if(create){create.textContent='+ Draft inspection';delete create.dataset.siteUnavailable;create.dataset.siteCreate='';create.title='Creates an internal inspection, not an ITP certification';}
        }else{
          doc.querySelectorAll('#tab-plans tbody').forEach(el=>el.dataset.siteRecords='plans');
          if(id==='safety'){
            const card=doc.createElement('section');card.className='card';
            card.innerHTML='<h2 class="card-title">Internal safety inspections</h2><p>Draft findings only. Not safety certification or permission to work.</p><button class="action-btn" type="button" data-site-create>Draft safety inspection</button><table class="table"><thead><tr><th>Inspection</th><th>Findings</th><th>Revision</th><th>Status</th><th>Checklist</th><th>Follow-ups</th><th>Boundary</th></tr></thead><tbody data-site-records="inspections"><tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table>';
            (doc.querySelector('#tab-overview')||doc.querySelector('.content-wrapper')).prepend(card);
          }
        }
      }
      const status=doc.createElement('p');status.className=id==='itp'?'card-title':'card-title';status.dataset.siteToolsStatus='';status.setAttribute('role','status');status.textContent=id==='engineering_calc'?'Local advisory calculator — inputs and results are not saved; professional verification required.':('Loading project records. '+boundary);
      (doc.querySelector('.workspace,.content-wrapper')||doc.body).prepend(status);
      return '<!DOCTYPE html>\n'+doc.documentElement.outerHTML;
    }
    function bindTable(table,records,kind){
      const headers=kind==='inspections'?['Internal inspection','Findings','Revision unavailable','Workflow status','Checklist items','Follow-ups','Boundary']:kind==='plans'?['Subject','Reference','Recorded by','Recorded at','Evidence grade','Record status','Boundary']:['ID','Title','Severity','Status','Description'];
      table.closest('table').querySelectorAll('th').forEach((cell,i)=>cell.textContent=headers[i]||'—');
      const template=table.querySelector('tr')?.cloneNode(true);table.replaceChildren();
      if(!template)return;
      const values=r=>kind==='inspections'?[r.inspectionType+' inspection',r.findings?.join('; ')||'No findings recorded','—',r.status,r.checklist?.length??0,r.followUps?.join('; ')||'No follow-ups recorded','Not statutory approval']:kind==='plans'?[r.subjectName,r.referenceNumber||'—',r.createdBy||'—',r.createdAt||'—',r.grade,r.status,'Not permission to work']:[r.id,r.title,r.severity,r.status,r.description];
      for(const record of records){const row=template.cloneNode(true);row.dataset.recordId=record.id;const data=values(record);row.querySelectorAll('td').forEach((cell,i)=>{cell.textContent=String(data[i]??'—');});table.append(row);}
      if(!records.length){const row=template.cloneNode(true);row.querySelectorAll('td').forEach((cell,i)=>cell.textContent=i===0?'No records returned':'—');table.append(row);}
    }
    async function mountFrame(id,frame){
      if(!supports(id))return false;
      const doc=frame.contentDocument,win=frame.contentWindow;if(!doc||!win)throw Error('A loaded same-origin frame is required.');
      const status=doc.querySelector('[data-site-tools-status]');if(!status)throw Error('prepareSource must run before mounting.');
      win.saveEngineeringCalculation=root.saveEngineeringCalculation;
      if(id==='engineering_calc'){engineeringFrame=frame;engineeringDirty=false;engineeringControls();doc.querySelectorAll('#sw-sub-rational input').forEach(input=>input.addEventListener('input',()=>{engineeringDirty=true;}));return true;}
      const setStatus=text=>{status.textContent=text;};
      doc.querySelectorAll('[data-site-unavailable]').forEach(button=>{button.disabled=true;button.title=unavailable;button.onclick=null;});
      doc.querySelectorAll('[data-site-project]').forEach(button=>{button.onclick=()=>setStatus('Change the selected project in the parent workspace.');});
      if(id==='itp')doc.querySelectorAll('.tab').forEach((button,index)=>button.onclick=()=>{
        doc.querySelectorAll('.tab').forEach(b=>b.classList.toggle('active',b===button));
        const headings=['','Inspection Test Plans','Material Testing','Hold Points','Recent Lab Results','Linked Non-Conformance','Inspector Sign-Off'];
        const target=[...doc.querySelectorAll('h2')].find(h=>h.textContent.includes(headings[index]||'Inspection Test Plans'));target?.scrollIntoView({block:'start'});setStatus(button.textContent+': '+(index===1?'Internal inspection records. ':unavailable+' ')+boundary);
      });
      const refresh=async()=>{
        const token=generation;
        doc.querySelectorAll('[data-site-create]').forEach(button=>{button.disabled=context.getState().readOnly===true;button.title=button.disabled?'Read-only membership: inspection creation requires write permission.':'Create an internal draft inspection.';});
        doc.querySelectorAll('[data-site-project]').forEach(el=>el.textContent='Selected project');
        setStatus('Loading project records. '+boundary);
        doc.querySelectorAll('[data-site-records]').forEach(table=>{table.querySelectorAll('td').forEach(cell=>cell.textContent='—');});
        try{const model=await adapter.load(id);if(frame.contentDocument!==doc||token!==generation)return false;
          doc.querySelectorAll('[data-site-project]').forEach(el=>el.textContent=model.projectName);
          doc.querySelectorAll('[data-site-records]').forEach(table=>{const kind=table.dataset.siteRecords;if(Array.isArray(model[kind]))bindTable(table,model[kind],kind);else{const cell=table.querySelector('td');if(cell)cell.textContent=unavailable;}});
          setStatus('Project: '+model.projectName+'. '+boundary+' Unsupported registers are unavailable, not empty or compliant.');return true;
        }catch(error){setStatus(error.message+' No records loaded. '+boundary);return false;}
      };
      doc.querySelectorAll('[data-site-create]').forEach(button=>button.onclick=async()=>{
        if(context.getState().readOnly===true)return setStatus('Read-only membership: you can review inspection records. '+boundary);
        // The original source has no editable form: collect explicit user input,
        // never manufacture a completed checklist or a professional sign-off.
        const finding=win.prompt('Draft internal inspection finding (not certification):');if(!finding?.trim())return;
        const inspectionType=id==='safety'?'safety':'quality';
        if(!win.confirm('Save this as a draft '+inspectionType+' inspection for the selected project?'))return;
        const token=generation;
        button.disabled=true;try{await adapter.createInspection({inspectionType,findings:[finding.trim()],checklist:[],followUps:[],signOffRequired:true});if(token!==generation||frame.contentDocument!==doc)return;await refresh();setStatus('Draft inspection verified in project records. '+boundary);}catch(error){setStatus(error.message+' No success claimed.');}finally{button.disabled=context.getState().readOnly===true;}
      });
      mounted.set(frame,{doc,refresh});return refresh();
    }
    return {supports,prepareSource,mountFrame};
  }
  root.V1CloneSiteTools={install};
})(window);
