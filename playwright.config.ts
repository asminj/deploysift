import { defineConfig } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './e2e',
  timeout: 180000,
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL,
  },
  webServer: process.env.E2E_EXTERNAL
    ? undefined
    : {
        command: 'C:\\wamp64\\bin\\php\\php8.3.28\\php.exe artisan serve --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: true,
        timeout: 60000,
      },
});
