(function(root,factory){
  const eligibility=factory();
  if(typeof module==='object'&&module.exports)module.exports=eligibility;
  root.ArchitexCpdEligibility=eligibility;
})(typeof window==='undefined'?globalThis:window,function(){
  function course(course,profile){
    if(!profile?.professionalBody)return{eligible:false,label:'Profile required',detail:'Complete your registered professional profile before you can enrol.',attemptDetail:'Complete your registered professional profile before you can submit this attempt.'};
    if(course.professionalBody!==profile.professionalBody)return{eligible:false,label:'Accredited to another professional body',detail:'Your registered body must match before you can enrol.',attemptDetail:'Your registered body must match before you can submit this attempt.'};
    return{eligible:true,label:'Eligible',detail:'',attemptDetail:''};
  }

  function attemptSubmission(attempt,currentCourse,profile,viewerUserId){
    if(attempt.learnerUserId!==viewerUserId)return{eligible:false,label:'Learner-owned attempt',attemptDetail:'This in-progress attempt belongs to another learner and cannot be submitted from your account.'};
    return course(currentCourse,profile);
  }

  return{course,attemptSubmission};
});
