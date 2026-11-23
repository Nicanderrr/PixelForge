import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('real provider submission completes with an image and an exact final charge', async ({
  page,
}) => {
  test.skip(
    process.env.RUN_PIXELFORGE_LIVE !== '1',
    'Opt-in only: creates a real simulated-provider generation.',
  );
  test.setTimeout(180000);
  const credentials = JSON.parse(readFileSync('.runtime/reviewer.json', 'utf8'));
  await page.goto('/login');
  await page.getByLabel('Email address').fill(credentials.email);
  await page.getByLabel('Password', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Sign in to your studio' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await page.locator('nav').getByRole('link', { name: 'Create image' }).click();
  await page
    .getByLabel('What do you have in mind?')
    .fill('A soft lavender architectural study [succeed] [duplicate]');
  await page.locator('input[name="quality"][value="low"]').check();
  await page.getByRole('button', { name: 'Add reference URL' }).click();
  await page.getByLabel('Reference image 1').fill('https://example.com/style.png');
  await expect(page.getByRole('button', { name: 'Generate image' })).toBeEnabled({
    timeout: 30000,
  });
  await page.getByRole('button', { name: 'Generate image' }).click();
  await expect(page).toHaveURL(/\/generations\/[a-f0-9-]+$/);
  await expect(page.locator('.status-badge')).toHaveText('Completed', { timeout: 150000 });
  const image = page.locator('.detail-preview > img');
  await expect(image).toBeVisible();
  await expect.poll(() => image.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBe(1024);
  await expect(page.locator('.final-price')).toContainText('$');
  await expect(page.locator('.usage-panel')).toContainText('765');
  await page.locator('.exact-values summary').click();
  await expect(page.locator('.exact-values code')).toHaveCount(2);
  await page.screenshot({ path: 'test-results/live-generation.png', fullPage: true });
});
