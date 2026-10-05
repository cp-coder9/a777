
(function(){
  const v7old={
    renderMain:window.renderMain,
    renderNavigator:window.renderNavigator,
    renderInspector:window.renderInspector,
    renderTopbar:window.renderTopbar,
    renderProjectSpace:window.renderProjectSpace,
    renderGlobalPage:window.renderGlobalPage,
    toolGroups:window.toolGroups,
    setToolTab:window.setToolTab,
    openGlobal:window.openGlobal
  };

  state.meetingsTab=state.meetingsTab||'my-day';
  state.meetingScreen=state.meetingScreen||'home';
  state.meetingWizardStep=state.meetingWizardStep||0;
  state.meetingRoomPanel=state.meetingRoomPanel||'agenda';
  state.meetingSelectedMinute=state.meetingSelectedMinute||0;
  state.meetingAccepted=state.meetingAccepted||{};
  state.meetingRecording=state.meetingRecording!==false;
  state.meetingTranscription=state.meetingTranscription!==false;
  state.meetingMic=state.meetingMic!==false;
  state.meetingCamera=state.meetingCamera!==false;
  state.meetingCaptions=state.meetingCaptions!==false;

  TOOLS.meetings={
    name:'Architex Meetings',
    icon:'meetings',
    tone:'coral',
    group:'Communication & Collaboration',
    stage:'All stages',
    summary:'Schedule, host and govern project or practice meetings with secure browser calls, agendas, recordings, cited AI minutes, human review and controlled workflow write-backs.',
    status:'live',
    source:'Architex Meetings implementation pack and integrated native prototype',
    tabs:[
      {key:'my-day',label:'My day',group:'Meetings',icon:'meeting_day',kind:'native'},
      {key:'upcoming',label:'Upcoming',group:'Meetings',icon:'calendar',kind:'native'},
      {key:'invitations',label:'Invitations',group:'Meetings',icon:'meeting_invite',kind:'native',badge:'2'},
      {key:'recordings',label:'Recordings & Minutes',group:'Records',icon:'meeting_recording',kind:'native'},
      {key:'reviews',label:'Draft reviews',group:'Records',icon:'meeting_review',kind:'native',badge:'3'},
      {key:'templates',label:'Templates',group:'Configuration',icon:'meeting_template',kind:'native'},
      {key:'settings',label:'Meeting settings',group:'Configuration',icon:'settings',kind:'native'}
    ]
  };

  Object.assign(iconShapes,{
    meetings:'<rect class="outline" x="3" y="5" width="13" height="14" rx="3"/><path class="outline" d="m16 10 5-3v10l-5-3z"/><path class="line" d="M7 9h5M7 13h4"/><path class="fold" d="M12 19h4v-4z"/>',
    meeting_day:'<rect class="outline" x="3" y="4" width="18" height="17" rx="3"/><path class="line" d="M7 2v4M17 2v4M3 9h18M7 13h4M7 17h7"/><path class="fold" d="M16 21h5v-5z"/>',
    meeting_invite:'<path class="outline" d="M3 6h18v13H3z"/><path class="line" d="m3 7 9 7 9-7M17 3v6M14 6h6"/><path class="fold" d="M16 19h5v-5z"/>',
    meeting_recording:'<rect class="outline" x="3" y="5" width="13" height="14" rx="3"/><circle class="line" cx="9.5" cy="12" r="3"/><path class="outline" d="m16 10 5-3v10l-5-3z"/><path class="fold" d="M12 19h4v-4z"/>',
    meeting_review:'<path class="outline" d="M5 3h14v18H5z"/><path class="line" d="M8 8h8M8 12h5M8 16h4M15 15l2 2 4-5"/><path class="fold" d="M15 21h4v-4z"/>',
    meeting_template:'<path class="outline" d="M4 4h16v16H4z"/><path class="line" d="M8 4v16M8 9h12M11 13h6M11 16h4"/><path class="fold" d="M16 20h4v-4z"/>',
    meeting_people:'<circle class="outline" cx="9" cy="8" r="3"/><path class="outline" d="M3 19c.5-4 2.5-6 6-6s5.5 2 6 6"/><circle class="line" cx="17" cy="9" r="2"/><path class="line" d="M15 14c3-.5 5 .8 6 4"/><path class="fold" d="M12 19h4v-4z"/>',
    meeting_agenda:'<path class="outline" d="M5 3h14v18H5z"/><path class="line" d="M8 8h8M8 12h8M8 16h6M3 7h2M3 11h2M3 15h2"/><path class="fold" d="M15 21h4v-4z"/>',
    meeting_policy:'<path class="outline" d="M12 2l8 4v6c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6z"/><path class="line" d="M9 12l2 2 4-5"/><path class="fold" d="M16 16l4-4v5z"/>',
    meeting_transcript:'<path class="outline" d="M4 4h16v16H4z"/><path class="line" d="M7 8h10M7 12h7M7 16h9"/><circle class="line" cx="18" cy="17" r="2"/><path class="fold" d="M15 20h5v-5z"/>',
    meeting_minutes:'<path class="outline" d="M5 2h10l4 4v16H5z"/><path class="line" d="M8 10h8M8 14h8M8 18h5"/><path class="fold" d="M15 2v4h4z"/>',
    meeting_caption:'<rect class="outline" x="3" y="5" width="18" height="14" rx="3"/><path class="line" d="M10 10c-3-2-5 0-5 2s2 4 5 2M19 10c-3-2-5 0-5 2s2 4 5 2"/><path class="fold" d="M16 19h5v-5z"/>',
    meeting_hand:'<path class="outline" d="M7 12V6a2 2 0 0 1 4 0v4-6a2 2 0 0 1 4 0v6-4a2 2 0 0 1 4 0v8c0 5-3 8-8 8-4 0-6-2-8-6l-1-3a2 2 0 0 1 4-1z"/><path class="fold" d="M15 20h4v-4z"/>',
    meeting_mic:'<rect class="outline" x="9" y="3" width="6" height="11" rx="3"/><path class="line" d="M6 11a6 6 0 0 0 12 0M12 17v4M8 21h8"/><path class="fold" d="M15 17h3v-3z"/>',
    meeting_camera:'<rect class="outline" x="3" y="6" width="13" height="12" rx="3"/><path class="outline" d="m16 10 5-3v10l-5-3z"/><path class="fold" d="M12 18h4v-4z"/>',
    meeting_share:'<rect class="outline" x="3" y="4" width="18" height="14" rx="2"/><path class="line" d="M12 15V7M8 11l4-4 4 4M8 21h8"/><path class="fold" d="M16 18h5v-5z"/>',
    meeting_decision:'<circle class="outline" cx="12" cy="12" r="9"/><path class="line" d="m8 12 3 3 5-7"/><path class="fold" d="M16 18l3-3v4z"/>',
    meeting_action:'<path class="outline" d="M5 3h14v18H5z"/><path class="line" d="M8 8h8M8 12h6M8 16h4M16 14v5M13.5 16.5h5"/><path class="fold" d="M15 21h4v-4z"/>'
  });

  const meetingData=window.__architexMeetingData||(window.__architexMeetingData={
    lifecycle:['Draft','Scheduled','Lobby open','Live','Processing','Review required','Published'],
    meetings:[
      {id:'M-1042',time:'09:00',title:'Design coordination — Faerie Glen',type:'Design coordination',stage:'Design',chair:'Justin Kruger',status:'Live in 12 min',attendees:4,actions:3},
      {id:'M-1043',time:'14:30',title:'Site progress meeting — Riverside Offices',type:'Site / progress',stage:'Build',chair:'Site Manager',status:'Scheduled',attendees:8,actions:8},
      {id:'M-1044',time:'16:00',title:'Commercial review — Waterfall Offices',type:'Contract / commercial',stage:'Pay',chair:'Quantity Surveyor',status:'Scheduled',attendees:6,actions:4}
    ],
    upcoming:[
      {date:'23 Jul · 09:00',title:'Consultant coordination',project:'Faerie Glen Residential',stage:'Design',status:'Scheduled'},
      {date:'24 Jul · 11:30',title:'Municipal readiness review',project:'Faerie Glen Residential',stage:'Comply',status:'Review required'},
      {date:'25 Jul · 08:00',title:'Site progress meeting',project:'Riverside Offices',stage:'Build',status:'Scheduled'},
      {date:'28 Jul · 15:00',title:'Tender clarification',project:'Camps Bay Residence',stage:'Procure',status:'Invitation pending'}
    ],
    invitations:[
      {title:'Fire strategy review',organiser:'N. Mokoena · Fire Engineer',when:'24 Jul · 10:00 SAST',scope:'Faerie Glen · fire package only',status:'Respond'},
      {title:'Contractor mobilisation workshop',organiser:'L. Dlamini · CPM',when:'29 Jul · 13:00 SAST',scope:'Riverside Offices · Build stage',status:'Tentative'}
    ],
    recordings:[
      {title:'Municipal readiness review',date:'18 Jul 2026',state:'AI draft · Review required',retention:'86 days remaining'},
      {title:'Design coordination 07',date:'11 Jul 2026',state:'Issued minutes',retention:'79 days remaining'},
      {title:'Client brief workshop',date:'02 Jul 2026',state:'Issued minutes',retention:'70 days remaining'}
    ],
    agenda:[
      {title:'Previous actions',owner:'Chair',minutes:5},
      {title:'Fire-plan review and escape width',owner:'Fire Engineer',minutes:15},
      {title:'Municipal submission readiness',owner:'Architect',minutes:10},
      {title:'Next actions and dates',owner:'Chair',minutes:5}
    ],
    outcomes:[
      {type:'Decision',title:'Fire-plan review precedes municipal pack issue.',owner:'Chair',due:'Before submission',destination:'Project Record',state:'pending',source:'12:04, 12:31'},
      {type:'Action',title:'Issue marked-up fire review.',owner:'Fire Engineer',due:'Thu 23 Jul',destination:'Action Centre',state:'pending',source:'12:31'},
      {type:'Action',title:'Prepare municipal checklist update.',owner:'Architect',due:'Fri 24 Jul',destination:'Action Centre',state:'pending',source:'18:10'},
      {type:'Risk proposal',title:'Submission date may slip if the fire review is late.',owner:'Project Manager',due:'Review today',destination:'Risk Register',state:'pending',source:'12:04'}
    ],
    minutes:[
      {type:'Discussion',text:'The team reviewed the fire-plan escape-width issue against drawing A-204 Rev P03.',source:'08:22–12:03'},
      {type:'Decision candidate',text:'The municipal pack will not be issued until the marked-up fire review is received and checked.',source:'12:04, 12:31'},
      {type:'Action candidate',text:'The Fire Engineer will issue the marked-up review by Thursday. The Architect will update the municipal checklist thereafter.',source:'12:31, 18:10'}
    ],
    transcript:[
      {time:'08:22',speaker:'Architect',text:'The main agenda item is the escape width shown on A-204 revision P03.',confidence:'98%'},
      {time:'12:04',speaker:'Architect',text:'The fire plan remains the blocker. We need the revised escape width before submission.',confidence:'97%'},
      {time:'12:31',speaker:'Fire Engineer',text:'I will issue the marked-up review by Thursday, but this is not a formal approval.',confidence:'96%'},
      {time:'18:10',speaker:'Client',text:'Please proceed with the municipal checklist once that review is received.',confidence:'94%'}
    ]
  });

  const mtgRoleLabels={
    client:'Client',architect:'Architect',bep:'Built Environment Professional',engineer:'Engineer',quantity_surveyor:'Quantity Surveyor',
    town_planner:'Town Planner',land_surveyor:'Land Surveyor',energy_professional:'Energy Professional',fire_engineer:'Fire Engineer',
    cpm:'Construction Project Manager',contractor:'Contractor',subcontractor:'Subcontractor',supplier:'Supplier',site_manager:'Site Manager',
    health_safety:'Health & Safety Officer',developer:'Developer',freelancer:'Freelancer',firm_admin:'Firm Administrator',
    admin:'Administrator',platform_admin:'Platform Administrator'
  };
  const mtgRoleFocus={
    client:'decisions, attendance, project progress and published records',
    architect:'agendas, design coordination, minutes, actions and project record links',
    engineer:'technical agenda items, evidence, actions, RFIs and sign-off boundaries',
    quantity_surveyor:'commercial meetings, valuations, recommendations and payment distinctions',
    cpm:'programme, risk, action ownership and meeting governance',
    contractor:'site progress, instructions, RFIs, safety, quality and claims',
    site_manager:'daily coordination, site evidence, actions and attendance',
    supplier:'scoped invitations, product clarifications, RFQs and delivery actions',
    firm_admin:'practice meetings, templates, policies and records',
    platform_admin:'meeting governance, feedback, security and system health'
  };
  const mtgRoleLabel=()=>mtgRoleLabels[state.role]||'Project member';
  const mtgFocus=()=>mtgRoleFocus[state.role]||'role-relevant meetings, agendas, records and assigned actions';

  const mtgStageMap={
    Brief:['practice','project_passport','planning','fee_proposal','forms','meetings','wingman'],
    Appoint:['practice','professional_directory','fee_proposal','forms','team_workspace','contracts','meetings','project_passport'],
    Design:['practice','specforge','xa','forms','bom','documents_drawings','meetings','wingman'],
    Comply:['planning','municipal','xa','forms','environmental_heritage','fire_safety','meetings','wingman'],
    Procure:['practice','bom','specforge','rfq_marketplace','supplier_catalog','contracts','meetings','wingman'],
    Build:['practice','safety','itp','site_instructions','ncr_manager','contractor_compliance','snag_manager','meetings','forms'],
    Pay:['practice','payments_escrow','valuations','contract_admin','expense_claim','forms','meetings','feedback','wingman'],
    'Close-out':['practice','snag_manager','documents_drawings','forms','project_passport','meetings','cpd','feedback','wingman']
  };
  const mtgStageCopy={
    Brief:'Define outcomes, constraints, viability and the initial professional scope.',
    Appoint:'Build the team, agree fees, execute appointments and allocate responsibilities.',
    Design:'Coordinate design information, specifications, quantities and technical reviews.',
    Comply:'Resolve statutory, municipal, SANS and specialist compliance.',
    Procure:'Issue packages and RFQs, compare offers and align awards with the programme.',
    Build:'Coordinate site execution, safety, quality, RFIs, instructions and progress.',
    Pay:'Manage valuations, claims, invoices, approvals and payment governance.',
    'Close-out':'Complete snags, handover records, certificates, final accounts and archive.'
  };
  const mtgRolePriority={
    client:['meetings','project_passport','practice','forms','documents_drawings','payments_escrow','wingman'],
    architect:['meetings','practice','project_passport','planning','municipal','xa','forms','specforge','bom','wingman'],
    engineer:['meetings','practice','itp','site_instructions','ncr_manager','xa','documents_drawings','wingman'],
    quantity_surveyor:['meetings','practice','bom','rfq_marketplace','payments_escrow','valuations','contracts','wingman'],
    town_planner:['meetings','planning','municipal','forms','project_passport','wingman'],
    cpm:['meetings','practice','safety','itp','site_instructions','ncr_manager','snag_manager','wingman'],
    contractor:['meetings','practice','safety','itp','site_instructions','ncr_manager','snag_manager','forms','wingman'],
    subcontractor:['meetings','practice','safety','itp','site_instructions','forms','wingman'],
    supplier:['meetings','supplier_catalog','rfq_marketplace','specforge','bom','forms','wingman'],
    site_manager:['meetings','practice','safety','itp','site_instructions','ncr_manager','snag_manager','wingman'],
    firm_admin:['meetings','practice','forms','feedback','wingman'],
    platform_admin:Object.keys(TOOLS)
  };

  function mtgRoleBanner(){
    return `<div class="role-banner"><div class="role-avatar">${mtgRoleLabel().split(/\s+/).map(x=>x[0]).join('').slice(0,2)}</div><div class="role-copy"><b>${mtgRoleLabel()} meeting experience</b><span>The meeting workspace prioritises ${mtgFocus()}.</span></div><div class="role-tags"><span>Project-aware</span><span>Human-published minutes</span><span>Audited</span></div></div>`;
  }
  function mtgContextRibbon(){
    return `<div class="mtg-context-ribbon"><div class="dc-icon">${icon('meetings',22)}</div><b>${state.mode==='project'?project.name:'Practice meeting space'}</b><span>${state.mode==='project'?project.stage+' stage · Project Passport '+project.revision:'Organisation-wide · project optional'}</span><span class="spacer"></span><span class="mtg-orientation">${state.mode==='project'?'Project-connected':'Standalone'}</span><span>Viewing as <b>${mtgRoleLabel()}</b></span></div>`;
  }
  function mtgPipeline(active='Scheduled'){
    return `<div class="mtg-pipeline">${meetingData.lifecycle.map((x,i)=>{const ai=meetingData.lifecycle.indexOf(active),cl=i<ai?'done':i===ai?'active':'';return `<div class="mtg-pipeline-step ${cl}"><i>${i+1}</i><span>${x}</span></div>`}).join('')}</div>`;
  }
  function mtgStatusClass(s){return /live|record/i.test(s)?'live':/review|draft/i.test(s)?'ai':/pending|warning|tentative/i.test(s)?'warn':/issued|published|accepted/i.test(s)?'ok':''}

  window.openMeetings=function(mode=state.mode){
    state.tool='meetings';state.mode=mode;state.global=mode==='project'?'projects':'inbox';state.toolTab=0;state.meetingsTab='my-day';state.meetingScreen='home';renderAll();
  };
  window.openMeetingScreen=function(screen){
    state.meetingScreen=screen;
    if(screen==='review')state.meetingsTab='reviews';
    renderNavigator();renderMain();renderInspector();updateFeedbackContext();
  };
  window.setMeetingWizardStep=function(step){
    state.meetingWizardStep=Math.max(0,Math.min(4,step));
    renderMain();
  };
  window.setMeetingRoomPanel=function(panel){
    state.meetingRoomPanel=panel;renderMain();
  };
  window.toggleMeetingControl=function(control){
    const key={mic:'meetingMic',camera:'meetingCamera',captions:'meetingCaptions',recording:'meetingRecording',transcription:'meetingTranscription'}[control];
    if(key){state[key]=!state[key];renderMain();renderInspector();}
  };
  window.setMeetingOutcome=function(index,newState){
    meetingData.outcomes[index].state=newState;renderMain();renderInspector();
  };
  window.selectMeetingMinute=function(index){state.meetingSelectedMinute=index;renderMain();}
  window.publishMeetingMinutes=function(){
    const pending=meetingData.outcomes.filter(x=>x.state==='pending').length;
    if(pending){showToast(`${pending} proposed outcomes still require review.`);return}
    state.meetingScreen='issued';showToast('Issued minutes created and approved write-backs queued exactly once.');renderMain();renderNavigator();renderInspector();
  };
  window.scheduleMeetingFromTemplate=function(name){
    state.meetingWizardStep=1;state.meetingScreen='schedule';showToast(`${name} template loaded.`);renderMain();
  };

  function renderMeetingHome(){
    return `${mtgPipeline('Scheduled')}<div class="mtg-grid">
      <section class="mtg-card large"><div class="mtg-actions"><div><h2>Today</h2><p>Your project conversations and governed records.</p></div><span class="spacer"></span><button class="btn ghost" onclick="openMeetingScreen('prejoin')">Meet now</button><button class="btn" onclick="openMeetingScreen('schedule')">Schedule meeting</button></div>
        <div class="mtg-timeline">${meetingData.meetings.slice(0,2).map((m,i)=>`<div class="mtg-row" onclick="openMeetingScreen('${i===0?'prejoin':'schedule'}')"><span class="mtg-time">${m.time}</span><div><b>${m.title}</b><small>${m.type} · ${m.stage} stage · ${m.attendees} participants · ${m.actions} open actions</small></div><span class="mtg-badge ${mtgStatusClass(m.status)}">${m.status}</span></div>`).join('')}</div>
      </section>
      <section class="mtg-card review"><h2>Minutes requiring review</h2><p><b>Municipal readiness review</b><br>AI draft · 6 decisions · 9 proposed actions</p><div class="mtg-actions"><span class="mtg-badge ai">Review required</span><button class="btn ghost" onclick="openMeetingScreen('review')">Review minutes</button></div></section>
      <section class="mtg-card"><h3>Upcoming this week</h3><p>7 meetings across 4 projects.</p><b>3 linked to stage gates</b></section>
      <section class="mtg-card"><h3>Open meeting actions</h3><p>18 open · 4 overdue · 6 awaiting others.</p><button class="btn ghost" onclick="openTool('practice',state.mode)">Open Action Centre</button></section>
      <section class="mtg-card"><h3>Processing</h3><p>1 transcript processing<br>1 recording retention warning</p><span class="mtg-badge warn">Recording unavailable on one item</span></section>
    </div>`;
  }

  function renderMeetingUpcoming(){
    return `<div class="mtg-filterbar"><input placeholder="Search meetings"><select><option>All projects</option><option>${project.name}</option><option>Riverside Offices</option></select><select><option>All meeting types</option><option>Design coordination</option><option>Site / progress</option><option>Commercial</option></select><span class="spacer"></span><button class="btn" onclick="openMeetingScreen('schedule')">Schedule</button></div><div class="mtg-list">${meetingData.upcoming.map((m,i)=>`<article class="mtg-list-item" onclick="openMeetingScreen('${i===0?'prejoin':'schedule'}')"><div class="mtg-list-icon">${icon('meetings',23)}</div><div><b>${m.title}</b><small>${m.date} · ${m.project} · ${m.stage} stage</small></div><span class="mtg-badge ${mtgStatusClass(m.status)}">${m.status}</span></article>`).join('')}</div>`;
  }
  function renderMeetingInvitations(){
    return `<div class="mtg-list">${meetingData.invitations.map((m,i)=>`<article class="mtg-list-item"><div class="mtg-list-icon">${icon('meeting_invite',23)}</div><div><b>${m.title}</b><small>${m.organiser} · ${m.when}<br>Guest/project scope: ${m.scope}</small></div><div class="mtg-actions"><button class="btn ghost" onclick="showToast('Invitation declined.')">Decline</button><button class="btn" onclick="showToast('Invitation accepted and calendar updated.')">Accept</button></div></article>`).join('')}</div><div class="mtg-policy"><strong>Scoped access:</strong> External guests see the meeting title, policy, permitted agenda/pre-reads and room only. Project navigation and unrelated records remain unavailable.</div>`;
  }
  function renderMeetingRecordings(){
    return `<div class="mtg-filterbar"><input placeholder="Search recordings, transcripts or minutes"><select><option>All states</option><option>AI draft</option><option>Issued minutes</option><option>Superseded</option></select></div><div class="mtg-list">${meetingData.recordings.map((m,i)=>`<article class="mtg-list-item" onclick="openMeetingScreen('${i===0?'review':'issued'}')"><div class="mtg-list-icon">${icon(i===0?'meeting_transcript':'meeting_minutes',23)}</div><div><b>${m.title}</b><small>${m.date} · ${m.retention}</small></div><span class="mtg-badge ${mtgStatusClass(m.state)}">${m.state}</span></article>`).join('')}</div>`;
  }
  function renderMeetingTemplates(){
    const templates=[
      ['Client brief','Brief','Outcomes, constraints, budget, responsibilities and next decisions.'],
      ['Design coordination','Design','Drawing revisions, consultant interfaces, decisions, actions and risks.'],
      ['Statutory review','Comply','Submission readiness, blockers, authority comments and responsibilities.'],
      ['Tender clarification','Procure','Equal clarification record, package questions and controlled responses.'],
      ['Site progress','Build','Programme, safety, quality, instructions, RFIs, claims and upcoming work.'],
      ['Commercial review','Pay','Valuations, variations, recommendations, approvals and payment status.'],
      ['Handover & close-out','Close-out','Snags, certificates, warranties, training and final account actions.'],
      ['Internal practice','Standalone','Resources, fees, forecasts, profitability and operational actions.'],
      ['CPD / training','Standalone','Attendance, learning outcomes, recording policy and issued certificate links.']
    ];
    return `<div class="mtg-template-grid">${templates.map(t=>`<article class="mtg-template" onclick="scheduleMeetingFromTemplate('${t[0]}')"><div class="mtg-list-icon">${icon('meeting_template',23)}</div><b>${t[0]}</b><small>${t[1]} · ${t[2]}</small></article>`).join('')}</div>`;
  }
  function renderMeetingSettings(){
    return `<div class="mtg-grid"><section class="mtg-card large"><h2>Organisation meeting policy</h2><div class="mtg-fields"><div class="mtg-field"><label>Default lobby</label><select><option>Host admits guests</option><option>Project members auto-admitted</option></select></div><div class="mtg-field"><label>Default retention class</label><select><option>90 days</option><option>Project duration + 5 years</option><option>No recording</option></select></div><div class="mtg-field"><label>Recording policy</label><select><option>Explicit consent required</option><option>Disabled by default</option></select></div><div class="mtg-field"><label>Transcription language</label><select><option>English (South Africa)</option><option>Auto-detect</option></select></div></div></section><section class="mtg-card"><h3>Guest access</h3><p>Meeting-only, expiring invitations with lobby admission and revocation.</p><span class="mtg-badge ok">Scoped access enforced</span></section><section class="mtg-card"><h3>Wingman</h3><p>AI minutes remain draft until an authorised chair or minute-taker publishes them.</p><span class="mtg-badge ai">Human review required</span></section><section class="mtg-card"><h3>Calendar projections</h3><p>Architex is canonical. Microsoft, Google and ICS are external projections.</p><button class="btn ghost" onclick="showToast('Calendar connector settings opened.')">Manage connectors</button></section></div>`;
  }

  function wizardStepContent(){
    const step=state.meetingWizardStep;
    if(step===0)return `<div class="mtg-fields"><div class="mtg-field"><label>Orientation</label><select><option>${state.mode==='project'?'Project-connected':'Standalone practice meeting'}</option></select></div><div class="mtg-field"><label>Meeting type</label><select><option>Design coordination</option><option>Client brief</option><option>Site / progress</option><option>Contract / commercial</option></select></div><div class="mtg-field"><label>Project</label><select><option>${state.mode==='project'?project.name:'No project attached'}</option><option>Camps Bay Residence</option></select></div><div class="mtg-field"><label>Work stage</label><select><option>${project.stage}</option><option>Comply</option><option>Build</option></select></div><div class="mtg-field full"><label>Title</label><input value="Design coordination — ${project.name}"></div><div class="mtg-field full"><label>Linked records</label><input value="A-204 Rev P03 · Municipal readiness · Fire review blocker"></div></div>`;
    if(step===1)return `<div><h2>Agenda</h2><p>Agenda sections retain owners, time allocations, linked records and prior actions.</p>${meetingData.agenda.map((a,i)=>`<div class="mtg-agenda-item"><i>${i+1}</i><input value="${a.title}"><input value="${a.owner}"><input type="number" value="${a.minutes}"></div>`).join('')}<button class="btn ghost" onclick="showToast('Agenda item added.')">Add agenda item</button></div>`;
    if(step===2)return `<div><h2>People</h2><p>Invite by Architex identity, project role or email. Meeting role and project authority remain separate.</p>${[['JK','Justin Kruger','Chair · Architect'],['NM','N. Mokoena','Fire Engineer'],['LS','L. Smith','Client'],['MP','M. Patel','Minute-taker']].map((a,i)=>`<div class="mtg-attendee"><div class="mtg-avatar">${a[0]}</div><div><b>${a[1]}</b><small>${a[2]} · SAST timezone</small></div><span class="mtg-badge ${i===0?'ok':''}">${i===0?'Host':'Invited'}</span></div>`).join('')}<button class="btn ghost" onclick="showToast('Attendee selector opened.')">Add attendee or guest</button></div>`;
    if(step===3)return `<div class="mtg-fields"><div class="mtg-field"><label>Date</label><input type="date" value="2026-07-24"></div><div class="mtg-field"><label>Start time · SAST</label><input type="time" value="09:00"></div><div class="mtg-field"><label>Duration</label><select><option>45 minutes</option><option>60 minutes</option><option>90 minutes</option></select></div><div class="mtg-field"><label>Recurrence</label><select><option>Does not repeat</option><option>Weekly</option><option>Fortnightly</option></select></div><div class="mtg-field full"><label>Availability</label><div class="mtg-policy"><strong>All timezones visible:</strong> 09:00 SAST · 08:00 BST · 07:00 UTC. One attendee has a soft conflict at 09:30.</div></div></div>`;
    return `<div class="mtg-fields"><div class="mtg-field"><label>Lobby policy</label><select><option>Host admits guests</option></select></div><div class="mtg-field"><label>Confidentiality</label><select><option>Project confidential</option><option>Commercially sensitive</option><option>Legally privileged</option></select></div><div class="mtg-field"><label>Recording</label><select><option>Ask informed consent before recording</option><option>Recording off</option></select></div><div class="mtg-field"><label>Transcription</label><select><option>On after consent</option><option>Off</option></select></div><div class="mtg-field"><label>Retention</label><select><option>90 days</option><option>Project retention policy</option></select></div><div class="mtg-field"><label>Reminder</label><select><option>24 hours and 15 minutes</option></select></div><div class="mtg-field full"><div class="mtg-policy"><strong>Policy step cannot be skipped.</strong> Recording and AI processing states remain persistently visible and audited. Confidential or privileged content may be excluded or redacted before AI processing.</div></div></div>`;
  }
  function renderScheduleWizard(){
    const names=['Context','Agenda','People','Time','Policy & Send'];
    return `${mtgPipeline('Draft')}<div class="mtg-wizard"><section class="mtg-wizard-main"><div class="mtg-wizard-steps">${names.map((x,i)=>`<div class="mtg-wizard-step ${i<state.meetingWizardStep?'done':i===state.meetingWizardStep?'active':''}">${i+1}. ${x}</div>`).join('')}</div>${wizardStepContent()}<div class="mtg-actions" style="margin-top:17px"><button class="btn ghost" onclick="openMeetingScreen('home')">Cancel</button><span class="spacer"></span>${state.meetingWizardStep?`<button class="btn ghost" onclick="setMeetingWizardStep(${state.meetingWizardStep-1})">Back</button>`:''}${state.meetingWizardStep<4?`<button class="btn" onclick="setMeetingWizardStep(${state.meetingWizardStep+1})">Continue</button>`:`<button class="btn" onclick="showToast('Meeting scheduled. Calendar, invitations, notifications and audit updated.');openMeetingScreen('home')">Preview invitation & send</button>`}</div></section><aside class="mtg-summary"><h3>Validation summary</h3><ul><li class="ok">✓ Project and stage selected</li><li class="ok">✓ Agenda has owners and time</li><li class="${state.meetingWizardStep>=2?'ok':''}">${state.meetingWizardStep>=2?'✓':'○'} Chair and attendees assigned</li><li class="${state.meetingWizardStep>=3?'ok':''}">${state.meetingWizardStep>=3?'✓':'○'} Timezone-safe time selected</li><li class="${state.meetingWizardStep>=4?'ok':''}">${state.meetingWizardStep>=4?'✓':'○'} Recording, retention and confidentiality policy confirmed</li></ul><div class="mtg-policy"><strong>Safe boundary:</strong> Scheduling does not grant project access beyond existing membership or scoped guest permission.</div></aside></div>`;
  }

  function renderPreJoin(){
    return `${mtgPipeline('Lobby open')}<div class="mtg-prejoin"><section class="mtg-preview"><span class="mtg-badge ok mtg-network">Good network · 28 ms</span><div class="mtg-preview-person"><div class="mtg-avatar">JK</div><b>Justin Kruger</b><span>Camera preview · ${state.meetingCamera?'Camera on':'Camera off'}</span></div></section><aside class="mtg-card"><h2>Ready to join?</h2><p>Design coordination — ${project.name}</p><div class="mtg-device-grid"><div class="mtg-device"><label>Camera</label><select><option>Integrated camera</option></select></div><div class="mtg-device"><label>Microphone</label><select><option>Default microphone</option></select></div><div class="mtg-device"><label>Speaker</label><select><option>Default speakers</option></select></div></div><div class="mtg-actions" style="margin:12px 0"><button class="btn ghost" onclick="toggleMeetingControl('mic')">${state.meetingMic?'Mute':'Unmute'}</button><button class="btn ghost" onclick="toggleMeetingControl('camera')">${state.meetingCamera?'Camera off':'Camera on'}</button><button class="btn ghost" onclick="showToast('Background selector opened.')">Background</button></div><div class="mtg-policy"><strong>Recording off until consent gate passes.</strong><br>The host may request recording and transcription. Persistent visual and audible indicators will remain active.</div><label class="mtg-check" style="margin-top:12px"><input id="mtgConsent" type="checkbox"><span>I have reviewed the confidentiality, recording, transcription and retention notice.</span></label><button class="btn" style="width:100%;margin-top:12px;min-height:44px" onclick="if(!document.getElementById('mtgConsent').checked){showToast('Review and acknowledge the meeting policy first.')}else{openMeetingScreen('room')}">Join meeting</button><button class="btn ghost" style="width:100%;margin-top:7px" onclick="showToast('Audio-only mode selected.')">Join audio-only</button></aside></div>`;
  }

  function roomPanel(){
    if(state.meetingRoomPanel==='people')return `<h3>People · 4</h3>${[['JK','Justin Kruger','Chair · Architect'],['NM','N. Mokoena','Fire Engineer'],['LS','L. Smith','Client'],['AI','Wingman','Visible meeting assistant']].map(x=>`<div class="mtg-chat"><b>${x[0]} · ${x[1]}</b><p>${x[2]}</p></div>`).join('')}`;
    if(state.meetingRoomPanel==='chat')return `<h3>Meeting chat</h3><div class="mtg-chat"><b>Fire Engineer · 09:18</b><p>I have linked the current markup to agenda item 2.</p></div><div class="mtg-chat"><b>Architect · 09:20</b><p>Please note that the markup is evidence only, not an issued revision.</p></div>`;
    if(state.meetingRoomPanel==='context')return `<h3>Project context</h3><div class="mtg-note"><b>A-204 Rev P03</b><br>Current drawing discussed on screen.</div><div class="mtg-note"><b>Municipal readiness 82%</b><br>Fire review remains a blocker.</div><div class="mtg-note"><b>Prior action</b><br>Confirm escape-width design responsibility.</div>`;
    return `<h3>Agenda & notes</h3>${meetingData.agenda.map((a,i)=>`<div class="mtg-note"><b>${i+1}. ${a.title}</b><br>${a.owner} · ${a.minutes} min${i===1?'<br><span style="color:#9ee8df">● Current agenda item</span>':''}</div>`).join('')}<button class="btn ghost" style="width:100%" onclick="showToast('Timestamped decision marker captured.')">Mark decision candidate</button>`;
  }
  function renderLiveRoom(){
    return `${mtgPipeline('Live')}<div class="mtg-room"><div class="mtg-room-top"><b>Design coordination — ${project.name}</b><span class="spacer"></span><button class="mtg-record-state" onclick="toggleMeetingControl('recording')">● Recording ${state.meetingRecording?'on':'off'}</button><button class="mtg-ai-state" onclick="toggleMeetingControl('transcription')">✦ Transcription ${state.meetingTranscription?'on':'off'}</button><span class="mtg-badge">00:38:12</span></div><div class="mtg-room-body"><div class="mtg-stage"><div class="mtg-share"><div class="mtg-drawing">A-204 · Ground-floor plan · Rev P03</div><div class="mtg-annotation"></div><span class="mtg-annotation-copy">Confirm escape width</span></div><div class="mtg-people-strip"><div class="mtg-person speaking"><div><b>JK</b><small>Chair · Architect</small></div></div><div class="mtg-person"><div><b>NM</b><small>Fire Engineer</small></div></div><div class="mtg-person"><div><b>LS</b><small>Client</small></div></div><div class="mtg-person"><div><b>AI</b><small>Wingman · Visible</small></div></div></div></div><aside class="mtg-room-panel"><div class="mtg-room-tabs">${[['people','People'],['chat','Chat'],['agenda','Agenda'],['context','Context']].map(x=>`<button class="${state.meetingRoomPanel===x[0]?'active':''}" onclick="setMeetingRoomPanel('${x[0]}')">${x[1]}</button>`).join('')}</div><div class="mtg-room-panel-body">${roomPanel()}</div></aside></div><div class="mtg-controls"><button class="mtg-control ${state.meetingMic?'':'off'}" onclick="toggleMeetingControl('mic')" title="Microphone">${icon('meeting_mic',22)}</button><button class="mtg-control ${state.meetingCamera?'':'off'}" onclick="toggleMeetingControl('camera')" title="Camera">${icon('meeting_camera',22)}</button><button class="mtg-control" onclick="showToast('Screen share selector opened.')" title="Share screen">${icon('meeting_share',22)}</button><button class="mtg-control" onclick="showToast('Hand raised.')" title="Raise hand">${icon('meeting_hand',22)}</button><button class="mtg-control ${state.meetingCaptions?'':'off'}" onclick="toggleMeetingControl('captions')" title="Captions">${icon('meeting_caption',22)}</button><button class="mtg-control" onclick="showToast('More controls opened.')" title="More">•••</button><button class="mtg-control leave" onclick="openMeetingScreen('review')">End for all</button></div></div>`;
  }

  function renderMinutesReview(){
    return `${mtgPipeline('Review required')}<div class="mtg-actions"><div><h2 style="margin:0">Municipal readiness review</h2><p style="margin:4px 0;color:var(--muted);font-size:12px">AI draft · sources linked · chair review required</p></div><span class="spacer"></span><span class="mtg-badge ai">AI draft</span><button class="btn" onclick="publishMeetingMinutes()">Publish minutes & reviewed outputs</button></div><div class="mtg-review-layout"><section class="mtg-review-col"><div class="mtg-review-head"><h3>Transcript</h3><p>Speaker · timestamp · confidence</p></div><div class="mtg-review-body">${meetingData.transcript.map((x,i)=>`<div class="mtg-segment ${state.meetingSelectedMinute===Math.min(i,2)?'active':''}" onclick="selectMeetingMinute(${Math.min(i,2)})"><small>${x.time} · ${x.speaker} · ${x.confidence}</small>${x.text}</div>`).join('')}</div></section><section class="mtg-review-col"><div class="mtg-review-head"><h3>Minutes editor</h3><p>Discussion, decisions and actions remain distinct.</p></div><div class="mtg-review-body">${meetingData.minutes.map((x,i)=>`<div class="mtg-minute ${state.meetingSelectedMinute===i?'active':''}" onclick="selectMeetingMinute(${i})"><label>${x.type}</label><textarea>${x.text}</textarea><div class="mtg-source">Source: ${x.source}</div></div>`).join('')}</div></section><section class="mtg-review-col"><div class="mtg-review-head"><h3>Outcomes review</h3><p>Nothing writes back until explicitly accepted.</p></div><div class="mtg-review-body">${meetingData.outcomes.map((x,i)=>`<div class="mtg-outcome"><div class="mtg-outcome-head">${icon(x.type==='Decision'?'meeting_decision':'meeting_action',19)}<b>${x.type}</b><span class="mtg-badge ${x.state==='accepted'?'ok':x.state==='rejected'?'live':'warn'}">${x.state}</span></div><p><b>${x.title}</b><br>Owner: ${x.owner} · Due: ${x.due}<br>Destination: ${x.destination} · Sources: ${x.source}</p><div class="mtg-outcome-actions"><button class="${x.state==='accepted'?'accepted':''}" onclick="setMeetingOutcome(${i},'accepted')">Accept</button><button class="${x.state==='rejected'?'rejected':''}" onclick="setMeetingOutcome(${i},'rejected')">Reject</button><button onclick="showToast('Outcome editor opened.')">Edit</button></div></div>`).join('')}</div></section></div>`;
  }
  function renderIssuedMinutes(){
    return `${mtgPipeline('Published')}<div class="mtg-issued"><article class="mtg-document"><span class="mtg-badge ok">Issued minutes · Revision 01</span><h1>Municipal readiness review</h1><p>${project.name} · Design / Comply interface · 18 July 2026 · 10:00–10:42 SAST</p><h2>Attendance</h2><p>Justin Kruger — Chair / Architect · N. Mokoena — Fire Engineer · L. Smith — Client · M. Patel — Minute-taker.</p><h2>Discussion</h2><p>The team reviewed the escape-width issue shown on A-204 Rev P03. The marked-up drawing remains meeting evidence and is not an issued revision.</p><h2>Decision</h2><p>The municipal pack will not be issued until the Fire Engineer’s marked-up review has been received and checked. This minute does not manufacture a formal approval or instruction.</p><h2>Actions</h2><ol><li>Fire Engineer to issue the marked-up review by Thursday.</li><li>Architect to update the municipal checklist after receipt.</li><li>Project Manager to review the proposed programme risk.</li></ol><div class="mtg-signoff"><div><b>Reviewed by</b><p>Justin Kruger · Chair<br>Issued 18 Jul 2026 · 15:42</p></div><div><b>Record integrity</b><p>Transcript sources retained<br>Audit reference: AUD-M-1042-R01</p></div></div></article><aside class="mtg-issued-side"><div class="mtg-side-card"><h3>Write-backs</h3><p>✓ Meeting minutes document<br>✓ ProjectRecord<br>✓ 2 Action Centre tasks<br>✓ Project Passport summary<br>○ 1 risk proposal awaiting owner review</p></div><div class="mtg-side-card"><h3>Revision history</h3><p>Revision 01 · Current<br>Corrections create a new revision and never overwrite issued minutes.</p></div><div class="mtg-side-card"><h3>Source access</h3><p>Recording and transcript links remain permission-aware and follow the 90-day retention policy.</p></div><button class="btn ghost" onclick="showToast('Correction request opened.')">Request correction</button></aside></div>`;
  }

  function renderMeetingsTool(){
    const tab=state.meetingsTab;
    let content='';
    if(state.meetingScreen==='schedule')content=renderScheduleWizard();
    else if(state.meetingScreen==='prejoin')content=renderPreJoin();
    else if(state.meetingScreen==='room')content=renderLiveRoom();
    else if(state.meetingScreen==='review')content=renderMinutesReview();
    else if(state.meetingScreen==='issued')content=renderIssuedMinutes();
    else if(tab==='upcoming')content=renderMeetingUpcoming();
    else if(tab==='invitations')content=renderMeetingInvitations();
    else if(tab==='recordings')content=renderMeetingRecordings();
    else if(tab==='reviews')content=renderMinutesReview();
    else if(tab==='templates')content=renderMeetingTemplates();
    else if(tab==='settings')content=renderMeetingSettings();
    else content=renderMeetingHome();
    const titleMap={schedule:'Schedule meeting',prejoin:'Pre-join',room:'Live room',review:'Minutes review',issued:'Issued minutes'};
    const title=titleMap[state.meetingScreen]||TOOLS.meetings.tabs.find(x=>x.key===tab)?.label||'Meetings';
    return `${pageHead('Architex Meetings — '+title,'The origin, host and governed system of record for project and practice meetings.','meetings','coral',`${state.mode==='standalone'?`<button class="btn ghost" onclick="attachProject()">Attach ${project.name}</button>`:''}<button class="btn ghost" onclick="openMeetingScreen('prejoin')">Meet now</button><button class="btn" onclick="openMeetingScreen('schedule')">Schedule</button>`)}${mtgRoleBanner()}<div class="mtg-shell">${mtgContextRibbon()}${content}</div>`;
  }

  function meetingsNavigator(){
    const nav=q('#navigator'),t=TOOLS.meetings,groups={};
    t.tabs.forEach(x=>(groups[x.group]??=[]).push(x));
    const backAction=state.mode==='project'?'openProjectSpace()':"openGlobal('inbox')";
    nav.innerHTML=`<div class="nav-head"><div class="nav-compact-mark" title="Architex Meetings">${icon('meetings',27)}</div><div class="kicker">${state.mode==='project'?'Project-connected tool':'Standalone meeting space'}</div><div class="nav-title">Architex Meetings</div><div class="nav-sub">${state.mode==='project'?project.name+' · '+project.stage+' stage':'Project and practice meetings'}</div>${modeSwitch()}</div><div class="back-row" onclick="${backAction}" title="${state.mode==='project'?'Back to datum project space':'Back to Inbox & Collaboration'}">${icon(state.mode==='project'?'projects':'inbox',18)}<span class="nav-label">${state.mode==='project'?'Back to datum project space':'Inbox & Collaboration'}</span></div><div class="nav-note"><b>${mtgRoleLabel()} view</b><br>Prioritises ${mtgFocus()}.</div>${Object.entries(groups).map(([g,a])=>`<div class="nav-section"><div class="nav-heading">${g}</div>${a.map(x=>{const i=t.tabs.indexOf(x);return `<button class="nav-row ${state.meetingsTab===x.key&&state.meetingScreen==='home'?'active':''}" style="${toneStyle('coral')}" onclick="setToolTab(${i})" title="${x.label}"><span class="nav-icon">${icon(x.icon,21)}</span><span class="nav-label">${x.label}</span>${x.badge?`<span class="badge">${x.badge}</span>`:''}</button>`}).join('')}</div>`).join('')}<div class="nav-section"><div class="nav-heading">Meeting pipeline</div>${[['schedule','Schedule wizard','meeting_agenda'],['prejoin','Pre-join & lobby','meeting_people'],['room','Live room','meeting_camera'],['review','Minutes review','meeting_review'],['issued','Issued minutes','meeting_minutes']].map(x=>`<button class="nav-row ${state.meetingScreen===x[0]?'active':''}" style="${toneStyle('coral')}" onclick="openMeetingScreen('${x[0]}')" title="${x[1]}"><span class="nav-icon">${icon(x[2],21)}</span><span class="nav-label">${x[1]}</span></button>`).join('')}</div><div class="nav-note"><b>Governed outputs:</b><br>Meeting discussion never bypasses authority, contract, document or project workflows.</div>`;
  }

  function collaborationNavigator(){
    const nav=q('#navigator');
    nav.innerHTML=`<div class="nav-head"><div class="nav-compact-mark" title="Inbox & Collaboration">${icon('inbox',27)}</div><div class="kicker">Global workspace</div><div class="nav-title">Inbox & Collaboration</div><div class="nav-sub">Conversations, meetings, notices and shared actions.</div></div><div class="nav-section"><div class="nav-heading">Collaboration</div><button class="nav-row active" style="${toneStyle('coral')}" onclick="openGlobal('inbox')" title="Collaboration home"><span class="nav-icon">${icon('inbox',21)}</span><span class="nav-label">Collaboration home</span></button><button class="nav-row" style="${toneStyle('coral')}" onclick="openMeetings('standalone')" title="Architex Meetings"><span class="nav-icon">${icon('meetings',21)}</span><span class="nav-label">Meetings</span><span class="badge">3</span></button><button class="nav-row" style="${toneStyle('coral')}" onclick="showToast('Messaging workspace represented as an existing platform area.')" title="Messages"><span class="nav-icon">${icon('inbox',21)}</span><span class="nav-label">Messages</span><span class="badge">7</span></button><button class="nav-row" style="${toneStyle('coral')}" onclick="openTool('inbox_action','standalone')" title="Action Centre"><span class="nav-icon">${icon('action_centre',21)}</span><span class="nav-label">Action Centre</span></button></div><div class="nav-section"><div class="nav-heading">Meeting quick access</div><button class="nav-row" onclick="openMeetings('standalone')"><span class="nav-icon">${icon('meeting_day',21)}</span><span class="nav-label">My day</span></button><button class="nav-row" onclick="openMeetings('standalone');setTimeout(()=>{state.meetingsTab='reviews';state.meetingScreen='review';renderAll()},0)"><span class="nav-icon">${icon('meeting_review',21)}</span><span class="nav-label">Draft reviews</span><span class="badge">3</span></button><button class="nav-row" onclick="openMeetings('standalone');setTimeout(()=>openMeetingScreen('schedule'),0)"><span class="nav-icon">${icon('meeting_agenda',21)}</span><span class="nav-label">Schedule meeting</span></button></div>`;
  }

  window.renderGlobalPage=function(id){
    if(id==='inbox')return `${pageHead('Inbox & Collaboration','The global home for messages, governed meetings, notices and action-bearing conversations.','inbox','coral',`<button class="btn ghost" onclick="openMeetings('standalone');setTimeout(()=>openMeetingScreen('prejoin'),0)">Meet now</button><button class="btn" onclick="openMeetings('standalone');setTimeout(()=>openMeetingScreen('schedule'),0)">Schedule meeting</button>`)}${mtgRoleBanner()}<div class="collab-hub"><article class="collab-card" onclick="openMeetings('standalone')"><div class="lib-icon">${icon('meetings',26)}</div><h3>Architex Meetings</h3><p>Schedule, host, record, transcribe, review and publish meetings with controlled project write-backs.</p><span class="mtg-badge ai">3 draft reviews</span></article><article class="collab-card" onclick="showToast('Messaging workspace opened.')"><div class="lib-icon">${icon('inbox',26)}</div><h3>Messages</h3><p>Direct, project and contextual conversations linked to records and actions.</p><span class="mtg-badge">7 unread</span></article><article class="collab-card" onclick="openTool('inbox_action','standalone')"><div class="lib-icon">${icon('action_centre',26)}</div><h3>Action Centre</h3><p>Role-routed work created by projects, tools and authorised meeting outcomes.</p><span class="mtg-badge warn">4 overdue</span></article></div>`;
    return v7old.renderGlobalPage(id);
  };

  window.openGlobal=function(id){
    if(id==='inbox'){state.global='inbox';state.mode='standalone';state.tool=null;state.toolTab=0;state.meetingScreen='home';renderAll();return}
    v7old.openGlobal(id);
  };

  window.toolGroups=function(mode){
    let html=v7old.toolGroups(mode);
    if(mode==='project'&&!html.includes("openTool('meetings'")){
      html=`<div class="nav-section"><div class="nav-heading">Communication & Collaboration</div><button class="nav-row" style="${toneStyle('coral')}" onclick="openTool('meetings','project')" title="Architex Meetings"><span class="nav-icon">${icon('meetings',21)}</span><span class="nav-label">Architex Meetings</span><span class="badge">3</span></button></div>`+html;
    }
    return html;
  };

  function mtgProjectIds(){
    const base=(mtgStageMap[project.stage]||[]).filter(id=>TOOLS[id]);
    const priority=mtgRolePriority[state.role]||['meetings','practice','forms','wingman'];
    const first=base.filter(id=>priority.includes(id));
    const rest=base.filter(id=>!first.includes(id));
    return [...new Set([...first,'meetings',...rest])].filter(id=>TOOLS[id]).slice(0,10);
  }
  function mtgDatumMetric(id){
    if(id==='meetings'){
      const byStage={Brief:['Client brief workshop','Agenda draft · 4 invitees'],Appoint:['Team appointment meeting','2 acceptances pending'],Design:['Design coordination','Live in 12 min'],Comply:['Municipal readiness review','AI draft · Review required'],Procure:['Tender clarification','Equal issue record'],Build:['Site progress meeting','8 open actions'],Pay:['Commercial review','3 valuation actions'],'Close-out':['Handover review','Issued minutes · Rev 01']};
      return byStage[project.stage]||['Project meeting','Scheduled'];
    }
    const known={practice:['Programme and resources','Project margin 26%'],planning:['2 active applications','Next deadline 4 Aug'],municipal:['Readiness 82%','4 blockers'],xa:['7 / 9 components pass','Fenestration pending'],forms:['6 forms exported','2 signatures pending'],bom:['47 material lines','R1.86m estimate'],specforge:['64 sections','12 clauses flagged'],itp:['3 hold points','1 NCR linked'],safety:['Safety file 91%','0 lost-time incidents'],wingman:['Project context active','6 suggested actions']};
    return known[id]||['Project-connected workspace',TOOLS[id]?.status==='live'?'Functional sample':'Integration scaffold'];
  }
  function mtgDatumCard(id,left,top,side){
    const t=TOOLS[id],m=mtgDatumMetric(id);if(!t)return'';
    return `<article class="datum-card ${side} ${id==='meetings'?'meeting-datum-card':''}" style="left:${left};top:${top};${toneStyle(t.tone)}" onclick="openTool('${id}','project')" title="Open ${t.name}"><div class="dc-head"><div class="dc-icon">${icon(t.icon,23)}</div><strong>${t.name}</strong></div><p>${t.summary}</p><div class="mini-row"><span>${m[0]}</span><span>›</span></div><div class="mini-row"><span>${m[1]}</span><span class="status ${/pending|block|review|draft|action/i.test(m[1])?'amber':'green'}">${t.status==='live'?'Live':'Scaffold'}</span></div></article>`;
  }
  window.renderProjectSpace=function(){
    const stages=['Brief','Appoint','Design','Comply','Procure','Build','Pay','Close-out'],ids=mtgProjectIds(),n1=Math.ceil(ids.length/2);
    const cards=ids.map((id,i)=>{const above=i<n1,idx=above?i:i-n1,count=above?n1:ids.length-n1,left=count<2?45:9+idx*(82/(count-1));return [id,left+'%',above?'11%':'67%',above?'above':'below']});
    return `${pageHead(project.name,'A stage-driven project workflow navigation centre. Meetings move with the project stage and write only reviewed outputs back to the datum.','projects','core',`<button class="btn ghost" onclick="openTool('practice','project')">${icon('practice_management',16)} Plan project</button><button class="btn ghost" onclick="openMeetings('project')">${icon('meetings',16)} Open meetings</button><button class="btn" onclick="toggleFeedback(true)">${icon('feedback',16)} Give feedback</button>`)}${mtgRoleBanner()}<section class="project-hero"><div class="project-top"><div><h2>${project.name}</h2><p>${project.location} · ${project.client} · ${project.professional}</p></div><span class="status green">● In progress · ${project.progress}%</span></div><div class="stages">${stages.map((s,i)=>`<button class="stage ${s===project.stage?'active':''}" onclick="selectProjectStage('${s}')" title="Show ${s} stage tools"><i>${i+1}</i><span>${s}</span></button>`).join('')}</div><div class="stage-help"><b>${project.stage} stage:</b> ${mtgStageCopy[project.stage]}</div></section><section class="datum-viewport"><div class="datum-world" style="transform:scale(${state.datumZoom})"><div class="datum-line"></div><div class="datum-origin"><img src="${LOGO}" alt="Architex bird"></div><div class="datum-label">DATUM<span>single line of truth</span></div><div class="datum-stage-badge"><b>${project.stage} tools for ${mtgRoleLabel()}</b>${ids.length} role-relevant workspaces, including the governed meeting pipeline, are active at this point.</div>${cards.map(c=>`<i class="datum-node" style="left:${c[1]}"></i>`).join('')}${cards.map(c=>mtgDatumCard(...c)).join('')}</div><div class="datum-controls"><button onclick="stepDatumZoom(-.1)" title="Zoom out">−</button><input id="datumZoom" type="range" min=".65" max="1.35" step=".05" value="${state.datumZoom}" oninput="setDatumZoom(this.value)"><output id="datumOut">${Math.round(state.datumZoom*100)}%</output><button onclick="stepDatumZoom(.1)" title="Zoom in">+</button><button onclick="fitDatum()" title="Fit datum to view">${icon('expand',17)}</button></div></section>`;
  };

  window.renderNavigator=function(){
    if(state.tool==='meetings'){meetingsNavigator();return}
    if(state.global==='inbox'&&!state.tool){collaborationNavigator();return}
    v7old.renderNavigator();
  };
  window.setToolTab=function(index){
    if(state.tool==='meetings'){
      state.toolTab=index;state.meetingsTab=TOOLS.meetings.tabs[index]?.key||'my-day';state.meetingScreen=state.meetingsTab==='reviews'?'review':'home';renderNavigator();renderMain();renderInspector();updateFeedbackContext();return;
    }
    v7old.setToolTab(index);
  };
  window.renderMain=function(){
    if(state.tool==='meetings'){q('#main').innerHTML=renderMeetingsTool();return}
    v7old.renderMain();
  };
  window.renderInspector=function(){
    if(state.tool!=='meetings'){v7old.renderInspector();return}
    q('#inspectTitle').textContent='Architex Meetings';
    q('#inspectSub').textContent=state.mode==='project'?project.name:'Practice meeting space';
    const screen=state.meetingScreen;
    const body=q('#inspectBody');
    if(screen==='room')body.innerHTML=`<div class="inspect-note"><b>Persistent meeting state</b><br>Recording ${state.meetingRecording?'on':'off'} · Transcription ${state.meetingTranscription?'on':'off'} · AI assistant visible.</div><div class="inspect-block"><h4>Meeting context</h4><p>${project.name}<br>${project.stage} stage · Passport ${project.revision}<br>A-204 Rev P03 · Fire review blocker</p></div><div class="inspect-block"><h4>Safe boundaries</h4><p>A shared markup is meeting evidence, not an issued drawing revision. A discussion cannot create an approval, instruction, variation or certificate.</p></div><div class="inspect-block"><h4>Connection</h4><p>Good network · 28 ms<br>Low-bandwidth and audio-only modes available.</p></div>`;
    else if(screen==='review')body.innerHTML=`<div class="inspect-note"><b>Human publication gate</b><br>Wingman output is an AI draft until an authorised reviewer publishes it.</div><div class="inspect-block"><h4>Outcome status</h4><p>${meetingData.outcomes.filter(x=>x.state==='accepted').length} accepted<br>${meetingData.outcomes.filter(x=>x.state==='rejected').length} rejected<br>${meetingData.outcomes.filter(x=>x.state==='pending').length} pending</p></div><div class="inspect-block"><h4>Write-back preview</h4><p>Meeting minutes document<br>ProjectRecord<br>Action Centre tasks<br>Risk proposal<br>Project Passport projection<br>Audit events</p></div><div class="inspect-block"><h4>Classification rule</h4><p>Use “Convert to approval request”, “Draft site instruction”, “Draft variation” or “Propose risk”. Never call a discussion approved.</p></div>`;
    else body.innerHTML=`<div class="inspect-note"><b>${mtgRoleLabel()} meeting experience</b><br>Prioritises ${mtgFocus()}.</div><div class="inspect-block"><h4>Current scope</h4><p>${state.mode==='project'?project.name+'<br>'+project.stage+' stage · '+project.municipality:'Organisation-wide<br>Project optional · unassigned drafts permitted'}</p></div><div class="inspect-block"><h4>Lifecycle</h4><p>Draft → Scheduled → Lobby open → Live → Processing → Review required → Published.</p></div><div class="inspect-block"><h4>Governance</h4><p>Consent, recording, transcript evidence, corrections, revisions and approved workflow conversions remain traceable.</p></div>`;
  };

  window.renderTopbar=function(){
    v7old.renderTopbar();
    const chip=q('#scopePill');if(state.tool==='meetings')chip.textContent=state.mode==='project'?'Project meeting orientation':'Standalone meeting orientation';
  };

  renderAll();
})();
