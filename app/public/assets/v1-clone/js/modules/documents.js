(function(root){
  'use strict';
  // Documents & Drawings — functional module controller.
  // v1 ships documents_drawings as a read-only scaffold, so this replaces it with
  // a real project-scoped controlled-document register: list revisions, create a
  // document (draft), add a revision, and drive submit-review -> approve -> issue.
  // Every write goes through the adapter, which is project-scoped and
  // read-back-verified against the server. Nothing here fabricates success, and no
  // external transmittal, PDF or municipal-submission provider is claimed.
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    if(!root.V1CloneDocumentsAdapter)throw Error('Load adapters/documents.js before the documents module.');
    const adapter=root.V1CloneDocumentsAdapter.create(context);
    const esc=v=>String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const TOOL='documents_drawings';
    const TYPES=root.V1CloneDocumentsAdapter.DOC_TYPES;
    const DISCIPLINES=root.V1CloneDocumentsAdapter.DISCIPLINES;
    const PURPOSES=root.V1CloneDocumentsAdapter.PURPOSES;
    const SHEETS=root.V1CloneDocumentsAdapter.SHEETS;
    const BOUNDARY='Internal controlled-document records only — not an external transmittal, statutory approval or permission to build.';
    let generation=0,disposed=false,active=false,records=[];
    function scope(){const s=context.getState();if(!s.ready||!s.session||!s.organisationId||!/^[a-f0-9]{32}$/i.test(s.selectedProjectId||''))throw Error('Select an authenticated project.');return s;}
    function notice(message){if (!ownsRoute(TOOL)) return;root.showToast?.(message);const el=root.document.querySelector('#main [data-documents-status]');if(el)el.textContent=message;}
    async function loadRecords(token=generation){const value=await adapter.list();if(disposed||!ownsRoute(TOOL)||token!==generation)return null;records=value;return records;}
    const label=v=>String(v==null?'—':v).replace(/_/g,' ');
    function actionButtons(r){
      if(r.status==='draft')return `<button class="btn" data-doc-action="submit-review" data-doc-id="${esc(r.id)}">Submit for review</button>`;
      if(r.status==='pending_review')return `<button class="btn" data-doc-action="approve" data-doc-id="${esc(r.id)}">Approve</button>`;
      if(r.status==='approved')return `<button class="btn" data-doc-action="issue" data-doc-id="${esc(r.id)}">Issue</button>`;
      if(r.status==='issued')return `<button class="btn" data-doc-action="revise" data-doc-id="${esc(r.id)}">Add revision</button>`;
      return '';
    }
    function render(){
      if (!ownsRoute(TOOL)) return false;
      const main=root.document.querySelector('#main');if(!main)return;
      const s=context.getState();
      const project=s.projects?.find(p=>p.id===s.selectedProjectId)?.name||'Selected project';
      const rows=records.length?records.map(r=>{
        const d=r.drawing?`${esc(r.drawing.drawingNumber||'—')} · ${esc(label(r.drawing.sheetType))}${r.drawing.scale?` · ${esc(r.drawing.scale)}`:''}`:'—';
        return `<tr data-documents-row="${esc(r.id)}">
          <td><strong>${esc(r.revisionCode||'—')}</strong></td>
          <td>${esc(r.title||'—')}</td>
          <td>${esc(label(r.documentType))}</td>
          <td>${esc(label(r.discipline))}</td>
          <td>${esc(label(r.issuePurpose))}</td>
          <td><span class="pill">${esc(label(r.status))}</span></td>
          <td>${d}</td>
          <td>${actionButtons(r)}</td>
        </tr>`;}).join(''):`<tr><td colspan="8">No controlled documents recorded for this project.</td></tr>`;
      main.innerHTML=`
        <div class="documents-workspace" data-documents-tool="${TOOL}">
          <div class="documents-hero">
            <div><h1>Documents &amp; Drawings</h1><p>Project: <strong>${esc(project)}</strong> · ${records.length} revision${records.length===1?'':'s'} recorded</p></div>
            <button class="btn" data-documents-create-toggle>+ New document</button>
          </div>
          <p class="pill" data-documents-status role="status">${esc(BOUNDARY)}</p>
          <section class="panel" data-documents-create-panel hidden>
            <h2>Create controlled document (draft revision)</h2>
            <div class="documents-form">
              <label>Title <input type="text" data-doc-field="title" maxlength="220"></label>
              <label>Document type <select data-doc-field="documentType">${TYPES.map(t=>`<option value="${esc(t)}">${esc(label(t))}</option>`).join('')}</select></label>
              <label>Discipline <select data-doc-field="discipline">${DISCIPLINES.map(t=>`<option value="${esc(t)}">${esc(label(t))}</option>`).join('')}</select></label>
              <label>Issue purpose <select data-doc-field="issuePurpose">${PURPOSES.map(t=>`<option value="${esc(t)}">${esc(label(t))}</option>`).join('')}</select></label>
              <label>Revision code <input type="text" data-doc-field="revisionCode" maxlength="40" value="A"></label>
              <label>Notes <textarea data-doc-field="notes" maxlength="12000"></textarea></label>
              <div data-doc-drawing-fields hidden>
                <label>Drawing number <input type="text" data-doc-field="drawingNumber" maxlength="100"></label>
                <label>Sheet type <select data-doc-field="sheetType">${SHEETS.map(t=>`<option value="${esc(t)}">${esc(label(t))}</option>`).join('')}</select></label>
                <label>Scale <input type="text" data-doc-field="scale" maxlength="40"></label>
              </div>
              <div class="documents-form-actions">
                <button class="btn" data-documents-create-submit>Save draft document</button>
                <button class="btn" data-documents-create-cancel>Cancel</button>
              </div>
            </div>
          </section>
          <section class="panel">
            <h2>Document register</h2>
            <table class="table">
              <thead><tr><th>Rev</th><th>Title</th><th>Type</th><th>Discipline</th><th>Purpose</th><th>Status</th><th>Drawing</th><th>Action</th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </section>
        </div>`;
      bind(main);
    }
    function bind(main){
      const panel=main.querySelector('[data-documents-create-panel]');
      const toggle=main.querySelector('[data-documents-create-toggle]');
      if(toggle&&panel)toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;});
      const cancel=main.querySelector('[data-documents-create-cancel]');
      if(cancel&&panel)cancel.addEventListener('click',()=>{panel.hidden=true;});
      const typeSelect=main.querySelector('[data-doc-field="documentType"]');
      const drawingFields=main.querySelector('[data-doc-drawing-fields]');
      if(typeSelect&&drawingFields){const sync=()=>{drawingFields.hidden=typeSelect.value!=='drawing';};typeSelect.addEventListener('change',sync);sync();}
      const submit=main.querySelector('[data-documents-create-submit]');
      if(submit)submit.addEventListener('click',async()=>{
        const value=k=>main.querySelector('[data-doc-field="'+k+'"]')?.value||'';
        const input={documentType:typeSelect?.value,discipline:value('discipline'),issuePurpose:value('issuePurpose'),title:value('title'),revisionCode:value('revisionCode'),notes:value('notes'),humanConfirmed:false};
        if(input.documentType==='drawing')input.drawing={drawingNumber:value('drawingNumber'),sheetType:value('sheetType'),scale:value('scale')};
        if(!input.title.trim()){notice('Enter a document title. Nothing was saved.');return;}
        if(!root.confirm?.('Create this controlled document as a draft revision for the selected project?'))return;
        input.humanConfirmed=true;submit.disabled=true;
        try{await adapter.create(input);await refresh();notice('Draft document created and verified in the document register.');}
        catch(error){notice(error.message+' No success claimed.');}
        finally{submit.disabled=false;}
      });
      main.querySelectorAll('[data-doc-action]').forEach(btn=>btn.addEventListener('click',async()=>{
        const s=scope();const id=btn.getAttribute('data-doc-id');const action=btn.getAttribute('data-doc-action');
        const record=records.find(r=>r.id===id);if(!record)return;
        const labels={'submit-review':'submit this revision for review','approve':'approve this exact revision','issue':'formally issue this approved revision','revise':'create a new revision of this issued document'};
        const reason=root.prompt?.('Reason to '+labels[action]+':');if(!reason||!reason.trim())return;
        let payload={reason:reason.trim(),humanConfirmed:false};
        if(action==='revise'){
          const code=root.prompt?.('New revision code (e.g. B):');if(!code||!code.trim())return;
          payload={revisionCode:code.trim(),reason:reason.trim(),humanConfirmed:false};
        }
        if(!root.confirm?.('Confirm: '+labels[action]+'? The server records this with your identity.'))return;
        payload.humanConfirmed=true;btn.disabled=true;
        try{
          if(action==='submit-review')await adapter.submitReview(id,payload);
          else if(action==='approve')await adapter.approve(id,payload);
          else if(action==='issue')await adapter.issue(id,payload);
          else if(action==='revise')await adapter.createRevisionTransition(id,payload);
          await refresh();notice('Server state updated and read back: '+action+' completed.');void s;
        }catch(error){notice(error.message+' No success claimed.');}
        finally{btn.disabled=false;}
      }));
    }
    async function refresh(){
      if (!ownsRoute(TOOL)) return false;
      const token=++generation;
      try{await loadRecords();if(disposed || !ownsRoute(TOOL) || token !== generation)return false;render();return true;}
      catch(error){ if (disposed || !ownsRoute(TOOL) || token !== generation) return false;notice(error.message+' No records loaded.');return false;}
    }
    async function apply(toolId){
      if(toolId!==TOOL)return {applied:false,reason:'not-documents'};
      active=true;const ok=await refresh();
      return {applied:ok,tool:TOOL};
    }
    const unsubscribe=typeof context.subscribe==='function'?context.subscribe(()=>{if(active&&!disposed)void refresh();}):null;
    function dispose(){disposed=true;++generation;active=false;if(unsubscribe)unsubscribe();}
    return {apply,dispose,spec:{tool:TOOL,name:'Documents & Drawings',functional:true}};
  }
  root.V1CloneDocuments={install};
})(window);
