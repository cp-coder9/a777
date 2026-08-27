import { test, expect } from '@playwright/test';

test('release 1 shell loads honest authenticated project state', async ({ page }) => {
  await page.goto(process.env.ARCHITEX_BROWSER_URL ?? 'http://127.0.0.1:8790/');
  await expect(page.locator('html')).toHaveAttribute('data-shell-state', /signed-out|empty|ready/);
  await expect(page.locator('.os-rail')).toBeVisible();
  await expect(page.locator('#navigator')).toBeVisible();
  await expect(page.locator('#main')).toBeVisible();
  await expect(page.locator('.inspector')).toBeVisible();
  await expect(page.locator('#roleSwitch')).toBeHidden();
  await expect(page.locator('#godModeToggle')).toBeHidden();
});

test('mobile shell retains its canonical rail and bounded main workspace', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(process.env.ARCHITEX_BROWSER_URL ?? 'http://127.0.0.1:8790/');
  const geometry = await page.locator('#main').evaluate(element => {
    const rectangle = element.getBoundingClientRect();
    return { left: rectangle.left, right: rectangle.right, viewport: window.visualViewport?.width ?? window.innerWidth };
  });
  expect(geometry.left).toBeGreaterThanOrEqual(58);
  expect(geometry.right).toBeLessThanOrEqual(geometry.viewport);
});
