import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/Browser',
  workers: 1,
  timeout: 120000,
  expect: { timeout: 20000 },
  use: {
    baseURL: process.env.APP_TEST_URL || 'http://127.0.0.1:8030',
    channel: 'chrome',
    viewport: { width: 1440, height: 1050 },
    trace: 'retain-on-failure',
  },
  reporter: 'list',
});
