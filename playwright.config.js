// Copyright (c) Martin Costello, 2017. All rights reserved.
// Licensed under the MIT. See the LICENSE file in the project root for full license information.

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  forbidOnly: !!process.env.CI,
  fullyParallel: true,
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  reporter: process.env.CI ? 'github' : 'list',
  retries: process.env.CI ? 2 : 0,
  testDir: './.',
  use: {
    baseURL: 'http://localhost:1313',
    trace: 'on-first-retry',
  },
  webServer: {
    command: 'npm run serve',
    reuseExistingServer: !process.env.CI,
    url: 'http://localhost:1313'
  },
  workers: process.env.CI ? 1 : undefined,
});
