(function () {
  'use strict';

  const root = document.documentElement;
  const access = document.getElementById('publicAccess');
  const landing = document.getElementById('publicLandingFrame');
  const birdFlock = document.getElementById('birdFlockTransition');
  const authMount = document.getElementById('publicAuthMount');
  const workspace = document.getElementById('app');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const accessStates = Object.freeze(['checking-session', 'public', 'auth', 'workspace']);
  const api = new window.ArchitexApi.ApiClient();
  const roles = Object.freeze([
    ['architect','Architect (PrArch)'],['client','Client / Property Owner'],['bep','Built Environment Professional'],['engineer','Consulting Engineer (PrEng)'],['quantity_surveyor','Quantity Surveyor (PrQS)'],['town_planner','Town Planner (PrPln)'],['land_surveyor','Land Surveyor'],['energy_professional','Energy Professional'],['fire_engineer','Fire Engineer'],['cpm','Construction Project Manager'],['contractor','Principal Contractor'],['subcontractor','Trade Subcontractor'],['supplier','Material Supplier'],['site_manager','Site Manager / Clerk of Works'],['health_safety','Health & Safety Officer (CHSO)'],['developer','Property Developer'],['freelancer','Specialist Freelancer'],['firm_admin','Practice Administrator'],['organisation_admin','Organisation Administrator'],['admin','Practice Operations Admin'],['platform_admin','Platform Super-Administrator'],
  ]);
  const roleGroups = Object.freeze([
    ['Clients & Development',['client','developer']],
    ['Built Environment Professionals',['architect','engineer','quantity_surveyor','town_planner','land_surveyor','energy_professional','fire_engineer','bep']],
    ['Delivery & Site',['cpm','contractor','subcontractor','site_manager','health_safety']],
    ['Supply & Specialist Services',['supplier','freelancer']],
    ['Practice & Platform Administration',['firm_admin','organisation_admin','admin','platform_admin']],
  ]);
  const roleFields = Object.freeze({
    architect:[['sacap_number','SACAP registration number','e.g. PrArch T4321',true]],
    engineer:[['ecsa_number','ECSA registration number','e.g. Pr Eng 20051234',true]],
    quantity_surveyor:[['sacqsp_number','SACQSP registration number','e.g. Pr QS 20198765',true]],
    town_planner:[['sacplan_number','SACPLAN registration number','e.g. A/1234/2015',true]],
    land_surveyor:[['sagc_number','SAGC registration number','e.g. LS/0123',true]],
    energy_professional:[['professional_registration_number','Professional registration number (ECSA / SANAS)','Registration body and number',true]],
    fire_engineer:[['professional_registration_number','Professional registration number (ECSA / Fire)','Registration body and number',true]],
    bep:[['professional_registration_number','Professional body registration number','Council and registration number',true]],
    health_safety:[['sacpcmp_number','SACPCMP registration number (CHSO)','e.g. CHSO/1234',true]],
    cpm:[['sacpcmp_number','SACPCMP registration number (Pr.CPM)','e.g. CPM/5678',true]],
    contractor:[['cidb_grade','CIDB grade','Grade 1|Grade 2|Grade 3|Grade 4|Grade 5|Grade 6|Grade 7|Grade 8|Grade 9',true],['cidb_classes','CIDB classes of works','e.g. GB, CE, ME',true],['csd_number','CSD supplier registration number','e.g. MAAA1234567',true]],
    subcontractor:[['trade_speciality','Trade / package specialisation','e.g. Formwork, waterproofing, glazing',true],['csd_number','CSD supplier registration number','e.g. MAAA1234567',true]],
    supplier:[['company_registration_number','Company registration number','e.g. 2016/123456/07',true],['vat_number','VAT number','e.g. 4123456789',false],['product_categories','Products / materials supplied','e.g. Ready-mix concrete, rebar, bricks',true],['bbbbee_level','B-BBEE level','Level 1|Level 2|Level 3|Level 4|Level 5|Level 6|Level 7|Level 8|Non-compliant',false]],
    developer:[['company_registration_number','Company registration number','e.g. 2014/987654/06',true]],
    freelancer:[['area_of_specialisation','Area of specialisation','e.g. Point-cloud scanning, 4D planning',true]],
  });
  let transitionTimer = null;
  let authMode = 'signin';
  let signupNotices = null;
  let landingTrigger = null;
  let pausedLandingAnimations = [];

  function buildBirdFlock() {
    if (!birdFlock || birdFlock.childElementCount > 0) return;
    const ghost = document.createElement('img');
    ghost.className = 'bird-flock-ghost';
    ghost.src = '/logo.png';
    ghost.alt = '';
    birdFlock.appendChild(ghost);
    const destinations = [
      [-43,-34],[-27,-39],[-10,-36],[12,-40],[29,-35],[44,-30],
      [-48,-12],[-31,-16],[-14,-11],[14,-15],[32,-10],[47,-14],
      [-46,13],[-29,9],[-12,16],[13,10],[30,15],[45,11],
      [-40,35],[-25,31],[-8,39],[10,33],[27,38],[41,30],
    ];
    destinations.forEach(([x, y], index) => {
      const row = Math.floor(index / 6);
      const column = index % 6;
      const fragment = document.createElement('img');
      fragment.className = 'bird-flock-fragment';
      fragment.src = '/logo.png';
      fragment.alt = '';
      fragment.style.setProperty('--dx', `${x}vw`);
      fragment.style.setProperty('--dy', `${y}vh`);
      fragment.style.setProperty('--dx-near', `${x * .22}vw`);
      fragment.style.setProperty('--dy-near', `${y * .22}vh`);
      fragment.style.setProperty('--dx-home', `${x * .18 - 46}vw`);
      fragment.style.setProperty('--dy-home', `${y * .18 - 43}vh`);
      fragment.style.setProperty('--delay', `${180 + index * 9}ms`);
      fragment.style.clipPath = `inset(${row * 25}% ${(5 - column) * (100 / 6)}% ${(3 - row) * 25}% ${column * (100 / 6)}%)`;
      birdFlock.appendChild(fragment);
    });
  }

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
  const slugify = value => String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 80);

  function setState(state) {
    if (!accessStates.includes(state)) throw new Error('Unknown public access state.');
    root.dataset.accessState = state;
  }

  function showLanding() {
    if (transitionTimer !== null) window.clearTimeout(transitionTimer);
    access?.classList.remove('is-flocking', 'is-auth-ready');
    if (landing) landing.inert = false;
    pausedLandingAnimations.forEach(animation => animation.play());
    pausedLandingAnimations = [];
    if (authMount) {
      authMount.hidden = true;
      authMount.inert = true;
      authMount.replaceChildren();
    }
    if (workspace) {
      workspace.inert = true;
      workspace.setAttribute('aria-hidden', 'true');
    }
    setState('public');
    landingTrigger?.focus();
  }

  function brand() {
    return '<div class="access-brand"><img src="/logo.png" alt="Architex"><div><strong>Architex OS</strong><span>Built environment access</span></div></div>';
  }

  function roleOptions() {
    const labels = new Map(roles);
    return '<option value="" disabled>Select your role profile…</option>' + roleGroups.map(([group, keys]) => `<optgroup label="${escapeHtml(group)}">${keys.map(key => `<option value="${key}">${escapeHtml(labels.get(key))}</option>`).join('')}</optgroup>`).join('');
  }

  function authMarkup() {
    const register = authMode === 'register';
    return `<div class="v8-signin">
      <button type="button" class="v8-signin-back" data-access-back>← Back to landing</button>
      <section class="v8-signin-panel" data-mode="${authMode}">
        <div class="v8-signin-identity">
          ${brand()}
          <div class="v8-access-badge">▣ ${register ? 'V8 secure enrolment' : 'V8 secure access'}</div>
          <h1>${register ? 'Join Architex OS' : 'Welcome back'}</h1>
          <p>${register ? 'Create your governed account, pick your role profile, and mount a workspace shaped around your part of the built environment.' : 'Resume your governed workspace with project context, evidence controls, and role permissions intact.'}</p>
          <div class="v8-status-stack"><span><i></i> Identity boundary active</span><span><i></i> Audit trail ready</span><span><i></i> Workspace encrypted</span></div>
        </div>
        <form class="v8-signin-form" id="v8AuthenticationForm">
          <div class="v8-form-head"><span class="v8-step">${register ? '01 / Enrol' : '01 / Authenticate'}</span><h2>${register ? 'Create your account' : 'Enter Architex OS'}</h2><p>${register ? 'Choose your role profile — professional registrations and supplier credentials mount the correct command centre.' : 'Use your organisation credentials to mount the V8 shell.'}</p></div>
          <div class="v8-mode-switch" role="tablist" aria-label="Authentication mode"><button type="button" role="tab" aria-selected="${!register}" data-auth-mode="signin">Sign in</button><button type="button" role="tab" aria-selected="${register}" data-auth-mode="register">Create account</button></div>
          <div class="v8-morph-zone ${register ? 'open' : ''}" aria-hidden="${!register}"><div>
            <label>Full name<input name="fullName" aria-label="Full name" placeholder="John Doe" autocomplete="name" ${register ? 'required' : 'disabled'}></label>
            <label>Organisation name<input name="organisationName" aria-label="Organisation name" placeholder="Your practice or organisation" autocomplete="organization" ${register ? 'required' : 'disabled'}></label>
            <label>Organisation slug<input name="organisationSlug" aria-label="Organisation slug" placeholder="your-practice" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" ${register ? 'required' : 'disabled'}></label>
            <label>Role profile<select name="roleKey" aria-label="Role profile" ${register ? 'required' : 'disabled'}>${roleOptions()}</select></label>
            <div class="v8-morph-zone v8-role-fields" aria-hidden="true"><div id="v8RoleFields"></div></div>
            <label class="v8-legal-acceptance"><input type="checkbox" name="acceptNotices" ${register ? 'required' : 'disabled'}> <span>I accept the current <a href="/terms" target="_blank">Terms</a>, <a href="/privacy" target="_blank">Privacy Policy</a>, and <a href="/disclaimer" target="_blank">platform disclaimer</a>.</span></label>
          </div></div>
          <label>Email address<input name="email" aria-label="Email address" type="email" placeholder="name@example.com" autocomplete="email" required></label>
          <label>Password<input name="password" aria-label="Password" type="password" placeholder="••••••••" minlength="${register ? '12' : '1'}" autocomplete="${register ? 'new-password' : 'current-password'}" required></label>
          <div class="v8-form-meta"><label><input type="checkbox" name="trustedDevice"> Keep this device trusted</label><button type="button">Recover access</button></div>
          <button class="v8-enter-button" type="submit">${register ? 'Create account' : 'Enter workspace'} →</button>
          <p id="v8AuthenticationStatus" class="v8-auth-status" role="alert"></p>
          <p class="v8-auth-note">◇ Protected by role-based access and immutable session logging.</p>
        </form>
      </section>
    </div>`;
  }

  function renderRoleFields(roleKey) {
    const container = document.getElementById('v8RoleFields');
    const zone = container?.closest('.v8-role-fields');
    if (!container || !zone) return;
    const fields = roleFields[roleKey] || [];
    container.innerHTML = fields.map(([name, label, options, required]) => {
      if (options.includes('|')) return `<label>${escapeHtml(label)}<select name="profile_${name}" aria-label="${escapeHtml(label)}" ${required ? 'required' : ''}><option value="" disabled selected>Select…</option>${options.split('|').map(option => `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`).join('')}</select></label>`;
      return `<label>${escapeHtml(label)}<input name="profile_${name}" aria-label="${escapeHtml(label)}" placeholder="${escapeHtml(options)}" ${required ? 'required' : ''}></label>`;
    }).join('');
    zone.classList.toggle('open', fields.length > 0);
    zone.setAttribute('aria-hidden', String(fields.length === 0));
  }

  async function currentSignupNotices() {
    if (signupNotices !== null) return signupNotices;
    const notices = await api.get('/legal-notices/signup');
    if (!Array.isArray(notices) || notices.length !== 4) throw new Error('Current legal notices are unavailable; registration is paused.');
    signupNotices = Object.fromEntries(notices.map(notice => [notice.noticeType, notice.version]));
    return signupNotices;
  }

  async function submitAuthentication(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    const status = document.getElementById('v8AuthenticationStatus');
    const submit = form.querySelector('.v8-enter-button');
    status.textContent = '';
    submit.disabled = true;
    submit.textContent = 'Working…';
    try {
      if (authMode === 'signin') {
        await api.post('/sessions', {email:values.get('email'), password:values.get('password')});
        window.location.reload();
        return;
      }
      const profile = {};
      for (const [name, value] of values.entries()) if (name.startsWith('profile_') && String(value).trim() !== '') profile[name.slice(8)] = String(value).trim();
      const result = await api.post('/registrations', {
        email:values.get('email'), password:values.get('password'),
        organisationName:values.get('organisationName'), organisationSlug:values.get('organisationSlug'),
        fullName:values.get('fullName'), roleKey:values.get('roleKey'), profile,
        acceptedNoticeVersions:await currentSignupNotices(),
      });
      status.textContent = result.status === 'pending_email_verification' ? 'Registration received. Check your email to verify the account.' : 'Registration received.';
      form.reset();
    } catch (error) {
      status.textContent = error.message;
    } finally {
      submit.disabled = false;
      submit.textContent = authMode === 'register' ? 'Create account →' : 'Enter workspace →';
    }
  }

  function bindAuth() {
    document.querySelectorAll('[data-auth-mode]').forEach(button => button.addEventListener('click', () => {
      authMode = button.dataset.authMode;
      authMount.innerHTML = authMarkup();
      bindAuth();
    }));
    document.querySelector('[data-access-back]')?.addEventListener('click', showLanding);
    const form = document.getElementById('v8AuthenticationForm');
    form?.addEventListener('submit', submitAuthentication);
    form?.elements.organisationName?.addEventListener('input', event => {
      const slug = form.elements.organisationSlug;
      if (!slug.dataset.userEdited) slug.value = slugify(event.target.value);
    });
    form?.elements.organisationSlug?.addEventListener('input', event => { event.target.dataset.userEdited = 'true'; event.target.value = slugify(event.target.value); });
    form?.elements.roleKey?.addEventListener('change', event => renderRoleFields(event.target.value));
    if (authMode === 'register') currentSignupNotices().catch(error => { document.getElementById('v8AuthenticationStatus').textContent = error.message; });
  }

  function showAuth(mode) {
    authMode = mode === 'sign up' || mode === 'create account' || mode === 'register' ? 'register' : 'signin';
    if (transitionTimer !== null) window.clearTimeout(transitionTimer);
    setState('auth');
    access?.classList.remove('is-auth-ready');
    access?.classList.add('is-flocking');
    buildBirdFlock();
    if (landing) landing.inert = true;
    pausedLandingAnimations = [...pausedLandingAnimations, ...(landing?.contentDocument?.getAnimations().filter(animation => animation.playState === 'running') || [])];
    pausedLandingAnimations.forEach(animation => animation.pause());
    if (authMount) {
      authMount.hidden = false;
      authMount.inert = true;
      authMount.innerHTML = authMarkup();
      bindAuth();
    }
    const finish = () => {
      access?.classList.remove('is-flocking');
      access?.classList.add('is-auth-ready');
      if (authMount) authMount.inert = false;
      authMount?.querySelector('h1')?.setAttribute('tabindex', '-1');
      authMount?.querySelector('h1')?.focus();
    };
    transitionTimer = window.setTimeout(finish, reducedMotion.matches ? 0 : 2400);
  }

  function showWorkspace() {
    if (transitionTimer !== null) window.clearTimeout(transitionTimer);
    access?.classList.remove('is-flocking', 'is-auth-ready');
    if (workspace) {
      workspace.inert = false;
      workspace.removeAttribute('aria-hidden');
    }
    setState('workspace');
  }

  function bindLandingActions() {
    const document = landing?.contentDocument;
    if (!document || document.documentElement.dataset.architexAccessBound === 'true') return;
    document.documentElement.dataset.architexAccessBound = 'true';
    const bird = document.querySelector('.bird-orbit');
    if (bird) {
      bird.setAttribute('role', 'button');
      bird.setAttribute('tabindex', '0');
      bird.setAttribute('aria-label', 'Request access to Architex');
      bird.style.cursor = 'pointer';
      bird.addEventListener('keydown', event => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        bird.click();
      });
    }
    document.addEventListener('click', event => {
      const trigger = event.target?.closest?.('a,button,[role="button"]');
      if (!trigger) return;
      const label = String(trigger.textContent || trigger.getAttribute('aria-label') || '').trim().toLowerCase();
      const birdActivated = Boolean(trigger.closest('.bird-orbit'));
      if (!birdActivated && !['sign in', 'sign up', 'create account', 'request access'].some(action => label.includes(action))) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      landingTrigger = trigger;
      showAuth(birdActivated ? 'sign in' : label);
    }, true);
  }

  landing?.addEventListener('load', bindLandingActions);
  bindLandingActions();
  window.ArchitexPublicAccess = Object.freeze({ showLanding, showAuth, showWorkspace });
}());
