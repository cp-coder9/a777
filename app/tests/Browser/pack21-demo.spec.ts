import { test, expect, BrowserContext, Page } from '@playwright/test';

const baseUrl = process.env.ARCHITEX_DEMO_BROWSER_URL ?? 'http://127.0.0.1:8791';
const productionUrl = process.env.ARCHITEX_PRODUCTION_BROWSER_URL ?? 'http://127.0.0.1:8790';
const roles = [
  ['client','Client / Developer'],['project_manager','Project Manager'],['architect','Architect (PrArch)'],
  ['architectural_technologist','Architectural Technologist'],['candidate_architect','Candidate Architect'],
  ['engineer_structural','Structural Engineer (PrEng)'],['engineer_civil','Civil Engineer (PrEng)'],
  ['engineer_electrical','Electrical Engineer (PrEng)'],['engineer_mechanical','Mechanical Engineer (PrEng)'],
  ['engineer_fire','Fire Engineer'],['quantity_surveyor','Quantity Surveyor (PrQS)'],['contractor','Contractor (Main)'],
  ['subcontractor','Subcontractor'],['supplier','Supplier'],['energy_consultant','Energy Consultant'],
  ['town_planner','Town Planner'],['environmental_consultant','Environmental Consultant'],
  ['accessibility_consultant','Accessibility Consultant'],['bep','BEP / Professional'],
  ['freelancer','Freelancer / Draughtsperson'],['cpd_officer','CPD Officer / Administrator'],['admin','Administrator'],
] as const;

async function signIn(page: Page, base: string, email: string, password: string) {
  await page.goto(`${base}/demo`);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', {name:'Sign in'}).click();
  await expect(page.locator('html')).toHaveAttribute('data-shell-state', /ready|empty/);
  await expect(page.locator('#demoSandboxWorkspace')).toBeVisible();
}

test.describe('Pack 21 persistent demo sandbox', () => {
  test('all 22 server-owned role lenses persist and render assigned projects', async ({page}) => {
    await signIn(page, baseUrl, process.env.ARCHITEX_DEMO_USER_A ?? '', process.env.ARCHITEX_DEMO_PASSWORD_A ?? '');
    await expect(page.locator('.demo-sandbox-status-rail')).toContainText('illustrative_demo_not_for_reliance');
    for (const [key,label] of roles) {
      const response = page.waitForResponse(value => value.url().endsWith('/api/v1/demo/sandbox/role') && value.request().method() === 'PUT');
      await page.getByLabel('Demo role lens').selectOption(key);
      expect((await response).status()).toBe(200);
      await expect(page.locator('.demo-sandbox-status-rail')).toContainText(label);
      await expect(page.locator('.demo-sandbox-card').first()).toBeVisible();
    }
    await page.reload();
    await expect(page.getByLabel('Demo role lens')).toHaveValue('admin');
    await expect(page.locator('.demo-sandbox-card')).toHaveCount(12);
  });

  test('two authenticated contexts have isolated sandboxes', async ({browser}) => {
    const contexts: BrowserContext[] = [await browser.newContext(), await browser.newContext()];
    try {
      const [a,b] = await Promise.all(contexts.map(context => context.newPage()));
      await Promise.all([
        signIn(a, baseUrl, process.env.ARCHITEX_DEMO_USER_A ?? '', process.env.ARCHITEX_DEMO_PASSWORD_A ?? ''),
        signIn(b, baseUrl, process.env.ARCHITEX_DEMO_USER_B ?? '', process.env.ARCHITEX_DEMO_PASSWORD_B ?? ''),
      ]);
      const [sandboxA,sandboxB] = await Promise.all([a.locator('.demo-sandbox-status-rail').textContent(),b.locator('.demo-sandbox-status-rail').textContent()]);
      expect(sandboxA).toContain('Generation 1');
      expect(sandboxB).toContain('Generation 1');
      await a.getByLabel('Demo role lens').selectOption('contractor');
      await expect(b.getByLabel('Demo role lens')).not.toHaveValue('contractor');
    } finally { await Promise.all(contexts.map(context => context.close())); }
  });

  test('owner reset creates the next persisted generation', async ({page}) => {
    await signIn(page, baseUrl, process.env.ARCHITEX_DEMO_RESET_USER ?? '', process.env.ARCHITEX_DEMO_RESET_PASSWORD ?? '');
    page.on('dialog', dialog => dialog.accept());
    await page.getByLabel('Reason').fill('Browser certification clean reset');
    await page.getByRole('button', {name:'RESET DEMO SANDBOX'}).click();
    await expect(page.locator('.demo-sandbox-status-rail')).toContainText('Generation 2');
  });

  test('desktop and mobile remain bounded inside the canonical V1 shell', async ({page}) => {
    await signIn(page, baseUrl, process.env.ARCHITEX_DEMO_USER_A ?? '', process.env.ARCHITEX_DEMO_PASSWORD_A ?? '');
    for (const viewport of [{width:1440,height:1000},{width:390,height:844}]) {
      await page.setViewportSize(viewport);
      const geometry = await page.evaluate(() => ({scrollWidth:document.documentElement.scrollWidth,width:window.innerWidth,rail:document.querySelectorAll('.os-rail').length,navigator:document.querySelectorAll('#navigator').length,main:document.querySelectorAll('#main').length,inspector:document.querySelectorAll('.inspector').length}));
      expect(geometry).toMatchObject({scrollWidth:viewport.width,width:viewport.width,rail:1,navigator:1,main:1,inspector:1});
    }
  });

  test('production mode exposes neither demo route nor controls', async ({page,request}) => {
    const response = await request.get(`${productionUrl}/api/v1/demo/sandbox`);
    expect(response.status()).toBe(404);
    await page.goto(productionUrl);
    await expect(page.locator('#demoSandboxRoleControl')).toHaveCount(0);
    await expect(page.locator('#demoSandboxStatusRail')).toHaveCount(0);
  });
});
