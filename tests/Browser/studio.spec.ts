import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('register, explore the studio, obtain real public pricing, update profile, and sign out', async ({
  page,
}) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const email = `browser-${Date.now()}@example.test`;
  const password = 'Test-only-long-passphrase!';
  await page.goto('/register');
  await page.getByLabel('Full name', { exact: true }).fill('Studio Tester');
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm password').fill(password);
  await page.getByRole('button', { name: 'Create your account' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole('heading', { name: /A little inspiration/ })).toBeVisible();
  await page.screenshot({ path: 'test-results/dashboard.png', fullPage: true });
  await page.locator('nav').getByRole('link', { name: 'Create image' }).click();
  await page
    .getByLabel('What do you have in mind?')
    .fill('A sunlit garden, soft pastel tones [succeed]');
  await expect(page.locator('.price-display strong')).toContainText('$', { timeout: 30000 });
  await expect(page.locator('.price-display strong')).not.toHaveText('—');
  await page.screenshot({ path: 'test-results/create-desktop.png', fullPage: true });
  const accessibility = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
    .analyze();
  expect(
    accessibility.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) })),
  ).toEqual([]);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: 'test-results/create-mobile.png', fullPage: true });
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
  ).toBeTruthy();
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await page.getByRole('link', { name: 'Account settings' }).click();
  await page.getByLabel('Full name', { exact: true }).fill('Creative Tester');
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByRole('status')).toContainText('updated');
  await page.setViewportSize({ width: 1440, height: 1050 });
  await page.getByRole('button', { name: 'Sign out', exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Sign in to your studio' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  expect(errors).toEqual([]);
});
