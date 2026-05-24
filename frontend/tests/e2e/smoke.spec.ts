import { expect, test } from '@playwright/test';

test('トップページからログイン画面へ遷移できる', async ({ page }) => {
  await page.goto('/');

  await expect(page.getByRole('heading', { name: '割り勘アプリ' })).toBeVisible();
  await page.getByRole('link', { name: 'ログインへ' }).click();

  await expect(page).toHaveURL('/login');
  await expect(page.getByRole('button', { name: '登録せずに始める' })).toBeVisible();
});
