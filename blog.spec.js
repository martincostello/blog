// Copyright (c) Martin Costello, 2017. All rights reserved.
// Licensed under the MIT. See the LICENSE file in the project root for full license information.

import { test, expect } from '@playwright/test';

test('has title', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle(/Martin Costello\'s Blog/);
});

test('about me link', async ({ page }) => {
  await page.goto('/');

  await page.getByRole('link', { name: 'About Me' }).click();

  await expect(page.getByRole('heading', { name: 'About Me' })).toBeVisible();
});
