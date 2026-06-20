import { expect, test } from '@playwright/test';

test('ゲストログイン後にプロジェクト一覧を表示できる', async ({ page }) => {
  await page.route('**/api/csrf-token', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ csrf_token: 'test-csrf-token' }),
    });
  });

  await page.route('**/api/auth/guest-login', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        customer: {
          customer_id: 1,
          is_guest: true,
          nick_name: 'E2Eゲスト',
        },
        token: 'e2e-token',
      }),
    });
  });

  await page.route('**/api/projects?page=1', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        projects: [],
        pagination: {
          current_page: 1,
          last_page: 1,
          per_page: 20,
          total: 0,
          from: 0,
          to: 0,
        },
      }),
    });
  });

  await page.goto('/login');
  await page.getByRole('button', { name: '登録せずに始める' }).click();

  await expect(page).toHaveURL('/projectslist');
  await expect(page.getByText('プロジェクトがありません')).toBeVisible();
});
