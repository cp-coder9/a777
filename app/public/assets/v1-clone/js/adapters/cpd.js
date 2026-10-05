(function(root){
  'use strict';
  // CPD learning is an ORGANISATION-scoped domain (courses, attempts and certificates
  // belong to an organisation + learner, never to a single construction project).
  // So, unlike the site-tools adapter which requires a selected project, every CPD
  // call is scoped to session + organisation only. Every mutating route is guarded by
  // explicit human confirmation and read-back-verified against the workspace before a
  // success is claimed. Nothing is fabricated.
  const unwrap=r=>r?.data??r;
  const HEX32=/^[a-f0-9]{32}$/i;
  const CODE32=/^[A-F0-9]{32}$/i;
  function create(context){
    function scope(){
      const s=context.getState();
      if(!s.ready||!s.session||!s.organisationId)throw Error('Sign in and select an organisation to use CPD learning.');
      if(!HEX32.test(s.organisationId||''))throw Error('Organisation context is not set. Reload the workspace.');
      return {...s};
    }
    function current(s){const now=context.getState();if(!now.ready||!now.session||now.organisationId!==s.organisationId||now.session!==s.session)throw Error('Scope changed; reload this workspace.');}
    function rows(data,key){if(!Array.isArray(data?.[key]))throw Error('Invalid '+key+' response.');return data[key];}
    async function workspace(){
      const s=scope();const data=unwrap(await context.read('/cpd/workspace'));current(s);
      if(!data||typeof data!=='object')throw Error('Invalid CPD workspace response.');
      return data;
    }
    async function listCourses(){
      const s=scope();const data=unwrap(await context.read('/cpd/workspace'));current(s);
      return rows(data,'courses');
    }
    async function createAssessment(input){
      const s=scope();
      if(input.humanConfirmed!==true)throw Error('CPD assessment creation requires explicit human confirmation.');
      if(!HEX32.test(input.contentId||''))throw Error('A valid content id is required to create an assessment.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Assessment save unavailable: shared collection read-back support is required. Nothing was submitted.');
      const body={humanConfirmed:true,contentId:input.contentId,title:input.title,passPercent:input.passPercent,
        attemptLimit:input.attemptLimit??3,timeLimitMinutes:input.timeLimitMinutes??null,questions:input.questions??[]};
      const saved=unwrap(await context.write('/cpd/assessments',body,{readBackPath:'/cpd/workspace',readBackCollection:'assessments',projectScoped:false}));current(s);
      const data=unwrap(await context.read('/cpd/workspace'));current(s);
      const record=rows(data,'assessments').find(r=>r.id===saved?.id);
      if(!record)throw Error('Assessment persistence not verified; refresh before retrying.');
      return record;
    }
    async function attemptAssessment({courseId}={}){
      const s=scope();
      if(!HEX32.test(courseId||''))throw Error('A valid course id is required to start an assessment.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Attempt start unavailable: shared collection read-back support is required. Nothing was submitted.');
      const saved=unwrap(await context.write('/cpd/courses/'+encodeURIComponent(courseId)+'/attempts',{},{
        readBackPath:'/cpd/workspace',readBackCollection:'attempts',projectScoped:false}));current(s);
      const data=unwrap(await context.read('/cpd/workspace'));current(s);
      const record=(data?.attempts||[]).find(r=>r.id===saved?.id);
      if(!record||record.status!=='in_progress')throw Error('Attempt start not verified; refresh before retrying.');
      return record;
    }
    async function scoreAttempt({attemptId,answers,humanConfirmed=false}={}){
      const s=scope();
      if(humanConfirmed!==true)throw Error('Attempt submission requires explicit human confirmation.');
      if(!HEX32.test(attemptId||''))throw Error('A valid attempt id is required.');
      if(!Array.isArray(answers)||answers.length===0)throw Error('Answers are required before submitting an assessment.');
      if(context.capabilities?.readBackCollection!==true)throw Error('Attempt grading unavailable: shared collection read-back support is required. Nothing was submitted.');
      const saved=unwrap(await context.write('/cpd/attempts/'+encodeURIComponent(attemptId)+'/submit',{humanConfirmed:true,answers},{
        readBackPath:'/cpd/workspace',readBackCollection:'attempts',projectScoped:false}));current(s);
      const data=unwrap(await context.read('/cpd/workspace'));current(s);
      const record=(data?.attempts||[]).find(r=>r.id===saved?.id);
      if(!record||!['passed','failed','manual_review_required'].includes(record.status))throw Error('Attempt grading not verified; refresh before retrying.');
      return record;
    }
    async function fetchCertificate(verificationCode){
      const s=scope();
      if(typeof verificationCode!=='string'||!CODE32.test(verificationCode.trim()))throw Error('A valid 32-character certificate verification code is required.');
      const data=unwrap(await context.read('/cpd/certificates/verify/'+encodeURIComponent(verificationCode.trim())));current(s);
      if(!data||data.valid!==true)throw Error('Certificate not found or not valid on the issuing service.');
      return data;
    }
    return {createAssessment,attemptAssessment,scoreAttempt,listCourses,fetchCertificate};
  }
  root.V1CloneCpdAdapter={create};
})(window);
