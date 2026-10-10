(function(root){
  'use strict';
  const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function install({context}){
    // Async work may finish after the shared shell has navigated to another tool.
    const ownsRoute = toolId => { const view = typeof state === 'object' ? state : root.state; return !view || view.tool === undefined || view.tool === toolId; };
    if(!root.V1CloneCpdAdapter||typeof root.V1CloneCpdAdapter.create!=='function')throw Error('The CPD adapter is required before the CPD controller.');
    const adapter=root.V1CloneCpdAdapter.create(context);
    let generation=0, active=false, currentAttempt=null, currentAssessment=null;
    const boundary='Internal CPD learning records only — not statutory certification, not legal professional-standing evidence, and not a council submission. Provider sync stays blocked until a real accredited provider is configured.';
    function target(){
      const doc=root.document;
      if(!doc)return null;
      return (typeof doc.querySelector==='function'&&doc.querySelector('#main'))||doc.body||null;
    }
    function shell(state){
      const role=state?.session?.userId?'Signed in':'Not signed in';
      return '<section class="cpd-workspace m3-commercial-workspace" data-cpd-root>'+
        '<div class="cpd-head"><h2>CPD Learning</h2><p class="cpd-scope">Organisation: '+escape(state?.organisationId||'Not selected')+' · '+escape(role)+'</p></div>'+
        '<p class="cpd-boundary" role="note">'+escape(boundary)+'</p>'+
        '<p class="cpd-status" data-cpd-status role="status">Loading CPD workspace…</p>'+
        '<div class="cpd-grid">'+
          '<section class="cpd-card" aria-labelledby="cpd-courses-title"><h3 id="cpd-courses-title">Published courses</h3><ul class="cpd-courses" data-cpd-courses><li>Loading…</li></ul></section>'+
          '<section class="cpd-card" aria-labelledby="cpd-attempt-title"><h3 id="cpd-attempt-title">Attempt assessment</h3>'+
            '<label>Course <select data-cpd-course></select></label>'+
            '<button class="btn" type="button" data-cpd-start>Start assessment</button>'+
            '<div class="cpd-questionnaire" data-cpd-questionnaire role="group" aria-label="Assessment questions"></div>'+
            '<button class="btn" type="button" data-cpd-submit hidden disabled>Submit answers</button>'+
            '<div class="cpd-score" data-cpd-score aria-live="polite"></div>'+
          '</section>'+
          '<section class="cpd-card" aria-labelledby="cpd-verify-title"><h3 id="cpd-verify-title">Verify certificate</h3>'+
            '<label>Verification code <input type="text" data-cpd-code maxlength="32" autocomplete="off" placeholder="32-character code"></label>'+
            '<button class="btn" type="button" data-cpd-verify>Verify</button>'+
            '<div class="cpd-verify" data-cpd-verify-result aria-live="polite"></div>'+
          '</section>'+
        '</div>'+
      '</section>';
    }
    function courseRow(course){
      return '<li data-cpd-course-row="'+escape(course.id)+'">'+escape(course.title||'Untitled course')+
        ' <span class="cpd-meta">'+escape(course.professionalBody||'—')+' · '+escape(course.approvedCredits||'0')+' credits · '+escape(course.status||'—')+'</span></li>';
    }
    function questionBlock(question,index){
      const type=String(question.questionType||question.type||'single_choice');
      const options=Array.isArray(question.options)?question.options:[];
      const head='<fieldset class="cpd-question" data-cpd-question="'+escape(question.id)+'"><legend>'+escape((index+1)+'. '+(question.prompt||'Question'))+'</legend>';
      if(type==='text_review')return head+'<textarea data-cpd-text rows="3" aria-label="Answer"></textarea></fieldset>';
      const input=type==='multiple_choice'||type==='scenario'?'checkbox':'radio';
      return head+options.map(option=>'<label class="cpd-option"><input type="'+input+'" name="'+escape(question.id)+'" value="'+escape(option.id)+'"> '+escape(option.optionText??option.text??'Option')+'</label>').join('')+'</fieldset>';
    }
    function bind(rootEl){
      rootEl.addEventListener('click',event=>{
        const button=event.target.closest?.('[data-cpd-start],[data-cpd-submit],[data-cpd-verify]');
        if(!button)return;
        if(button.matches('[data-cpd-start]'))startAttempt(rootEl,button);
        else if(button.matches('[data-cpd-submit]'))submitAttempt(rootEl,button);
        else if(button.matches('[data-cpd-verify]'))verifyCertificate(rootEl,button);
      });
    }
    function setStatus(rootEl,message){if (!ownsRoute('cpd_learning')) return;const el=rootEl.querySelector('[data-cpd-status]');if(el)el.textContent=message;}
    async function renderCourses(rootEl){
      const select=rootEl.querySelector('[data-cpd-course]');
      const list=rootEl.querySelector('[data-cpd-courses]');
      try{
        const courses=await adapter.listCourses();
        if(list)list.innerHTML=courses.length?courses.map(courseRow).join(''):'<li>No published courses returned for this organisation.</li>';
        if(select)select.innerHTML=courses.length?courses.map(c=>'<option value="'+escape(c.id)+'">'+escape(c.title||c.id)+'</option>').join(''):'<option value="">No courses available</option>';
        return courses;
      }catch(error){
        if(list)list.innerHTML='<li>'+escape(error.message)+'</li>';
        throw error;
      }
    }
    async function startAttempt(rootEl,button){
      const select=rootEl.querySelector('[data-cpd-course]');
      const questionnaire=rootEl.querySelector('[data-cpd-questionnaire]');
      const submit=rootEl.querySelector('[data-cpd-submit]');
      const score=rootEl.querySelector('[data-cpd-score]');
      const courseId=select?.value;
      if(!courseId){setStatus(rootEl,'Select a course before starting an assessment.');return;}
      button.disabled=true;setStatus(rootEl,'Starting assessment…');
      try{
        const attempt=await adapter.attemptAssessment({courseId});
        // Reload the workspace to resolve the assessment questions for this attempt.
        const ws=await context.read('/cpd/workspace');
        const workspace=ws?.data??ws;
        currentAttempt=attempt;
        currentAssessment=(workspace?.assessments||[]).find(a=>a.id===attempt.assessmentId)||null;
        if(score)score.textContent='';
        if(currentAssessment&&questionnaire){
          questionnaire.innerHTML=currentAssessment.questions.map(questionBlock).join('');
          submit.hidden=false;submit.disabled=false;
          setStatus(rootEl,'Attempt '+attempt.id.slice(0,8)+' started ('+attempt.status+'). Answer every question, then submit.');
        }else{
          questionnaire.innerHTML='';
          submit.hidden=true;submit.disabled=true;
          setStatus(rootEl,'Attempt '+attempt.id.slice(0,8)+' started, but assessment questions were not available in the workspace.');
        }
      }catch(error){
        setStatus(rootEl,error.message+' No success claimed.');
      }finally{button.disabled=false;}
    }
    async function submitAttempt(rootEl,button){
      const questionnaire=rootEl.querySelector('[data-cpd-questionnaire]');
      const score=rootEl.querySelector('[data-cpd-score]');
      if(!currentAttempt){setStatus(rootEl,'Start an assessment before submitting.');return;}
      const answers=[...questionnaire.querySelectorAll('[data-cpd-question]')].map(field=>{
        const questionId=field.dataset.cpdQuestion;
        const text=field.querySelector('[data-cpd-text]');
        if(text)return {questionId,text:text.value.trim()};
        return {questionId,selectedOptionIds:[...field.querySelectorAll('input:checked')].map(input=>input.value)};
      });
      if(!answers.length){setStatus(rootEl,'No questions are available to submit.');return;}
      button.disabled=true;setStatus(rootEl,'Submitting answers…');
      try{
        const record=await adapter.scoreAttempt({attemptId:currentAttempt.id,answers,humanConfirmed:true});
        const approved=currentAssessment?.passPercent;
        if(score)score.innerHTML='<p><strong>Result:</strong> '+escape(record.status)+' · score '+escape(record.finalScore??record.autoScore??'—')+'%'+(approved!=null?' (pass mark '+escape(approved)+'%)':'')+'</p>'+
          (record.passed===true?'<p>A certificate was issued automatically for this passing attempt.</p>':'<p>No certificate is issued for this attempt.</p>');
        setStatus(rootEl,'Attempt '+record.id.slice(0,8)+' graded: '+record.status+'.');
      }catch(error){
        setStatus(rootEl,error.message+' No success claimed.');
      }finally{button.disabled=false;}
    }
    async function verifyCertificate(rootEl,button){
      const input=rootEl.querySelector('[data-cpd-code]');
      const result=rootEl.querySelector('[data-cpd-verify-result]');
      const code=(input?.value||'').trim();
      if(!code){setStatus(rootEl,'Enter a certificate verification code.');return;}
      button.disabled=true;setStatus(rootEl,'Verifying certificate…');
      try{
        const certificate=await adapter.fetchCertificate(code);
        if(result)result.innerHTML='<p><strong>Valid certificate.</strong> '+escape(certificate.learnerName||'—')+' · '+escape(certificate.professionalBody||'—')+' · '+escape(certificate.creditsAwarded||'0')+' credits</p>'+
          '<p class="cpd-meta">Category '+escape(certificate.approvedCategory||'—')+' · Accreditation '+escape(certificate.accreditationReference||'—')+' · Issued '+escape(certificate.issuedAt||'—')+'</p>';
        setStatus(rootEl,'Certificate verified on the issuing service.');
      }catch(error){
        if(result)result.innerHTML='<p>'+escape(error.message)+'</p>';
        setStatus(rootEl,error.message+' No validity claimed.');
      }finally{button.disabled=false;}
    }
    async function apply(toolId){
      if(!ownsRoute('cpd_learning')||toolId!=='cpd_learning')return {applied:false,reason:'not-cpd'};
      const rootEl=target();
      if(!rootEl)return {applied:false,reason:'no-render-target'};
      const token=++generation;active=true;
      const state=context.getState();
      rootEl.innerHTML=shell(state);
      bind(rootEl);
      try{
        const courses=await renderCourses(rootEl);
        if(token!==generation||!ownsRoute('cpd_learning'))return {applied:false,reason:'stale'};
        setStatus(rootEl,courses.length?('Loaded '+courses.length+' published course(s). '+boundary):('No published courses. '+boundary));
        return {applied:true,courses:courses.length};
      }catch(error){
        if(token!==generation||!ownsRoute('cpd_learning'))return {applied:false,reason:'stale'};
        setStatus(rootEl,error.message+' '+boundary);
        return {applied:true,courses:0,error:error.message};
      }
    }
    function dispose(){generation++;active=false;currentAttempt=null;currentAssessment=null;}
    return {apply,dispose};
  }
  root.V1CloneCpd={install};
})(window);
