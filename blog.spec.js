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

test('archive links are valid', async ({ page }) => {
  await page.goto('/');

  await page.getByRole('link', { name: 'Archive' }).click();

  await expect(page).toHaveTitle(/Archive/);

  const archiveLinks = page.locator('a.archive-link');
  await expect(archiveLinks).not.toHaveCount(0);

  const articles = [];

  for (const link of await archiveLinks.elementHandles()) {
    articles.push(await link.getAttribute('href'));
  }

  for (const article of articles) {
    await expect(async () => {
      const response = await page.request.get(article);
      expect(response.status()).toBe(200);
    }).toPass();
  }
});

test('page images are valid', async ({ page }) => {
  test.setTimeout(90_000);

  await page.goto('/archive/');

  const archiveLinks = page.locator('a.archive-link');
  await expect(archiveLinks).not.toHaveCount(0);

  const pageUrls = ['/', '/about-me/', '/archive/'];

  for (const link of await archiveLinks.elementHandles()) {
    pageUrls.push(await link.getAttribute('href'));
  }

  for (const url of pageUrls) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');

    const images = page.locator('img');

    for (const image of await images.elementHandles()) {
      const src = await image.getAttribute('src');
      if (src.startsWith('data:')) {
        continue;
      }
      await expect(async () => {
        const response = await page.request.get(src);
        expect(response.status(), `Image ${src} on page ${url} could not be loaded`).toBe(200);
      }).toPass();
    }

    const metaImage = page.locator('meta[property="og:image"]');
    if (metaImage) {
      await expect(async () => {
        const src = await metaImage.getAttribute('content');
        const response = await page.request.get(src);
        expect(response.status(), `Image ${src} on page ${url} could not be loaded`).toBe(200);
      }).toPass();
    }
  }
});
